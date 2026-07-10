@extends('layouts.app', ['title' => 'Edit '.$header->document_number.' · SAMS'])

@section('body')
    @php
        $lineValues = old('lines');

        if ($lineValues === null) {
            $lineValues = $lines->map(fn ($line) => [
                'item_id' => $line->item_id,
                'budget_line_id' => $line->budget_line_id,
                'quantity' => $line->quantity,
                'estimated_unit_price' => $line->estimated_unit_price,
                'notes' => $line->notes,
            ])->values()->all();
        }

        $lineCount = max(6, count($lineValues) + 2);
    @endphp

    <div class="app-shell">
        @include('partials.sidebar')

        <main class="main">
            <header class="topbar">
                <div>
                    <p class="eyebrow">Purchase Request</p>
                    <h1>Edit {{ $header->document_number }}</h1>
                </div>

                <a class="button secondary inline" href="{{ route('purchase-requests.show', $header->id) }}">Kembali</a>
            </header>

            @if ($errors->any())
                <div class="error">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('purchase-requests.update', $header->id) }}">
                @csrf
                @method('PUT')

                <section class="card" style="margin-bottom:18px;">
                    <h2>Informasi PR</h2>
                    <div class="form-grid">
                        <label class="field">
                            <span class="label">Departemen *</span>
                            <select class="input" name="department_id" required>
                                @foreach ($departments as $department)
                                    <option value="{{ $department->id }}" @selected((int) old('department_id', $header->department_id) === $department->id)>{{ $department->name }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="field">
                            <span class="label">Prioritas *</span>
                            <select class="input" name="priority" required>
                                @foreach (['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('priority', $header->priority) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="field">
                            <span class="label">Tanggal Request *</span>
                            <input class="input" name="request_date" type="date" value="{{ old('request_date', $header->request_date) }}" required>
                        </label>

                        <label class="field">
                            <span class="label">Tanggal Dibutuhkan</span>
                            <input class="input" name="required_date" type="date" value="{{ old('required_date', $header->required_date) }}">
                        </label>

                        <label class="field full">
                            <span class="label">Tujuan / Catatan</span>
                            <textarea class="input" name="purpose">{{ old('purpose', $header->purpose) }}</textarea>
                        </label>
                    </div>
                </section>

                <section class="card">
                    <div class="toolbar">
                        <div>
                            <h2 style="margin-bottom:6px;">Item yang diminta</h2>
                            <p class="muted" style="margin:0;">Kosongkan item pada baris yang tidak dipakai. Perubahan akan mengganti daftar item draft ini.</p>
                        </div>
                    </div>

                    <div class="line-items">
                        @for ($i = 0; $i < $lineCount; $i++)
                            @php
                                $line = $lineValues[$i] ?? [];
                            @endphp
                            <div class="line-card budgeted">
                                <label class="field" style="margin-bottom:0;">
                                    <span class="label">Item</span>
                                    <select class="input" name="lines[{{ $i }}][item_id]">
                                        <option value="">- Pilih item -</option>
                                        @foreach ($items as $item)
                                            <option value="{{ $item->id }}" @selected((string) ($line['item_id'] ?? '') === (string) $item->id)>
                                                {{ $item->sku }} &middot; {{ $item->name }} ({{ $item->unit_code }})
                                            </option>
                                        @endforeach
                                    </select>
                                </label>

                                <label class="field" style="margin-bottom:0;">
                                    <span class="label">Budget</span>
                                    <select class="input" name="lines[{{ $i }}][budget_line_id]">
                                        <option value="">- Tanpa budget -</option>
                                        @foreach ($budgetLines as $budgetLine)
                                            @php
                                                $availableBudget = (float) $budgetLine->allocated_amount - (float) $budgetLine->committed_amount - (float) $budgetLine->actual_amount;
                                            @endphp
                                            <option value="{{ $budgetLine->id }}" @selected((string) ($line['budget_line_id'] ?? '') === (string) $budgetLine->id)>
                                                {{ $budgetLine->department_code }} · {{ $budgetLine->account_code }} - {{ $budgetLine->description }} · Sisa Rp {{ number_format($availableBudget, 0, ',', '.') }}
                                            </option>
                                        @endforeach
                                    </select>
                                </label>

                                <label class="field" style="margin-bottom:0;">
                                    <span class="label">Qty</span>
                                    <input class="input" name="lines[{{ $i }}][quantity]" type="number" step="0.0001" min="0" value="{{ $line['quantity'] ?? '' }}">
                                </label>

                                <label class="field" style="margin-bottom:0;">
                                    <span class="label">Harga Estimasi</span>
                                    <input class="input" name="lines[{{ $i }}][estimated_unit_price]" type="number" step="0.0001" min="0" value="{{ $line['estimated_unit_price'] ?? '' }}">
                                </label>

                                <label class="field" style="margin-bottom:0;">
                                    <span class="label">Catatan</span>
                                    <input class="input" name="lines[{{ $i }}][notes]" type="text" value="{{ $line['notes'] ?? '' }}" placeholder="Opsional">
                                </label>
                            </div>
                        @endfor
                    </div>

                    <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:24px;">
                        <a class="button secondary inline" href="{{ route('purchase-requests.show', $header->id) }}">Batal</a>
                        <button class="button inline" type="submit">Simpan Perubahan</button>
                    </div>
                </section>
            </form>
        </main>
    </div>
@endsection
