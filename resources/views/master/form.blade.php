@extends('layouts.app', ['title' => ($row ? 'Edit ' : 'Tambah ').$config['title'].' · SAMS'])

@section('body')
    <div class="app-shell">
        @include('partials.sidebar')

        <main class="main">
            <header class="topbar">
                <div>
                    <p class="eyebrow">Master Data</p>
                    <h1>{{ $row ? 'Edit' : 'Tambah' }} {{ $config['title'] }}</h1>
                </div>

                <a class="button secondary inline" href="{{ route('master.index', $master) }}">Kembali</a>
            </header>

            @if ($errors->any())
                <div class="error">
                    {{ $errors->first() }}
                </div>
            @endif

            <section class="card">
                <form method="POST" action="{{ $row ? route('master.update', [$master, $row->id]) : route('master.store', $master) }}">
                    @csrf
                    @if ($row)
                        @method('PUT')
                    @endif

                    <div class="form-grid">
                        @foreach ($config['fields'] as $field => $fieldConfig)
                            @php
                                $value = old($field, $row->{$field} ?? ($fieldConfig['default'] ?? null));
                                $type = $fieldConfig['type'];
                            @endphp

                            <label class="field {{ $type === 'textarea' ? 'full' : '' }}">
                                <span class="label">{{ $fieldConfig['label'] }}{{ ($fieldConfig['required'] ?? false) ? ' *' : '' }}</span>

                                @if ($type === 'textarea')
                                    <textarea class="input" name="{{ $field }}">{{ $value }}</textarea>
                                @elseif ($type === 'select')
                                    <select class="input" name="{{ $field }}" {{ ($fieldConfig['required'] ?? false) ? 'required' : '' }}>
                                        @if (! ($fieldConfig['required'] ?? false))
                                            <option value="">- Tidak dipilih -</option>
                                        @endif
                                        @foreach (($fieldConfig['options'] ?? $options[$fieldConfig['source'] ?? ''] ?? []) as $optionValue => $optionLabel)
                                            <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ $optionLabel }}</option>
                                        @endforeach
                                    </select>
                                @elseif ($type === 'checkbox')
                                    <span style="display:flex;align-items:center;gap:10px;height:48px;">
                                        <input name="{{ $field }}" type="checkbox" value="1" @checked((bool) $value)>
                                        <span class="muted">Aktifkan pilihan ini</span>
                                    </span>
                                @else
                                    <input class="input" name="{{ $field }}" type="{{ $type }}" value="{{ $value }}" {{ ($fieldConfig['required'] ?? false) ? 'required' : '' }} step="{{ $type === 'number' ? '0.0001' : '' }}">
                                @endif
                            </label>
                        @endforeach
                    </div>

                    <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:24px;">
                        <a class="button secondary inline" href="{{ route('master.index', $master) }}">Batal</a>
                        <button class="button inline" type="submit">Simpan</button>
                    </div>
                </form>
            </section>
        </main>
    </div>
@endsection
