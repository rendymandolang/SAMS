@extends('layouts.app', ['title' => 'Login SAMS'])

@section('body')
<main class="auth-shell">
    <section class="auth-card">
        <div class="auth-form">
            <div style="text-align:center;margin-bottom:30px;">
                <div class="brand" style="color:var(--ink);"><span class="brand-mark">S</span><span>SAMS</span></div>
                <p class="muted" style="margin:10px 0 0;font-size:12px;letter-spacing:.04em;">SMART ASSET MANAGEMENT SYSTEM</p>
            </div>

            <h1 style="font-size:24px;text-align:center;margin:0 0 26px;">Masuk ke SAMS</h1>

            @if ($errors->any())
                <div class="error">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf
                <label class="field">
                    <span class="label">Email</span>
                    <input class="input" name="email" type="email" value="{{ old('email') }}" autocomplete="email" placeholder="nama@perusahaan.com" required autofocus>
                </label>
                <label class="field">
                    <span class="label">Password</span>
                    <input class="input" name="password" type="password" autocomplete="current-password" required>
                </label>
                <label style="display:flex;align-items:center;gap:10px;margin:2px 0 22px;color:var(--muted);font-size:14px;">
                    <input name="remember" type="checkbox" value="1"> Ingat saya
                </label>
                <button class="button" type="submit" style="width:100%;justify-content:center;">Masuk</button>
            </form>
        </div>
    </section>

    <footer class="auth-footer">
        <nav class="auth-links" aria-label="Informasi SAMS">
            <a href="{{ route('public.info', 'status') }}">Status</a>
            <a href="{{ route('public.info', 'security') }}">Keamanan</a>
            <a href="{{ route('public.info', 'terms') }}">Ketentuan</a>
            <a href="{{ route('public.info', 'privacy') }}">Privasi</a>
            <a href="{{ route('public.info', 'help') }}">Pusat Bantuan</a>
            <a href="{{ route('public.info', 'access') }}">Minta Akses</a>
        </nav>
        <div class="auth-meta">SAMS v1.0.1 · Dikembangkan oleh Rendy Mandolang, SE., MM., CPA., CHCGM.</div>
        <div class="auth-contact">Peluang investasi: <a href="mailto:rendymandolang@gmail.com">rendymandolang@gmail.com</a> · <a href="mailto:hello@rendymandolang.my.id">hello@rendymandolang.my.id</a></div>
    </footer>
</main>
@endsection
