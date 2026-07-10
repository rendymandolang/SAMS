<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GoodsReceiptController;
use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseRequestController;
use App\Http\Controllers\StockOnHandController;
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
    Route::get('/master', [MasterDataController::class, 'home'])->name('master.home');
    Route::get('/master/{master}', [MasterDataController::class, 'index'])->name('master.index');
    Route::get('/master/{master}/create', [MasterDataController::class, 'create'])->name('master.create');
    Route::post('/master/{master}', [MasterDataController::class, 'store'])->name('master.store');
    Route::get('/master/{master}/{id}/edit', [MasterDataController::class, 'edit'])->name('master.edit');
    Route::put('/master/{master}/{id}', [MasterDataController::class, 'update'])->name('master.update');
    Route::delete('/master/{master}/{id}', [MasterDataController::class, 'destroy'])->name('master.destroy');
    Route::get('/purchase-requests', [PurchaseRequestController::class, 'index'])->name('purchase-requests.index');
    Route::get('/purchase-requests/create', [PurchaseRequestController::class, 'create'])->name('purchase-requests.create');
    Route::post('/purchase-requests', [PurchaseRequestController::class, 'store'])->name('purchase-requests.store');
    Route::get('/purchase-requests/{purchaseRequest}/edit', [PurchaseRequestController::class, 'edit'])->name('purchase-requests.edit');
    Route::put('/purchase-requests/{purchaseRequest}', [PurchaseRequestController::class, 'update'])->name('purchase-requests.update');
    Route::get('/purchase-requests/{purchaseRequest}', [PurchaseRequestController::class, 'show'])->name('purchase-requests.show');
    Route::post('/purchase-requests/{purchaseRequest}/submit', [PurchaseRequestController::class, 'submit'])->name('purchase-requests.submit');
    Route::post('/purchase-requests/{purchaseRequest}/approve', [PurchaseRequestController::class, 'approve'])->name('purchase-requests.approve');
    Route::post('/purchase-requests/{purchaseRequest}/reject', [PurchaseRequestController::class, 'reject'])->name('purchase-requests.reject');
    Route::delete('/purchase-requests/{purchaseRequest}', [PurchaseRequestController::class, 'destroy'])->name('purchase-requests.destroy');
    Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
    Route::get('/purchase-orders/create/from-pr/{purchaseRequest}', [PurchaseOrderController::class, 'createFromPurchaseRequest'])->name('purchase-orders.create-from-pr');
    Route::post('/purchase-orders/from-pr/{purchaseRequest}', [PurchaseOrderController::class, 'storeFromPurchaseRequest'])->name('purchase-orders.store-from-pr');
    Route::get('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('purchase-orders.show');
    Route::post('/purchase-orders/{purchaseOrder}/submit', [PurchaseOrderController::class, 'submit'])->name('purchase-orders.submit');
    Route::post('/purchase-orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])->name('purchase-orders.approve');
    Route::get('/goods-receipts', [GoodsReceiptController::class, 'index'])->name('goods-receipts.index');
    Route::get('/goods-receipts/create/from-po/{purchaseOrder}', [GoodsReceiptController::class, 'createFromPurchaseOrder'])->name('goods-receipts.create-from-po');
    Route::post('/goods-receipts/from-po/{purchaseOrder}', [GoodsReceiptController::class, 'storeFromPurchaseOrder'])->name('goods-receipts.store-from-po');
    Route::get('/goods-receipts/{goodsReceipt}', [GoodsReceiptController::class, 'show'])->name('goods-receipts.show');
    Route::post('/goods-receipts/{goodsReceipt}/post', [GoodsReceiptController::class, 'post'])->name('goods-receipts.post');
    Route::get('/inventory/stock-on-hand', StockOnHandController::class)->name('inventory.stock-on-hand');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
