<?php

namespace App\Http\Controllers;

use App\Support\AccountingConsolidationService;
use App\Support\AuditLogger;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountingConsolidationController extends Controller
{
    public function index(CompanyContext $context): View
    {
        return view('accounting.consolidation.index', ['company' => $context->current(), 'groups' => DB::table('accounting_consolidation_groups')->where('owner_company_id', $context->id())->orderBy('name')->get(), 'companies' => $context->memberships()]);
    }

    public function store(Request $request, CompanyContext $context, AccountingConsolidationService $service): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'presentation_currency' => ['required', 'string', 'size:3']]);
        $id = DB::transaction(function () use ($data, $context, $service): int {
            $id = DB::table('accounting_consolidation_groups')->insertGetId(['owner_company_id' => $context->id(), 'name' => $data['name'], 'presentation_currency' => strtoupper($data['presentation_currency']), 'is_active' => true, 'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('accounting_consolidation_members')->insert(['group_id' => $id, 'company_id' => $context->id(), 'ownership_percent' => 100, 'is_parent' => true, 'created_at' => now(), 'updated_at' => now()]);
            $service->syncMappings($id, $context->id());

            return $id;
        });
        AuditLogger::log('accounting_consolidation_group_created', 'accounting_consolidation_group', $id, null, $data, $context->id());

        return redirect()->route('accounting.consolidation.show', $id)->with('status', 'Consolidation group berhasil dibuat.');
    }

    public function show(int $group, CompanyContext $context): View
    {
        $row = $this->group($group, $context);

        return view('accounting.consolidation.show', ['company' => $context->current(), 'group' => $row, 'members' => DB::table('accounting_consolidation_members')->join('companies', 'companies.id', '=', 'accounting_consolidation_members.company_id')->where('group_id', $row->id)->select('accounting_consolidation_members.*', 'companies.name', 'companies.code', 'companies.currency')->get(), 'availableCompanies' => $context->memberships(), 'runs' => DB::table('accounting_consolidation_runs')->where('group_id', $row->id)->orderByDesc('period_to')->get()]);
    }

    public function addMember(Request $request, int $group, CompanyContext $context, AccountingConsolidationService $service): RedirectResponse
    {
        $row = $this->group($group, $context);
        $data = $request->validate(['company_id' => ['required', 'integer'], 'ownership_percent' => ['required', 'numeric', 'gt:0', 'max:100']]);
        abort_unless($context->memberships()->contains('id', (int) $data['company_id']), 403);
        DB::table('accounting_consolidation_members')->insertOrIgnore(['group_id' => $row->id, 'company_id' => $data['company_id'], 'ownership_percent' => $data['ownership_percent'], 'is_parent' => false, 'created_at' => now(), 'updated_at' => now()]);
        $service->syncMappings($row->id, (int) $data['company_id']);
        AuditLogger::log('accounting_consolidation_member_added', 'accounting_consolidation_group', $row->id, null, $data, $context->id());

        return back()->with('status', 'Entity berhasil ditambahkan dan COA dipetakan.');
    }

    public function createRun(Request $request, int $group, CompanyContext $context, AccountingConsolidationService $service): RedirectResponse
    {
        $row = $this->group($group, $context);
        $data = $request->validate(['period_from' => ['required', 'date'], 'period_to' => ['required', 'date', 'after_or_equal:period_from'], 'rates' => ['nullable', 'array'], 'rates.*' => ['nullable', 'numeric', 'gt:0']]);
        $id = $service->createRun($row->id, $data['period_from'], $data['period_to'], $data['rates'] ?? [], (int) auth()->id());
        AuditLogger::log('accounting_consolidation_run_created', 'accounting_consolidation_run', $id, null, ['group_id' => $row->id], $context->id());

        return redirect()->route('accounting.consolidation.runs.show', [$row->id, $id]);
    }

    public function showRun(int $group, int $run, CompanyContext $context): View
    {
        $row = $this->group($group, $context);
        $runRow = DB::table('accounting_consolidation_runs')->where('group_id', $row->id)->where('id', $run)->firstOrFail();
        $lines = DB::table('accounting_consolidation_lines')->leftJoin('companies', 'companies.id', '=', 'accounting_consolidation_lines.source_company_id')->where('run_id', $runRow->id)->select('accounting_consolidation_lines.*', 'companies.name as company_name')->orderBy('consolidation_code')->get();
        $summary = $lines->groupBy(fn ($line) => $line->consolidation_code.'|'.$line->account_type)->map(function ($rows) {
            $first = $rows->first();

            return (object) ['code' => $first->consolidation_code, 'name' => $first->consolidation_name, 'type' => $first->account_type, 'debit' => $rows->sum('debit'), 'credit' => $rows->sum('credit'), 'period_debit' => $rows->sum('period_debit'), 'period_credit' => $rows->sum('period_credit')];
        })->values();

        $assets = $summary->where('type', 'asset')->map(fn ($row) => (object) ['code' => $row->code, 'name' => $row->name, 'amount' => (float) $row->debit - (float) $row->credit]);
        $liabilities = $summary->where('type', 'liability')->map(fn ($row) => (object) ['code' => $row->code, 'name' => $row->name, 'amount' => (float) $row->credit - (float) $row->debit]);
        $equity = $summary->where('type', 'equity')->map(fn ($row) => (object) ['code' => $row->code, 'name' => $row->name, 'amount' => (float) $row->credit - (float) $row->debit]);
        $revenue = $summary->where('type', 'revenue')->map(fn ($row) => (object) ['code' => $row->code, 'name' => $row->name, 'amount' => (float) $row->period_credit - (float) $row->period_debit]);
        $expense = $summary->where('type', 'expense')->map(fn ($row) => (object) ['code' => $row->code, 'name' => $row->name, 'amount' => (float) $row->period_debit - (float) $row->period_credit]);
        $statement = compact('assets', 'liabilities', 'equity', 'revenue', 'expense') + ['netIncome' => (float) $revenue->sum('amount') - (float) $expense->sum('amount')];

        return view('accounting.consolidation.run', ['company' => $context->current(), 'group' => $row, 'run' => $runRow, 'lines' => $lines, 'summary' => $summary, 'statement' => $statement]);
    }

    public function addElimination(Request $request, int $group, int $run, CompanyContext $context, AccountingConsolidationService $service): RedirectResponse
    {
        $this->group($group, $context);
        abort_unless(DB::table('accounting_consolidation_runs')->where('group_id', $group)->where('id', $run)->exists(), 404);
        $request->merge(['lines' => collect($request->input('lines', []))->filter(fn ($line) => filled($line['code'] ?? null))->values()->all()]);
        $data = $request->validate(['lines' => ['required', 'array', 'min:2'], 'lines.*.code' => ['required', 'string', 'max:60'], 'lines.*.name' => ['required', 'string', 'max:255'], 'lines.*.type' => ['required', Rule::in(['asset', 'liability', 'equity', 'revenue', 'expense'])], 'lines.*.description' => ['nullable', 'string', 'max:500'], 'lines.*.debit' => ['nullable', 'numeric', 'min:0'], 'lines.*.credit' => ['nullable', 'numeric', 'min:0']]);
        $service->addElimination($run, $data['lines'], (int) auth()->id());
        AuditLogger::log('accounting_consolidation_elimination_added', 'accounting_consolidation_run', $run, null, ['line_count' => count($data['lines'])], $context->id());

        return back()->with('status', 'Balanced elimination entry berhasil ditambahkan.');
    }

    public function finalize(int $group, int $run, CompanyContext $context, AccountingConsolidationService $service): RedirectResponse
    {
        $this->group($group, $context);
        abort_unless(DB::table('accounting_consolidation_runs')->where('group_id', $group)->where('id', $run)->exists(), 404);
        $service->finalize($run, (int) auth()->id());
        AuditLogger::log('accounting_consolidation_finalized', 'accounting_consolidation_run', $run, ['status' => 'draft'], ['status' => 'completed'], $context->id());

        return back()->with('status', 'Consolidation finalized dan dikunci.');
    }

    private function group(int $id, CompanyContext $context): object
    {
        return DB::table('accounting_consolidation_groups')->where('owner_company_id', $context->id())->where('id', $id)->firstOrFail();
    }
}
