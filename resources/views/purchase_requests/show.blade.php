@extends('layouts.app', ['title' => $header->document_number.' · SAMS'])

@section('body')
    <div class="app-shell">
        @include('partials.sidebar')

        <main class="main">
            <header class="topbar">
                <div>
                    <p class="eyebrow">Purchase Request</p>
                    <h1>{{ $header->document_number }}</h1>
                </div>

                <div style="display:flex;gap:10px;align-items:center;">
                    <a class="button secondary inline" href="{{ route('purchase-requests.index') }}">Kembali</a>
                    @if ($header->status === 'draft')
                        <a class="button secondary inline" href="{{ route('purchase-requests.edit', $header->id) }}">Edit Draft</a>
                        <form method="POST" action="{{ route('purchase-requests.submit', $header->id) }}">
                            @csrf
                            <button class="button inline" type="submit">Submit</button>
                        </form>
                    @endif
                </div>
            </header>

            @if (session('status'))
                <div class="notice">{{ session('status') }}</div>
            @endif

            <section class="card" style="margin-bottom:18px;">
                <div class="detail-grid">
                    <div class="detail-box">
                        <div class="muted">Status</div>
                        <div class="value"><span class="status">{{ $header->status }}</span></div>
                    </div>
                    <div class="detail-box">
                        <div class="muted">Departemen</div>
                        <div class="value">{{ $header->department_name }}</div>
                    </div>
                    <div class="detail-box">
                        <div class="muted">Peminta</div>
                        <div class="value">{{ $header->requester_name }}</div>
                    </div>
                    <div class="detail-box">
                        <div class="muted">Total Estimasi</div>
                        <div class="value">Rp {{ number_format((float) $header->estimated_total, 0, ',', '.') }}</div>
                    </div>
                    <div class="detail-box">
                        <div class="muted">Cabang</div>
                        <div class="value">{{ $header->branch_name }}</div>
                    </div>
                    <div class="detail-box">
                        <div class="muted">Tanggal Request</div>
                        <div class="value">{{ \Illuminate\Support\Carbon::parse($header->request_date)->format('d M Y') }}</div>
                    </div>
                    <div class="detail-box">
                        <div class="muted">Tanggal Dibutuhkan</div>
                        <div class="value">{{ $header->required_date ? \Illuminate\Support\Carbon::parse($header->required_date)->format('d M Y') : '-' }}</div>
                    </div>
                    <div class="detail-box">
                        <div class="muted">Prioritas</div>
                        <div class="value">{{ ucfirst($header->priority) }}</div>
                    </div>
                </div>

                <div>
                    <div class="muted">Tujuan / Catatan</div>
                    <p style="margin:8px 0 0;line-height:1.7;">{{ $header->purpose ?: '-' }}</p>
                </div>
            </section>

            <section class="card">
                <h2>Item PR</h2>
                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Item</th>
                            <th>Qty</th>
                            <th>Satuan</th>
                            <th>Budget</th>
                            <th>Harga Estimasi</th>
                            <th>Total</th>
                            <th>Catatan</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($items as $item)
                            <tr>
                                <td>{{ $item->sku }}</td>
                                <td><strong>{{ $item->item_name }}</strong></td>
                                <td>{{ number_format((float) $item->quantity, 2, ',', '.') }}</td>
                                <td>{{ $item->unit_code }}</td>
                                <td>{{ $item->budget_account_code ? $item->budget_account_code.' - '.$item->budget_description : '-' }}</td>
                                <td>Rp {{ number_format((float) $item->estimated_unit_price, 0, ',', '.') }}</td>
                                <td>Rp {{ number_format((float) $item->estimated_total, 0, ',', '.') }}</td>
                                <td>{{ $item->notes ?: '-' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($header->status === 'draft')
                    <form method="POST" action="{{ route('purchase-requests.destroy', $header->id) }}" onsubmit="return confirm('Hapus draft Purchase Request ini?')" style="display:flex;justify-content:flex-end;margin-top:22px;">
                        @csrf
                        @method('DELETE')
                        <button class="button danger inline" type="submit">Hapus Draft</button>
                    </form>
                @endif
            </section>
        </main>
    </div>
@endsection
