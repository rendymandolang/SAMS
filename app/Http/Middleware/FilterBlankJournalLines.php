<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Validation\ValidationException;

class FilterBlankJournalLines
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('post') && $request->has('lines')) {
            $lines = collect($request->input('lines', []))
                    ->filter(fn (array $line): bool => filled($line['gl_account_id'] ?? null))
                    ->values()
                    ->all();
            foreach ($lines as $line) {
                $debit = (float) ($line['debit'] ?? 0);
                $credit = (float) ($line['credit'] ?? 0);
                if (($debit > 0 && $credit > 0) || ($debit <= 0 && $credit <= 0)) {
                    throw ValidationException::withMessages(['lines' => 'Setiap baris wajib memiliki salah satu nilai debit atau credit.']);
                }
            }
            $request->merge(['lines' => $lines]);
        }

        return $next($request);
    }
}
