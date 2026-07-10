@extends('layouts.app', ['title' => 'User Management · SAMS'])

@section('body')
    <div class="app-shell">
        @include('partials.sidebar')

        <main class="main">
            <header class="topbar">
                <div>
                    <p class="eyebrow">Control</p>
                    <h1>User Management</h1>
                </div>

                <a class="button inline" href="{{ route('users.create') }}">+ Tambah User</a>
            </header>

            @if (session('status'))
                <div class="notice">{{ session('status') }}</div>
            @endif

            <section class="card">
                <div class="toolbar">
                    <div>
                        <h2 style="margin-bottom:6px;">Daftar User</h2>
                        <p class="muted" style="margin:0;">Kelola akun, role, status aktif, dan reset password user.</p>
                    </div>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Dibuat</th>
                            <th style="text-align:right;">Aksi</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($users as $user)
                            <tr>
                                <td><strong>{{ $user->name }}</strong></td>
                                <td>{{ $user->email }}</td>
                                <td><span class="status">{{ $roles[$user->role] ?? $user->role }}</span></td>
                                <td>
                                    @if ($user->is_active)
                                        <span class="badge">Aktif</span>
                                    @else
                                        <span class="badge next">Nonaktif</span>
                                    @endif
                                </td>
                                <td>{{ $user->created_at?->format('d M Y') }}</td>
                                <td>
                                    <div class="actions">
                                        <a class="link-action" href="{{ route('users.edit', $user->id) }}">Edit</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($users->hasPages())
                    <div class="pagination">{{ $users->links() }}</div>
                @endif
            </section>
        </main>
    </div>
@endsection
