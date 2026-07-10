@extends('layouts.app', ['title' => 'Login SAMS'])

@section('body')
    <main class="auth-shell">
        <section class="auth-card">
            <div class="auth-hero">
                <div class="brand">
                    <span class="brand-mark">S</span>
                    <span>SAMS</span>
                </div>
                <div style="margin-top: 76px;">
                    <p style="opacity:.75;font-weight:700;letter-spacing:.08em;text-transform:uppercase;">Smart Asset Management System</p>
                    <h1 style="font-size:44px;line-height:1.05;margin-bottom:18px;">Kontrol aset, stok, pembelian, dan budget dalam satu cockpit.</h1>
                    <p style="max-width:430px;opacity:.78;line-height:1.7;">Versi awal ini kita pakai sebagai pondasi lokal. Nanti setelah modul inti matang, baru kita migrasikan ke VPS dengan lebih rapi.</p>
                </div>
            </div>

            <div class="auth-form">
                <div class="brand" style="margin-bottom:34px;color:var(--ink);">
                    <span class="brand-mark">S</span>
                    <span>SAMS</span>
                </div>

                <h2 style="font-size:28px;margin-bottom:8px;">Masuk ke dashboard</h2>
                <p class="muted" style="margin-bottom:28px;">Gunakan akun admin lokal yang sudah disiapkan.</p>

                @if ($errors->any())
                    <div class="error">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('login') }}">
                    @csrf

                    <label class="field">
                        <span class="label">Email</span>
                        <input class="input" name="email" type="email" value="{{ old('email', 'admin@sams.local') }}" autocomplete="email" required autofocus>
                    </label>

                    <label class="field">
                        <span class="label">Password</span>
                        <input class="input" name="password" type="password" autocomplete="current-password" required>
                    </label>

                    <label style="display:flex;align-items:center;gap:10px;margin:2px 0 22px;color:var(--muted);font-size:14px;">
                        <input name="remember" type="checkbox" value="1">
                        Ingat saya di perangkat ini
                    </label>

                    <button class="button" type="submit">Login</button>
                </form>

                <p class="muted" style="margin-top:22px;font-size:14px;">Demo lokal: <strong>admin@sams.local</strong> / <strong>password</strong></p>
            </div>
        </section>
    </main>
@endsection
