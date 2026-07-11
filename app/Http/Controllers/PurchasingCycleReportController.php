<?php

namespace App\Http\Controllers;

use App\Support\CompanyContext;
use App\Support\CsvExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchasingCycleReportController extends Controller
{
    public function index(Request $request): View
    {
        return view('reports.purchasing_cycle', $this->data($request));
    }

    public function print(Request $request): View
    {
        return view('reports.purchasing_cycle_print', $this->data($request));
    }

    public function export(Request $request): StreamedResponse
    {
        $data = $this->data($request);

        return CsvExporter::download('purchasing-cycle-'.now()->format('Ymd-His').'.csv', [
            'PR Number',
            'PR Date',
            'Department',
            'Requester',
            'Priority',
            'PR Status',
            'PR Value',
            'PO Number',
            'PO Status',
            'Supplier',
            'PO Date',
            'PO Value',
            'Variance',
            'GR Count',
            'Latest GR',
            'Received Percent',
            'Cycle Status',
        ], $data['rows']->map(fn (object $row) => [
            $row->document_number,
            $row->request_date,
            $row->department_code.' - '.$row->department_name,
            $row->requester_name,
            $row->priority,
            $row->status,
            (float) $row->estimated_total,
            $row->purchase_order_number,
            $row->purchase_order_status,
            $row->supplier_name,
            $row->order_date,
            (float) $row->purchase_order_total,
            $row->variance_amount,
            $row->goods_receipt_count,
            $row->latest_goods_receipt_number,
            round((float) $row->received_percent, 2),
            $row->cycle_status,
        ]));
    }

    private function data(Request $request): array
    {
        $context = app(CompanyContext::class);
        $company = $context->current();
        $branch = $context->branch();

        $filters = [
            'date_from' => $request->input('date_from', now()->startOfMonth()->format('Y-m-d')),
            'date_to' => $request->input('date_to', now()->format('Y-m-d')),
            'department_id' => $request->integer('department_id') ?: null,
            'cycle_status' => $request->input('cycle_status'),
        ];

        $dateFrom = Carbon::parse($filters['date_from'])->startOfDay();
        $dateTo = Carbon::parse($filters['date_to'])->endOfDay();

        $purchaseRequests = DB::table('purchase_requests')
            ->join('departments', 'departments.id', '=', 'purchase_requests.department_id')
            ->join('users', 'users.id', '=', 'purchase_requests.requested_by')
            ->where('purchase_requests.company_id', $company->id)
            ->whereBetween('purchase_requests.request_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->when($filters['department_id'], fn ($query, int $departmentId) => $query->where('purchase_requests.department_id', $departmentId))
            ->whereNull('purchase_requests.deleted_at')
            ->select(
                'purchase_requests.*',
                'departments.code as department_code',
                'departments.name as department_name',
                'users.name as requester_name',
            )
            ->orderByDesc('purchase_requests.request_date')
            ->orderByDesc('purchase_requests.id')
            ->get();

        $purchaseOrders = DB::table('purchase_orders')
            ->join('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
            ->where('purchase_orders.company_id', $company->id)
            ->whereIn('purchase_orders.purchase_request_id', $purchaseRequests->pluck('id')->all())
            ->whereNull('purchase_orders.deleted_at')
            ->select('purchase_orders.*', 'suppliers.name as supplier_name')
            ->get()
            ->keyBy('purchase_request_id');

        $purchaseOrderIds = $purchaseOrders->pluck('id')->all();

        $purchaseOrderTotals = collect();
        $goodsReceiptSummaries = collect();

        if ($purchaseOrderIds !== []) {
            $purchaseOrderTotals = DB::table('purchase_order_items')
                ->whereIn('purchase_order_id', $purchaseOrderIds)
                ->select(
                    'purchase_order_id',
                    DB::raw('SUM(quantity) as ordered_quantity'),
                    DB::raw('SUM(received_quantity) as received_quantity'),
                    DB::raw('SUM(line_total) as ordered_amount'),
                )
                ->groupBy('purchase_order_id')
                ->get()
                ->keyBy('purchase_order_id');

            $goodsReceiptSummaries = DB::table('goods_receipts')
                ->whereIn('purchase_order_id', $purchaseOrderIds)
                ->select(
                    'purchase_order_id',
                    DB::raw('COUNT(*) as goods_receipt_count'),
                    DB::raw('MAX(document_number) as latest_goods_receipt_number'),
                    DB::raw('MAX(received_at) as latest_received_at'),
                )
                ->groupBy('purchase_order_id')
                ->get()
                ->keyBy('purchase_order_id');
        }

        $rows = $purchaseRequests
            ->map(function (object $purchaseRequest) use ($purchaseOrders, $purchaseOrderTotals, $goodsReceiptSummaries) {
                $purchaseOrder = $purchaseOrders->get($purchaseRequest->id);
                $poTotals = $purchaseOrder ? $purchaseOrderTotals->get($purchaseOrder->id) : null;
                $grSummary = $purchaseOrder ? $goodsReceiptSummaries->get($purchaseOrder->id) : null;

                $orderedQuantity = (float) ($poTotals?->ordered_quantity ?? 0);
                $receivedQuantity = (float) ($poTotals?->received_quantity ?? 0);
                $receivedPercent = $orderedQuantity > 0 ? min(100, ($receivedQuantity / $orderedQuantity) * 100) : 0;
                $purchaseOrderAmount = (float) ($purchaseOrder?->total_amount ?? 0);

                $purchaseRequest->purchase_order_id = $purchaseOrder?->id;
                $purchaseRequest->purchase_order_number = $purchaseOrder?->document_number;
                $purchaseRequest->purchase_order_status = $purchaseOrder?->status;
                $purchaseRequest->supplier_name = $purchaseOrder?->supplier_name;
                $purchaseRequest->order_date = $purchaseOrder?->order_date;
                $purchaseRequest->purchase_order_total = $purchaseOrderAmount;
                $purchaseRequest->ordered_quantity = $orderedQuantity;
                $purchaseRequest->received_quantity = $receivedQuantity;
                $purchaseRequest->received_percent = $receivedPercent;
                $purchaseRequest->goods_receipt_count = (int) ($grSummary?->goods_receipt_count ?? 0);
                $purchaseRequest->latest_goods_receipt_number = $grSummary?->latest_goods_receipt_number;
                $purchaseRequest->latest_received_at = $grSummary?->latest_received_at;
                $purchaseRequest->variance_amount = $purchaseOrder ? $purchaseOrderAmount - (float) $purchaseRequest->estimated_total : null;
                $purchaseRequest->cycle_status = $this->cycleStatus($purchaseRequest, $purchaseOrder);

                return $purchaseRequest;
            })
            ->when($filters['cycle_status'], fn ($collection, string $status) => $collection->where('cycle_status', $status))
            ->values();

        $summary = [
            'document_count' => $rows->count(),
            'purchase_request_total' => $rows->sum(fn (object $row) => (float) $row->estimated_total),
            'purchase_order_total' => $rows->sum(fn (object $row) => (float) $row->purchase_order_total),
            'received_count' => $rows->where('cycle_status', 'completed')->count(),
            'risk_count' => $rows->whereIn('cycle_status', ['waiting_pr_approval', 'waiting_po_approval', 'awaiting_receipt', 'partial_received'])->count(),
        ];

        return [
            'company' => $company,
            'branch' => $branch,
            'filters' => $filters,
            'departments' => $this->departments((int) $company->id),
            'rows' => $rows,
            'summary' => $summary,
            'cycleStatuses' => $this->cycleStatuses(),
        ];
    }

    private function cycleStatus(object $purchaseRequest, ?object $purchaseOrder): string
    {
        if ($purchaseRequest->status === 'submitted') {
            return 'waiting_pr_approval';
        }

        if ($purchaseRequest->status === 'approved') {
            return 'ready_for_po';
        }

        if (! $purchaseOrder) {
            return $purchaseRequest->status;
        }

        return match ($purchaseOrder->status) {
            'draft' => 'po_draft',
            'submitted' => 'waiting_po_approval',
            'approved' => 'awaiting_receipt',
            'partial_received' => 'partial_received',
            'received' => 'completed',
            default => $purchaseOrder->status,
        };
    }

    private function cycleStatuses(): array
    {
        return [
            'waiting_pr_approval' => 'Waiting PR Approval',
            'ready_for_po' => 'Ready for PO',
            'po_draft' => 'PO Draft',
            'waiting_po_approval' => 'Waiting PO Approval',
            'awaiting_receipt' => 'Awaiting Receipt',
            'partial_received' => 'Partial Received',
            'completed' => 'Completed',
            'rejected' => 'Rejected',
        ];
    }

    private function departments(int $companyId)
    {
        return DB::table('departments')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();
    }
}
