<?php

use App\Http\Controllers\AccessControlController;
use App\Http\Controllers\AccountingAdvancedController;
use App\Http\Controllers\AccountingAutomationController;
use App\Http\Controllers\AccountingCloseController;
use App\Http\Controllers\AccountingConfigurationController;
use App\Http\Controllers\AccountingController;
use App\Http\Controllers\AccountingReportController;
use App\Http\Controllers\AccountsPayableController;
use App\Http\Controllers\AccountsReceivableController;
use App\Http\Controllers\AiInsightController;
use App\Http\Controllers\ApprovalCenterController;
use App\Http\Controllers\AssetMaintenanceController;
use App\Http\Controllers\AssetMaintenanceReportController;
use App\Http\Controllers\AssetRegisterController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\BankReconciliationController;
use App\Http\Controllers\BudgetControlController;
use App\Http\Controllers\CompanyBackupController;
use App\Http\Controllers\CompanyContextController;
use App\Http\Controllers\CompanySettingsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataConnectionController;
use App\Http\Controllers\EnterpriseSettingsController;
use App\Http\Controllers\GoodsReceiptController;
use App\Http\Controllers\HrisController;
use App\Http\Controllers\InventoryMovementReportController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\PublicInfoController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseRequestController;
use App\Http\Controllers\PurchasingCycleReportController;
use App\Http\Controllers\ReportCenterController;
use App\Http\Controllers\StockOnHandController;
use App\Http\Controllers\StockOpnameController;
use App\Http\Controllers\SupplierCatalogController;
use App\Http\Controllers\SupplierPerformanceReportController;
use App\Http\Controllers\TransactionPeriodLockController;
use App\Http\Controllers\UserManagementController;
use App\Http\Middleware\FilterBlankJournalLines;
use Illuminate\Support\Facades\Route;

foreach (['role', 'user', 'attachment', 'backup', 'invoice', 'id', 'purchaseRequest', 'purchaseOrder', 'goodsReceipt', 'goodsReceiptItem', 'asset', 'maintenance', 'stockOpname', 'journal'] as $numericParameter) {
    Route::pattern($numericParameter, '[0-9]+');
}

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::get('/info/{page}', PublicInfoController::class)
    ->whereIn('page', ['status', 'security', 'terms', 'privacy', 'help', 'access'])
    ->name('public.info');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:supersoft-login');
});

Route::get('/locale/{locale}', LocaleController::class)->name('locale.update');

