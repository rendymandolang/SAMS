@extends('layouts.app', ['title' => $title.' · '.config('supersoft.product_name')])

@section('body')
<main class="auth-shell">
    <section class="auth-card">
        <div class="auth-form">
            <div class="brand" style="margin-bottom:30px;color:var(--ink);"><span class="brand-mark">S</span><span>SuperSoft</span></div>
            <p class="eyebrow">{{ $label }}</p>
            <h1 style="font-size:26px;margin:0 0 10px;">{{ $title }}</h1>
            <p class="muted" style="line-height:1.7;margin-bottom:24px;">{{ $summary }}</p>
            <div style="display:grid;gap:10px;margin-bottom:26px;">
                @foreach ($items as $item)
                    <div class="detail-box" style="font-size:14px;line-height:1.55;">{{ $item }}</div>
                @endforeach
            </div>
            @if ($title === 'Akses & Kemitraan' || $title === 'Pusat Bantuan')
                <a class="button" style="width:100%;justify-content:center;margin-bottom:10px;" href="mailto:rendymandolang@gmail.com">Hubungi Rendy Mandolang</a>
                <a class="button secondary" style="width:100%;justify-content:center;" href="mailto:hello@rendymandolang.my.id">Email Alternatif</a>
            @endif
            <a href="{{ route('login') }}" style="display:block;text-align:center;margin-top:24px;color:var(--primary);font-size:14px;font-weight:700;">Kembali ke login</a>
        </div>
    </section>
    <footer class="auth-footer"><div class="auth-meta">{{ config('supersoft.product_name') }} v{{ config('supersoft.version') }} · {{ config('supersoft.company_name') }}</div></footer>
</main>
@endsection
