<?php

namespace App\Http\Controllers;

use App\Support\AuditLogger;
use App\Support\CompanyContext;
use App\Support\DocumentStateMachine;
use App\Support\TransactionPeriodLock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class GoodsReceiptController extends Controller
{
    public function index(): View
    {
        $company = $this->company();

        $goodsReceipts = DB::table('goods_receipts')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'goods_receipts.purchase_order_id')
            ->join('storage_locations', 'storage_locations.id', '=', 'goods_receipts.storage_location_id')
            ->join('users', 'users.id', '=', 'goods_receipts.received_by')
            ->where('goods_receipts.company_id', $company->id)
            ->select(
                'goods_receipts.*',
                'purchase_orders.document_number as purchase_order_number',
                'storage_locations.code as storage_location_code',
                'storage_locations.name as storage_location_name',
                'users.name as receiver_name',
            )
            ->orderByDesc('goods_receipts.received_at')
            ->orderByDesc('goods_receipts.id')
            ->paginate(10);

        return view('goods_receipts.index', compact('goodsReceipts'));
    }

    public function createFromPurchaseOrder(int $purchaseOrder): View|RedirectResponse
    {
        $header = $this->findApprovedPurchaseOrder($purchaseOrder);
        $items = $this->receivablePurchaseOrderItems($header->id);

        if ($items->isEmpty()) {
            return redirect()
                ->route('purchase-orders.show', $header->id)
                ->with('status', 'Semua item pada Purchase Order ini sudah diterima.');
        }

        return view('goods_receipts.create_from_po', [
            'header' => $header,
            'items' => $items,
            'locations' => $this->storageLocations((int) $header->branch_id),
        ]);
    }

    public function storeFromPurchaseOrder(Request $request, int $purchaseOrder): RedirectResponse
    {
        $header = $this->findApprovedPurchaseOrder($purchaseOrder);

        $validated = $request->validate([
            'storage_location_id' => ['required', 'integer', 'exists:storage_locations,id'],
            'received_at' => ['required', 'date'],
            'supplier_delivery_number' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array'],
            'lines.*.purchase_order_item_id' => ['required', 'integer', 'exists:purchase_order_items,id'],
            'lines.*.accepted_quantity' => ['nullable', 'numeric', 'min:0'],
            'lines.*.rejected_quantity' => ['nullable', 'numeric', 'min:0'],
            'lines.*.lot_number' => ['nullable', 'string', 'max:80'],
            'lines.*.expiry_date' => ['nullable', 'date'],
            'lines.*.rejection_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $location = DB::table('storage_locations')
            ->where('branch_id', $header->branch_id)
            ->where('id', $validated['storage_location_id'])
            ->whereNull('deleted_at')
            ->first();

        abort_unless($location, 422);

        $lines = $this->validatedLines($validated['lines'], $header->id);

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages([
                'lines' => 'Minimal satu quantity diterima atau ditolak harus diisi.',
            ]);
        }

        $goodsReceiptId = DB::transaction(function () use ($header, $validated, $lines) {
            $now = now();

            $goodsReceiptId = DB::table('goods_receipts')->insertGetId([
                'company_id' => $header->company_id,
                'branch_id' => $header->branch_id,
                'purchase_order_id' => $header->id,
                'storage_location_id' => $validated['storage_location_id'],
                'received_by' => auth()->id(),
                'document_number' => $this->nextDocumentNumber((int) $header->company_id, (int) $header->branch_id),
                'received_at' => $validated['received_at'],
                'supplier_delivery_number' => $validated['supplier_delivery_number'] ?? null,
                'status' => 'draft',
                'notes' => $validated['notes'] ?? null,
                'posted_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($lines as $line) {
                DB::table('goods_receipt_items')->insert([
                    'goods_receipt_id' => $goodsReceiptId,
                    'purchase_order_item_id' => $line['purchase_order_item']->id,
                    'item_id' => $line['purchase_order_item']->item_id,
                    'unit_id' => $line['purchase_order_item']->unit_id,
                    'quantity' => $line['accepted_quantity'] + $line['rejected_quantity'],
                    'accepted_quantity' => $line['accepted_quantity'],
                    'rejected_quantity' => $line['rejected_quantity'],
                    'unit_cost' => $line['purchase_order_item']->unit_price,
                    'expiry_date' => $line['expiry_date'],
                    'lot_number' => $line['lot_number'],
                    'rejection_reason' => $line['rejection_reason'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            return $goodsReceiptId;
        });

        return redirect()
            ->route('goods-receipts.show', $goodsReceiptId)
            ->with('status', 'Goods Receipt berhasil dibuat sebagai draft.');
    }

    public function show(int $goodsReceipt): View
    {
        $header = $this->findGoodsReceipt($goodsReceipt);
        $items = $this->goodsReceiptItems((int) $header->id);
        $attachments = AttachmentController::listFor('goods_receipt', (int) $header->id);

        return view('goods_receipts.show', compact('header', 'items', 'attachments'));
    }

    public function print(int $goodsReceipt): View
    {
        $header = $this->findGoodsReceipt($goodsReceipt);
        $items = $this->goodsReceiptItems((int) $header->id);
        $company = $this->company();
        $branch = DB::table('branches')->where('id', $header->branch_id)->first();

        return view('goods_receipts.print', compact('header', 'items', 'company', 'branch'));
    }

    private function goodsReceiptItems(int $goodsReceiptId)
    {
        return DB::table('goods_receipt_items')
            ->join('items', 'items.id', '=', 'goods_receipt_items.item_id')
            ->join('units', 'units.id', '=', 'goods_receipt_items.unit_id')
            ->leftJoin('asset_registers', 'asset_registers.goods_receipt_item_id', '=', 'goods_receipt_items.id')
            ->where('goods_receipt_items.goods_receipt_id', $goodsReceiptId)
            ->select(
                'goods_receipt_items.*',
                'items.sku',
                'items.name as item_name',
                'items.item_type',
                'units.code as unit_code',
                'asset_registers.id as registered_asset_id',
                'asset_registers.asset_number as registered_asset_number',
            )
            ->orderBy('goods_receipt_items.id')
            ->get();
    }

    public function post(int $goodsReceipt): RedirectResponse
    {
        $header = $this->findGoodsReceipt($goodsReceipt);

        $posted = DB::transaction(function () use ($header): bool {
            $lockedHeader = DB::table('goods_receipts')->where('id', $header->id)->lockForUpdate()->first();
            if (! $lockedHeader || ! DocumentStateMachine::allows('goods_receipt', $lockedHeader->status, 'posted')) {
                return false;
            }

            TransactionPeriodLock::ensureOpen((int) $lockedHeader->company_id, 'inventory', $lockedHeader->received_at);

            $now = now();
            $items = DB::table('goods_receipt_items')
                ->where('goods_receipt_id', $lockedHeader->id)
                ->get();

            foreach ($items as $item) {
                DB::table('purchase_order_items')
                    ->where('id', $item->purchase_order_item_id)
                    ->increment('received_quantity', (float) $item->accepted_quantity, ['updated_at' => $now]);

                if ((float) $item->accepted_quantity > 0) {
                    $this->actualizeBudgetLine($item, $now);

                    DB::table('stock_movements')->insert([
                        'company_id' => $lockedHeader->company_id,
                        'branch_id' => $lockedHeader->branch_id,
                        'storage_location_id' => $lockedHeader->storage_location_id,
                        'item_id' => $item->item_id,
                        'movement_type' => 'goods_receipt',
                        'movement_at' => $lockedHeader->received_at,
                        'quantity' => $item->accepted_quantity,
                        'unit_cost' => $item->unit_cost,
                        'total_cost' => (float) $item->accepted_quantity * (float) $item->unit_cost,
                        'source_type' => 'goods_receipt',
                        'source_id' => $lockedHeader->id,
                        'source_line_id' => $item->id,
                        'created_by' => auth()->id(),
                        'notes' => $lockedHeader->document_number,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            DB::table('goods_receipts')->where('id', $lockedHeader->id)->update([
                'status' => 'posted',
                'posted_at' => $now,
                'updated_at' => $now,
            ]);

            $this->refreshPurchaseOrderReceiptStatus((int) $lockedHeader->purchase_order_id);

            AuditLogger::log('goods_receipt_posted', 'goods_receipt', (int) $lockedHeader->id, ['status' => $lockedHeader->status], ['status' => 'posted'], (int) $lockedHeader->company_id);

            return true;
        });

        if (! $posted) {
            return redirect()->route('goods-receipts.show', $header->id)->with('status', 'Hanya Goods Receipt draft yang bisa diposting.');
        }

        return redirect()
            ->route('goods-receipts.show', $header->id)
            ->with('status', 'Goods Receipt berhasil diposting dan stok masuk gudang.');
    }

    public function reverse(Request $request, int $goodsReceipt): RedirectResponse
    {
        $header = $this->findGoodsReceipt($goodsReceipt);
        $validated = $request->validate(['reversal_reason' => ['required', 'string', 'max:1000']]);

        $reversed = DB::transaction(function () use ($header, $validated): bool {
            $lockedHeader = DB::table('goods_receipts')->where('id', $header->id)->lockForUpdate()->first();
            if (! $lockedHeader || ! DocumentStateMachine::allows('goods_receipt', $lockedHeader->status, 'reversed')) {
                return false;
            }

            TransactionPeriodLock::ensureOpen((int) $lockedHeader->company_id, 'inventory', $lockedHeader->received_at);
            $now = now();
            $items = DB::table('goods_receipt_items')->where('goods_receipt_id', $lockedHeader->id)->get();

            $registeredAssets = DB::table('asset_registers')
                ->whereIn('goods_receipt_item_id', $items->pluck('id'))
                ->whereNull('deleted_at')
                ->exists();
            if ($registeredAssets) {
                throw ValidationException::withMessages([
                    'reversal_reason' => 'Goods Receipt tidak dapat direversal karena salah satu item sudah terdaftar sebagai aset.',
                ]);
            }

            foreach ($items as $item) {
                $quantity = (float) $item->accepted_quantity;
                $purchaseOrderItem = DB::table('purchase_order_items')->where('id', $item->purchase_order_item_id)->lockForUpdate()->first();
                if ($purchaseOrderItem) {
                    DB::table('purchase_order_items')->where('id', $purchaseOrderItem->id)->update([
                        'received_quantity' => max(0, (float) $purchaseOrderItem->received_quantity - $quantity),
                        'updated_at' => $now,
                    ]);
                }

                if ($quantity <= 0) {
                    continue;
                }

                $this->reverseBudgetLine($item, $now);
                DB::table('stock_movements')->insert([
                    'company_id' => $lockedHeader->company_id,
                    'branch_id' => $lockedHeader->branch_id,
                    'storage_location_id' => $lockedHeader->storage_location_id,
                    'item_id' => $item->item_id,
                    'movement_type' => 'goods_receipt_reversal',
                    'movement_at' => $now,
                    'quantity' => -$quantity,
                    'unit_cost' => $item->unit_cost,
                    'total_cost' => -($quantity * (float) $item->unit_cost),
                    'source_type' => 'goods_receipt',
                    'source_id' => $lockedHeader->id,
                    'source_line_id' => $item->id,
                    'created_by' => auth()->id(),
                    'notes' => 'Reversal '.$lockedHeader->document_number.': '.$validated['reversal_reason'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('goods_receipts')->where('id', $lockedHeader->id)->update([
                'status' => 'reversed',
                'reversed_at' => $now,
                'reversed_by' => auth()->id(),
                'reversal_reason' => $validated['reversal_reason'],
                'updated_at' => $now,
            ]);
            $this->refreshPurchaseOrderReceiptStatus((int) $lockedHeader->purchase_order_id);
            AuditLogger::log('goods_receipt_reversed', 'goods_receipt', (int) $lockedHeader->id, ['status' => 'posted'], ['status' => 'reversed', 'reason' => $validated['reversal_reason']], (int) $lockedHeader->company_id);

            return true;
        });

        return redirect()->route('goods-receipts.show', $header->id)->with('status', $reversed ? 'Goods Receipt berhasil direversal.' : 'Hanya Goods Receipt posted yang bisa direversal.');
    }

    private function actualizeBudgetLine(object $goodsReceiptItem, $now): void
    {
        $purchaseOrderItem = DB::table('purchase_order_items')
            ->where('id', $goodsReceiptItem->purchase_order_item_id)
            ->first();

        if (! $purchaseOrderItem?->purchase_request_item_id) {
            return;
        }

        $purchaseRequestItem = DB::table('purchase_request_items')
            ->where('id', $purchaseOrderItem->purchase_request_item_id)
            ->first();

        if (! $purchaseRequestItem?->budget_line_id) {
            return;
        }

        $actualAmount = (float) $goodsReceiptItem->accepted_quantity * (float) $goodsReceiptItem->unit_cost;

        if ($actualAmount <= 0) {
            return;
        }

        $budgetLine = DB::table('budget_lines')
            ->where('id', $purchaseRequestItem->budget_line_id)
            ->lockForUpdate()
            ->first();

        if (! $budgetLine) {
            return;
        }

        DB::table('budget_lines')
            ->where('id', $budgetLine->id)
            ->update([
                'committed_amount' => max(0, (float) $budgetLine->committed_amount - $actualAmount),
                'actual_amount' => (float) $budgetLine->actual_amount + $actualAmount,
                'updated_at' => $now,
            ]);
    }

    private function reverseBudgetLine(object $goodsReceiptItem, $now): void
    {
        $purchaseOrderItem = DB::table('purchase_order_items')->where('id', $goodsReceiptItem->purchase_order_item_id)->first();
        $purchaseRequestItem = $purchaseOrderItem?->purchase_request_item_id
            ? DB::table('purchase_request_items')->where('id', $purchaseOrderItem->purchase_request_item_id)->first()
            : null;
        if (! $purchaseRequestItem?->budget_line_id) {
            return;
        }

        $amount = (float) $goodsReceiptItem->accepted_quantity * (float) $goodsReceiptItem->unit_cost;
        $budgetLine = DB::table('budget_lines')->where('id', $purchaseRequestItem->budget_line_id)->lockForUpdate()->first();
        if ($budgetLine && $amount > 0) {
            DB::table('budget_lines')->where('id', $budgetLine->id)->update([
                'committed_amount' => (float) $budgetLine->committed_amount + $amount,
                'actual_amount' => max(0, (float) $budgetLine->actual_amount - $amount),
                'updated_at' => $now,
            ]);
        }
    }

    private function validatedLines(array $inputLines, int $purchaseOrderId)
    {
        return collect($inputLines)
            ->map(function (array $line) use ($purchaseOrderId) {
                $purchaseOrderItem = DB::table('purchase_order_items')
                    ->where('purchase_order_id', $purchaseOrderId)
                    ->where('id', $line['purchase_order_item_id'])
                    ->first();

                if (! $purchaseOrderItem) {
                    throw ValidationException::withMessages([
                        'lines' => 'Item PO tidak valid.',
                    ]);
                }

                $accepted = (float) ($line['accepted_quantity'] ?? 0);
                $rejected = (float) ($line['rejected_quantity'] ?? 0);
                $remaining = (float) $purchaseOrderItem->quantity - (float) $purchaseOrderItem->received_quantity;

                if (($accepted + $rejected) <= 0) {
                    return null;
                }

                if (($accepted + $rejected) > $remaining) {
                    throw ValidationException::withMessages([
                        'lines' => 'Quantity penerimaan melebihi sisa PO.',
                    ]);
                }

                return [
                    'purchase_order_item' => $purchaseOrderItem,
                    'accepted_quantity' => $accepted,
                    'rejected_quantity' => $rejected,
                    'lot_number' => $line['lot_number'] ?? null,
                    'expiry_date' => $line['expiry_date'] ?? null,
                    'rejection_reason' => $line['rejection_reason'] ?? null,
                ];
            })
            ->filter()
            ->values();
    }

    private function findGoodsReceipt(int $goodsReceipt): object
    {
        $company = $this->company();

        $header = DB::table('goods_receipts')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'goods_receipts.purchase_order_id')
            ->join('storage_locations', 'storage_locations.id', '=', 'goods_receipts.storage_location_id')
            ->join('users', 'users.id', '=', 'goods_receipts.received_by')
            ->where('goods_receipts.company_id', $company->id)
            ->where('goods_receipts.id', $goodsReceipt)
            ->select(
                'goods_receipts.*',
                'purchase_orders.document_number as purchase_order_number',
                'storage_locations.code as storage_location_code',
                'storage_locations.name as storage_location_name',
                'users.name as receiver_name',
            )
            ->first();

        abort_unless($header, 404);

        return $header;
    }

    private function findApprovedPurchaseOrder(int $purchaseOrder): object
    {
        $company = $this->company();

        $header = DB::table('purchase_orders')
            ->join('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
            ->where('purchase_orders.company_id', $company->id)
            ->where('purchase_orders.id', $purchaseOrder)
            ->where('purchase_orders.status', 'approved')
            ->whereNull('purchase_orders.deleted_at')
            ->select('purchase_orders.*', 'suppliers.name as supplier_name')
            ->first();

        abort_unless($header, 404);

        return $header;
    }

    private function receivablePurchaseOrderItems(int $purchaseOrderId)
    {
        return DB::table('purchase_order_items')
            ->join('items', 'items.id', '=', 'purchase_order_items.item_id')
            ->join('units', 'units.id', '=', 'purchase_order_items.unit_id')
            ->where('purchase_order_items.purchase_order_id', $purchaseOrderId)
            ->whereColumn('purchase_order_items.received_quantity', '<', 'purchase_order_items.quantity')
            ->select(
                'purchase_order_items.*',
                'items.sku',
                'items.name as item_name',
                'units.code as unit_code',
            )
            ->orderBy('purchase_order_items.id')
            ->get();
    }

    private function storageLocations(int $branchId)
    {
        $company = $this->company();

        return DB::table('storage_locations')
            ->where('company_id', $company->id)
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();
    }

    private function refreshPurchaseOrderReceiptStatus(int $purchaseOrderId): void
    {
        $items = DB::table('purchase_order_items')
            ->where('purchase_order_id', $purchaseOrderId)
            ->get();

        $allReceived = $items->every(fn (object $item) => (float) $item->received_quantity >= (float) $item->quantity);
        $anyReceived = $items->contains(fn (object $item) => (float) $item->received_quantity > 0);

        DB::table('purchase_orders')->where('id', $purchaseOrderId)->update([
            'status' => $allReceived ? 'received' : ($anyReceived ? 'partial_received' : 'approved'),
            'updated_at' => now(),
        ]);
    }

    private function nextDocumentNumber(int $companyId, int $branchId): string
    {
        $period = now()->format('Ym');
        $sequence = DB::table('document_sequences')
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('document_type', 'goods_receipt')
            ->lockForUpdate()
            ->first();

        if (! $sequence) {
            DB::table('document_sequences')->insert([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'document_type' => 'goods_receipt',
                'prefix' => 'GR',
                'next_number' => 1,
                'padding' => 5,
                'period_format' => 'Ym',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = DB::table('document_sequences')
                ->where('company_id', $companyId)
                ->where('branch_id', $branchId)
                ->where('document_type', 'goods_receipt')
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
        return app(CompanyContext::class)->current();
    }
}
