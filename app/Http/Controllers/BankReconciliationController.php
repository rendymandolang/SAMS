<?php

namespace App\Http\Controllers;

use App\Support\AuditLogger;
use App\Support\BankReconciliationService;
use App\Support\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BankReconciliationController extends Controller
{
    public function index(CompanyContext $context): View
    {
        $companyId = $context->id();
        $accounts = DB::table('accounting_bank_accounts')->join('gl_accounts', 'gl_accounts.id', '=', 'accounting_bank_accounts.gl_account_id')
            ->where('accounting_bank_accounts.company_id', $companyId)
            ->select('accounting_bank_accounts.*', 'gl_accounts.code as gl_code', 'gl_accounts.name as gl_name')->orderBy('accounting_bank_accounts.code')->get();
        $imports = DB::table('bank_statement_imports')->join('accounting_bank_accounts', 'accounting_bank_accounts.id', '=', 'bank_statement_imports.bank_account_id')
            ->join('bank_reconciliations', 'bank_reconciliations.bank_statement_import_id', '=', 'bank_statement_imports.id')
            ->where('bank_statement_imports.company_id', $companyId)
            ->select('bank_statement_imports.*', 'accounting_bank_accounts.name as bank_account_name', 'bank_reconciliations.id as reconciliation_id', 'bank_reconciliations.status as reconciliation_status', 'bank_reconciliations.difference')
            ->orderByDesc('bank_statement_imports.period_end')->orderByDesc('bank_statement_imports.id')->paginate(30);

        return view('accounting.bank-reconciliation.index', [
            'company' => $context->current(), 'bankAccounts' => $accounts, 'imports' => $imports,
            'assetAccounts' => DB::table('gl_accounts')->where('company_id', $companyId)->where('type', 'asset')->where('allow_posting', true)->where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            echo "Date,Description,Reference,Debit,Credit,Balance\n";
            echo now()->toDateString().",Example incoming transfer,TRX-001,0,1000000,1000000\n";
        }, 'supersoft-bank-statement-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function storeBankAccount(Request $request, CompanyContext $context): RedirectResponse
    {
        $company = $context->current();
        $v = $request->validate([
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9.\-]+$/'], 'name' => ['required', 'string', 'max:255'],
            'bank_name' => ['required', 'string', 'max:255'], 'account_number_masked' => ['nullable', 'string', 'max:50'],
            'currency' => ['required', 'string', 'size:3'], 'gl_account_id' => ['required', 'integer'],
        ]);
        $v['code'] = Str::upper(trim($v['code']));
        $v['currency'] = Str::upper($v['currency']);
        if ($v['currency'] !== Str::upper($company->currency)) {
            throw ValidationException::withMessages(['currency' => 'Rekening bank harus menggunakan base currency perusahaan pada tahap ini.']);
        }
        if (! DB::table('gl_accounts')->where('company_id', $company->id)->where('id', $v['gl_account_id'])->where('type', 'asset')->where('allow_posting', true)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['gl_account_id' => 'GL account bank tidak valid.']);
        }
        if (DB::table('accounting_bank_accounts')->where('company_id', $company->id)->where(fn ($query) => $query->where('code', $v['code'])->orWhere('gl_account_id', $v['gl_account_id']))->exists()) {
            throw ValidationException::withMessages(['code' => 'Code atau GL account sudah terhubung dengan rekening bank lain.']);
        }
        $id = DB::table('accounting_bank_accounts')->insertGetId($v + ['company_id' => $company->id, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        AuditLogger::log('bank_account_created', 'accounting_bank_account', $id, null, ['code' => $v['code'], 'gl_account_id' => $v['gl_account_id']], (int) $company->id);

        return back()->with('status', 'Rekening bank berhasil dihubungkan ke General Ledger.');
    }

    public function import(Request $request, CompanyContext $context, BankReconciliationService $service): RedirectResponse
    {
        $v = $request->validate([
            'bank_account_id' => ['required', 'integer'], 'statement' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            'closing_balance' => ['nullable', 'numeric', 'between:-999999999999999.9999,999999999999999.9999'],
        ]);
        $result = $service->import($context->id(), (int) $v['bank_account_id'], (int) auth()->id(), $request->file('statement'), isset($v['closing_balance']) ? (float) $v['closing_balance'] : null);
        AuditLogger::log('bank_statement_imported', 'bank_statement_import', $result['import_id'], null, ['line_count' => $result['line_count'], 'auto_matched' => $result['auto_matched']], $context->id());

        return redirect()->route('accounting.bank-reconciliation.show', $result['reconciliation_id'])
            ->with('status', $result['line_count'].' transaksi diimpor; '.$result['auto_matched'].' cocok otomatis.');
    }

    public function show(int $reconciliation, CompanyContext $context, BankReconciliationService $service): View
    {
        $companyId = $context->id();
        $record = DB::table('bank_reconciliations')->join('bank_statement_imports', 'bank_statement_imports.id', '=', 'bank_reconciliations.bank_statement_import_id')
            ->join('accounting_bank_accounts', 'accounting_bank_accounts.id', '=', 'bank_reconciliations.bank_account_id')
            ->join('gl_accounts', 'gl_accounts.id', '=', 'accounting_bank_accounts.gl_account_id')
            ->where('bank_reconciliations.company_id', $companyId)->where('bank_reconciliations.id', $reconciliation)
            ->select('bank_reconciliations.*', 'bank_statement_imports.original_filename', 'bank_statement_imports.period_start', 'bank_statement_imports.period_end', 'bank_statement_imports.line_count', 'accounting_bank_accounts.name as bank_account_name', 'accounting_bank_accounts.bank_name', 'accounting_bank_accounts.gl_account_id', 'gl_accounts.code as gl_code', 'gl_accounts.name as gl_name')->firstOrFail();
        $bookBalance = $service->bookBalance($companyId, (int) $record->bank_account_id, $record->statement_date);
        if ($record->status !== 'completed') {
            DB::table('bank_reconciliations')->where('id', $record->id)->update(['book_balance' => $bookBalance, 'difference' => round((float) $record->statement_balance - $bookBalance, 4), 'updated_at' => now()]);
            $record->book_balance = $bookBalance;
            $record->difference = round((float) $record->statement_balance - $bookBalance, 4);
        }
        $lines = DB::table('bank_statement_lines')->leftJoin('journal_entry_lines', 'journal_entry_lines.id', '=', 'bank_statement_lines.matched_journal_entry_line_id')
            ->leftJoin('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('bank_statement_lines.bank_statement_import_id', $record->bank_statement_import_id)
            ->select('bank_statement_lines.*', 'journal_entries.document_number as journal_number', 'journal_entries.id as journal_id')->orderBy('transaction_date')->orderBy('bank_statement_lines.id')->get();
        $usedIds = DB::table('bank_statement_lines')->whereNotNull('matched_journal_entry_line_id')->pluck('matched_journal_entry_line_id');
        $candidates = DB::table('journal_entry_lines')->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.company_id', $companyId)->where('journal_entries.status', 'posted')->where('journal_entry_lines.gl_account_id', $record->gl_account_id)
            ->whereBetween('journal_entries.journal_date', [CarbonImmutable::parse($record->period_start)->subDays(7)->toDateString(), CarbonImmutable::parse($record->period_end)->addDays(7)->toDateString()])
            ->when($usedIds->isNotEmpty(), fn ($query) => $query->whereNotIn('journal_entry_lines.id', $usedIds))
            ->select('journal_entry_lines.*', 'journal_entries.document_number', 'journal_entries.journal_date', 'journal_entries.memo')->orderBy('journal_entries.journal_date')->get();

        return view('accounting.bank-reconciliation.show', ['company' => $context->current(), 'reconciliation' => $record, 'lines' => $lines, 'candidates' => $candidates, 'unresolved' => $lines->where('status', 'unmatched')->count()]);
    }

    public function print(int $reconciliation, CompanyContext $context, BankReconciliationService $service): View
    {
        $companyId = $context->id();
        $record = DB::table('bank_reconciliations')->join('bank_statement_imports', 'bank_statement_imports.id', '=', 'bank_reconciliations.bank_statement_import_id')
            ->join('accounting_bank_accounts', 'accounting_bank_accounts.id', '=', 'bank_reconciliations.bank_account_id')
            ->join('gl_accounts', 'gl_accounts.id', '=', 'accounting_bank_accounts.gl_account_id')
            ->leftJoin('users', 'users.id', '=', 'bank_reconciliations.completed_by')
            ->where('bank_reconciliations.company_id', $companyId)->where('bank_reconciliations.id', $reconciliation)
            ->select('bank_reconciliations.*', 'bank_statement_imports.original_filename', 'bank_statement_imports.period_start', 'bank_statement_imports.period_end', 'bank_statement_imports.line_count', 'accounting_bank_accounts.name as bank_account_name', 'accounting_bank_accounts.bank_name', 'accounting_bank_accounts.account_number_masked', 'accounting_bank_accounts.currency', 'gl_accounts.code as gl_code', 'gl_accounts.name as gl_name', 'users.name as completed_by_name')->firstOrFail();
        if ($record->status !== 'completed') {
            $record->book_balance = $service->bookBalance($companyId, (int) $record->bank_account_id, $record->statement_date);
            $record->difference = round((float) $record->statement_balance - (float) $record->book_balance, 4);
        }
        $lines = DB::table('bank_statement_lines')->leftJoin('journal_entry_lines', 'journal_entry_lines.id', '=', 'bank_statement_lines.matched_journal_entry_line_id')
            ->leftJoin('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('bank_statement_lines.bank_statement_import_id', $record->bank_statement_import_id)
            ->select('bank_statement_lines.*', 'journal_entries.document_number as journal_number')->orderBy('transaction_date')->orderBy('bank_statement_lines.id')->get();

        return view('accounting.bank-reconciliation.print', ['company' => $context->current(), 'reconciliation' => $record, 'lines' => $lines]);
    }

    public function match(Request $request, int $line, CompanyContext $context, BankReconciliationService $service): RedirectResponse
    {
        $v = $request->validate(['journal_entry_line_id' => ['required', 'integer']]);
        $service->match($context->id(), $line, (int) $v['journal_entry_line_id'], (int) auth()->id());
        AuditLogger::log('bank_statement_line_matched', 'bank_statement_line', $line, null, ['journal_entry_line_id' => $v['journal_entry_line_id']], $context->id());

        return back()->with('status', 'Transaksi berhasil dicocokkan.');
    }

    public function unmatch(int $line, CompanyContext $context, BankReconciliationService $service): RedirectResponse
    {
        $service->unmatch($context->id(), $line);
        AuditLogger::log('bank_statement_line_unmatched', 'bank_statement_line', $line, null, ['status' => 'unmatched'], $context->id());

        return back()->with('status', 'Pencocokan transaksi dibatalkan.');
    }

    public function exclude(Request $request, int $line, CompanyContext $context, BankReconciliationService $service): RedirectResponse
    {
        $v = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $service->exclude($context->id(), $line, (int) auth()->id(), $v['reason']);
        AuditLogger::log('bank_statement_line_excluded', 'bank_statement_line', $line, null, ['reason' => $v['reason']], $context->id());

        return back()->with('status', 'Transaksi ditandai sebagai pengecualian dengan alasan audit.');
    }

    public function complete(int $reconciliation, CompanyContext $context, BankReconciliationService $service): RedirectResponse
    {
        $service->complete($context->id(), $reconciliation, (int) auth()->id());
        AuditLogger::log('bank_reconciliation_completed', 'bank_reconciliation', $reconciliation, ['status' => 'draft'], ['status' => 'completed'], $context->id());

        return back()->with('status', 'Bank reconciliation selesai dan dikunci.');
    }
}