Route::middleware('auth')->group(function () {
    Route::post('/context/company', [CompanyContextController::class, 'update'])->name('context.company.update');
    Route::get('/dashboard', DashboardController::class)->middleware(['module:core', 'permission:core.dashboard.view'])->name('dashboard');

    Route::middleware(['module:core', 'permission:core.settings.manage'])->group(function () {
        Route::get('/settings/company', [CompanySettingsController::class, 'edit'])->name('settings.company.edit');
        Route::put('/settings/company', [CompanySettingsController::class, 'update'])->name('settings.company.update');
        Route::get('/settings/period-locks', [TransactionPeriodLockController::class, 'index'])->name('settings.period-locks.index');
        Route::post('/settings/period-locks', [TransactionPeriodLockController::class, 'store'])->name('settings.period-locks.store');
        Route::delete('/settings/period-locks/{periodLock}', [TransactionPeriodLockController::class, 'destroy'])->name('settings.period-locks.destroy');
        Route::get('/settings/data-connections', [DataConnectionController::class, 'index'])->name('data-connections.index');
        Route::post('/settings/data-connections/{connection}/test', [DataConnectionController::class, 'test'])->name('data-connections.test');
        Route::put('/settings/data-connections/{connection}/toggle', [DataConnectionController::class, 'toggle'])->name('data-connections.toggle');
        Route::get('/settings/enterprise', [EnterpriseSettingsController::class, 'index'])->name('settings.enterprise');
        Route::put('/settings/enterprise/storage', [EnterpriseSettingsController::class, 'updateStorage'])->name('settings.enterprise.storage.update');
        Route::post('/settings/enterprise/storage/test', [EnterpriseSettingsController::class, 'testStorage'])->name('settings.enterprise.storage.test');
        Route::post('/settings/enterprise/backups', [CompanyBackupController::class, 'store'])->name('settings.enterprise.backups.store');
        Route::post('/settings/enterprise/backups/{backup}/verify', [CompanyBackupController::class, 'verify'])->name('settings.enterprise.backups.verify');
    });
    Route::middleware(['module:core', 'permission:core.access.manage'])->group(function () {
        Route::get('/settings/access-control', [AccessControlController::class, 'index'])->name('access-control.index');
        Route::put('/settings/access-control/modules', [AccessControlController::class, 'updateModules'])->name('access-control.modules.update');
        Route::put('/settings/access-control/roles/{role}/permissions', [AccessControlController::class, 'updateRolePermissions'])->name('access-control.roles.permissions.update');
    });
    Route::middleware(['module:core', 'permission:core.users.manage'])->group(function () {
        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserManagementController::class, 'create'])->name('users.create');
        Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserManagementController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
    });
    Route::middleware(['module:core', 'permission:core.audit.view'])->group(function () {
        Route::get('/audit-logs', AuditLogController::class)->name('audit-logs.index');
    });
    Route::get('/approvals', ApprovalCenterController::class)
        ->middleware(['module:core', 'module:procurement', 'permission:core.approvals.view'])
        ->name('approvals.index');
    Route::get('/ai-insights', [AiInsightController::class, 'index'])->middleware(['module:intelligence', 'permission:intelligence.view'])->name('ai-insights.index');
    Route::middleware(['module:accounting', 'permission:accounting.view'])->group(function () {
        Route::get('/accounting', [AccountingController::class, 'index'])->name('accounting.index');
        Route::get('/accounting/reports/{report}', [AccountingReportController::class, 'show'])->name('accounting.reports.show');
        Route::get('/accounting/journals/{journal}', [AccountingController::class, 'show'])->name('accounting.show');
        Route::get('/accounting/journals/{journal}/print', [AccountingController::class, 'print'])->name('accounting.print');
        Route::get('/accounting/payables', [AccountsPayableController::class, 'index'])->name('accounting.payables.index');
        Route::get('/accounting/payables/{invoice}', [AccountsPayableController::class, 'show'])->name('accounting.payables.show');
        Route::get('/accounting/payables/{invoice}/print', [AccountsPayableController::class, 'print'])->name('accounting.payables.print');
        Route::get('/accounting/receivables', [AccountsReceivableController::class, 'index'])->name('accounting.receivables.index');
        Route::get('/accounting/receivables/{invoice}', [AccountsReceivableController::class, 'show'])->name('accounting.receivables.show');
        Route::get('/accounting/receivables/{invoice}/print', [AccountsReceivableController::class, 'print'])->name('accounting.receivables.print');
        Route::get('/accounting/bank-reconciliation', [BankReconciliationController::class, 'index'])->name('accounting.bank-reconciliation.index');
        Route::get('/accounting/bank-reconciliation/template/csv', [BankReconciliationController::class, 'template'])->name('accounting.bank-reconciliation.template');
        Route::get('/accounting/bank-reconciliation/{reconciliation}', [BankReconciliationController::class, 'show'])->name('accounting.bank-reconciliation.show');
        Route::get('/accounting/bank-reconciliation/{reconciliation}/print', [BankReconciliationController::class, 'print'])->name('accounting.bank-reconciliation.print');
        Route::get('/accounting/configuration', [AccountingConfigurationController::class, 'index'])->name('accounting.configuration.index');
        Route::get('/accounting/advanced-controls', [AccountingAdvancedController::class, 'index'])->name('accounting.advanced.index');
        Route::get('/accounting/automation', [AccountingAutomationController::class, 'index'])->name('accounting.automation.index');
    });
    Route::middleware(['module:accounting', 'permission:accounting.manage'])->group(function () {
        Route::post('/accounting/accounts', [AccountingController::class, 'storeAccount'])->name('accounting.accounts.store');
        Route::get('/accounting/journals/create', [AccountingController::class, 'create'])->name('accounting.create');
        Route::post('/accounting/journals', [AccountingController::class, 'store'])->middleware(FilterBlankJournalLines::class)->name('accounting.store');
        Route::get('/accounting/payables/create', [AccountsPayableController::class, 'create'])->name('accounting.payables.create');
        Route::post('/accounting/payables', [AccountsPayableController::class, 'store'])->name('accounting.payables.store');
        Route::post('/accounting/customers', [AccountsReceivableController::class, 'storeCustomer'])->name('accounting.customers.store');
        Route::get('/accounting/receivables/create', [AccountsReceivableController::class, 'create'])->name('accounting.receivables.create');
        Route::post('/accounting/receivables', [AccountsReceivableController::class, 'store'])->name('accounting.receivables.store');
        Route::post('/accounting/bank-accounts', [BankReconciliationController::class, 'storeBankAccount'])->name('accounting.bank-accounts.store');
        Route::post('/accounting/bank-statements/import', [BankReconciliationController::class, 'import'])->name('accounting.bank-statements.import');
        Route::post('/accounting/configuration/settings', [AccountingConfigurationController::class, 'storeSettings'])->name('accounting.configuration.settings');
        Route::post('/accounting/configuration/exchange-rates', [AccountingConfigurationController::class, 'storeExchangeRate'])->name('accounting.configuration.exchange-rates');
        Route::post('/accounting/configuration/fx-accounts', [AccountingConfigurationController::class, 'storeFxAccounts'])->name('accounting.configuration.fx-accounts');
        Route::post('/accounting/configuration/fx-revaluation', [AccountingConfigurationController::class, 'revalue'])->name('accounting.configuration.fx-revaluation');
        Route::post('/accounting/configuration/tax-codes', [AccountingConfigurationController::class, 'storeTaxCode'])->name('accounting.configuration.tax-codes');
        Route::post('/accounting/configuration/posting-rules', [AccountingConfigurationController::class, 'storePostingRule'])->name('accounting.configuration.posting-rules');
        Route::post('/accounting/configuration/tax-codes/{taxCode}/toggle', [AccountingConfigurationController::class, 'toggleTaxCode'])->name('accounting.configuration.tax-codes.toggle');
        Route::post('/accounting/credit-notes', [AccountingAdvancedController::class, 'storeCreditNote'])->name('accounting.credit-notes.store');
        Route::post('/accounting/automation/accounts/{account}', [AccountingAutomationController::class, 'updateAccount'])->name('accounting.automation.accounts.update');
        Route::post('/accounting/automation/templates', [AccountingAutomationController::class, 'store'])->name('accounting.automation.templates.store');
        Route::post('/accounting/automation/templates/{template}/generate', [AccountingAutomationController::class, 'generate'])->name('accounting.automation.templates.generate');
    });
    Route::post('/accounting/journals/{journal}/post', [AccountingController::class, 'post'])->middleware(['module:accounting', 'permission:accounting.post'])->name('accounting.post');
    Route::post('/accounting/journals/{journal}/reverse', [AccountingController::class, 'reverse'])->middleware(['module:accounting', 'permission:accounting.post'])->name('accounting.reverse');
    Route::post('/accounting/payables/{invoice}/post', [AccountsPayableController::class, 'post'])->middleware(['module:accounting', 'permission:accounting.post'])->name('accounting.payables.post');
    Route::post('/accounting/payments', [AccountsPayableController::class, 'pay'])->middleware(['module:accounting', 'permission:accounting.post'])->name('accounting.payments.store');
    Route::post('/accounting/receivables/{invoice}/post', [AccountsReceivableController::class, 'post'])->middleware(['module:accounting', 'permission:accounting.post'])->name('accounting.receivables.post');
    Route::post('/accounting/receipts', [AccountsReceivableController::class, 'receive'])->middleware(['module:accounting', 'permission:accounting.post'])->name('accounting.receipts.store');
    Route::post('/accounting/bank-lines/{line}/match', [BankReconciliationController::class, 'match'])->middleware(['module:accounting', 'permission:accounting.post'])->name('accounting.bank-lines.match');
    Route::post('/accounting/bank-lines/{line}/unmatch', [BankReconciliationController::class, 'unmatch'])->middleware(['module:accounting', 'permission:accounting.post'])->name('accounting.bank-lines.unmatch');
    Route::post('/accounting/bank-lines/{line}/exclude', [BankReconciliationController::class, 'exclude'])->middleware(['module:accounting', 'permission:accounting.post'])->name('accounting.bank-lines.exclude');
    Route::post('/accounting/bank-reconciliation/{reconciliation}/complete', [BankReconciliationController::class, 'complete'])->middleware(['module:accounting', 'permission:accounting.post'])->name('accounting.bank-reconciliation.complete');
    Route::post('/accounting/credit-notes/{creditNote}/post', [AccountingAdvancedController::class, 'postCreditNote'])->middleware(['module:accounting', 'permission:accounting.post'])->name('accounting.credit-notes.post');
    Route::post('/accounting/payments/{payment}/reverse', [AccountingAdvancedController::class, 'reversePayment'])->middleware(['module:accounting', 'permission:accounting.post'])->name('accounting.payments.reverse');
    Route::post('/accounting/receipts/{receipt}/reverse', [AccountingAdvancedController::class, 'reverseReceipt'])->middleware(['module:accounting', 'permission:accounting.post'])->name('accounting.receipts.reverse');
    Route::post('/accounting/fiscal-close', [AccountingAdvancedController::class, 'closeFiscalYear'])->middleware(['module:accounting', 'permission:accounting.post'])->name('accounting.fiscal-close.store');
    Route::post('/accounting/fiscal-close/{close}/reopen', [AccountingAdvancedController::class, 'reopenFiscalYear'])->middleware(['module:accounting', 'permission:accounting.post'])->name('accounting.fiscal-close.reopen');
    Route::middleware(['module:accounting', 'permission:accounting.post'])->group(function () {
        Route::get('/accounting/close-month', [AccountingCloseController::class, 'index'])->name('accounting.close-month');
        Route::post('/accounting/close-month', [AccountingCloseController::class, 'store'])->name('accounting.close-month.store');
        Route::delete('/accounting/close-month/{lock}', [AccountingCloseController::class, 'destroy'])->name('accounting.close-month.destroy');
    });
    Route::middleware(['module:hris', 'permission:hris.view'])->group(function () {
        Route::get('/hris', [HrisController::class, 'index'])->name('hris.index');
        Route::get('/hris/employees/{employee}', [HrisController::class, 'show'])->name('hris.employees.show');
    });
    Route::middleware(['module:hris', 'permission:hris.manage'])->group(function () {
        Route::post('/hris/positions', [HrisController::class, 'storePosition'])->name('hris.positions.store');
        Route::post('/hris/employees', [HrisController::class, 'storeEmployee'])->name('hris.employees.store');
        Route::patch('/hris/employees/{employee}', [HrisController::class, 'updateEmployee'])->name('hris.employees.update');
        Route::post('/hris/leave-types', [HrisController::class, 'storeLeaveType'])->name('hris.leave-types.store');
        Route::post('/hris/employees/{employee}/documents', [HrisController::class, 'uploadDocument'])->name('hris.documents.store');
    });
    Route::post('/hris/leave-requests', [HrisController::class, 'requestLeave'])->middleware(['module:hris', 'permission:hris.leave.self'])->name('hris.leave-requests.store');
    Route::post('/hris/leave-requests/{leaveRequest}/decision', [HrisController::class, 'decideLeave'])->middleware(['module:hris', 'permission:hris.leave.approve'])->name('hris.leave-requests.decision');
    Route::post('/hris/leave-requests/{leaveRequest}/cancel', [HrisController::class, 'cancelLeave'])->middleware(['module:hris', 'permission:hris.leave.self'])->name('hris.leave-requests.cancel');
    Route::get('/hris/documents/{document}/download', [HrisController::class, 'downloadDocument'])->middleware(['module:hris', 'permission:hris.sensitive.view'])->name('hris.documents.download');
    Route::post('/ai-insights/generate', [AiInsightController::class, 'generate'])->middleware(['module:intelligence', 'permission:intelligence.generate'])->name('ai-insights.generate');
    Route::post('/ai-insights/query', [AiInsightController::class, 'query'])->middleware(['module:intelligence', 'permission:intelligence.generate'])->name('ai-insights.query');
    Route::post('/ai-insights/narrative', [AiInsightController::class, 'narrative'])->middleware(['module:intelligence', 'permission:intelligence.generate'])->name('ai-insights.narrative');
    Route::put('/ai-insights/settings', [AiInsightController::class, 'updateSettings'])->middleware(['module:intelligence', 'permission:core.settings.manage'])->name('ai-insights.settings');
    Route::middleware(['module:core', 'permission:core.attachments.manage'])->group(function () {
        Route::post('/attachments/{type}/{id}', [AttachmentController::class, 'store'])->name('attachments.store');
        Route::get('/attachments/{attachment}/download', [AttachmentController::class, 'download'])->name('attachments.download');
        Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');
    });
    Route::middleware(['module:core', 'permission:core.master.view'])->group(function () {
        Route::get('/master', [MasterDataController::class, 'home'])->name('master.home');
        Route::get('/master/{master}', [MasterDataController::class, 'index'])->name('master.index');
    });
    Route::middleware(['module:core', 'permission:core.master.manage'])->group(function () {
        Route::get('/master/{master}/create', [MasterDataController::class, 'create'])->name('master.create');
        Route::post('/master/{master}', [MasterDataController::class, 'store'])->name('master.store');
        Route::get('/master/{master}/{id}/edit', [MasterDataController::class, 'edit'])->name('master.edit');
        Route::put('/master/{master}/{id}', [MasterDataController::class, 'update'])->name('master.update');
        Route::delete('/master/{master}/{id}', [MasterDataController::class, 'destroy'])->name('master.destroy');
    });

    Route::middleware(['module:procurement', 'permission:procurement.pr.view'])->group(function () {
        Route::get('/purchase-requests', [PurchaseRequestController::class, 'index'])->name('purchase-requests.index');
        Route::get('/purchase-requests/{purchaseRequest}/print', [PurchaseRequestController::class, 'print'])->name('purchase-requests.print');
        Route::get('/purchase-requests/{purchaseRequest}', [PurchaseRequestController::class, 'show'])->name('purchase-requests.show');
    });
    Route::middleware(['module:procurement', 'permission:procurement.pr.manage'])->group(function () {
        Route::get('/purchase-requests/create', [PurchaseRequestController::class, 'create'])->name('purchase-requests.create');
        Route::post('/purchase-requests', [PurchaseRequestController::class, 'store'])->name('purchase-requests.store');
        Route::get('/purchase-requests/{purchaseRequest}/edit', [PurchaseRequestController::class, 'edit'])->name('purchase-requests.edit');
        Route::put('/purchase-requests/{purchaseRequest}', [PurchaseRequestController::class, 'update'])->name('purchase-requests.update');
        Route::post('/purchase-requests/{purchaseRequest}/submit', [PurchaseRequestController::class, 'submit'])->name('purchase-requests.submit');
        Route::delete('/purchase-requests/{purchaseRequest}', [PurchaseRequestController::class, 'destroy'])->name('purchase-requests.destroy');
    });
    Route::middleware(['module:procurement', 'permission:procurement.pr.approve'])->group(function () {
        Route::post('/purchase-requests/{purchaseRequest}/approve', [PurchaseRequestController::class, 'approve'])->name('purchase-requests.approve');
        Route::post('/purchase-requests/{purchaseRequest}/reject', [PurchaseRequestController::class, 'reject'])->name('purchase-requests.reject');
    });
    Route::middleware(['module:procurement', 'permission:procurement.po.view'])->group(function () {
        Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
        Route::get('/purchase-orders/{purchaseOrder}/print', [PurchaseOrderController::class, 'print'])->name('purchase-orders.print');
        Route::get('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('purchase-orders.show');
        Route::get('/supplier-catalogs', [SupplierCatalogController::class, 'index'])->name('supplier-catalogs.index');
        Route::get('/supplier-catalogs/{catalog}', [SupplierCatalogController::class, 'show'])->name('supplier-catalogs.show');
        Route::post('/supplier-catalogs/compare', [SupplierCatalogController::class, 'compare'])->middleware(['module:intelligence', 'permission:intelligence.view'])->name('supplier-catalogs.compare');
        Route::post('/supplier-catalogs/comparisons/{comparison}/decide', [SupplierCatalogController::class, 'decide'])->middleware(['module:intelligence', 'permission:intelligence.view', 'permission:procurement.po.manage'])->name('supplier-catalogs.comparisons.decide');
    });
    Route::middleware(['module:procurement', 'permission:procurement.po.manage'])->group(function () {
        Route::get('/purchase-orders/create/from-pr/{purchaseRequest}', [PurchaseOrderController::class, 'createFromPurchaseRequest'])->name('purchase-orders.create-from-pr');
        Route::post('/purchase-orders/from-pr/{purchaseRequest}', [PurchaseOrderController::class, 'storeFromPurchaseRequest'])->name('purchase-orders.store-from-pr');
        Route::post('/purchase-orders/{purchaseOrder}/submit', [PurchaseOrderController::class, 'submit'])->name('purchase-orders.submit');
        Route::post('/supplier-catalogs', [SupplierCatalogController::class, 'store'])->name('supplier-catalogs.store');
        Route::put('/supplier-catalogs/{catalog}/items/{catalogItem}', [SupplierCatalogController::class, 'updateItem'])->name('supplier-catalogs.items.update');
        Route::post('/supplier-catalogs/{catalog}/publish', [SupplierCatalogController::class, 'publish'])->name('supplier-catalogs.publish');
    });
    Route::post('/purchase-orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])->middleware(['module:procurement', 'permission:procurement.po.approve'])->name('purchase-orders.approve');

    Route::middleware(['module:inventory', 'permission:inventory.gr.view'])->group(function () {
        Route::get('/goods-receipts', [GoodsReceiptController::class, 'index'])->name('goods-receipts.index');
        Route::get('/goods-receipts/{goodsReceipt}/print', [GoodsReceiptController::class, 'print'])->name('goods-receipts.print');
        Route::get('/goods-receipts/{goodsReceipt}', [GoodsReceiptController::class, 'show'])->name('goods-receipts.show');
    });
    Route::middleware(['module:inventory', 'permission:inventory.gr.manage'])->group(function () {
        Route::get('/goods-receipts/create/from-po/{purchaseOrder}', [GoodsReceiptController::class, 'createFromPurchaseOrder'])->name('goods-receipts.create-from-po');
        Route::post('/goods-receipts/from-po/{purchaseOrder}', [GoodsReceiptController::class, 'storeFromPurchaseOrder'])->name('goods-receipts.store-from-po');
        Route::post('/goods-receipts/{goodsReceipt}/post', [GoodsReceiptController::class, 'post'])->name('goods-receipts.post');
        Route::post('/goods-receipts/{goodsReceipt}/reverse', [GoodsReceiptController::class, 'reverse'])->name('goods-receipts.reverse');
    });

    Route::middleware(['module:assets', 'permission:assets.register.view'])->group(function () {
        Route::get('/assets', [AssetRegisterController::class, 'index'])->name('assets.index');
        Route::get('/assets/{asset}/print', [AssetRegisterController::class, 'print'])->name('assets.print');
        Route::get('/assets/{asset}', [AssetRegisterController::class, 'show'])->name('assets.show');
    });
    Route::middleware(['module:assets', 'permission:assets.register.manage'])->group(function () {
        Route::get('/assets/create', [AssetRegisterController::class, 'create'])->name('assets.create');
        Route::get('/assets/create/from-gr-item/{goodsReceiptItem}', [AssetRegisterController::class, 'createFromGoodsReceiptItem'])->name('assets.create-from-gr-item');
        Route::post('/assets', [AssetRegisterController::class, 'store'])->name('assets.store');
    });
    Route::middleware(['module:assets', 'permission:assets.maintenance.view'])->group(function () {
        Route::get('/asset-maintenances', [AssetMaintenanceController::class, 'index'])->name('asset-maintenances.index');
        Route::get('/asset-maintenances/{maintenance}/print', [AssetMaintenanceController::class, 'print'])->name('asset-maintenances.print');
        Route::get('/asset-maintenances/{maintenance}', [AssetMaintenanceController::class, 'show'])->name('asset-maintenances.show');
    });
    Route::middleware(['module:assets', 'permission:assets.maintenance.manage'])->group(function () {
        Route::get('/assets/{asset}/maintenances/create', [AssetMaintenanceController::class, 'create'])->name('asset-maintenances.create');
        Route::post('/assets/{asset}/maintenances', [AssetMaintenanceController::class, 'store'])->name('asset-maintenances.store');
        Route::post('/asset-maintenances/{maintenance}/complete', [AssetMaintenanceController::class, 'complete'])->name('asset-maintenances.complete');
    });

    Route::middleware(['module:reporting', 'module:assets', 'permission:reporting.assets.view'])->group(function () {
        Route::get('/reports/assets/maintenance-history', [AssetMaintenanceReportController::class, 'index'])->name('reports.assets.maintenance-history');
        Route::get('/reports/assets/maintenance-history/print', [AssetMaintenanceReportController::class, 'print'])->name('reports.assets.maintenance-history.print');
        Route::get('/reports/assets/maintenance-history/export', [AssetMaintenanceReportController::class, 'export'])->name('reports.assets.maintenance-history.export');
    });
    Route::middleware(['module:inventory', 'permission:inventory.stock.view'])->group(function () {
        Route::get('/inventory/stock-on-hand', StockOnHandController::class)->name('inventory.stock-on-hand');
        Route::get('/stock-opnames', [StockOpnameController::class, 'index'])->name('stock-opnames.index');
        Route::get('/stock-opnames/{stockOpname}/print', [StockOpnameController::class, 'print'])->name('stock-opnames.print');
        Route::get('/stock-opnames/{stockOpname}', [StockOpnameController::class, 'show'])->name('stock-opnames.show');
    });
    Route::middleware(['module:inventory', 'permission:inventory.stock.manage'])->group(function () {
        Route::get('/stock-opnames/create', [StockOpnameController::class, 'create'])->name('stock-opnames.create');
        Route::post('/stock-opnames', [StockOpnameController::class, 'store'])->name('stock-opnames.store');
        Route::post('/stock-opnames/{stockOpname}/post', [StockOpnameController::class, 'post'])->name('stock-opnames.post');
        Route::post('/stock-opnames/{stockOpname}/reverse', [StockOpnameController::class, 'reverse'])->name('stock-opnames.reverse');
    });

    Route::get('/reports', ReportCenterController::class)->middleware(['module:reporting', 'permission:reporting.view'])->name('reports.index');
    Route::middleware(['module:reporting', 'module:inventory', 'permission:reporting.view'])->group(function () {
        Route::get('/reports/inventory/movements', InventoryMovementReportController::class)->name('reports.inventory.movements');
        Route::get('/reports/inventory/movements/export', [InventoryMovementReportController::class, 'export'])->name('reports.inventory.movements.export');
    });
    Route::middleware(['module:reporting', 'module:procurement', 'permission:reporting.procurement.view'])->group(function () {
        Route::get('/reports/purchasing/cycle', [PurchasingCycleReportController::class, 'index'])->name('reports.purchasing.cycle');
        Route::get('/reports/purchasing/cycle/print', [PurchasingCycleReportController::class, 'print'])->name('reports.purchasing.cycle.print');
        Route::get('/reports/purchasing/cycle/export', [PurchasingCycleReportController::class, 'export'])->name('reports.purchasing.cycle.export');
        Route::get('/reports/purchasing/suppliers', [SupplierPerformanceReportController::class, 'index'])->name('reports.purchasing.suppliers');
        Route::get('/reports/purchasing/suppliers/print', [SupplierPerformanceReportController::class, 'print'])->name('reports.purchasing.suppliers.print');
        Route::get('/reports/purchasing/suppliers/export', [SupplierPerformanceReportController::class, 'export'])->name('reports.purchasing.suppliers.export');
    });
    Route::middleware(['module:budgeting', 'permission:budgeting.view'])->group(function () {
        Route::get('/budget-control', [BudgetControlController::class, 'index'])->name('budget-control.index');
        Route::get('/budget-control/print', [BudgetControlController::class, 'print'])->name('budget-control.print');
        Route::get('/budget-control/export', [BudgetControlController::class, 'export'])->name('budget-control.export');
    });

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
