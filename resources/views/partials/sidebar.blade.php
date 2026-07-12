@php
    $companyContext = app(\App\Support\CompanyContext::class);
    $user = auth()->user();
    $currentCompany = $companyContext->current();
    $companyMemberships = $companyContext->memberships();
    $companyLogoUrl = filled($currentCompany->logo_path) ? url('storage/'.$currentCompany->logo_path) : null;
    $procurementOpen = request()->routeIs('purchase-requests.*', 'purchase-orders.*', 'supplier-catalogs.*');
    $inventoryOpen = request()->routeIs('goods-receipts.*', 'inventory.*', 'stock-opnames.*');
    $assetOpen = request()->routeIs('assets.*', 'asset-maintenances.*');
    $reportsOpen = request()->routeIs('reports.*', 'budget-control.*');
    $masterOpen = request()->routeIs('master.*');
    $adminOpen = request()->routeIs('approvals.*', 'users.*', 'audit-logs.*', 'settings.*', 'access-control.*', 'data-connections.*');
    $canPurchaseRequests = $user->canAccessModule('procurement') && $user->hasPermission('procurement.pr.view');
    $canPurchaseOrders = $user->canAccessModule('procurement') && $user->hasPermission('procurement.po.view');
    $canSupplierCatalogs = $canPurchaseOrders;
    $canGoodsReceipts = $user->canAccessModule('inventory') && $user->hasPermission('inventory.gr.view');
    $canStock = $user->canAccessModule('inventory') && $user->hasPermission('inventory.stock.view');
    $canAssetRegister = $user->canAccessModule('assets') && $user->hasPermission('assets.register.view');
    $canAssetMaintenance = $user->canAccessModule('assets') && $user->hasPermission('assets.maintenance.view');
    $canProcurement = $canPurchaseRequests || $canPurchaseOrders || $canSupplierCatalogs;
    $canInventory = $canGoodsReceipts || $canStock;
    $canAssets = $canAssetRegister || $canAssetMaintenance;
    $canReportCenter = $user->canAccessModule('reporting') && $user->hasPermission('reporting.view');
    $canInventoryReport = $canReportCenter && $user->canAccessModule('inventory');
    $canAssetReport = $user->canAccessModule('reporting') && $user->canAccessModule('assets') && $user->hasPermission('reporting.assets.view');
    $canProcurementReport = $user->canAccessModule('reporting') && $user->canAccessModule('procurement') && $user->hasPermission('reporting.procurement.view');
    $canBudgeting = $user->canAccessModule('budgeting') && $user->hasPermission('budgeting.view');
    $canReporting = $canReportCenter || $canInventoryReport || $canAssetReport || $canProcurementReport;
    $canIntelligence = $user->canAccessModule('intelligence') && $user->hasPermission('intelligence.view');
    $canAccounting = $user->canAccessModule('accounting') && $user->hasPermission('accounting.view');
    $canMaster = $user->hasPermission('core.master.view');
    $canApprovalCenter = $user->canAccessModule('procurement') && $user->hasPermission('core.approvals.view') && ($user->hasPermission('procurement.pr.approve') || $user->hasPermission('procurement.po.approve'));
    $canAdministration = $canApprovalCenter || $user->hasPermission('core.users.manage') || $user->hasPermission('core.audit.view') || $user->hasPermission('core.settings.manage') || $user->hasPermission('core.access.manage');
@endphp

<button class="mobile-menu-toggle" type="button" data-sidebar-toggle aria-controls="app-sidebar" aria-expanded="false" aria-label="{{ app()->getLocale() === 'id' ? 'Buka navigasi' : 'Open navigation' }}">
    <x-icon name="menu" />
</button>
<button class="sidebar-backdrop" type="button" data-sidebar-backdrop aria-label="{{ app()->getLocale() === 'id' ? 'Tutup navigasi' : 'Close navigation' }}"></button>

