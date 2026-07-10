@extends('layouts.app', ['title' => 'Approval Center - SAMS'])

@section('body')
    <div class="app-shell">
        @include('partials.sidebar')

        <main class="main">
            <header class="topbar">
                <div>
                    <p class="eyebrow">Control Desk</p>
                    <h1>Approval Center</h1>
                </div>
            </header>

            @if (session('status'))
                <div class="notice">{{ session('status') }}</div>
            @endif

            <section class="grid stats" style="margin-bottom:18px;">
                <div class="card">
                    <div class="muted">Pending Approval</div>
                    <div class="stat-value">{{ number_format($summary['pending_count']) }}</div>
                    <div class="muted">PR + PO menunggu keputusan</div>
                </div>
                <div class="card">
                    <div class="muted">Purchase Request</div>
                    <div class="stat-value">{{ number_format($summary['purchase_request_count']) }}</div>
                    <div class="muted">Rp {{ number_format((float) $summary['purchase_request_total'], 0, ',', '.') }}</div>
                </div>
                <div class="card">
                    <div class="muted">Purchase Order</div>
                    <div class="stat-value">{{ number_format($summary['purchase_order_count']) }}</div>
                    <div class="muted">Rp {{ number_format((float) $summary['purchase_order_total'], 0, ',', '.') }}</div>
                </div>
                <div class="card">
                    <div class="muted">High Priority</div>
                    <div class="stat-value">{{ number_format($summary['urgent_count']) }}</div>
                    <div class="muted">PR urgent/high perlu didahulukan</div>
                </div>
            </section>

            <section class="card" style="margin-bottom:18px;">
                <div class="toolbar">
                    <div>
                        <h2 style="margin-bottom:6px;">Purchase Request Pending</h2>
                        <p class="muted" style="margin:0;">Keputusan PR akan mengunci atau melepas komitmen budget.</p>
                    </div>
                    <span class="badge">{{ number_format($purchaseRequests->count()) }} PR</span>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>Dokumen</th>
                            <th>Departemen</th>
                            <th>Peminta</th>
                            <th>Prioritas</th>
                            <th>Tanggal</th>
                            <th>Total</th>
                            <th style="text-align:right;">Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($purchaseRequests as $purchaseRequest)
                            <tr>
                                <td>
                                    <strong>{{ $purchaseRequest->document_number }}</strong>
                                    <div class="muted">{{ $purchaseRequest->purpose ?: 'Tanpa catatan' }}</div>
                                </td>
                                <td>{{ $purchaseRequest->department_name }}</td>
                                <td>{{ $purchaseRequest->requester_name }}</td>
                                <td><span class="status">{{ $purchaseRequest->priority }}</span></td>
                                <td>{{ \Illuminate\Support\Carbon::parse($purchaseRequest->request_date)->format('d M Y') }}</td>
                                <td>Rp {{ number_format((float) $purchaseRequest->estimated_total, 0, ',', '.') }}</td>
                                <td>
                                    <div class="actions">
                                        <a class="link-action" href="{{ route('purchase-requests.show', $purchaseRequest->id) }}">Review</a>
                                        <form method="POST" action="{{ route('purchase-requests.approve', $purchaseRequest->id) }}">
                                            @csrf
                                            <button class="link-action" type="submit">Approve</button>
                                        </form>
                                        <form method="POST" action="{{ route('purchase-requests.reject', $purchaseRequest->id) }}" onsubmit="return confirm('Reject Purchase Request ini?')">
                                            @csrf
                                            <button class="link-action danger" type="submit">Reject</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7"><div class="empty-state">Tidak ada Purchase Request yang menunggu approval.</div></td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="card">
                <div class="toolbar">
                    <div>
                        <h2 style="margin-bottom:6px;">Purchase Order Pending</h2>
                        <p class="muted" style="margin:0;">PO yang disetujui akan siap diterima oleh gudang sebagai Goods Receipt.</p>
                    </div>
                    <span class="badge">{{ number_format($purchaseOrders->count()) }} PO</span>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>Dokumen</th>
                            <th>Supplier</th>
                            <th>Source PR</th>
                            <th>Dibuat Oleh</th>
                            <th>Tanggal</th>
                            <th>Total</th>
                            <th style="text-align:right;">Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($purchaseOrders as $purchaseOrder)
                            <tr>
                                <td><strong>{{ $purchaseOrder->document_number }}</strong></td>
                                <td>{{ $purchaseOrder->supplier_name }}</td>
                                <td>{{ $purchaseOrder->purchase_request_number ?: '-' }}</td>
                                <td>{{ $purchaseOrder->creator_name }}</td>
                                <td>{{ \Illuminate\Support\Carbon::parse($purchaseOrder->order_date)->format('d M Y') }}</td>
                                <td>Rp {{ number_format((float) $purchaseOrder->total_amount, 0, ',', '.') }}</td>
                                <td>
                                    <div class="actions">
                                        <a class="link-action" href="{{ route('purchase-orders.show', $purchaseOrder->id) }}">Review</a>
                                        <form method="POST" action="{{ route('purchase-orders.approve', $purchaseOrder->id) }}">
                                            @csrf
                                            <button class="link-action" type="submit">Approve</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7"><div class="empty-state">Tidak ada Purchase Order yang menunggu approval.</div></td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
@endsection
