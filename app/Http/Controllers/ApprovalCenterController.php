<?php

namespace App\Http\Controllers;

use App\Support\CompanyContext;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ApprovalCenterController extends Controller
{
    public function __invoke(): View
    {
        $company = app(CompanyContext::class)->current();
        $user = auth()->user();
        $canApprovePurchaseRequests = $user->hasPermission('procurement.pr.approve');
        $canApprovePurchaseOrders = $user->hasPermission('procurement.po.approve');

        abort_unless($canApprovePurchaseRequests || $canApprovePurchaseOrders, 403);

        $purchaseRequests = $canApprovePurchaseRequests ? DB::table('purchase_requests')
            ->join('departments', 'departments.id', '=', 'purchase_requests.department_id')
            ->join('users', 'users.id', '=', 'purchase_requests.requested_by')
            ->where('purchase_requests.company_id', $company->id)
            ->where('purchase_requests.status', 'submitted')
            ->whereNull('purchase_requests.deleted_at')
            ->select(
                'purchase_requests.*',
                'departments.name as department_name',
                'users.name as requester_name',
            )
            ->orderByRaw("CASE purchase_requests.priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 ELSE 4 END")
            ->orderBy('purchase_requests.request_date')
            ->get() : collect();

        $purchaseOrders = $canApprovePurchaseOrders ? DB::table('purchase_orders')
            ->join('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
            ->join('users', 'users.id', '=', 'purchase_orders.created_by')
            ->leftJoin('purchase_requests', 'purchase_requests.id', '=', 'purchase_orders.purchase_request_id')
            ->where('purchase_orders.company_id', $company->id)
            ->where('purchase_orders.status', 'submitted')
            ->whereNull('purchase_orders.deleted_at')
            ->select(
                'purchase_orders.*',
                'suppliers.name as supplier_name',
                'users.name as creator_name',
                'purchase_requests.document_number as purchase_request_number',
            )
            ->orderBy('purchase_orders.order_date')
            ->get() : collect();

        $summary = [
            'pending_count' => $purchaseRequests->count() + $purchaseOrders->count(),
            'purchase_request_count' => $purchaseRequests->count(),
            'purchase_order_count' => $purchaseOrders->count(),
            'purchase_request_total' => $purchaseRequests->sum(fn (object $row) => (float) $row->estimated_total),
            'purchase_order_total' => $purchaseOrders->sum(fn (object $row) => (float) $row->total_amount),
            'urgent_count' => $purchaseRequests->whereIn('priority', ['urgent', 'high'])->count(),
        ];

        return view('approvals.index', compact(
            'company',
            'purchaseRequests',
            'purchaseOrders',
            'summary',
            'canApprovePurchaseRequests',
            'canApprovePurchaseOrders',
        ));
    }
}
