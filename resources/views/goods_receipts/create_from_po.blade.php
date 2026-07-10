@extends('layouts.app', ['title' => 'Buat GR dari '.$header->document_number.' · SAMS'])

@section('body')
    <div class="app-shell">
        @include('partials.sidebar')

        <main class="main">
            <header class="topbar">
                <div>
                    <p class="eyebrow">Goods Receipt</p>
                    <h1>Buat GR dari {{ $header->document_number }}</h1>
                </div>

                <a class="button secondary inline" href="{{ route('purchase-orders.show', $header->id) }}">Kembali ke PO</a>
            </header>

            @if ($errors->any())
                <div class="error">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('goods-receipts.store-from-po', $header->id) }}">
                @csrf

                <section class="card" style="margin-bottom:18px;">
                    <h2>Informasi Penerimaan</h2>
                    <div class="form-grid">
                        <label class="field">
                            <span class="label">Gudang / Lokasi *</span>
                            <select class="input" name="storage_location_id" required>
                                @foreach ($locations as $location)
                                    <option value="{{ $location->id }}" @selected((string) old('storage_location_id') === (string) $location->id)>{{ $location->code }} · {{ $location->name }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="field">
                            <span class="label">Tanggal Terima *</span>
                            <input class="input" name="received_at" type="datetime-local" value="{{ old('received_at', now()->format('Y-m-d\\TH:i')) }}" required>
                        </label>

                        <label class="field">
                            <span class="label">No Surat Jalan Supplier</span>
                            <input class="input" name="supplier_delivery_number" type="text" value="{{ old('supplier_delivery_number') }}">
                        </label>

                        <label class="field full">
                            <span class="label">Catatan</span>
                            <textarea class="input" name="notes">{{ old('notes') }}</textarea>
                        </label>
                    </div>
                </section>

                <section class="card">
                    <h2>Item Diterima</h2>
                    <div class="table-wrap">
                        <table>
                            <thead>
                            <tr>
                                <th>Item</th>
                                <th>Sisa PO</th>
                                <th>Accepted</th>
                                <th>Rejected</th>
                                <th>Lot</th>
                                <th>Expiry</th>
                                <th>Alasan Reject</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($items as $index => $item)
                                @php
                                    $remaining = (float) $item->quantity - (float) $item->received_quantity;
                                @endphp
                                <tr>
                                    <td>
                                        <strong>{{ $item->sku }}</strong><br>
                                        <span class="muted">{{ $item->item_name }} ({{ $item->unit_code }})</span>
                                        <input type="hidden" name="lines[{{ $index }}][purchase_order_item_id]" value="{{ $item->id }}">
                                    </td>
                                    <td>{{ number_format($remaining, 2, ',', '.') }}</td>
                                    <td><input class="input" name="lines[{{ $index }}][accepted_quantity]" type="number" min="0" step="0.0001" value="{{ old("lines.$index.accepted_quantity", $remaining) }}"></td>
                                    <td><input class="input" name="lines[{{ $index }}][rejected_quantity]" type="number" min="0" step="0.0001" value="{{ old("lines.$index.rejected_quantity", 0) }}"></td>
                                    <td><input class="input" name="lines[{{ $index }}][lot_number]" type="text" value="{{ old("lines.$index.lot_number") }}"></td>
                                    <td><input class="input" name="lines[{{ $index }}][expiry_date]" type="date" value="{{ old("lines.$index.expiry_date") }}"></td>
                                    <td><input class="input" name="lines[{{ $index }}][rejection_reason]" type="text" value="{{ old("lines.$index.rejection_reason") }}"></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:24px;">
                        <a class="button secondary inline" href="{{ route('purchase-orders.show', $header->id) }}">Batal</a>
                        <button class="button inline" type="submit">Simpan Draft GR</button>
                    </div>
                </section>
            </form>
        </main>
    </div>
@endsection
