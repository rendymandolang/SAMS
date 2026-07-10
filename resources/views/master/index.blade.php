@extends('layouts.app', ['title' => $config['title'].' · SAMS'])

@section('body')
    <div class="app-shell">
        @include('partials.sidebar')

        <main class="main">
            <header class="topbar">
                <div>
                    <p class="eyebrow">Master Data</p>
                    <h1>{{ $config['title'] }}</h1>
                </div>

                <div class="user-pill">
                    <div>
                        <strong>{{ auth()->user()->name }}</strong>
                        <div class="muted" style="font-size:12px;">{{ str_replace('_', ' ', auth()->user()->role) }}</div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="logout" type="submit">Logout</button>
                    </form>
                </div>
            </header>

            @if (session('status'))
                <div class="notice">{{ session('status') }}</div>
            @endif

            <section class="card">
                <div class="toolbar">
                    <div>
                        <h2 style="margin-bottom:6px;">Daftar {{ $config['title'] }}</h2>
                        <p class="muted" style="margin:0;">{{ $config['description'] }}</p>
                    </div>
                    @if (auth()->user()->hasAnyRole(['super_admin', 'purchasing', 'warehouse']))
                        <a class="button inline" href="{{ route('master.create', $master) }}">+ Tambah</a>
                    @endif
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            @foreach ($config['columns'] as $label)
                                <th>{{ $label }}</th>
                            @endforeach
                            <th style="text-align:right;">Aksi</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                @foreach ($config['columns'] as $field => $label)
                                    <td>
                                        @if ($field === 'allow_negative_stock')
                                            {{ ($row->{$field} ?? false) ? 'Ya' : 'Tidak' }}
                                        @elseif (in_array($field, ['standard_cost'], true))
                                            Rp {{ number_format((float) ($row->{$field} ?? 0), 0, ',', '.') }}
                                        @else
                                            {{ $row->{$field} ?? '-' }}
                                        @endif
                                    </td>
                                @endforeach
                                <td>
                                    @if (auth()->user()->hasAnyRole(['super_admin', 'purchasing', 'warehouse']))
                                        <div class="actions">
                                            <a class="link-action" href="{{ route('master.edit', [$master, $row->id]) }}">Edit</a>
                                            <form method="POST" action="{{ route('master.destroy', [$master, $row->id]) }}" onsubmit="return confirm('Hapus data ini?')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="link-action danger" type="submit">Hapus</button>
                                            </form>
                                        </div>
                                    @else
                                        <span class="muted">View only</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($config['columns']) + 1 }}">
                                    <div class="empty-state">Belum ada data. Klik tombol Tambah untuk membuat data pertama.</div>
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($rows->hasPages())
                    <div class="pagination">{{ $rows->links() }}</div>
                @endif
            </section>
        </main>
    </div>
@endsection
