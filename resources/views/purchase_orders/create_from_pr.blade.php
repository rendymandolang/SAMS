@extends('layouts.app', ['title' => 'Buat PO dari '.$header->document_number.' · SAMS'])

@section('body')
    <div class="app-shell">
        @include('partials.sidebar')

        <main class="main">
            <header class="topbar">
                <div>
                    <p class="eyebrow">Purchase Order</p>
                    <h1>Buat PO dari {{ $header->document_number }}</h1>
                </div>

                <a class="button secondary inline" href="{{ route('purchase-requests.show', $header->id) }}">Kembali ke PR</a>
            </header>

            @if ($errors->any())
                <div class="error">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('purchase-orders.store-from-pr', $header->id) }}">
                @csrf

                <section class="card" style="margin-bottom:18px;">
                    <h2>Informasi PO</h2>
                    <div class="form-grid">
                        <label class="field">
                            <span class="label">Supplier *</span>
                            <select class="input" name="supplier_id" required>
                                <option value="">- Pilih supplier -</option>
                                @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}" @selected((string) old('supplier_id') === (string) $supplier->id)>{{ $supplier->code }} · {{ $supplier->name }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="field">
                            <span class="label">Tanggal PO *</span>
                            <input class="input" name="order_date" type="date" value="{{ old('order_date', now()->format('Y-m-d')) }}" required>
                        </label>

                        <label class="field">
                            <span class="label">Tanggal Diharapkan</span>
                            <input class="input" name="expected_date" type="date" value="{{ old('expected_date') }}">
                        </label>

                        <label class="field full">
                            <span class="label">Catatan PO</span>
                            <textarea class="input" name="notes" placeholder="Opsional">{{ old('notes') }}</textarea>
                        </label>
                    </div>
                </section>

                <section class="card">
                    <h2>Item dari PR</h2>
                    <div class="table-wrap">
                        <table>
                            <thead>
                            <tr>
                                <th>SKU</th>
                                <th>Item</th>
                                <th>Qty</th>
                                <th>Satuan</th>
                                <th>Harga</th>
                                <th>Total</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($items as $item)
                                <tr>
                                    <td>{{ $item->sku }}</td>
                                    <td><strong>{{ $item->item_name }}</strong></td>
                                    <td>{{ number_format((float) $item->quantity, 2, ',', '.') }}</td>
                                    <td>{{ $item->unit_code }}</td>
                                    <td>Rp {{ number_format((float) $item->estimated_unit_price, 0, ',', '.') }}</td>
                                    <td>Rp {{ number_format((float) $item->estimated_total, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:24px;">
                        <a class="button secondary inline" href="{{ route('purchase-requests.show', $header->id) }}">Batal</a>
                        <button class="button inline" type="submit">Buat PO Draft</button>
                    </div>
                </section>
            </form>
        </main>
    </div>
@endsection
