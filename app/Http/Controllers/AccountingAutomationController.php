<?php

namespace App\Http\Controllers;

use App\Support\AuditLogger;
use App\Support\CompanyContext;
use App\Support\TransactionPeriodLock;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccountingAutomationController extends Controller
{
    public function index(CompanyContext $context): View
    {
        $companyId = $context->id();
        $templates = DB::table('accounting_recurring_templates')->where('company_id', $companyId)->orderBy('name')->get();

        return view('accounting.automation', [
            'company' => $context->current(),
            'templates' => $templates,
            'accounts' => DB::table('gl_accounts')->where('company_id', $companyId)->where('is_active', true)->where('allow_posting', true)->orderBy('code')->get(),
            'departments' => DB::table('departments')->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(),
            'cashAccounts' => DB::table('gl_accounts')->where('company_id', $companyId)->where('type', 'asset')->where('allow_posting', true)->orderBy('code')->get(),
        ]);
    }

    public function updateAccount(Request $request, int $account, CompanyContext $context): RedirectResponse
    {
        $row = DB::table('gl_accounts')->where('company_id', $context->id())->where('id', $account)->firstOrFail();
        $data = $request->validate(['is_cash_account' => ['nullable', 'boolean'], 'cash_flow_activity' => ['nullable', Rule::in(['operating', 'investing', 'financing'])]]);
        $data['is_cash_account'] = $request->boolean('is_cash_account');
        if ($data['is_cash_account'] && $row->type !== 'asset') {
            throw ValidationException::withMessages(['is_cash_account' => 'Cash/bank account harus bertipe asset.']);
        }
        DB::table('gl_accounts')->where('id', $row->id)->update($data + ['updated_at' => now()]);
        AuditLogger::log('gl_account_reporting_updated', 'gl_account', (int) $row->id, ['is_cash_account' => $row->is_cash_account, 'cash_flow_activity' => $row->cash_flow_activity], $data, $context->id());

        return back()->with('status', 'Klasifikasi laporan akun berhasil diperbarui.');
    }

    public function store(Request $request, CompanyContext $context): RedirectResponse
    {
        $request->merge(['lines' => collect($request->input('lines', []))->filter(fn ($line) => filled($line['gl_account_id'] ?? null))->values()->all()]);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'frequency' => ['required', Rule::in(['monthly', 'quarterly', 'yearly'])], 'starts_on' => ['required', 'date'], 'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'], 'memo' => ['required', 'string', 'max:2000'], 'is_adjustment' => ['nullable', 'boolean'], 'cash_flow_activity' => ['nullable', Rule::in(['operating', 'investing', 'financing'])], 'lines' => ['required', 'array', 'min:2', 'max:100'], 'lines.*.gl_account_id' => ['required', 'integer'], 'lines.*.department_id' => ['nullable', 'integer'], 'lines.*.description' => ['nullable', 'string', 'max:255'], 'lines.*.debit' => ['nullable', 'numeric', 'min:0'], 'lines.*.credit' => ['nullable', 'numeric', 'min:0']]);
        $debit = round((float) collect($data['lines'])->sum('debit'), 4);
        $credit = round((float) collect($data['lines'])->sum('credit'), 4);
        if ($debit <= 0 || abs($debit - $credit) > .005 || collect($data['lines'])->contains(fn ($line) => (float) ($line['debit'] ?? 0) > 0 && (float) ($line['credit'] ?? 0) > 0)) {
            throw ValidationException::withMessages(['lines' => 'Recurring journal harus balance dan setiap line hanya menggunakan debit atau credit.']);
        }
        $accountIds = collect($data['lines'])->pluck('gl_account_id')->unique();
        abort_unless(DB::table('gl_accounts')->where('company_id', $context->id())->whereIn('id', $accountIds)->where('is_active', true)->where('allow_posting', true)->count() === $accountIds->count(), 422);
        $departmentIds = collect($data['lines'])->pluck('department_id')->filter()->unique();
        abort_unless($departmentIds->isEmpty() || DB::table('departments')->where('company_id', $context->id())->whereIn('id', $departmentIds)->count() === $departmentIds->count(), 422);
        $id = DB::transaction(function () use ($data, $context): int {
            $id = DB::table('accounting_recurring_templates')->insertGetId(['company_id' => $context->id(), 'branch_id' => $context->branch()?->id, 'name' => $data['name'], 'frequency' => $data['frequency'], 'starts_on' => $data['starts_on'], 'ends_on' => $data['ends_on'] ?? null, 'next_run_on' => $data['starts_on'], 'memo' => $data['memo'], 'is_adjustment' => (bool) ($data['is_adjustment'] ?? false), 'cash_flow_activity' => $data['cash_flow_activity'] ?? null, 'is_active' => true, 'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
            foreach ($data['lines'] as $index => $line) {
                DB::table('accounting_recurring_template_lines')->insert(['template_id' => $id, 'gl_account_id' => $line['gl_account_id'], 'department_id' => $line['department_id'] ?? null, 'description' => $line['description'] ?? null, 'debit' => $line['debit'] ?? 0, 'credit' => $line['credit'] ?? 0, 'line_number' => $index + 1, 'created_at' => now(), 'updated_at' => now()]);
            }

            return $id;
        });
        AuditLogger::log('accounting_recurring_template_created', 'accounting_recurring_template', $id, null, ['name' => $data['name'], 'frequency' => $data['frequency']], $context->id());

        return back()->with('status', 'Recurring journal template berhasil dibuat.');
    }

    public function generate(Request $request, int $template, CompanyContext $context): RedirectResponse
    {
        $data = $request->validate(['journal_date' => ['required', 'date']]);
        $journalId = DB::transaction(function () use ($template, $context, $data): int {
            $row = DB::table('accounting_recurring_templates')->where('company_id', $context->id())->where('id', $template)->where('is_active', true)->lockForUpdate()->firstOrFail();
            abort_if($data['journal_date'] !== $row->next_run_on, 422, 'Tanggal harus sama dengan jadwal template berikutnya.');
            abort_if($row->ends_on && $data['journal_date'] > $row->ends_on, 422, 'Recurring template sudah berakhir.');
            TransactionPeriodLock::ensureOpen($context->id(), 'accounting', $data['journal_date']);
            $lines = DB::table('accounting_recurring_template_lines')->where('template_id', $row->id)->orderBy('line_number')->get();
            $number = 'REC-'.date('Ym', strtotime($data['journal_date'])).'-'.str_pad((string) $row->id, 5, '0', STR_PAD_LEFT).'-'.date('d', strtotime($data['journal_date']));
            $journalId = DB::table('journal_entries')->insertGetId(['company_id' => $context->id(), 'branch_id' => $row->branch_id, 'document_number' => $number, 'journal_date' => $data['journal_date'], 'source_type' => 'recurring', 'status' => 'draft', 'is_adjustment' => $row->is_adjustment, 'cash_flow_activity' => $row->cash_flow_activity, 'memo' => $row->memo, 'total_debit' => $lines->sum('debit'), 'total_credit' => $lines->sum('credit'), 'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
            foreach ($lines as $line) {
                DB::table('journal_entry_lines')->insert(['journal_entry_id' => $journalId, 'gl_account_id' => $line->gl_account_id, 'department_id' => $line->department_id, 'description' => $line->description, 'debit' => $line->debit, 'credit' => $line->credit, 'line_number' => $line->line_number, 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('accounting_recurring_runs')->insert(['company_id' => $context->id(), 'template_id' => $row->id, 'scheduled_for' => $data['journal_date'], 'journal_entry_id' => $journalId, 'generated_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
            $months = match ($row->frequency) {
                'monthly' => 1, 'quarterly' => 3, default => 12
            };
            $anchorDay = CarbonImmutable::parse($row->starts_on)->day;
            $candidate = CarbonImmutable::parse($row->next_run_on)->addMonthsNoOverflow($months)->startOfMonth();
            $next = $candidate->day(min($anchorDay, $candidate->daysInMonth));
            DB::table('accounting_recurring_templates')->where('id', $row->id)->update(['next_run_on' => $next->toDateString(), 'is_active' => ! $row->ends_on || $next->toDateString() <= $row->ends_on, 'updated_at' => now()]);

            return $journalId;
        });
        AuditLogger::log('accounting_recurring_journal_generated', 'journal_entry', $journalId, null, ['template_id' => $template, 'status' => 'draft'], $context->id());

        return redirect()->route('accounting.show', $journalId)->with('status', 'Recurring journal dibuat sebagai draft dan menunggu review.');
    }
}
