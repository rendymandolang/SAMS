@extends('layouts.app', ['title' => 'Purchase Order · SAMS'])

@section('body')
    <div class="app-shell">
        @include('partials.sidebar')

        <main class="main">
            <header class="topbar">
                <div>
                    <p class="eyebrow">Purchasing</p>
                    <h1>Purchase Order</h1>
                </div>
            </header>

            @if (session('status'))
                <div class="notice">{{ session('status') }}</div>
            @endif

            <section class="card">
                <div class="toolbar">
                    <div>
                        <h2 style="margin-bottom:6px;">Daftar Purchase Order</h2>
                        <p class="muted" style="margin:0;">PO dibuat dari Purchase Request yang sudah approved.</p>
                    </div>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>No PO</th>
                            <th>Tanggal</th>
                            <th>Supplier</th>
                            <th>Source PR</th>
                            <th>Status</th>
                            <th>Total</th>
                            <th style="text-align:right;">Aksi</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($purchaseOrders as $purchaseOrder)
                            <tr>
                                <td><strong>{{ $purchaseOrder->document_number }}</strong></td>
                                <td>{{ \Illuminate\Support\Carbon::parse($purchaseOrder->order_date)->format('d M Y') }}</td>
                                <td>{{ $purchaseOrder->supplier_name }}</td>
                                <td>{{ $purchaseOrder->purchase_request_number ?: '-' }}</td>
                                <td><span class="status">{{ $purchaseOrder->status }}</span></td>
                                <td>Rp {{ number_format((float) $purchaseOrder->total_amount, 0, ',', '.') }}</td>
                                <td>
                                    <div class="actions">
                                        <a class="link-action" href="{{ route('purchase-orders.show', $purchaseOrder->id) }}">Detail</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">Belum ada Purchase Order. Buat PO dari PR yang sudah approved.</div>
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($purchaseOrders->hasPages())
                    <div class="pagination">{{ $purchaseOrders->links() }}</div>
                @endif
            </section>
        </main>
    </div>
@endsection
