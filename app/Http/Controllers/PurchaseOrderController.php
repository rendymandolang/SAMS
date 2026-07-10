<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function index(): View
    {
        $company = $this->company();

        $purchaseOrders = DB::table('purchase_orders')
            ->join('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
            ->leftJoin('purchase_requests', 'purchase_requests.id', '=', 'purchase_orders.purchase_request_id')
            ->where('purchase_orders.company_id', $company->id)
            ->whereNull('purchase_orders.deleted_at')
            ->select(
                'purchase_orders.*',
                'suppliers.name as supplier_name',
                'purchase_requests.document_number as purchase_request_number',
            )
            ->orderByDesc('purchase_orders.order_date')
            ->orderByDesc('purchase_orders.id')
            ->paginate(10);

        return view('purchase_orders.index', compact('purchaseOrders'));
    }

    public function createFromPurchaseRequest(int $purchaseRequest): View|RedirectResponse
    {
        $header = $this->findApprovedPurchaseRequest($purchaseRequest);

        if ($this->purchaseOrderExistsForPurchaseRequest($header->id)) {
            return redirect()
                ->route('purchase-requests.show', $header->id)
                ->with('status', 'Purchase Request ini sudah memiliki Purchase Order.');
        }

        $items = $this->purchaseRequestItems($header->id);

        return view('purchase_orders.create_from_pr', [
            'header' => $header,
            'items' => $items,
            'suppliers' => $this->suppliers(),
        ]);
    }

    public function storeFromPurchaseRequest(Request $request, int $purchaseRequest): RedirectResponse
    {
        $company = $this->company();
        $header = $this->findApprovedPurchaseRequest($purchaseRequest);

        if ($this->purchaseOrderExistsForPurchaseRequest($header->id)) {
            return redirect()
                ->route('purchase-requests.show', $header->id)
                ->with('status', 'Purchase Request ini sudah memiliki Purchase Order.');
        }

        $validated = $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'order_date' => ['required', 'date'],
            'expected_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $supplier = DB::table('suppliers')
            ->where('company_id', $company->id)
            ->where('id', $validated['supplier_id'])
            ->whereNull('deleted_at')
            ->first();

        abort_unless($supplier, 422);

        $purchaseOrderId = DB::transaction(function () use ($header, $validated) {
            $items = $this->purchaseRequestItems($header->id);
            $subtotal = $items->sum('estimated_total');
            $now = now();

            $purchaseOrderId = DB::table('purchase_orders')->insertGetId([
                'company_id' => $header->company_id,
                'branch_id' => $header->branch_id,
                'supplier_id' => $validated['supplier_id'],
                'purchase_request_id' => $header->id,
                'created_by' => auth()->id(),
                'document_number' => $this->nextDocumentNumber((int) $header->company_id, (int) $header->branch_id),
                'order_date' => $validated['order_date'],
                'expected_date' => $validated['expected_date'] ?? null,
                'status' => 'draft',
                'currency' => 'IDR',
                'subtotal' => $subtotal,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'total_amount' => $subtotal,
                'notes' => $validated['notes'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($items as $item) {
                DB::table('purchase_order_items')->insert([
                    'purchase_order_id' => $purchaseOrderId,
                    'purchase_request_item_id' => $item->id,
                    'item_id' => $item->item_id,
                    'unit_id' => $item->unit_id,
                    'quantity' => $item->quantity,
                    'received_quantity' => 0,
                    'unit_price' => $item->estimated_unit_price,
                    'discount_amount' => 0,
                    'tax_amount' => 0,
                    'line_total' => $item->estimated_total,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('purchase_requests')->where('id', $header->id)->update([
                'status' => 'converted_to_po',
                'updated_at' => $now,
            ]);

            return $purchaseOrderId;
        });

        return redirect()
            ->route('purchase-orders.show', $purchaseOrderId)
            ->with('status', 'Purchase Order berhasil dibuat dari Purchase Request.');
    }

    public function show(int $purchaseOrder): View
    {
        $header = $this->findPurchaseOrder($purchaseOrder);

        $items = DB::table('purchase_order_items')
            ->join('items', 'items.id', '=', 'purchase_order_items.item_id')
            ->join('units', 'units.id', '=', 'purchase_order_items.unit_id')
            ->where('purchase_order_items.purchase_order_id', $header->id)
            ->select(
                'purchase_order_items.*',
                'items.sku',
                'items.name as item_name',
                'units.code as unit_code',
            )
            ->orderBy('purchase_order_items.id')
            ->get();

        return view('purchase_orders.show', compact('header', 'items'));
    }

    public function submit(int $purchaseOrder): RedirectResponse
    {
        $header = $this->findPurchaseOrder($purchaseOrder);

        if ($header->status !== 'draft') {
            return redirect()
                ->route('purchase-orders.show', $header->id)
                ->with('status', 'Hanya Purchase Order draft yang bisa disubmit.');
        }

        DB::table('purchase_orders')->where('id', $header->id)->update([
            'status' => 'submitted',
            'updated_at' => now(),
        ]);

        return redirect()
            ->route('purchase-orders.show', $header->id)
            ->with('status', 'Purchase Order berhasil disubmit.');
    }

    public function approve(int $purchaseOrder): RedirectResponse
    {
        $header = $this->findPurchaseOrder($purchaseOrder);

        if ($header->status !== 'submitted') {
            return redirect()
                ->route('purchase-orders.show', $header->id)
                ->with('status', 'Hanya Purchase Order submitted yang bisa di-approve.');
        }

        DB::table('purchase_orders')->where('id', $header->id)->update([
            'status' => 'approved',
            'updated_at' => now(),
        ]);

        return redirect()
            ->route('purchase-orders.show', $header->id)
            ->with('status', 'Purchase Order berhasil di-approve.');
    }

    private function findPurchaseOrder(int $purchaseOrder): object
    {
        $company = $this->company();

        $header = DB::table('purchase_orders')
            ->join('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
            ->leftJoin('purchase_requests', 'purchase_requests.id', '=', 'purchase_orders.purchase_request_id')
            ->join('users', 'users.id', '=', 'purchase_orders.created_by')
            ->where('purchase_orders.company_id', $company->id)
            ->where('purchase_orders.id', $purchaseOrder)
            ->whereNull('purchase_orders.deleted_at')
            ->select(
                'purchase_orders.*',
                'suppliers.name as supplier_name',
                'suppliers.contact_person',
                'suppliers.phone as supplier_phone',
                'purchase_requests.document_number as purchase_request_number',
                'users.name as creator_name',
            )
            ->first();

        abort_unless($header, 404);

        return $header;
    }

    private function findApprovedPurchaseRequest(int $id): object
    {
        $company = $this->company();

        $header = DB::table('purchase_requests')
            ->join('departments', 'departments.id', '=', 'purchase_requests.department_id')
            ->join('branches', 'branches.id', '=', 'purchase_requests.branch_id')
            ->where('purchase_requests.company_id', $company->id)
            ->where('purchase_requests.id', $id)
            ->where('purchase_requests.status', 'approved')
            ->whereNull('purchase_requests.deleted_at')
            ->select(
                'purchase_requests.*',
                'departments.name as department_name',
                'branches.name as branch_name',
            )
            ->first();

        abort_unless($header, 404);

        return $header;
    }

    private function purchaseRequestItems(int $purchaseRequestId)
    {
        return DB::table('purchase_request_items')
            ->join('items', 'items.id', '=', 'purchase_request_items.item_id')
            ->join('units', 'units.id', '=', 'purchase_request_items.unit_id')
            ->where('purchase_request_items.purchase_request_id', $purchaseRequestId)
            ->select(
                'purchase_request_items.*',
                'items.sku',
                'items.name as item_name',
                'units.code as unit_code',
            )
            ->orderBy('purchase_request_items.id')
            ->get();
    }

    private function purchaseOrderExistsForPurchaseRequest(int $purchaseRequestId): bool
    {
        return DB::table('purchase_orders')
            ->where('purchase_request_id', $purchaseRequestId)
            ->whereNull('deleted_at')
            ->exists();
    }

    private function suppliers()
    {
        $company = $this->company();

        return DB::table('suppliers')
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();
    }

    private function nextDocumentNumber(int $companyId, int $branchId): string
    {
        $period = now()->format('Ym');
        $sequence = DB::table('document_sequences')
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('document_type', 'purchase_order')
            ->lockForUpdate()
            ->first();

        if (! $sequence) {
            DB::table('document_sequences')->insert([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'document_type' => 'purchase_order',
                'prefix' => 'PO',
                'next_number' => 1,
                'padding' => 5,
                'period_format' => 'Ym',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = DB::table('document_sequences')
                ->where('company_id', $companyId)
                ->where('branch_id', $branchId)
                ->where('document_type', 'purchase_order')
                ->lockForUpdate()
                ->first();
        }

        $number = str_pad((string) $sequence->next_number, (int) $sequence->padding, '0', STR_PAD_LEFT);

        DB::table('document_sequences')->where('id', $sequence->id)->update([
            'next_number' => $sequence->next_number + 1,
            'updated_at' => now(),
        ]);

        return "{$sequence->prefix}-{$period}-{$number}";
    }

    private function company(): object
    {
        return DB::table('companies')->where('is_active', true)->orderBy('id')->firstOrFail();
    }
}
