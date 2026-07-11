@extends('layouts.app')

@section('title', __('navigation.items.period_locks'))

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">{{ $company->name }}</p>
            <h1>{{ __('navigation.items.period_locks') }}</h1>
            <p>Tutup periode agar transaksi bertanggal di dalam periode tersebut tidak dapat diproses ulang atau diposting.</p>
        </div>
    </div>

    <div class="grid two-columns">
        <section class="card">
            <h2>Kunci periode baru</h2>
            <form method="POST" action="{{ route('settings.period-locks.store') }}" class="form-grid">
                @csrf
                <label>Modul
                    <select name="module" required>
                        <option value="procurement">Procurement</option>
                        <option value="inventory">Inventory</option>
                    </select>
                </label>
                <label>Mulai <input type="date" name="starts_on" value="{{ old('starts_on') }}" required></label>
                <label>Selesai <input type="date" name="ends_on" value="{{ old('ends_on') }}" required></label>
                <label class="full">Alasan <textarea name="reason" rows="3" required>{{ old('reason') }}</textarea></label>
                <div class="full"><button class="button primary" type="submit">Kunci periode</button></div>
            </form>
        </section>

        <section class="card">
            <h2>Periode terkunci</h2>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Modul</th><th>Periode</th><th>Alasan</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($locks as $lock)
                        <tr>
                            <td>{{ str($lock->module)->title() }}</td>
                            <td>{{ $lock->starts_on }} – {{ $lock->ends_on }}</td>
                            <td>{{ $lock->reason }}<br><small>{{ $lock->locked_by_name }}</small></td>
                            <td><form method="POST" action="{{ route('settings.period-locks.destroy', $lock->id) }}">@csrf @method('DELETE')<button class="button secondary" type="submit">Buka</button></form></td>
                        </tr>
                    @empty
                        <tr><td colspan="4">Belum ada periode yang dikunci.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
