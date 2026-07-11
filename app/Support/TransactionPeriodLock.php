<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionPeriodLock
{
    public const MODULES = ['procurement', 'inventory'];

    public static function ensureOpen(int $companyId, string $module, CarbonInterface|string $date): void
    {
        $date = $date instanceof CarbonInterface ? $date->toDateString() : substr($date, 0, 10);

        $lock = DB::table('transaction_period_locks')
            ->where('company_id', $companyId)
            ->where('module', $module)
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date)
            ->first();

        if ($lock) {
            throw ValidationException::withMessages([
                'period' => "Periode {$module} tanggal {$date} sudah dikunci: {$lock->reason}",
            ]);
        }
    }
}
