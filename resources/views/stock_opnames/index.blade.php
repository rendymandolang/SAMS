@extends('layouts.app', ['title' => 'Stock Opname · SAMS'])

@section('body')
    <div class="app-shell">
        @include('partials.sidebar')

        <main class="main">
            <header class="topbar">
                <div>
                    <p class="eyebrow">Inventory Control</p>
                    <h1>Stock Opname</h1>
                </div>

                @if (auth()->user()->hasAnyRole(['super_admin', 'warehouse']))
                    <a class="button inline" href="{{ route('stock-opnames.create') }}">Buat Opname</a>
                @endif
            </header>

            @if (session('status'))
                <div class="notice">{{ session('status') }}</div>
            @endif

            <section class="card">
                <div class="toolbar">
                    <div>
                        <h2 style="margin-bottom:6px;">Daftar Stock Opname</h2>
                        <p class="muted" style="margin:0;">Kontrol hitung fisik stok dan adjustment selisih.</p>
                    </div>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>No Opname</th>
                            <th>Tanggal</th>
                            <th>Gudang</th>
                            <th>Dibuat oleh</th>
                            <th>Status</th>
                            <th>Posted</th>
                            <th style="text-align:right;">Aksi</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($stockOpnames as $stockOpname)
                            <tr>
                                <td><strong>{{ $stockOpname->document_number }}</strong></td>
                                <td>{{ \Illuminate\Support\Carbon::parse($stockOpname->count_date)->format('d M Y') }}</td>
                                <td><strong>{{ $stockOpname->location_code }}</strong><br><span class="muted">{{ $stockOpname->location_name }}</span></td>
                                <td>{{ $stockOpname->creator_name }}</td>
                                <td><span class="status">{{ $stockOpname->status }}</span></td>
                                <td>{{ $stockOpname->posted_at ? \Illuminate\Support\Carbon::parse($stockOpname->posted_at)->format('d M Y H:i') : '-' }}</td>
                                <td>
                                    <div class="actions">
                                        <a class="link-action" href="{{ route('stock-opnames.show', $stockOpname->id) }}">Detail</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">Belum ada Stock Opname. Buat opname dari gudang yang sudah punya saldo stok.</div>
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($stockOpnames->hasPages())
                    <div class="pagination">{{ $stockOpnames->links() }}</div>
                @endif
            </section>
        </main>
    </div>
@endsection
