<?php

namespace App\Http\Controllers;

use App\Support\AdvancedAccountingService;
use App\Support\AuditLogger;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountingAdvancedController extends Controller
{
    public function index(CompanyContext $context): View
    {
        $companyId = $context->id();

        return view('accounting.advanced', [
            'company' => $context->current(),
            'payableInvoices' => DB::table('ap_invoices')->join('suppliers', 'suppliers.id', '=', 'ap_invoices.supplier_id')->where('ap_invoices.company_id', $companyId)->whereIn('ap_invoices.status', ['posted', 'partially_paid'])->select('ap_invoices.*', 'suppliers.name as party_name')->orderByDesc('invoice_date')->get(),
            'receivableInvoices' => DB::table('ar_invoices')->join('accounting_customers', 'accounting_customers.id', '=', 'ar_invoices.customer_id')->where('ar_invoices.company_id', $companyId)->whereIn('ar_invoices.status', ['posted', 'partially_received'])->select('ar_invoices.*', 'accounting_customers.name as party_name')->orderByDesc('invoice_date')->get(),
            'creditNotes' => DB::table('accounting_credit_notes')->leftJoin('journal_entries', 'journal_entries.id', '=', 'accounting_credit_notes.journal_entry_id')->where('accounting_credit_notes.company_id', $companyId)->select('accounting_credit_notes.*', 'journal_entries.document_number as journal_number')->orderByDesc('credit_date')->get(),
            'fiscalCloses' => DB::table('fiscal_year_closes')->join('gl_accounts', 'gl_accounts.id', '=', 'fiscal_year_closes.retained_earnings_account_id')->where('fiscal_year_closes.company_id', $companyId)->select('fiscal_year_closes.*', 'gl_accounts.code as retained_code', 'gl_accounts.name as retained_name')->orderByDesc('fiscal_year')->get(),
            'apOffsetAccounts' => $this->accounts($companyId, ['asset', 'expense']),
            'arOffsetAccounts' => $this->accounts($companyId, ['revenue']),
            'equityAccounts' => $this->accounts($companyId, ['equity']),
        ]);
    }

    public function storeCreditNote(Request $request, CompanyContext $context, AdvancedAccountingService $service): RedirectResponse
    {
        $v = $request->validate([
            'type' => ['required', Rule::in(['ap', 'ar'])], 'invoice_id' => ['required', 'integer'], 'credit_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'], 'offset_account_id' => ['required', 'integer'],
            'external_reference' => ['nullable', 'string', 'max:120'], 'reason' => ['required', 'string', 'max:2000'],
        ]);
        $id = $service->createCreditNote($context->id(), $context->branch()?->id, (int) auth()->id(), $v);
        AuditLogger::log('accounting_credit_note_created', 'accounting_credit_note', $id, null, $v, $context->id());

        return back()->with('status', 'Credit note berhasil disimpan sebagai draft.');
    }

    public function postCreditNote(int $creditNote, CompanyContext $context, AdvancedAccountingService $service): RedirectResponse
    {
        $journalId = $service->postCreditNote($context->id(), $creditNote, (int) auth()->id());
        AuditLogger::log('accounting_credit_note_posted', 'accounting_credit_note', $creditNote, ['status' => 'draft'], ['status' => 'posted', 'journal_entry_id' => $journalId], $context->id());

        return back()->with('status', 'Credit note berhasil diposting dan outstanding invoice diperbarui.');
    }

    public function reversePayment(Request $request, int $payment, CompanyContext $context, AdvancedAccountingService $service): RedirectResponse
    {
        $v = $this->reversalData($request);
        $journalId = $service->reversePayment($context->id(), $payment, (int) auth()->id(), $v['reversal_date'], $v['reason']);
        AuditLogger::log('ap_payment_reversed', 'ap_payment', $payment, ['status' => 'posted'], ['status' => 'reversed', 'journal_entry_id' => $journalId, 'reason' => $v['reason']], $context->id());

        return back()->with('status', 'Supplier payment berhasil direversal secara terkendali.');
    }

    public function reverseReceipt(Request $request, int $receipt, CompanyContext $context, AdvancedAccountingService $service): RedirectResponse
    {
        $v = $this->reversalData($request);
        $journalId = $service->reverseReceipt($context->id(), $receipt, (int) auth()->id(), $v['reversal_date'], $v['reason']);
        AuditLogger::log('ar_receipt_reversed', 'ar_receipt', $receipt, ['status' => 'posted'], ['status' => 'reversed', 'journal_entry_id' => $journalId, 'reason' => $v['reason']], $context->id());

        return back()->with('status', 'Customer receipt berhasil direversal secara terkendali.');
    }

    public function closeFiscalYear(Request $request, CompanyContext $context, AdvancedAccountingService $service): RedirectResponse
    {
        $v = $request->validate(['fiscal_year' => ['required', 'integer', 'min:2000', 'max:2100'], 'retained_earnings_account_id' => ['required', 'integer']]);
        $id = $service->closeFiscalYear($context->id(), (int) auth()->id(), (int) $v['fiscal_year'], (int) $v['retained_earnings_account_id']);
        AuditLogger::log('accounting_fiscal_year_closed', 'fiscal_year_close', $id, null, $v, $context->id());

        return back()->with('status', 'Fiscal year berhasil ditutup dan saldo laba-rugi dipindahkan ke retained earnings.');
    }

    public function reopenFiscalYear(Request $request, int $close, CompanyContext $context, AdvancedAccountingService $service): RedirectResponse
    {
        $v = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:500']]);
        $journalId = $service->reopenFiscalYear($context->id(), $close, (int) auth()->id(), $v['reason']);
        AuditLogger::log('accounting_fiscal_year_reopened', 'fiscal_year_close', $close, ['status' => 'completed'], ['status' => 'reopened', 'journal_entry_id' => $journalId, 'reason' => $v['reason']], $context->id());

        return back()->with('status', 'Fiscal year dibuka kembali melalui reversal journal.');
    }

    private function reversalData(Request $request): array
    {
        return $request->validate(['reversal_date' => ['required', 'date'], 'reason' => ['required', 'string', 'min:10', 'max:500']]);
    }

    private function accounts(int $companyId, array $types)
    {
        return DB::table('gl_accounts')->where('company_id', $companyId)->whereIn('type', $types)->where('is_active', true)->where('allow_posting', true)->orderBy('code')->get();
    }
}
