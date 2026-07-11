<?php

namespace App\Http\Controllers;

use App\Support\AuditLogger;
use App\Support\CompanyContext;
use App\Support\TransactionPeriodLock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TransactionPeriodLockController extends Controller
{
    public function index(CompanyContext $context): View
    {
        $company = $context->current();
        $locks = DB::table('transaction_period_locks')
            ->join('users', 'users.id', '=', 'transaction_period_locks.locked_by')
            ->where('transaction_period_locks.company_id', $company->id)
            ->select('transaction_period_locks.*', 'users.name as locked_by_name')
            ->orderByDesc('starts_on')
            ->get();

        return view('settings.period-locks', compact('company', 'locks'));
    }

    public function store(Request $request, CompanyContext $context): RedirectResponse
    {
        $company = $context->current();
        $validated = $request->validate([
            'module' => ['required', Rule::in(TransactionPeriodLock::MODULES)],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $overlaps = DB::table('transaction_period_locks')
            ->where('company_id', $company->id)
            ->where('module', $validated['module'])
            ->where('starts_on', '<=', $validated['ends_on'])
            ->where('ends_on', '>=', $validated['starts_on'])
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages(['starts_on' => 'Periode ini bertumpang tindih dengan periode yang sudah dikunci.']);
        }

        $id = DB::table('transaction_period_locks')->insertGetId([
            'company_id' => $company->id,
            'module' => $validated['module'],
            'starts_on' => $validated['starts_on'],
            'ends_on' => $validated['ends_on'],
            'reason' => $validated['reason'],
            'locked_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        AuditLogger::log('transaction_period_locked', 'transaction_period_lock', $id, null, $validated, (int) $company->id);

        return back()->with('status', 'Periode transaksi berhasil dikunci.');
    }

    public function destroy(int $periodLock, CompanyContext $context): RedirectResponse
    {
        $company = $context->current();
        $lock = DB::table('transaction_period_locks')->where('company_id', $company->id)->where('id', $periodLock)->first();
        abort_unless($lock, 404);

        DB::table('transaction_period_locks')->where('id', $lock->id)->delete();
        AuditLogger::log('transaction_period_unlocked', 'transaction_period_lock', (int) $lock->id, (array) $lock, null, (int) $company->id);

        return back()->with('status', 'Kunci periode berhasil dibuka.');
    }
}
