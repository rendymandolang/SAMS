<?php

use App\Http\Controllers\ApprovalCenterController;
use App\Http\Controllers\AssetRegisterController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\BudgetControlController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GoodsReceiptController;
use App\Http\Controllers\InventoryMovementReportController;
use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseRequestController;
use App\Http\Controllers\PurchasingCycleReportController;
use App\Http\Controllers\StockOnHandController;
use App\Http\Controllers\StockOpnameController;
use App\Http\Controllers\SupplierPerformanceReportController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::middleware('role:super_admin')->group(function () {
        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserManagementController::class, 'create'])->name('users.create');
        Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserManagementController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
        Route::get('/audit-logs', AuditLogController::class)->name('audit-logs.index');
    });
    Route::get('/approvals', ApprovalCenterController::class)
        ->middleware('role:super_admin,finance')
        ->name('approvals.index');
    Route::get('/master', [MasterDataController::class, 'home'])->name('master.home');
    Route::get('/master/{master}', [MasterDataController::class, 'index'])->name('master.index');
    Route::middleware('role:super_admin,purchasing,warehouse')->group(function () {
        Route::get('/master/{master}/create', [MasterDataController::class, 'create'])->name('master.create');
        Route::post('/master/{master}', [MasterDataController::class, 'store'])->name('master.store');
        Route::get('/master/{master}/{id}/edit', [MasterDataController::class, 'edit'])->name('master.edit');
        Route::put('/master/{master}/{id}', [MasterDataController::class, 'update'])->name('master.update');
        Route::delete('/master/{master}/{id}', [MasterDataController::class, 'destroy'])->name('master.destroy');
    });
    Route::get('/purchase-requests', [PurchaseRequestController::class, 'index'])->name('purchase-requests.index');
    Route::middleware('role:super_admin,purchasing,warehouse,staff')->group(function () {
        Route::get('/purchase-requests/create', [PurchaseRequestController::class, 'create'])->name('purchase-requests.create');
        Route::post('/purchase-requests', [PurchaseRequestController::class, 'store'])->name('purchase-requests.store');
        Route::get('/purchase-requests/{purchaseRequest}/edit', [PurchaseRequestController::class, 'edit'])->name('purchase-requests.edit');
        Route::put('/purchase-requests/{purchaseRequest}', [PurchaseRequestController::class, 'update'])->name('purchase-requests.update');
        Route::post('/purchase-requests/{purchaseRequest}/submit', [PurchaseRequestController::class, 'submit'])->name('purchase-requests.submit');
        Route::delete('/purchase-requests/{purchaseRequest}', [PurchaseRequestController::class, 'destroy'])->name('purchase-requests.destroy');
    });
    Route::get('/purchase-requests/{purchaseRequest}/print', [PurchaseRequestController::class, 'print'])->name('purchase-requests.print');
    Route::get('/purchase-requests/{purchaseRequest}', [PurchaseRequestController::class, 'show'])->name('purchase-requests.show');
    Route::middleware('role:super_admin,finance')->group(function () {
        Route::post('/purchase-requests/{purchaseRequest}/approve', [PurchaseRequestController::class, 'approve'])->name('purchase-requests.approve');
        Route::post('/purchase-requests/{purchaseRequest}/reject', [PurchaseRequestController::class, 'reject'])->name('purchase-requests.reject');
    });
    Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
    Route::middleware('role:super_admin,purchasing')->group(function () {
        Route::get('/purchase-orders/create/from-pr/{purchaseRequest}', [PurchaseOrderController::class, 'createFromPurchaseRequest'])->name('purchase-orders.create-from-pr');
        Route::post('/purchase-orders/from-pr/{purchaseRequest}', [PurchaseOrderController::class, 'storeFromPurchaseRequest'])->name('purchase-orders.store-from-pr');
        Route::post('/purchase-orders/{purchaseOrder}/submit', [PurchaseOrderController::class, 'submit'])->name('purchase-orders.submit');
    });
    Route::get('/purchase-orders/{purchaseOrder}/print', [PurchaseOrderController::class, 'print'])->name('purchase-orders.print');
    Route::get('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('purchase-orders.show');
    Route::post('/purchase-orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])->middleware('role:super_admin,finance')->name('purchase-orders.approve');
    Route::get('/goods-receipts', [GoodsReceiptController::class, 'index'])->name('goods-receipts.index');
    Route::middleware('role:super_admin,warehouse')->group(function () {
        Route::get('/goods-receipts/create/from-po/{purchaseOrder}', [GoodsReceiptController::class, 'createFromPurchaseOrder'])->name('goods-receipts.create-from-po');
        Route::post('/goods-receipts/from-po/{purchaseOrder}', [GoodsReceiptController::class, 'storeFromPurchaseOrder'])->name('goods-receipts.store-from-po');
        Route::post('/goods-receipts/{goodsReceipt}/post', [GoodsReceiptController::class, 'post'])->name('goods-receipts.post');
    });
    Route::get('/goods-receipts/{goodsReceipt}/print', [GoodsReceiptController::class, 'print'])->name('goods-receipts.print');
    Route::get('/goods-receipts/{goodsReceipt}', [GoodsReceiptController::class, 'show'])->name('goods-receipts.show');
    Route::get('/assets', [AssetRegisterController::class, 'index'])->name('assets.index');
    Route::middleware('role:super_admin,purchasing,warehouse')->group(function () {
        Route::get('/assets/create', [AssetRegisterController::class, 'create'])->name('assets.create');
        Route::post('/assets', [AssetRegisterController::class, 'store'])->name('assets.store');
    });
    Route::get('/assets/{asset}/print', [AssetRegisterController::class, 'print'])->name('assets.print');
    Route::get('/assets/{asset}', [AssetRegisterController::class, 'show'])->name('assets.show');
    Route::get('/inventory/stock-on-hand', StockOnHandController::class)->name('inventory.stock-on-hand');
    Route::get('/stock-opnames', [StockOpnameController::class, 'index'])->name('stock-opnames.index');
    Route::middleware('role:super_admin,warehouse')->group(function () {
        Route::get('/stock-opnames/create', [StockOpnameController::class, 'create'])->name('stock-opnames.create');
        Route::post('/stock-opnames', [StockOpnameController::class, 'store'])->name('stock-opnames.store');
        Route::post('/stock-opnames/{stockOpname}/post', [StockOpnameController::class, 'post'])->name('stock-opnames.post');
    });
    Route::get('/stock-opnames/{stockOpname}/print', [StockOpnameController::class, 'print'])->name('stock-opnames.print');
    Route::get('/stock-opnames/{stockOpname}', [StockOpnameController::class, 'show'])->name('stock-opnames.show');
    Route::get('/reports/inventory/movements', InventoryMovementReportController::class)->name('reports.inventory.movements');
    Route::middleware('role:super_admin,finance,purchasing')->group(function () {
        Route::get('/reports/purchasing/cycle', [PurchasingCycleReportController::class, 'index'])->name('reports.purchasing.cycle');
        Route::get('/reports/purchasing/cycle/print', [PurchasingCycleReportController::class, 'print'])->name('reports.purchasing.cycle.print');
        Route::get('/reports/purchasing/suppliers', [SupplierPerformanceReportController::class, 'index'])->name('reports.purchasing.suppliers');
        Route::get('/reports/purchasing/suppliers/print', [SupplierPerformanceReportController::class, 'print'])->name('reports.purchasing.suppliers.print');
    });
    Route::middleware('role:super_admin,finance,purchasing')->group(function () {
        Route::get('/budget-control', [BudgetControlController::class, 'index'])->name('budget-control.index');
        Route::get('/budget-control/print', [BudgetControlController::class, 'print'])->name('budget-control.print');
    });
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
