<aside class="sidebar">
    <div class="brand">
        <span class="brand-mark">S</span>
        <span>SAMS</span>
    </div>

    <div class="nav-title">Main</div>
    <nav class="nav-group">
        <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><span>Dashboard</span><span>&bull;</span></a>
        <a class="nav-link {{ request()->routeIs('purchase-requests.*') ? 'active' : '' }}" href="{{ route('purchase-requests.index') }}"><span>Purchase Request</span><span>&rsaquo;</span></a>
        <a class="nav-link {{ request()->routeIs('purchase-orders.*') ? 'active' : '' }}" href="{{ route('purchase-orders.index') }}"><span>Purchase Order</span><span>&rsaquo;</span></a>
        <a class="nav-link {{ request()->routeIs('goods-receipts.*') ? 'active' : '' }}" href="{{ route('goods-receipts.index') }}"><span>Goods Receipt</span><span>&rsaquo;</span></a>
        <a class="nav-link {{ request()->routeIs('inventory.*') ? 'active' : '' }}" href="{{ route('inventory.stock-on-hand') }}"><span>Stock On Hand</span><span>&rsaquo;</span></a>
        <a class="nav-link {{ request()->routeIs('stock-opnames.*') ? 'active' : '' }}" href="{{ route('stock-opnames.index') }}"><span>Stock Opname</span><span>&rsaquo;</span></a>
        <a class="nav-link {{ request()->routeIs('reports.inventory.*') ? 'active' : '' }}" href="{{ route('reports.inventory.movements') }}"><span>Mutasi Stok</span><span>&rsaquo;</span></a>
        <a class="nav-link" href="#"><span>Budget Control</span><span>&rsaquo;</span></a>
    </nav>

    <div class="nav-title">Master Data</div>
    <nav class="nav-group">
        <a class="nav-link {{ request()->routeIs('master.home') ? 'active' : '' }}" href="{{ route('master.home') }}"><span>Overview</span><span>&rsaquo;</span></a>
        <a class="nav-link {{ request()->is('master/items*') ? 'active' : '' }}" href="{{ route('master.index', 'items') }}"><span>Item</span><span>&rsaquo;</span></a>
        <a class="nav-link {{ request()->is('master/item-categories*') ? 'active' : '' }}" href="{{ route('master.index', 'item-categories') }}"><span>Kategori Item</span><span>&rsaquo;</span></a>
        <a class="nav-link {{ request()->is('master/units*') ? 'active' : '' }}" href="{{ route('master.index', 'units') }}"><span>Satuan</span><span>&rsaquo;</span></a>
        <a class="nav-link {{ request()->is('master/suppliers*') ? 'active' : '' }}" href="{{ route('master.index', 'suppliers') }}"><span>Supplier</span><span>&rsaquo;</span></a>
        <a class="nav-link {{ request()->is('master/storage-locations*') ? 'active' : '' }}" href="{{ route('master.index', 'storage-locations') }}"><span>Gudang / Lokasi</span><span>&rsaquo;</span></a>
    </nav>

    <div class="nav-title">Control</div>
    <nav class="nav-group">
        <a class="nav-link" href="#"><span>Approval Flow</span><span>&rsaquo;</span></a>
        <a class="nav-link" href="#"><span>Audit Trail</span><span>&rsaquo;</span></a>
    </nav>
</aside>
