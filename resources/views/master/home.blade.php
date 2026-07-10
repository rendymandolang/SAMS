@extends('layouts.app', ['title' => 'Master Data · SAMS'])

@section('body')
    <div class="app-shell">
        @include('partials.sidebar')

        <main class="main">
            <header class="topbar">
                <div>
                    <p class="eyebrow">Master Data Center</p>
                    <h1>Master Data</h1>
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

            <section class="card" style="margin-bottom:18px;">
                <p class="eyebrow">Pondasi transaksi</p>
                <h2>Rapikan data dasar sebelum masuk Purchase Request</h2>
                <p class="muted" style="margin-bottom:0;line-height:1.7;">Master data adalah kamus utama sistem. Kalau item, satuan, supplier, dan gudang sudah rapi, modul pembelian dan stok akan jauh lebih enak dibangun.</p>
            </section>

            <section class="grid stats">
                @foreach ($cards as $card)
                    <a class="card" href="{{ route('master.index', $card['key']) }}">
                        <div class="muted">{{ $card['title'] }}</div>
                        <div class="stat-value">{{ number_format($card['count']) }}</div>
                        <p class="muted" style="margin-bottom:14px;line-height:1.55;">{{ $card['description'] }}</p>
                        <span class="badge">Kelola data</span>
                    </a>
                @endforeach
            </section>
        </main>
    </div>
@endsection
