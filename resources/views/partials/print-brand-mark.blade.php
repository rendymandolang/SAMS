@php
    $printColor = static fn ($value, $fallback) => is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', $value) ? strtoupper($value) : $fallback;
    $printPrimary = $printColor($company?->primary_color ?? null, '#5967D8');
    $printAccent = $printColor($company?->accent_color ?? null, '#2F9D8F');
@endphp
<div class="brand-mark" style="background:linear-gradient(145deg,{{ $printPrimary }},{{ $printAccent }});">
    @if (filled($company?->logo_path))
        <img src="{{ url('storage/'.$company->logo_path) }}" alt="{{ $company->name }}" style="width:100%;height:100%;object-fit:contain;border-radius:inherit;background:#fff;padding:3px;">
    @else
        {{ mb_strtoupper(mb_substr($company?->name ?: 'SAMS', 0, 1)) }}
    @endif
</div>
