@php
    $companyContext = app(\App\Support\CompanyContext::class);
    $currentCompany = $companyContext->current();
    $companyMemberships = $companyContext->memberships();
    $companyLogoUrl = filled($currentCompany->logo_path) ? url('storage/'.$currentCompany->logo_path) : null;
    $procurementOpen = request()->routeIs('purchase-requests.*', 'purchase-orders.*', 'goods-receipts.*');
    $inventoryOpen = request()->routeIs('inventory.*', 'stock-opnames.*');
    $assetOpen = request()->routeIs('assets.*', 'asset-maintenances.*');
    $reportsOpen = request()->routeIs('reports.*', 'budget-control.*');
    $masterOpen = request()->routeIs('master.*');
    $adminOpen = request()->routeIs('approvals.*', 'users.*', 'audit-logs.*', 'settings.*');
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
        </div>

        <details class="nav-section" @if($procurementOpen) open @endif>
            <summary class="nav-section-summary">
                <x-icon class="nav-icon" name="procurement" />
                <span>{{ __('navigation.sections.procurement') }}</span>
                <x-icon class="chevron" name="chevron" />
            </summary>
            <div class="nav-submenu">
                <a class="nav-link {{ request()->routeIs('purchase-requests.*') ? 'active' : '' }}" href="{{ route('purchase-requests.index') }}" @if(request()->routeIs('purchase-requests.*')) aria-current="page" @endif><span>{{ __('navigation.items.purchase_requests') }}</span></a>
                <a class="nav-link {{ request()->routeIs('purchase-orders.*') ? 'active' : '' }}" href="{{ route('purchase-orders.index') }}" @if(request()->routeIs('purchase-orders.*')) aria-current="page" @endif><span>{{ __('navigation.items.purchase_orders') }}</span></a>
                <a class="nav-link {{ request()->routeIs('goods-receipts.*') ? 'active' : '' }}" href="{{ route('goods-receipts.index') }}" @if(request()->routeIs('goods-receipts.*')) aria-current="page" @endif><span>{{ __('navigation.items.goods_receipts') }}</span></a>
            </div>
        </details>

        <details class="nav-section" @if($inventoryOpen) open @endif>
            <summary class="nav-section-summary">
                <x-icon class="nav-icon" name="inventory" />
                <span>{{ __('navigation.sections.inventory') }}</span>
                <x-icon class="chevron" name="chevron" />
            </summary>
            <div class="nav-submenu">
                <a class="nav-link {{ request()->routeIs('inventory.*') ? 'active' : '' }}" href="{{ route('inventory.stock-on-hand') }}" @if(request()->routeIs('inventory.*')) aria-current="page" @endif><span>{{ __('navigation.items.stock_on_hand') }}</span></a>
                <a class="nav-link {{ request()->routeIs('stock-opnames.*') ? 'active' : '' }}" href="{{ route('stock-opnames.index') }}" @if(request()->routeIs('stock-opnames.*')) aria-current="page" @endif><span>{{ __('navigation.items.stock_opname') }}</span></a>
            </div>
        </details>

        <details class="nav-section" @if($assetOpen) open @endif>
            <summary class="nav-section-summary">
                <x-icon class="nav-icon" name="asset" />
                <span>{{ __('navigation.sections.asset_management') }}</span>
                <x-icon class="chevron" name="chevron" />
            </summary>
            <div class="nav-submenu">
                <a class="nav-link {{ request()->routeIs('assets.*') ? 'active' : '' }}" href="{{ route('assets.index') }}" @if(request()->routeIs('assets.*')) aria-current="page" @endif><span>{{ __('navigation.items.asset_register') }}</span></a>
                <a class="nav-link {{ request()->routeIs('asset-maintenances.*') ? 'active' : '' }}" href="{{ route('asset-maintenances.index') }}" @if(request()->routeIs('asset-maintenances.*')) aria-current="page" @endif><span>{{ __('navigation.items.asset_maintenance') }}</span></a>
            </div>
        </details>

        <details class="nav-section" @if($reportsOpen) open @endif>
            <summary class="nav-section-summary">
                <x-icon class="nav-icon" name="reports" />
                <span>{{ __('navigation.sections.reports') }}</span>
                <x-icon class="chevron" name="chevron" />
            </summary>
            <div class="nav-submenu">
                <a class="nav-link {{ request()->routeIs('reports.index') ? 'active' : '' }}" href="{{ route('reports.index') }}" @if(request()->routeIs('reports.index')) aria-current="page" @endif><span>{{ __('navigation.items.report_center') }}</span></a>
                <a class="nav-link {{ request()->routeIs('reports.inventory.*') ? 'active' : '' }}" href="{{ route('reports.inventory.movements') }}" @if(request()->routeIs('reports.inventory.*')) aria-current="page" @endif><span>{{ __('navigation.items.inventory_movements') }}</span></a>
                <a class="nav-link {{ request()->routeIs('reports.assets.*') ? 'active' : '' }}" href="{{ route('reports.assets.maintenance-history') }}" @if(request()->routeIs('reports.assets.*')) aria-current="page" @endif><span>{{ __('navigation.items.maintenance_history') }}</span></a>
                @if (auth()->user()->hasAnyRole(['super_admin', 'finance', 'purchasing']))
                    <a class="nav-link {{ request()->routeIs('reports.purchasing.cycle*') ? 'active' : '' }}" href="{{ route('reports.purchasing.cycle') }}" @if(request()->routeIs('reports.purchasing.cycle*')) aria-current="page" @endif><span>{{ __('navigation.items.purchasing_cycle') }}</span></a>
                    <a class="nav-link {{ request()->routeIs('reports.purchasing.suppliers*') ? 'active' : '' }}" href="{{ route('reports.purchasing.suppliers') }}" @if(request()->routeIs('reports.purchasing.suppliers*')) aria-current="page" @endif><span>{{ __('navigation.items.supplier_performance') }}</span></a>
                    <a class="nav-link {{ request()->routeIs('budget-control.*') ? 'active' : '' }}" href="{{ route('budget-control.index') }}" @if(request()->routeIs('budget-control.*')) aria-current="page" @endif><span>{{ __('navigation.items.budget_control') }}</span></a>
                @endif
            </div>
        </details>

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

        @if (auth()->user()->hasAnyRole(['super_admin', 'finance']))
            <details class="nav-section" @if($adminOpen) open @endif>
                <summary class="nav-section-summary">
                    <x-icon class="nav-icon" name="settings" />
                    <span>{{ __('navigation.sections.administration') }}</span>
                    <x-icon class="chevron" name="chevron" />
                </summary>
                <div class="nav-submenu">
                    <a class="nav-link {{ request()->routeIs('approvals.*') ? 'active' : '' }}" href="{{ route('approvals.index') }}"><span>{{ __('navigation.items.approval_center') }}</span></a>
                    @if (auth()->user()->hasRole('super_admin'))
                        <a class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}"><span>{{ __('navigation.items.user_management') }}</span></a>
                        <a class="nav-link {{ request()->routeIs('audit-logs.*') ? 'active' : '' }}" href="{{ route('audit-logs.index') }}"><span>{{ __('navigation.items.audit_logs') }}</span></a>
                        <a class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}" href="{{ route('settings.company.edit') }}"><span>{{ __('navigation.items.company_settings') }}</span></a>
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
                <span>{{ str_replace('_', ' ', auth()->user()->role) }}</span>
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
