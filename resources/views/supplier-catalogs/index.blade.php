@extends('layouts.app', ['title' => __('navigation.items.supplier_catalogs').' · SAMS'])

@section('body')
<div class="app-shell">
    @include('partials.sidebar')
    <main class="main">
        <header class="topbar"><div><p class="eyebrow">Procurement Intelligence</p><h1>{{ __('navigation.items.supplier_catalogs') }}</h1><p class="muted">Upload katalog makanan, ATK, furniture, engineering, retail, dan kategori lainnya.</p></div></header>
        @if(session('status'))<div class="notice">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif

        <div class="grid two-columns" style="margin-bottom:18px;">
            @if(auth()->user()->hasPermission('procurement.po.manage'))
            <section class="card"><h2>Upload Supplier Catalog</h2>
                <form method="POST" action="{{ route('supplier-catalogs.store') }}" enctype="multipart/form-data" class="form-grid">@csrf
                    <label>Supplier<select class="input" name="supplier_id" required><option value="">Pilih supplier</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->code }} · {{ $supplier->name }}</option>@endforeach</select></label>
                    <label>Nama katalog<input class="input" name="name" required placeholder="Price List Juli 2026"></label>
                    <label>Mata uang<input class="input" name="currency" value="IDR" maxlength="3" required></label>
                    <label>File<input class="input" type="file" name="catalog_file" accept=".csv,.txt,.xlsx,.xls,.pdf" required></label>
                    <label>Berlaku mulai<input class="input" type="date" name="valid_from"></label><label>Berlaku sampai<input class="input" type="date" name="valid_until"></label>
                    <div class="field full"><button class="button primary" type="submit">Upload & Scan</button></div>
                </form>
            </section>
            @endif
            <section class="card"><h2>AI Price Comparison</h2>
                <form method="POST" action="{{ route('supplier-catalogs.compare') }}" class="form-grid">@csrf
                    <label class="full">Produk<input class="input" name="query" required placeholder="Contoh: salmon fillet, kertas A4, kursi kantor"></label>
                    <label>Quantity<input class="input" type="number" step="0.0001" min="0.0001" name="quantity" required></label>
                    <label>Unit<select class="input" name="unit"><option>KG</option><option>L</option><option>PCS</option><option>SET</option><option>PACK</option><option>BOX</option><option>ROLL</option></select></label>
                    <label class="full">Budget opsional<input class="input" type="number" step="0.01" min="0" name="budget"></label>
                    <div class="field full"><button class="button primary" type="submit">Bandingkan Supplier</button></div>
                </form>
            </section>
        </div>

        @if($comparison)
        <section class="card" style="margin-bottom:18px;"><h2>Hasil: {{ $comparison->query }} · {{ $comparison->quantity }} {{ $comparison->unit }}</h2>
            <div class="table-wrap"><table><thead><tr><th>Supplier</th><th>Produk</th><th>Harga/{{ $comparison->unit }}</th><th>Total</th><th>Budget</th><th>Risk</th><th>Score</th></tr></thead><tbody>
            @forelse($comparison->results as $row)<tr><td><strong>{{ $row['supplier_name'] }}</strong><br><small>{{ $row['catalog_name'] }}</small></td><td>{{ $row['product_name'] }} @if($row['brand'])<br><small>{{ $row['brand'] }}</small>@endif</td><td>Rp {{ number_format($row['unit_price'],0,',','.') }}</td><td>Rp {{ number_format($row['total_cost'],0,',','.') }}</td><td>@if($row['within_budget']===null)-@elseif($row['within_budget'])<span class="badge">Sesuai</span>@else<span class="status">Lebih</span>@endif</td><td>{{ $row['supplier_risk'] }}/100</td><td>{{ $row['recommendation_score'] }}/100</td></tr>@empty<tr><td colspan="7">Tidak ada produk published dengan nama dan unit yang sesuai. Periksa alias/spesifikasi katalog.</td></tr>@endforelse
            </tbody></table></div><p class="muted" style="margin-top:12px;">Harga murah tidak otomatis berarti produk setara. Review brand, ukuran, grade, MOQ, pajak, ongkir, dan spesifikasi sebelum membuat PO.</p>
        </section>
        @endif

        <section class="card"><h2>Uploaded Catalogs</h2><div class="table-wrap"><table><thead><tr><th>Supplier</th><th>Katalog</th><th>File</th><th>Status</th><th>Rows</th><th>Berlaku</th></tr></thead><tbody>
            @forelse($catalogs as $catalog)<tr><td>{{ $catalog->supplier_name }}</td><td><a class="link-action" href="{{ route('supplier-catalogs.show',$catalog->id) }}">{{ $catalog->name }}</a></td><td>{{ $catalog->original_filename }}</td><td><span class="status">{{ $catalog->status }}</span></td><td>{{ $catalog->row_count }}</td><td>{{ $catalog->valid_from ?: '-' }} – {{ $catalog->valid_until ?: '-' }}</td></tr>@empty<tr><td colspan="6">Belum ada katalog.</td></tr>@endforelse
        </tbody></table></div></section>
    </main>
</div>
@endsection
