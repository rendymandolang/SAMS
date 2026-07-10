@extends('layouts.app', ['title' => 'Dashboard SAMS'])

@section('body')
    <div class="app-shell">
        @include('partials.sidebar')

        <main class="main">
            <header class="topbar">
                <div>
                    <p class="eyebrow">{{ $company?->name ?? 'SAMS Demo Company' }} &middot; {{ $branch?->name ?? 'Head Office' }}</p>
                    <h1>Dashboard SAMS</h1>
                </div>

                <div class="user-pill">
                    <div>
                        <strong>{{ auth()->user()->name }}</strong>
                        <div class="muted" style="font-size:12px;">{{ str_replace('_', ' ', auth()->user()->role) }}</div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="logout" type="submit">Logout</button>
                    </form>
                </div>
            </header>

            <section class="grid stats">
                <div class="card">
                    <div class="muted">Purchase Request</div>
                    <div class="stat-value">{{ number_format($stats['purchase_requests']) }}</div>
                    <div class="muted">Dokumen permintaan</div>
                </div>
                <div class="card">
                    <div class="muted">Purchase Order</div>
                    <div class="stat-value">{{ number_format($stats['purchase_orders']) }}</div>
                    <div class="muted">Pesanan supplier</div>
                </div>
                <div class="card">
                    <div class="muted">Master Item</div>
                    <div class="stat-value">{{ number_format($stats['items']) }}</div>
                    <div class="muted">Barang/jasa aktif</div>
                </div>
                <div class="card">
                    <div class="muted">Supplier</div>
                    <div class="stat-value">{{ number_format($stats['suppliers']) }}</div>
                    <div class="muted">Vendor terdaftar</div>
                </div>
                <div class="card">
                    <div class="muted">Goods Receipt</div>
                    <div class="stat-value">{{ number_format($stats['goods_receipts']) }}</div>
                    <div class="muted">Penerimaan barang</div>
                </div>
                <div class="card">
                    <div class="muted">Stock Opname</div>
                    <div class="stat-value">{{ number_format($stats['stock_opnames']) }}</div>
                    <div class="muted">Hasil hitung fisik</div>
                </div>
                <div class="card">
                    <div class="muted">Nilai Stok</div>
                    <div class="stat-value">Rp {{ number_format((float) $stats['stock_on_hand_value'], 0, ',', '.') }}</div>
                    <div class="muted">Stock movement posted</div>
                </div>
            </section>

            <section class="grid content-grid">
                <div class="card">
                    <p class="eyebrow">Roadmap modul</p>
                    <h2>Fondasi sistem sudah mulai berbentuk</h2>
                    <p class="muted" style="line-height:1.7;">Tahap ini fokus membuat alur masuk sistem, konteks perusahaan/cabang, dan menu utama. Setelah ini kita bisa mulai CRUD master data dan transaksi pertama.</p>

                    <div class="module-list">
                        @foreach ($modules as $module)
                            <div class="module-row">
                                <div>
                                    <strong>{{ $module['name'] }}</strong>
                                    <p class="muted" style="margin:6px 0 0;line-height:1.55;">{{ $module['description'] }}</p>
                                </div>
                                <span class="badge {{ $module['status'] === 'Berikutnya' ? 'next' : '' }}">{{ $module['status'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <aside class="card">
                    <p class="eyebrow">Aksi cepat</p>
                    <h2>Yang kita bangun berikutnya</h2>

                    <div class="quick-actions">
                        <div class="quick-action">
                            <strong>Master Item</strong>
                            <p class="muted" style="margin:6px 0 0;">Form barang, kategori, satuan, dan harga standar.</p>
                            <a class="link-action" style="display:inline-block;margin-top:10px;" href="{{ route('master.index', 'items') }}">Buka Item</a>
                        </div>
                        <div class="quick-action">
                            <strong>Supplier</strong>
                            <p class="muted" style="margin:6px 0 0;">Data vendor, termin pembayaran, kontak, dan NPWP.</p>
                            <a class="link-action" style="display:inline-block;margin-top:10px;" href="{{ route('master.index', 'suppliers') }}">Buka Supplier</a>
                        </div>
                        <div class="quick-action">
                            <strong>Purchase Request</strong>
                            <p class="muted" style="margin:6px 0 0;">Draft PR dari departemen dan validasi budget awal.</p>
                            <a class="link-action" style="display:inline-block;margin-top:10px;" href="{{ route('purchase-requests.create') }}">Buat PR</a>
                        </div>
                        <div class="quick-action">
                            <strong>Purchase Order</strong>
                            <p class="muted" style="margin:6px 0 0;">PO dari Purchase Request yang sudah approved.</p>
                            <a class="link-action" style="display:inline-block;margin-top:10px;" href="{{ route('purchase-orders.index') }}">Buka PO</a>
                        </div>
                        <div class="quick-action">
                            <strong>Stock On Hand</strong>
                            <p class="muted" style="margin:6px 0 0;">Saldo stok per gudang dari stock movement.</p>
                            <a class="link-action" style="display:inline-block;margin-top:10px;" href="{{ route('inventory.stock-on-hand') }}">Lihat Stok</a>
                        </div>
                        <div class="quick-action">
                            <strong>Stock Opname</strong>
                            <p class="muted" style="margin:6px 0 0;">Hitung fisik stok dan posting adjustment selisih.</p>
                            <a class="link-action" style="display:inline-block;margin-top:10px;" href="{{ route('stock-opnames.create') }}">Buat Opname</a>
                        </div>
                        <div class="quick-action">
                            <strong>Mutasi Stok</strong>
                            <p class="muted" style="margin:6px 0 0;">Laporan masuk/keluar stok lengkap dengan saldo berjalan.</p>
                            <a class="link-action" style="display:inline-block;margin-top:10px;" href="{{ route('reports.inventory.movements') }}">Buka Laporan</a>
                        </div>
                        @if (auth()->user()->hasAnyRole(['super_admin', 'finance', 'purchasing']))
                            <div class="quick-action">
                                <strong>Budget Control</strong>
                                <p class="muted" style="margin:6px 0 0;">Pantau allocated, committed, actual, remaining, dan status risiko budget.</p>
                                <a class="link-action" style="display:inline-block;margin-top:10px;" href="{{ route('budget-control.index') }}">Buka Control</a>
                            </div>
                        @endif
                    </div>

                    <div style="margin-top:22px;padding:18px;border-radius:18px;background:linear-gradient(145deg,#6259ca,#20c997);color:#fff;">
                        <strong>Status lokal</strong>
                        <p style="margin:8px 0 0;line-height:1.6;opacity:.86;">Auth, dashboard, dan data demo siap dipakai untuk iterasi modul berikutnya.</p>
                    </div>
                </aside>
            </section>
        </main>
    </div>
@endsection
