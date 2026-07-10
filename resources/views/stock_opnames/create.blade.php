@extends('layouts.app', ['title' => 'Buat Stock Opname · SAMS'])

@section('body')
    <div class="app-shell">
        @include('partials.sidebar')

        <main class="main">
            <header class="topbar">
                <div>
                    <p class="eyebrow">Stock Opname</p>
                    <h1>Buat Stock Opname</h1>
                </div>

                <a class="button secondary inline" href="{{ route('stock-opnames.index') }}">Kembali</a>
            </header>

            @if ($errors->any())
                <div class="error">{{ $errors->first() }}</div>
            @endif

            <section class="card" style="margin-bottom:18px;">
                <h2>Pilih Gudang</h2>
                <form method="GET" action="{{ route('stock-opnames.create') }}">
                    <div class="form-grid">
                        <label class="field">
                            <span class="label">Gudang / Lokasi</span>
                            <select class="input" name="storage_location_id" onchange="this.form.submit()">
                                @foreach ($locations as $location)
                                    <option value="{{ $location->id }}" @selected((int) $selectedLocationId === (int) $location->id)>{{ $location->code }} · {{ $location->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                </form>
            </section>

            <form method="POST" action="{{ route('stock-opnames.store') }}">
                @csrf
                <input type="hidden" name="storage_location_id" value="{{ $selectedLocationId }}">

                <section class="card" style="margin-bottom:18px;">
                    <h2>Informasi Opname</h2>
                    <div class="form-grid">
                        <label class="field">
                            <span class="label">Tanggal Hitung *</span>
                            <input class="input" name="count_date" type="date" value="{{ old('count_date', now()->format('Y-m-d')) }}" required>
                        </label>

                        <label class="field full">
                            <span class="label">Catatan</span>
                            <textarea class="input" name="notes">{{ old('notes') }}</textarea>
                        </label>
                    </div>
                </section>

                <section class="card">
                    <div class="toolbar">
                        <div>
                            <h2 style="margin-bottom:6px;">Item Stok {{ $selectedLocation ? $selectedLocation->code : '' }}</h2>
                            <p class="muted" style="margin:0;">Quantity sistem akan disimpan sebagai acuan saat draft dibuat.</p>
                        </div>
                    </div>

                    <div class="table-wrap">
                        <table>
                            <thead>
                            <tr>
                                <th>Item</th>
                                <th>Qty Sistem</th>
                                <th>Satuan</th>
                                <th>Average Cost</th>
                                <th>Qty Fisik</th>
                                <th>Catatan</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse ($balances as $index => $balance)
                                <tr>
                                    <td>
                                        <strong>{{ $balance->sku }}</strong><br>
                                        <span class="muted">{{ $balance->item_name }}</span>
                                        <input type="hidden" name="items[{{ $index }}][item_id]" value="{{ $balance->item_id }}">
                                    </td>
                                    <td>{{ number_format((float) $balance->quantity_on_hand, 2, ',', '.') }}</td>
                                    <td>{{ $balance->unit_code }}</td>
                                    <td>Rp {{ number_format((float) $balance->average_cost, 0, ',', '.') }}</td>
                                    <td>
                                        <input class="input" name="items[{{ $index }}][counted_quantity]" type="number" min="0" step="0.0001" value="{{ old("items.$index.counted_quantity", (float) $balance->quantity_on_hand) }}">
                                    </td>
                                    <td><input class="input" name="items[{{ $index }}][notes]" type="text" value="{{ old("items.$index.notes") }}"></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6">
                                        <div class="empty-state">Gudang ini belum punya saldo stok. Posting Goods Receipt dulu sebelum Stock Opname.</div>
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:24px;">
                        <a class="button secondary inline" href="{{ route('stock-opnames.index') }}">Batal</a>
                        <button class="button inline" type="submit" @disabled($balances->isEmpty())>Simpan Draft Opname</button>
                    </div>
                </section>
            </form>
        </main>
    </div>
@endsection
