@extends('layouts.app', ['title' => 'Tambah Asset - SAMS'])

@section('body')
    <div class="app-shell">
        @include('partials.sidebar')

        <main class="main">
            <header class="topbar">
                <div>
                    <p class="eyebrow">Asset Register</p>
                    <h1>Tambah Asset</h1>
                </div>

                <a class="button secondary inline" href="{{ route('assets.index') }}">Kembali</a>
            </header>

            @if ($errors->any())
                <div class="error">{{ $errors->first() }}</div>
            @endif

            <section class="card">
                <form method="POST" action="{{ route('assets.store') }}">
                    @csrf

                    <div class="form-grid">
                        <label class="field">
                            <span class="label">Nomor Asset</span>
                            <input class="input" name="asset_number" value="{{ old('asset_number') }}" placeholder="{{ $nextAssetNumber }}">
                        </label>

                        <label class="field">
                            <span class="label">Nama Asset</span>
                            <input class="input" name="asset_name" value="{{ old('asset_name') }}" required placeholder="Contoh: Laptop Operations FO-01">
                        </label>

                        <label class="field">
                            <span class="label">Item Asset</span>
                            <select class="input" name="item_id" required>
                                <option value="">Pilih item asset</option>
                                @foreach ($items as $item)
                                    <option value="{{ $item->id }}" @selected((int) old('item_id') === (int) $item->id)>{{ $item->sku }} - {{ $item->name }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="field">
                            <span class="label">Serial Number</span>
                            <input class="input" name="serial_number" value="{{ old('serial_number') }}" placeholder="Opsional">
                        </label>

                        <label class="field">
                            <span class="label">Departemen</span>
                            <select class="input" name="department_id">
                                <option value="">Pilih departemen</option>
                                @foreach ($departments as $department)
                                    <option value="{{ $department->id }}" @selected((int) old('department_id') === (int) $department->id)>{{ $department->code }} - {{ $department->name }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="field">
                            <span class="label">Lokasi</span>
                            <select class="input" name="storage_location_id">
                                <option value="">Pilih lokasi</option>
                                @foreach ($locations as $location)
                                    <option value="{{ $location->id }}" @selected((int) old('storage_location_id') === (int) $location->id)>{{ $location->code }} - {{ $location->name }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="field">
                            <span class="label">Tanggal Perolehan</span>
                            <input class="input" name="acquisition_date" type="date" value="{{ old('acquisition_date', now()->format('Y-m-d')) }}" required>
                        </label>

                        <label class="field">
                            <span class="label">Nilai Perolehan</span>
                            <input class="input" name="acquisition_cost" type="number" min="0" step="0.01" value="{{ old('acquisition_cost', 0) }}" required>
                        </label>

                        <label class="field">
                            <span class="label">Kondisi</span>
                            <select class="input" name="condition" required>
                                @foreach ($conditions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('condition', 'good') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="field">
                            <span class="label">Status</span>
                            <select class="input" name="status" required>
                                @foreach ($statuses as $value => $label)
                                    <option value="{{ $value }}" @selected(old('status', 'active') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="field full">
                            <span class="label">Catatan</span>
                            <textarea class="input" name="notes" placeholder="Catatan kondisi, lokasi detail, atau informasi garansi">{{ old('notes') }}</textarea>
                        </label>
                    </div>

                    <div style="display:flex;justify-content:flex-end;gap:12px;margin-top:12px;">
                        <a class="button secondary inline" href="{{ route('assets.index') }}">Batal</a>
                        <button class="button inline" type="submit">Simpan Asset</button>
                    </div>
                </form>
            </section>
        </main>
    </div>
@endsection
