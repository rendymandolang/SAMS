<?php

namespace App\Http\Controllers;

use App\Support\AccountsReceivableService;
use App\Support\AuditLogger;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccountsReceivableController extends Controller
{
    public function index(Request $request, CompanyContext $context): View
    {
        $companyId = $context->id();
        $invoices = DB::table('ar_invoices')->join('accounting_customers', 'accounting_customers.id', '=', 'ar_invoices.customer_id')
            ->where('ar_invoices.company_id', $companyId)->when($request->filled('status'), fn ($q) => $q->where('ar_invoices.status', $request->string('status')->toString()))
            ->select('ar_invoices.*', 'accounting_customers.name as customer_name')->orderByDesc('invoice_date')->orderByDesc('id')->paginate(50)->withQueryString();
        $open = DB::table('ar_invoices')->where('company_id', $companyId)->whereIn('status', ['posted', 'partially_received'])->get();
        $today = today()->toDateString();
        $aging = ['current' => (float) $open->where('due_date', '>=', $today)->sum('outstanding_amount'), 'days_1_30' => 0.0, 'days_31_60' => 0.0, 'days_61_90' => 0.0, 'days_over_90' => 0.0];
        foreach ($open->where('due_date', '<', $today) as $invoice) {
            $days = abs((int) today()->diffInDays($invoice->due_date, true));
            $bucket = match (true) {
                $days <= 30 => 'days_1_30', $days <= 60 => 'days_31_60', $days <= 90 => 'days_61_90', default => 'days_over_90'
            };
            $aging[$bucket] += (float) $invoice->outstanding_amount;
        }

        return view('accounting.receivables.index', ['company' => $context->current(), 'invoices' => $invoices, 'aging' => $aging, 'totalOutstanding' => (float) $open->sum('outstanding_amount'), 'customers' => DB::table('accounting_customers')->where('company_id', $companyId)->orderBy('code')->get()]);
    }

    public function storeCustomer(Request $request, CompanyContext $context): RedirectResponse
    {
        $v = $request->validate(['code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9.\-]+$/'], 'name' => ['required', 'string', 'max:255'], 'email' => ['nullable', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:30'], 'tax_number' => ['nullable', 'string', 'max:50'], 'address' => ['nullable', 'string', 'max:2000'], 'payment_terms_days' => ['required', 'integer', 'min:0', 'max:3650']]);
        $code = Str::upper(trim($v['code']));
        if (DB::table('accounting_customers')->where('company_id', $context->id())->where('code', $code)->exists()) {
            throw ValidationException::withMessages(['code' => 'Customer code sudah digunakan.']);
        }
        $v['code'] = $code;
        $id = DB::table('accounting_customers')->insertGetId($v + ['company_id' => $context->id(), 'code' => $code, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        AuditLogger::log('ar_customer_created', 'accounting_customer', $id, null, ['code' => $code, 'name' => $v['name']], $context->id());

        return back()->with('status', 'Customer berhasil ditambahkan.');
    }

    public function create(CompanyContext $context): View
    {
        $id = $context->id();
        $postingRules = DB::table('accounting_posting_rules')->where('company_id', $id)->where('transaction_type', 'ar_invoice')->pluck('gl_account_id', 'account_role');

        return view('accounting.receivables.create', ['company' => $context->current(), 'customers' => DB::table('accounting_customers')->where('company_id', $id)->where('is_active', true)->orderBy('name')->get(), 'revenueAccounts' => $this->accounts($id, ['revenue']), 'assetAccounts' => $this->accounts($id, ['asset']), 'taxAccounts' => $this->accounts($id, ['liability']), 'departments' => DB::table('departments')->where('company_id', $id)->where('is_active', true)->orderBy('name')->get(), 'taxCodes' => DB::table('accounting_tax_codes')->where('company_id', $id)->where('type', 'sales')->where('is_active', true)->orderBy('code')->get(), 'postingRules' => $postingRules]);
    }

    public function store(Request $request, CompanyContext $context, AccountsReceivableService $service): RedirectResponse
    {
        $request->merge(['lines' => collect($request->input('lines', []))->filter(fn (array $line): bool => filled($line['gl_account_id'] ?? null) || filled($line['description'] ?? null) || filled($line['unit_price'] ?? null))->values()->all()]);
        $v = $request->validate(['customer_id' => ['required', 'integer'], 'customer_reference' => ['nullable', 'string', 'max:100'], 'invoice_date' => ['required', 'date'], 'due_date' => ['required', 'date', 'after_or_equal:invoice_date'], 'currency' => ['required', 'string', 'size:3'], 'ar_account_id' => ['required', 'integer'], 'tax_account_id' => ['nullable', 'integer'], 'tax_code_id' => ['nullable', 'integer'], 'tax_amount' => ['nullable', 'numeric', 'min:0'], 'notes' => ['nullable', 'string', 'max:2000'], 'lines' => ['required', 'array', 'min:1', 'max:100'], 'lines.*.gl_account_id' => ['required', 'integer'], 'lines.*.department_id' => ['nullable', 'integer'], 'lines.*.description' => ['required', 'string', 'max:255'], 'lines.*.quantity' => ['required', 'numeric', 'gt:0'], 'lines.*.unit_price' => ['required', 'numeric', 'gt:0']]);
        $company = $context->current();
        $companyId = (int) $company->id;
        $v['currency'] = strtoupper($v['currency']);
        abort_unless(DB::table('accounting_customers')->where('company_id', $companyId)->where('id', $v['customer_id'])->where('is_active', true)->exists(), 422);
        $this->validateAccount($companyId, (int) $v['ar_account_id'], ['asset'], 'ar_account_id');
        if (! empty($v['tax_code_id'])) {
            abort_unless(DB::table('accounting_tax_codes')->where('company_id', $companyId)->where('id', $v['tax_code_id'])->where('type', 'sales')->where('is_active', true)->exists(), 422);
        }
        if (empty($v['tax_code_id']) && (float) ($v['tax_amount'] ?? 0) > 0 && empty($v['tax_account_id'])) {
            throw ValidationException::withMessages(['tax_account_id' => 'Output tax account wajib dipilih jika tax amount lebih dari nol.']);
        }
        if (! empty($v['tax_account_id'])) {
            $this->validateAccount($companyId, (int) $v['tax_account_id'], ['liability'], 'tax_account_id');
        }
        foreach (collect($v['lines'])->pluck('gl_account_id')->unique() as $accountId) {
            $this->validateAccount($companyId, (int) $accountId, ['revenue'], 'lines');
        }
        $departments = collect($v['lines'])->pluck('department_id')->filter()->unique();
        abort_unless($departments->isEmpty() || DB::table('departments')->where('company_id', $companyId)->whereIn('id', $departments)->count() === $departments->count(), 422);
        $id = $service->createInvoice($companyId, $context->branch()?->id, (int) auth()->id(), $v);
        AuditLogger::log('ar_invoice_created', 'ar_invoice', $id, null, ['customer_id' => $v['customer_id']], $companyId);

        return redirect()->route('accounting.receivables.show', $id)->with('status', 'Customer invoice berhasil disimpan sebagai draft.');
    }

    public function show(int $invoice, CompanyContext $context): View
    {
        $data = $this->invoiceData($context->id(), $invoice);
        $data['company'] = $context->current();
        $data['cashAccounts'] = $this->accounts($context->id(), ['asset']);

        return view('accounting.receivables.show', $data);
    }

    public function print(int $invoice, CompanyContext $context): View
    {
        return view('accounting.receivables.print', $this->invoiceData($context->id(), $invoice) + ['company' => $context->current()]);
    }

    public function post(int $invoice, CompanyContext $context, AccountsReceivableService $service): RedirectResponse
    {
        $journal = $service->postInvoice($context->id(), $invoice, (int) auth()->id());
        AuditLogger::log('ar_invoice_posted', 'ar_invoice', $invoice, ['status' => 'draft'], ['status' => 'posted', 'journal_entry_id' => $journal], $context->id());

        return back()->with('status', 'Customer invoice berhasil diposting ke General Ledger dan AR aging.');
    }

    public function receive(Request $request, CompanyContext $context, AccountsReceivableService $service): RedirectResponse
    {
        $v = $request->validate(['invoice_id' => ['required', 'integer'], 'receipt_date' => ['required', 'date'], 'cash_account_id' => ['required', 'integer'], 'amount' => ['required', 'numeric', 'gt:0'], 'receipt_reference' => ['nullable', 'string', 'max:120'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $company = $context->current();
        $invoice = DB::table('ar_invoices')->where('company_id', $company->id)->where('id', $v['invoice_id'])->firstOrFail();
        $this->validateAccount((int) $company->id, (int) $v['cash_account_id'], ['asset'], 'cash_account_id');
        $id = $service->createReceipt((int) $company->id, $context->branch()?->id, (int) auth()->id(), $v + ['currency' => $invoice->currency]);
        AuditLogger::log('ar_receipt_posted', 'ar_receipt', $id, null, ['invoice_id' => $v['invoice_id'], 'amount' => $v['amount']], (int) $company->id);

        return back()->with('status', 'Penerimaan customer berhasil dialokasikan dan diposting.');
    }

    private function invoiceData(int $companyId, int $invoiceId): array
    {
        $invoice = DB::table('ar_invoices')->join('accounting_customers', 'accounting_customers.id', '=', 'ar_invoices.customer_id')->leftJoin('journal_entries', 'journal_entries.id', '=', 'ar_invoices.journal_entry_id')->where('ar_invoices.company_id', $companyId)->where('ar_invoices.id', $invoiceId)->select('ar_invoices.*', 'accounting_customers.name as customer_name', 'accounting_customers.address as customer_address', 'accounting_customers.tax_number as customer_tax_number', 'journal_entries.document_number as journal_number')->first();
        abort_unless($invoice, 404);
        $lines = DB::table('ar_invoice_lines')->join('gl_accounts', 'gl_accounts.id', '=', 'ar_invoice_lines.gl_account_id')->leftJoin('departments', 'departments.id', '=', 'ar_invoice_lines.department_id')->where('ar_invoice_id', $invoice->id)->select('ar_invoice_lines.*', 'gl_accounts.code as account_code', 'gl_accounts.name as account_name', 'departments.name as department_name')->orderBy('line_number')->get();
        $receipts = DB::table('ar_receipt_allocations')->join('ar_receipts', 'ar_receipts.id', '=', 'ar_receipt_allocations.ar_receipt_id')->where('ar_invoice_id', $invoice->id)->select('ar_receipts.*', 'ar_receipt_allocations.amount as allocated_amount')->orderByDesc('receipt_date')->get();

        return compact('invoice', 'lines', 'receipts');
    }

    private function accounts(int $companyId, array $types)
    {
        return DB::table('gl_accounts')->where('company_id', $companyId)->whereIn('type', $types)->where('is_active', true)->where('allow_posting', true)->orderBy('code')->get();
    }

    private function validateAccount(int $companyId, int $accountId, array $types, string $field): void
    {
        if (! DB::table('gl_accounts')->where('company_id', $companyId)->where('id', $accountId)->whereIn('type', $types)->where('is_active', true)->where('allow_posting', true)->exists()) {
            throw ValidationException::withMessages([$field => 'GL account tidak valid untuk transaksi ini.']);
        }
    }
}
