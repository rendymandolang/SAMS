@extends('layouts.app', ['title' => ($user ? 'Edit User' : 'Tambah User').' · SAMS'])

@section('body')
    <div class="app-shell">
        @include('partials.sidebar')

        <main class="main">
            <header class="topbar">
                <div>
                    <p class="eyebrow">User Management</p>
                    <h1>{{ $user ? 'Edit User' : 'Tambah User' }}</h1>
                </div>

                <a class="button secondary inline" href="{{ route('users.index') }}">Kembali</a>
            </header>

            @if ($errors->any())
                <div class="error">{{ $errors->first() }}</div>
            @endif

            <section class="card">
                <form method="POST" action="{{ $user ? route('users.update', $user->id) : route('users.store') }}">
                    @csrf
                    @if ($user)
                        @method('PUT')
                    @endif

                    <div class="form-grid">
                        <label class="field">
                            <span class="label">Nama *</span>
                            <input class="input" name="name" type="text" value="{{ old('name', $user?->name) }}" required>
                        </label>

                        <label class="field">
                            <span class="label">Email *</span>
                            <input class="input" name="email" type="email" value="{{ old('email', $user?->email) }}" required>
                        </label>

                        <label class="field">
                            <span class="label">Role *</span>
                            <select class="input" name="role" required>
                                @foreach ($roles as $value => $label)
                                    <option value="{{ $value }}" @selected(old('role', $user?->role ?? 'staff') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="field">
                            <span class="label">Status</span>
                            <select class="input" name="is_active">
                                <option value="1" @selected(old('is_active', $user?->is_active ?? true))>Aktif</option>
                                <option value="0" @selected(! (bool) old('is_active', $user?->is_active ?? true)) @disabled($user && (int) $user->id === (int) auth()->id())>Nonaktif</option>
                            </select>
                            @if ($user && (int) $user->id === (int) auth()->id())
                                <span class="muted" style="font-size:12px;">Akun sendiri tidak bisa dinonaktifkan.</span>
                            @endif
                        </label>

                        <label class="field">
                            <span class="label">{{ $user ? 'Password Baru' : 'Password *' }}</span>
                            <input class="input" name="password" type="password" @required(! $user)>
                            @if ($user)
                                <span class="muted" style="font-size:12px;">Kosongkan jika password tidak diganti.</span>
                            @endif
                        </label>

                        <label class="field">
                            <span class="label">Konfirmasi Password{{ $user ? ' Baru' : ' *' }}</span>
                            <input class="input" name="password_confirmation" type="password" @required(! $user)>
                        </label>
                    </div>

                    <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:24px;">
                        <a class="button secondary inline" href="{{ route('users.index') }}">Batal</a>
                        <button class="button inline" type="submit">Simpan</button>
                    </div>
                </form>
            </section>
        </main>
    </div>
@endsection
