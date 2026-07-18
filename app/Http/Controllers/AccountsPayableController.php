<?php

namespace App\Http\Controllers;

use App\Support\AccountsPayableService;
use App\Support\AuditLogger;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccountsPayableController extends Controller
{
    public function index(Request $request, CompanyContext $companyContext): View
    {
        $companyId = $companyContext->id();
        $status = $request->string('status')->toString();
        $invoices = DB::table('ap_invoices')
            ->join('suppliers', 'suppliers.id', '=', 'ap_invoices.supplier_id')
            ->where('ap_invoices.company_id', $companyId)
            ->when($status, fn ($query) => $query->where('ap_invoices.status', $status))
            ->select('ap_invoices.*', 'suppliers.name as supplier_name')
            ->orderByDesc('ap_invoices.invoice_date')
            ->orderByDesc('ap_invoices.id')
            ->paginate(50)
            ->withQueryString();
        $open = DB::table('ap_invoices')
            ->where('company_id', $companyId)
            ->whereIn('status', ['posted', 'partially_paid'])
            ->get();
        $today = today()->toDateString();
        $aging = [
            'current' => (float) $open->where('due_date', '>=', $today)->sum('outstanding_amount'),
            'days_1_30' => 0.0,
            'days_31_60' => 0.0,
            'days_61_90' => 0.0,
            'days_over_90' => 0.0,
        ];
        foreach ($open->where('due_date', '<', $today) as $invoice) {
            $days = abs((int) today()->diffInDays($invoice->due_date, true));
            $bucket = match (true) {
                $days <= 30 => 'days_1_30',
                $days <= 60 => 'days_31_60',
                $days <= 90 => 'days_61_90',
                default => 'days_over_90',
            };
            $aging[$bucket] += (float) $invoice->outstanding_amount;
        }

        return view('accounting.payables.index', [
            'company' => $companyContext->current(),
            'invoices' => $invoices,
            'aging' => $aging,
            'totalOutstanding' => (float) $open->sum('outstanding_amount'),
        ]);
    }

    public function create(CompanyContext $companyContext): View
    {
        $companyId = $companyContext->id();
        $postingRules = DB::table('accounting_posting_rules')->where('company_id', $companyId)->where('transaction_type', 'ap_invoice')->pluck('gl_account_id', 'account_role');

        return view('accounting.payables.create', [
            'company' => $companyContext->current(),
            'suppliers' => DB::table('suppliers')->where('company_id', $companyId)->where('is_active', true)->whereNull('deleted_at')->orderBy('name')->get(),
            'purchaseOrders' => DB::table('purchase_orders')->where('company_id', $companyId)->whereIn('status', ['approved', 'partially_received', 'received'])->orderByDesc('order_date')->limit(100)->get(),
            'debitAccounts' => $this->accounts($companyId, ['asset', 'expense']),
            'liabilityAccounts' => $this->accounts($companyId, ['liability']),
            'taxAccounts' => $this->accounts($companyId, ['asset', 'expense']),
            'departments' => DB::table('departments')->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(),
            'taxCodes' => DB::table('accounting_tax_codes')->where('company_id', $companyId)->where('type', 'purchase')->where('is_active', true)->orderBy('code')->get(),
            'withholdingCodes' => DB::table('accounting_tax_codes')->where('company_id', $companyId)->where('type', 'withholding')->where('is_active', true)->orderBy('code')->get(),
            'postingRules' => $postingRules,
            'poItems' => DB::table('purchase_order_items')->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')->join('items', 'items.id', '=', 'purchase_order_items.item_id')->where('purchase_orders.company_id', $companyId)->whereIn('purchase_orders.status', ['approved', 'partially_received', 'received'])->select('purchase_order_items.*', 'purchase_orders.document_number as po_number', 'items.name as item_name')->orderByDesc('purchase_orders.order_date')->limit(500)->get(),
            'receiptItems' => DB::table('goods_receipt_items')->join('goods_receipts', 'goods_receipts.id', '=', 'goods_receipt_items.goods_receipt_id')->join('items', 'items.id', '=', 'goods_receipt_items.item_id')->where('goods_receipts.company_id', $companyId)->where('goods_receipts.status', 'posted')->select('goods_receipt_items.*', 'goods_receipts.document_number as receipt_number', 'goods_receipts.purchase_order_id', 'items.name as item_name')->orderByDesc('goods_receipts.received_at')->limit(500)->get(),
        ]);
    }

    public function store(Request $request, CompanyContext $companyContext, AccountsPayableService $service): RedirectResponse
    {
        $company = $companyContext->current();
        $request->merge([
            'lines' => collect($request->input('lines', []))
                ->filter(fn (array $line): bool => filled($line['gl_account_id'] ?? null) || filled($line['description'] ?? null) || filled($line['unit_price'] ?? null))
                ->values()
                ->all(),
        ]);
        $validated = $request->validate([
            'supplier_id' => ['required', 'integer'],
            'purchase_order_id' => ['nullable', 'integer'],
            'supplier_invoice_number' => ['required', 'string', 'max:100'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:invoice_date'],
            'currency' => ['required', 'string', 'size:3'],
            'ap_account_id' => ['required', 'integer'],
            'tax_account_id' => ['nullable', 'integer'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_code_id' => ['nullable', 'integer'],
            'withholding_tax_code_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.gl_account_id' => ['required', 'integer'],
            'lines.*.department_id' => ['nullable', 'integer'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'gt:0'],
            'lines.*.purchase_order_item_id' => ['nullable', 'integer'],
            'lines.*.goods_receipt_item_id' => ['nullable', 'integer'],
        ]);
        $companyId = (int) $company->id;
        $validated['currency'] = strtoupper($validated['currency']);
        abort_unless(DB::table('suppliers')->where('company_id', $companyId)->where('id', $validated['supplier_id'])->where('is_active', true)->exists(), 422);
        if (DB::table('ap_invoices')->where('company_id', $companyId)->where('supplier_id', $validated['supplier_id'])->where('supplier_invoice_number', trim($validated['supplier_invoice_number']))->exists()) {
            throw ValidationException::withMessages(['supplier_invoice_number' => 'Nomor invoice supplier sudah pernah dicatat.']);
        }
        if (! empty($validated['purchase_order_id'])) {
            abort_unless(DB::table('purchase_orders')->where('company_id', $companyId)->where('supplier_id', $validated['supplier_id'])->where('id', $validated['purchase_order_id'])->exists(), 422);
        }
        $this->validateAccount($companyId, (int) $validated['ap_account_id'], ['liability'], 'ap_account_id');
        if (! empty($validated['tax_code_id'])) {
            abort_unless(DB::table('accounting_tax_codes')->where('company_id', $companyId)->where('id', $validated['tax_code_id'])->where('type', 'purchase')->where('is_active', true)->exists(), 422);
        }
        if (! empty($validated['withholding_tax_code_id'])) {
            abort_unless(DB::table('accounting_tax_codes')->where('company_id', $companyId)->where('id', $validated['withholding_tax_code_id'])->where('type', 'withholding')->where('is_active', true)->exists(), 422);
        }
        if (empty($validated['tax_code_id']) && (float) ($validated['tax_amount'] ?? 0) > 0 && empty($validated['tax_account_id'])) {
            throw ValidationException::withMessages(['tax_account_id' => 'Tax account wajib dipilih jika tax amount lebih dari nol.']);
        }
        if (! empty($validated['tax_account_id'])) {
            $this->validateAccount($companyId, (int) $validated['tax_account_id'], ['asset', 'expense'], 'tax_account_id');
        }
        foreach (collect($validated['lines'])->pluck('gl_account_id')->unique() as $accountId) {
            $this->validateAccount($companyId, (int) $accountId, ['asset', 'expense'], 'lines');
        }
        $departmentIds = collect($validated['lines'])->pluck('department_id')->filter()->unique();
        abort_unless($departmentIds->isEmpty() || DB::table('departments')->where('company_id', $companyId)->whereIn('id', $departmentIds)->count() === $departmentIds->count(), 422);
        $poItemIds = collect($validated['lines'])->pluck('purchase_order_item_id')->filter()->unique();
        abort_unless($poItemIds->isEmpty() || DB::table('purchase_order_items')->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')->where('purchase_orders.company_id', $companyId)->whereIn('purchase_order_items.id', $poItemIds)->count() === $poItemIds->count(), 422);
        $receiptItemIds = collect($validated['lines'])->pluck('goods_receipt_item_id')->filter()->unique();
        abort_unless($receiptItemIds->isEmpty() || DB::table('goods_receipt_items')->join('goods_receipts', 'goods_receipts.id', '=', 'goods_receipt_items.goods_receipt_id')->where('goods_receipts.company_id', $companyId)->whereIn('goods_receipt_items.id', $receiptItemIds)->count() === $receiptItemIds->count(), 422);

        $invoiceId = $service->createInvoice($companyId, $companyContext->branch()?->id, (int) auth()->id(), $validated);
        AuditLogger::log('ap_invoice_created', 'ap_invoice', $invoiceId, null, [
            'supplier_id' => $validated['supplier_id'],
            'supplier_invoice_number' => $validated['supplier_invoice_number'],
        ], $companyId);

        return redirect()->route('accounting.payables.show', $invoiceId)->with('status', 'Supplier invoice berhasil disimpan sebagai draft.');
    }

    public function show(int $invoice, CompanyContext $companyContext): View
    {
        $companyId = $companyContext->id();
        $row = DB::table('ap_invoices')
            ->join('suppliers', 'suppliers.id', '=', 'ap_invoices.supplier_id')
            ->leftJoin('journal_entries', 'journal_entries.id', '=', 'ap_invoices.journal_entry_id')
            ->where('ap_invoices.company_id', $companyId)
            ->where('ap_invoices.id', $invoice)
            ->select('ap_invoices.*', 'suppliers.name as supplier_name', 'journal_entries.document_number as journal_number')
            ->first();
        abort_unless($row, 404);
        $lines = DB::table('ap_invoice_lines')
            ->join('gl_accounts', 'gl_accounts.id', '=', 'ap_invoice_lines.gl_account_id')
            ->leftJoin('departments', 'departments.id', '=', 'ap_invoice_lines.department_id')
            ->where('ap_invoice_lines.ap_invoice_id', $row->id)
            ->select('ap_invoice_lines.*', 'gl_accounts.code as account_code', 'gl_accounts.name as account_name', 'departments.name as department_name')
            ->orderBy('line_number')
            ->get();
        $payments = DB::table('ap_payment_allocations')
            ->join('ap_payments', 'ap_payments.id', '=', 'ap_payment_allocations.ap_payment_id')
            ->where('ap_payment_allocations.ap_invoice_id', $row->id)
            ->select('ap_payments.*', 'ap_payment_allocations.amount as allocated_amount')
            ->orderByDesc('ap_payments.payment_date')
            ->get();

        return view('accounting.payables.show', [
            'company' => $companyContext->current(),
            'invoice' => $row,
            'lines' => $lines,
            'payments' => $payments,
            'cashAccounts' => $this->accounts($companyId, ['asset']),
        ]);
    }

    public function print(int $invoice, CompanyContext $companyContext): View
    {
        $companyId = $companyContext->id();
        $row = DB::table('ap_invoices')
            ->join('suppliers', 'suppliers.id', '=', 'ap_invoices.supplier_id')
            ->leftJoin('journal_entries', 'journal_entries.id', '=', 'ap_invoices.journal_entry_id')
            ->where('ap_invoices.company_id', $companyId)
            ->where('ap_invoices.id', $invoice)
            ->select('ap_invoices.*', 'suppliers.name as supplier_name', 'suppliers.address as supplier_address', 'suppliers.tax_number as supplier_tax_number', 'journal_entries.document_number as journal_number')
            ->first();
        abort_unless($row, 404);
        $lines = DB::table('ap_invoice_lines')
            ->join('gl_accounts', 'gl_accounts.id', '=', 'ap_invoice_lines.gl_account_id')
            ->leftJoin('departments', 'departments.id', '=', 'ap_invoice_lines.department_id')
            ->where('ap_invoice_lines.ap_invoice_id', $row->id)
            ->select('ap_invoice_lines.*', 'gl_accounts.code as account_code', 'gl_accounts.name as account_name', 'departments.name as department_name')
            ->orderBy('line_number')
            ->get();

        return view('accounting.payables.print', [
            'company' => $companyContext->current(),
            'invoice' => $row,
            'lines' => $lines,
        ]);
    }

    public function post(int $invoice, CompanyContext $companyContext, AccountsPayableService $service): RedirectResponse
    {
        $companyId = $companyContext->id();
        $journalId = $service->postInvoice($companyId, $invoice, (int) auth()->id());
        AuditLogger::log('ap_invoice_posted', 'ap_invoice', $invoice, ['status' => 'draft'], [
            'status' => 'posted',
            'journal_entry_id' => $journalId,
        ], $companyId);

        return back()->with('status', 'Supplier invoice berhasil diposting ke General Ledger dan AP aging.');
    }

    public function pay(Request $request, CompanyContext $companyContext, AccountsPayableService $service): RedirectResponse
    {
        $validated = $request->validate([
            'invoice_id' => ['required', 'integer'],
            'payment_date' => ['required', 'date'],
            'cash_account_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $company = $companyContext->current();
        $invoice = DB::table('ap_invoices')->where('company_id', $company->id)->where('id', $validated['invoice_id'])->first();
        abort_unless($invoice, 404);
        $this->validateAccount((int) $company->id, (int) $validated['cash_account_id'], ['asset'], 'cash_account_id');

        $paymentId = $service->createPayment((int) $company->id, $companyContext->branch()?->id, (int) auth()->id(), [
            'supplier_id' => $invoice->supplier_id,
            'payment_date' => $validated['payment_date'],
            'currency' => $invoice->currency,
            'cash_account_id' => $validated['cash_account_id'],
            'ap_account_id' => $invoice->ap_account_id,
            'payment_reference' => $validated['payment_reference'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ], [['invoice_id' => (int) $invoice->id, 'amount' => $validated['amount']]]);
        AuditLogger::log('ap_payment_posted', 'ap_payment', $paymentId, null, [
            'invoice_id' => $invoice->id,
            'amount' => $validated['amount'],
        ], (int) $company->id);

        return back()->with('status', 'Pembayaran supplier berhasil dialokasikan dan diposting ke General Ledger.');
    }

    private function accounts(int $companyId, array $types)
    {
        return DB::table('gl_accounts')
            ->where('company_id', $companyId)
            ->whereIn('type', $types)
            ->where('is_active', true)
            ->where('allow_posting', true)
            ->orderBy('code')
            ->get();
    }

    private function validateAccount(int $companyId, int $accountId, array $types, string $field): void
    {
        if (! DB::table('gl_accounts')->where('company_id', $companyId)->where('id', $accountId)->whereIn('type', $types)->where('is_active', true)->where('allow_posting', true)->exists()) {
            throw ValidationException::withMessages([$field => 'GL account tidak valid untuk transaksi ini.']);
        }
    }
}
