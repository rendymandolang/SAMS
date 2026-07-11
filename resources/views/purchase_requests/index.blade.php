@extends('layouts.app', ['title' => 'Purchase Request · SAMS'])

@section('body')
    <div class="app-shell">
        @include('partials.sidebar')

        <main class="main">
            <header class="topbar">
                <div>
                    <p class="eyebrow">Purchasing</p>
                    <h1>Purchase Request</h1>
                </div>

                @if (auth()->user()->hasPermission('procurement.pr.manage'))
                    <a class="button inline" href="{{ route('purchase-requests.create') }}">+ Buat PR</a>
                @endif
            </header>

            @if (session('status'))
                <div class="notice">{{ session('status') }}</div>
            @endif

            <section class="card">
                <div class="toolbar">
                    <div>
                        <h2 style="margin-bottom:6px;">Daftar Purchase Request</h2>
                        <p class="muted" style="margin:0;">Permintaan pembelian dari departemen sebelum menjadi Purchase Order.</p>
                    </div>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>No Dokumen</th>
                            <th>Tanggal</th>
                            <th>Departemen</th>
                            <th>Peminta</th>
                            <th>Prioritas</th>
                            <th>Status</th>
                            <th>Total Estimasi</th>
                            <th style="text-align:right;">Aksi</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($purchaseRequests as $purchaseRequest)
                            <tr>
                                <td><strong>{{ $purchaseRequest->document_number }}</strong></td>
                                <td>{{ \Illuminate\Support\Carbon::parse($purchaseRequest->request_date)->format('d M Y') }}</td>
                                <td>{{ $purchaseRequest->department_name }}</td>
                                <td>{{ $purchaseRequest->requester_name }}</td>
                                <td>{{ ucfirst($purchaseRequest->priority) }}</td>
                                <td><span class="status">{{ $purchaseRequest->status }}</span></td>
                                <td>Rp {{ number_format((float) $purchaseRequest->estimated_total, 0, ',', '.') }}</td>
                                <td>
                                    <div class="actions">
                                        <a class="link-action" href="{{ route('purchase-requests.show', $purchaseRequest->id) }}">Detail</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">Belum ada Purchase Request. Klik tombol Buat PR untuk membuat draft pertama.</div>
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($purchaseRequests->hasPages())
                    <div class="pagination">{{ $purchaseRequests->links() }}</div>
                @endif
            </section>
        </main>
    </div>
@endsection
