@extends('layouts.app', ['title' => __('settings.title').' · SAMS'])

@php
    $safeColor = static function (string $field, string $fallback): string {
        $value = (string) old($field, $fallback);

        return preg_match('/\A#[0-9A-Fa-f]{6}\z/', $value) ? strtoupper($value) : $fallback;
    };

    $primaryColor = $safeColor('primary_color', $company->primary_color ?? '#5967D8');
    $sidebarColor = $safeColor('sidebar_color', $company->sidebar_color ?? '#182335');
    $accentColor = $safeColor('accent_color', $company->accent_color ?? '#2F9D8F');
@endphp

@section('body')
    <div class="app-shell">
        @include('partials.sidebar')

        <main class="main">
            <header class="topbar">
                <div>
                    <p class="eyebrow">{{ __('settings.eyebrows.workspace') }}</p>
                    <h1>{{ __('settings.title') }}</h1>
                    <p class="muted" style="margin:8px 0 0;">{{ __('settings.subtitle') }}</p>
                </div>
            </header>

            @if (session('status'))
                <div class="notice">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="error">
                    <strong>{{ __('settings.errors.not_saved') }}</strong>
                    <div style="margin-top:6px;">{{ $errors->first() }}</div>
                </div>
            @endif

            <form method="POST" action="{{ route('settings.company.update') }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="settings-layout">
                    <div class="settings-main">
                        <section class="card settings-section">
                            <div class="settings-heading">
                                <div class="settings-icon">ID</div>
                                <div>
                                    <p class="eyebrow">{{ __('settings.eyebrows.business_profile') }}</p>
                                    <h2>{{ __('settings.sections.company_identity') }}</h2>
                                    <p class="muted">{{ __('settings.descriptions.identity') }}</p>
                                </div>
                            </div>

                            <div class="form-grid">
                                <label class="field">
                                    <span class="label">{{ __('settings.fields.display_name') }} *</span>
                                    <input class="input" name="name" type="text" value="{{ old('name', $company->name) }}" maxlength="255" required>
                                    <span class="settings-help">{{ __('settings.descriptions.display_name') }}</span>
                                </label>

                                <label class="field">
                                    <span class="label">{{ __('settings.fields.entity_code') }}</span>
                                    <input class="input" type="text" value="{{ $company->code }}" disabled>
                                    <span class="settings-help">{{ __('settings.help.entity_code') }}</span>
                                </label>

                                <label class="field full">
                                    <span class="label">{{ __('settings.fields.legal_name') }}</span>
                                    <input class="input" name="legal_name" type="text" value="{{ old('legal_name', $company->legal_name) }}" maxlength="255">
                                </label>

                                <label class="field">
                                    <span class="label">{{ __('settings.fields.tax_number') }}</span>
                                    <input class="input" name="tax_number" type="text" value="{{ old('tax_number', $company->tax_number) }}" maxlength="100">
                                </label>

                                <label class="field">
                                    <span class="label">{{ __('settings.fields.phone') }}</span>
                                    <input class="input" name="phone" type="tel" value="{{ old('phone', $company->phone) }}" maxlength="50">
                                </label>

                                <label class="field full">
                                    <span class="label">{{ __('settings.fields.address') }}</span>
                                    <textarea class="input" name="address" maxlength="1000" rows="3">{{ old('address', $company->address) }}</textarea>
                                </label>

                                <label class="field full">
                                    <span class="label">{{ __('settings.fields.email') }}</span>
                                    <input class="input" name="email" type="email" value="{{ old('email', $company->email) }}" maxlength="255">
                                </label>
                            </div>
                        </section>

                        <section class="card settings-section">
                            <div class="settings-heading">
                                <div class="settings-icon">A</div>
                                <div>
                                    <p class="eyebrow">{{ __('settings.eyebrows.regional') }}</p>
                                    <h2>{{ __('settings.sections.localization') }}</h2>
                                    <p class="muted">{{ __('settings.descriptions.regional') }}</p>
                                </div>
                            </div>

                            <div class="form-grid">
                                <label class="field">
                                    <span class="label">{{ __('settings.fields.language') }} *</span>
                                    <select class="input" name="locale" required>
                                        @foreach ($languages as $value => $label)
                                            <option value="{{ $value }}" @selected(old('locale', $company->locale) === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </label>

                                <label class="field">
                                    <span class="label">{{ __('settings.fields.timezone') }} *</span>
                                    <select class="input" name="timezone" required>
                                        @unless (array_key_exists(old('timezone', $company->timezone), $timezones))
                                            <option value="{{ old('timezone', $company->timezone) }}" selected>{{ old('timezone', $company->timezone) }}</option>
                                        @endunless
                                        @foreach ($timezones as $value => $label)
                                            <option value="{{ $value }}" @selected(old('timezone', $company->timezone) === $value)>{{ $label }} - {{ $value }}</option>
                                        @endforeach
                                    </select>
                                </label>

                                <label class="field">
                                    <span class="label">{{ __('settings.fields.currency') }} *</span>
                                    <select class="input" name="currency" required>
                                        @foreach ($currencies as $value => $label)
                                            <option value="{{ $value }}" @selected(old('currency', $company->currency) === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </label>

                                <label class="field">
                                    <span class="label">{{ __('settings.fields.date_format') }} *</span>
                                    <select class="input" name="date_format" required>
                                        @foreach ($dateFormats as $value => $label)
                                            <option value="{{ $value }}" @selected(old('date_format', $company->date_format) === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </label>

                                <label class="field">
                                    <span class="label">{{ __('settings.fields.time_format') }} *</span>
                                    <select class="input" name="time_format" required>
                                        @foreach ($timeFormats as $value => $label)
                                            <option value="{{ $value }}" @selected(old('time_format', $company->time_format) === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            </div>
                        </section>

                        <section class="card settings-section">
                            <div class="settings-heading">
                                <div class="settings-icon">UI</div>
                                <div>
                                    <p class="eyebrow">{{ __('settings.eyebrows.appearance') }}</p>
                                    <h2>{{ __('settings.sections.appearance') }}</h2>
                                    <p class="muted">{{ __('settings.descriptions.appearance') }}</p>
                                </div>
                            </div>

                            <div class="palette-grid" aria-label="Recommended color palettes">
                                @foreach ($palettes as $key => $palette)
                                    <button class="palette-option" type="button"
                                        data-palette-primary="{{ $palette['primary'] }}"
                                        data-palette-sidebar="{{ $palette['sidebar'] }}"
                                        data-palette-accent="{{ $palette['accent'] }}">
                                        <span class="palette-dots" aria-hidden="true">
                                            <span style="background:{{ $palette['primary'] }}"></span>
                                            <span style="background:{{ $palette['sidebar'] }}"></span>
                                            <span style="background:{{ $palette['accent'] }}"></span>
                                        </span>
                                        <strong>{{ $palette['label'] }}</strong>
                                    </button>
                                @endforeach
                            </div>

                            <div class="form-grid color-fields">
                                <label class="field">
                                    <span class="label">{{ __('settings.fields.primary_color') }}</span>
                                    <input class="color-input" id="primary_color" name="primary_color" type="color" value="{{ $primaryColor }}" required>
                                </label>

                                <label class="field">
                                    <span class="label">{{ __('settings.fields.sidebar_color') }}</span>
                                    <input class="color-input" id="sidebar_color" name="sidebar_color" type="color" value="{{ $sidebarColor }}" required>
                                </label>

                                <label class="field">
                                    <span class="label">{{ __('settings.fields.accent_color') }}</span>
                                    <input class="color-input" id="accent_color" name="accent_color" type="color" value="{{ $accentColor }}" required>
                                </label>
                            </div>
                        </section>
                    </div>

                    <aside class="settings-aside">
                        <section class="card settings-section sticky-card">
                            <p class="eyebrow">{{ __('settings.eyebrows.brand_asset') }}</p>
                            <h2>{{ __('settings.fields.logo') }}</h2>

                            <div class="logo-preview">
                                @if ($logoUrl)
                                    <img src="{{ $logoUrl }}" alt="Logo {{ $company->name }}">
                                @else
                                    <span>{{ strtoupper(mb_substr($company->name, 0, 2)) }}</span>
                                @endif
                            </div>

                            <label class="field">
                                <span class="label">{{ __('settings.fields.upload_logo') }}</span>
                                <input class="input" name="logo" type="file" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp">
                                <span class="settings-help">{{ __('settings.help.logo_format') }}</span>
                            </label>

                            @if ($logoUrl)
                                <label class="remove-logo">
                                    <input name="remove_logo" type="checkbox" value="1">
                                    <span>{{ __('settings.fields.remove_logo') }}</span>
                                </label>
                            @endif

                            <div class="theme-preview" id="theme-preview" style="--preview-primary:{{ $primaryColor }};--preview-sidebar:{{ $sidebarColor }};--preview-accent:{{ $accentColor }};">
                                <div class="theme-preview-sidebar">
                                    <span></span><span></span><span></span>
                                </div>
                                <div class="theme-preview-content">
                                    <span class="preview-title"></span>
                                    <span class="preview-card"></span>
                                    <span class="preview-button"></span>
                                </div>
                            </div>
                            <p class="settings-help" style="margin:10px 0 0;">{{ __('settings.preview') }}</p>
                        </section>
                    </aside>
                </div>

                <div class="settings-actions">
                    <span class="muted">{{ __('settings.help.audit') }}</span>
                    <button class="button inline" type="submit">{{ __('settings.save') }}</button>
                </div>
            </form>
        </main>
    </div>

    <style>
        .settings-layout { display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:20px;align-items:start; }
        .settings-main { display:grid;gap:20px; }
        .settings-section { padding:26px; }
        .settings-heading { display:grid;grid-template-columns:44px 1fr;gap:14px;align-items:start;margin-bottom:22px; }
        .settings-heading h2,.settings-section h2 { margin-bottom:5px; }
        .settings-heading p:last-child { margin-bottom:0;line-height:1.55; }
        .settings-icon { display:grid;place-items:center;width:44px;height:44px;border-radius:14px;color:var(--primary);background:var(--primary-soft);font-size:13px;font-weight:900;letter-spacing:.04em; }
        .settings-help { color:var(--muted);font-size:12px;line-height:1.5; }
        .sticky-card { position:sticky;top:24px; }
        .logo-preview { display:grid;place-items:center;width:100%;min-height:142px;margin:18px 0;border:1px dashed var(--primary-line);border-radius:18px;background:var(--surface-muted);overflow:hidden; }
        .logo-preview img { max-width:80%;max-height:110px;object-fit:contain; }
        .logo-preview span { display:grid;place-items:center;width:72px;height:72px;border-radius:22px;color:#fff;background:linear-gradient(145deg,var(--primary),var(--accent));font-size:22px;font-weight:900; }
        .remove-logo { display:flex;gap:9px;align-items:center;margin-top:-4px;color:#991b1b;font-size:13px;font-weight:700; }
        .palette-grid { display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin:0 0 20px; }
        .palette-option { display:flex;align-items:center;gap:10px;border:1px solid var(--line);border-radius:14px;padding:11px;background:#fff;color:var(--ink);cursor:pointer;text-align:left;transition:.2s ease; }
        .palette-option:hover,.palette-option:focus-visible { border-color:var(--primary);box-shadow:var(--shadow-soft);outline:none;transform:translateY(-1px); }
        .palette-dots { display:flex; }
        .palette-dots span { width:17px;height:34px; }
        .palette-dots span:first-child { border-radius:9px 0 0 9px; }
        .palette-dots span:last-child { border-radius:0 9px 9px 0; }
        .color-fields { grid-template-columns:repeat(3,minmax(0,1fr)); }
        .color-input { width:100%;height:48px;border:1px solid var(--line);border-radius:14px;padding:5px;background:#fff;cursor:pointer; }
        .theme-preview { display:grid;grid-template-columns:74px 1fr;height:128px;margin-top:22px;border:1px solid var(--line);border-radius:16px;overflow:hidden;background:#f8faff; }
        .theme-preview-sidebar { display:grid;align-content:start;gap:10px;padding:18px 12px;background:var(--preview-sidebar); }
        .theme-preview-sidebar span { display:block;height:6px;border-radius:8px;background:rgba(255,255,255,.25); }
        .theme-preview-sidebar span:first-child { background:var(--preview-accent); }
        .theme-preview-content { display:grid;align-content:start;gap:10px;padding:18px; }
        .preview-title { width:58%;height:9px;border-radius:8px;background:#cbd5e1; }
        .preview-card { height:42px;border:1px solid #e5e7eb;border-radius:9px;background:#fff;box-shadow:0 7px 18px rgba(17,24,39,.06); }
        .preview-button { width:38%;height:14px;border-radius:8px;background:var(--preview-primary); }
        .settings-actions { display:flex;justify-content:space-between;align-items:center;gap:16px;margin-top:20px;padding:18px 20px;border:1px solid var(--line);border-radius:18px;background:rgba(255,255,255,.92);box-shadow:var(--shadow-soft); }
        @media (max-width:1050px) { .settings-layout { grid-template-columns:1fr; }.sticky-card { position:static; }.settings-aside { order:-1; }.logo-preview { min-height:110px; } }
        @media (max-width:720px) { .settings-section { padding:20px; }.palette-grid,.color-fields { grid-template-columns:1fr; }.settings-actions { align-items:stretch;flex-direction:column; }.settings-actions .button { width:100%; } }
    </style>

    <script>
        (() => {
            const preview = document.getElementById('theme-preview');
            const fields = {
                primary: document.getElementById('primary_color'),
                sidebar: document.getElementById('sidebar_color'),
                accent: document.getElementById('accent_color'),
            };
            const refreshPreview = () => {
                preview.style.setProperty('--preview-primary', fields.primary.value);
                preview.style.setProperty('--preview-sidebar', fields.sidebar.value);
                preview.style.setProperty('--preview-accent', fields.accent.value);
            };

            Object.values(fields).forEach((field) => field.addEventListener('input', refreshPreview));
            document.querySelectorAll('[data-palette-primary]').forEach((option) => {
                option.addEventListener('click', () => {
                    fields.primary.value = option.dataset.palettePrimary;
                    fields.sidebar.value = option.dataset.paletteSidebar;
                    fields.accent.value = option.dataset.paletteAccent;
                    refreshPreview();
                });
            });
        })();
    </script>
@endsection