<aside class="sidebar" id="app-sidebar">
    <a class="brand" href="{{ route('dashboard') }}" aria-label="{{ $currentCompany->name }}">
        @if ($companyLogoUrl)
            <img class="brand-logo" src="{{ $companyLogoUrl }}" alt="{{ $currentCompany->name }}">
        @else
            <span class="brand-mark">{{ mb_strtoupper(mb_substr($currentCompany->name, 0, 1)) }}</span>
        @endif
        <span class="brand-copy">
            <span class="brand-name">{{ $currentCompany->name }}</span>
            <span class="brand-product">SuperSoft · SAMS</span>
        </span>
    </a>

    <nav class="sidebar-nav" aria-label="{{ app()->getLocale() === 'id' ? 'Navigasi utama' : 'Main navigation' }}">
        <div class="nav-title">{{ __('navigation.sections.workspace') }}</div>
        <div class="nav-group">
            <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif>
                <x-icon class="nav-icon" name="dashboard" />
                <span>{{ __('navigation.dashboard') }}</span>
            </a>
            @if ($canIntelligence)
                <a class="nav-link {{ request()->routeIs('ai-insights.*') ? 'active' : '' }}" href="{{ route('ai-insights.index') }}">
                    <x-icon class="nav-icon" name="sparkles" />
                    <span>{{ __('navigation.items.ai_insights') }}</span>
                </a>
            @endif
            @if ($canAccounting)
                <a class="nav-link {{ request()->routeIs('accounting.index','accounting.show','accounting.create') ? 'active' : '' }}" href="{{ route('accounting.index') }}"><x-icon class="nav-icon" name="reports" /><span>Accounting</span></a>
                <a class="nav-link {{ request('report')==='general-ledger' ? 'active' : '' }}" href="{{ route('accounting.reports.show','general-ledger') }}"><span>General Ledger</span></a>
                <a class="nav-link" href="{{ route('accounting.reports.show','trial-balance') }}"><span>Trial Balance</span></a>
                <a class="nav-link" href="{{ route('accounting.reports.show','profit-loss') }}"><span>Profit & Loss</span></a>
                <a class="nav-link" href="{{ route('accounting.reports.show','balance-sheet') }}"><span>Balance Sheet</span></a>
                @if($user->hasPermission('accounting.post'))<a class="nav-link {{ request()->routeIs('accounting.close-month*')?'active':'' }}" href="{{ route('accounting.close-month') }}"><span>Close Month</span></a>@endif
            @endif
        </div>

        @if ($canProcurement)
        <details class="nav-section" @if($procurementOpen) open @endif>
            <summary class="nav-section-summary">
                <x-icon class="nav-icon" name="procurement" />
                <span>{{ __('navigation.sections.procurement') }}</span>
                <x-icon class="chevron" name="chevron" />
            </summary>
            <div class="nav-submenu">
                @if ($canPurchaseRequests)
                    <a class="nav-link {{ request()->routeIs('purchase-requests.*') ? 'active' : '' }}" href="{{ route('purchase-requests.index') }}" @if(request()->routeIs('purchase-requests.*')) aria-current="page" @endif><span>{{ __('navigation.items.purchase_requests') }}</span></a>
                @endif
                @if ($canPurchaseOrders)
                    <a class="nav-link {{ request()->routeIs('purchase-orders.*') ? 'active' : '' }}" href="{{ route('purchase-orders.index') }}" @if(request()->routeIs('purchase-orders.*')) aria-current="page" @endif><span>{{ __('navigation.items.purchase_orders') }}</span></a>
                @endif
                @if ($canSupplierCatalogs)
                    <a class="nav-link {{ request()->routeIs('supplier-catalogs.*') ? 'active' : '' }}" href="{{ route('supplier-catalogs.index') }}"><span>{{ __('navigation.items.supplier_catalogs') }}</span></a>
                @endif
            </div>
        </details>
        @endif

        @if ($canInventory)
        <details class="nav-section" @if($inventoryOpen) open @endif>
            <summary class="nav-section-summary">
                <x-icon class="nav-icon" name="inventory" />
                <span>{{ __('navigation.sections.inventory') }}</span>
                <x-icon class="chevron" name="chevron" />
            </summary>
            <div class="nav-submenu">
                @if ($canGoodsReceipts)
                    <a class="nav-link {{ request()->routeIs('goods-receipts.*') ? 'active' : '' }}" href="{{ route('goods-receipts.index') }}" @if(request()->routeIs('goods-receipts.*')) aria-current="page" @endif><span>{{ __('navigation.items.goods_receipts') }}</span></a>
                @endif
                @if ($canStock)
                    <a class="nav-link {{ request()->routeIs('inventory.*') ? 'active' : '' }}" href="{{ route('inventory.stock-on-hand') }}" @if(request()->routeIs('inventory.*')) aria-current="page" @endif><span>{{ __('navigation.items.stock_on_hand') }}</span></a>
                    <a class="nav-link {{ request()->routeIs('stock-opnames.*') ? 'active' : '' }}" href="{{ route('stock-opnames.index') }}" @if(request()->routeIs('stock-opnames.*')) aria-current="page" @endif><span>{{ __('navigation.items.stock_opname') }}</span></a>
                @endif
            </div>
        </details>
        @endif

        @if ($canAssets)
        <details class="nav-section" @if($assetOpen) open @endif>
            <summary class="nav-section-summary">
                <x-icon class="nav-icon" name="asset" />
                <span>{{ __('navigation.sections.asset_management') }}</span>
                <x-icon class="chevron" name="chevron" />
            </summary>
            <div class="nav-submenu">
                @if ($canAssetRegister)
                    <a class="nav-link {{ request()->routeIs('assets.*') ? 'active' : '' }}" href="{{ route('assets.index') }}" @if(request()->routeIs('assets.*')) aria-current="page" @endif><span>{{ __('navigation.items.asset_register') }}</span></a>
                @endif
                @if ($canAssetMaintenance)
                    <a class="nav-link {{ request()->routeIs('asset-maintenances.*') ? 'active' : '' }}" href="{{ route('asset-maintenances.index') }}" @if(request()->routeIs('asset-maintenances.*')) aria-current="page" @endif><span>{{ __('navigation.items.asset_maintenance') }}</span></a>
                @endif
            </div>
        </details>
        @endif

        @if ($canReporting || $canBudgeting)
        <details class="nav-section" @if($reportsOpen) open @endif>
            <summary class="nav-section-summary">
                <x-icon class="nav-icon" name="reports" />
                <span>{{ __('navigation.sections.reports') }}</span>
                <x-icon class="chevron" name="chevron" />
            </summary>
            <div class="nav-submenu">
                @if ($canReportCenter)
                    <a class="nav-link {{ request()->routeIs('reports.index') ? 'active' : '' }}" href="{{ route('reports.index') }}" @if(request()->routeIs('reports.index')) aria-current="page" @endif><span>{{ __('navigation.items.report_center') }}</span></a>
                @endif
                @if ($canInventoryReport)
                    <a class="nav-link {{ request()->routeIs('reports.inventory.*') ? 'active' : '' }}" href="{{ route('reports.inventory.movements') }}" @if(request()->routeIs('reports.inventory.*')) aria-current="page" @endif><span>{{ __('navigation.items.inventory_movements') }}</span></a>
                @endif
                @if ($canAssetReport)
                    <a class="nav-link {{ request()->routeIs('reports.assets.*') ? 'active' : '' }}" href="{{ route('reports.assets.maintenance-history') }}" @if(request()->routeIs('reports.assets.*')) aria-current="page" @endif><span>{{ __('navigation.items.maintenance_history') }}</span></a>
                @endif
                @if ($canProcurementReport)
                    <a class="nav-link {{ request()->routeIs('reports.purchasing.cycle*') ? 'active' : '' }}" href="{{ route('reports.purchasing.cycle') }}" @if(request()->routeIs('reports.purchasing.cycle*')) aria-current="page" @endif><span>{{ __('navigation.items.purchasing_cycle') }}</span></a>
                    <a class="nav-link {{ request()->routeIs('reports.purchasing.suppliers*') ? 'active' : '' }}" href="{{ route('reports.purchasing.suppliers') }}" @if(request()->routeIs('reports.purchasing.suppliers*')) aria-current="page" @endif><span>{{ __('navigation.items.supplier_performance') }}</span></a>
                @endif
                @if ($canBudgeting)
                    <a class="nav-link {{ request()->routeIs('budget-control.*') ? 'active' : '' }}" href="{{ route('budget-control.index') }}" @if(request()->routeIs('budget-control.*')) aria-current="page" @endif><span>{{ __('navigation.items.budget_control') }}</span></a>
                @endif
            </div>
        </details>
        @endif

        @if ($canMaster)
        <details class="nav-section" @if($masterOpen) open @endif>
            <summary class="nav-section-summary">
                <x-icon class="nav-icon" name="master" />
                <span>{{ __('navigation.sections.master_data') }}</span>
                <x-icon class="chevron" name="chevron" />
            </summary>
            <div class="nav-submenu">
                <a class="nav-link {{ request()->routeIs('master.home') ? 'active' : '' }}" href="{{ route('master.home') }}"><span>{{ __('navigation.items.master_data') }}</span></a>
                <a class="nav-link {{ request()->is('master/items*') ? 'active' : '' }}" href="{{ route('master.index', 'items') }}"><span>{{ __('navigation.items.items') }}</span></a>
                <a class="nav-link {{ request()->is('master/item-categories*') ? 'active' : '' }}" href="{{ route('master.index', 'item-categories') }}"><span>{{ __('navigation.items.item_categories') }}</span></a>
                <a class="nav-link {{ request()->is('master/units*') ? 'active' : '' }}" href="{{ route('master.index', 'units') }}"><span>{{ __('navigation.items.units') }}</span></a>
                <a class="nav-link {{ request()->is('master/suppliers*') ? 'active' : '' }}" href="{{ route('master.index', 'suppliers') }}"><span>{{ __('navigation.items.suppliers') }}</span></a>
                <a class="nav-link {{ request()->is('master/storage-locations*') ? 'active' : '' }}" href="{{ route('master.index', 'storage-locations') }}"><span>{{ __('navigation.items.storage_locations') }}</span></a>
            </div>
        </details>
        @endif

        @if ($canAdministration)
            <details class="nav-section" @if($adminOpen) open @endif>
                <summary class="nav-section-summary">
                    <x-icon class="nav-icon" name="settings" />
                    <span>{{ __('navigation.sections.administration') }}</span>
                    <x-icon class="chevron" name="chevron" />
                </summary>
                <div class="nav-submenu">
                    @if ($canApprovalCenter)
                        <a class="nav-link {{ request()->routeIs('approvals.*') ? 'active' : '' }}" href="{{ route('approvals.index') }}"><span>{{ __('navigation.items.approval_center') }}</span></a>
                    @endif
                    @if ($user->hasPermission('core.users.manage'))
                        <a class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}"><span>{{ __('navigation.items.user_management') }}</span></a>
                    @endif
                    @if ($user->hasPermission('core.audit.view'))
                        <a class="nav-link {{ request()->routeIs('audit-logs.*') ? 'active' : '' }}" href="{{ route('audit-logs.index') }}"><span>{{ __('navigation.items.audit_logs') }}</span></a>
                    @endif
                    @if ($user->hasPermission('core.settings.manage'))
                        <a class="nav-link {{ request()->routeIs('settings.company.*') ? 'active' : '' }}" href="{{ route('settings.company.edit') }}"><span>{{ __('navigation.items.company_settings') }}</span></a>
                        <a class="nav-link {{ request()->routeIs('settings.period-locks.*') ? 'active' : '' }}" href="{{ route('settings.period-locks.index') }}"><span>{{ __('navigation.items.period_locks') }}</span></a>
                        <a class="nav-link {{ request()->routeIs('data-connections.*') ? 'active' : '' }}" href="{{ route('data-connections.index') }}"><span>Data Connections</span></a>
                    @endif
                    @if ($user->hasPermission('core.access.manage'))
                        <a class="nav-link {{ request()->routeIs('access-control.*') ? 'active' : '' }}" href="{{ route('access-control.index') }}"><span>{{ __('navigation.items.access_control') }}</span></a>
                    @endif
                </div>
            </details>
        @endif
    </nav>

    <div class="sidebar-spacer"></div>

    <div class="sidebar-account">
        <div class="company-chip">
            <span class="company-chip__mark"><x-icon name="building" style="width:17px;height:17px;" /></span>
            <span class="company-chip__copy">
                <strong>{{ auth()->user()->name }}</strong>
                @php $currentRoleKey = $user->currentRoleKey(); @endphp
                <span>{{ \Illuminate\Support\Facades\Lang::has('access.roles.'.$currentRoleKey) ? __('access.roles.'.$currentRoleKey) : str($currentRoleKey)->replace('_', ' ')->title() }}</span>
            </span>
        </div>

        @if ($companyMemberships->count() > 1)
            <form method="POST" action="{{ route('context.company.update') }}">
                @csrf
                <select class="company-select" name="company_id" aria-label="{{ __('reports.filters.company') }}" onchange="this.form.submit()">
                    @foreach ($companyMemberships as $membership)
                        <option value="{{ $membership->id }}" @selected((int) $membership->id === (int) $currentCompany->id)>{{ $membership->name }}</option>
                    @endforeach
                </select>
            </form>
        @endif

        <div class="sidebar-account__row">
            <div class="locale-switch" aria-label="{{ __('common.language') }}">
                @foreach (\App\Support\SupportedLocale::options() as $locale => $option)
                    <a class="{{ app()->getLocale() === $locale ? 'active' : '' }}" href="{{ route('locale.update', $locale) }}" lang="{{ $locale }}" @if(app()->getLocale() === $locale) aria-current="true" @endif>{{ $option['short_name'] }}</a>
                @endforeach
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="sidebar-logout" type="submit">{{ __('navigation.account.logout') }}</button>
            </form>
        </div>
    </div>
</aside>
