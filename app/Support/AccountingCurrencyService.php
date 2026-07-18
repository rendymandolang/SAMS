<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountingCurrencyService
{
    public function rate(int $companyId, string $currency, string $date): float
    {
        $base = strtoupper((string) DB::table('companies')->where('id', $companyId)->value('currency'));
        $currency = strtoupper($currency);
        if ($currency === $base) {
            return 1.0;
        }
        $rate = DB::table('accounting_exchange_rates')->where('company_id', $companyId)->where('currency', $currency)->where('rate_date', '<=', $date)->orderByDesc('rate_date')->value('rate_to_base');
        if (! $rate || (float) $rate <= 0) {
            throw ValidationException::withMessages(['currency' => "Kurs {$currency} ke {$base} belum tersedia untuk {$date}."]);
        }

        return (float) $rate;
    }

    public function toBase(float $foreignAmount, float $rate): float
    {
        return round($foreignAmount * $rate, 4);
    }

    public function revalue(int $companyId, string $currency, string $date, int $userId): int
    {
        return DB::transaction(function () use ($companyId, $currency, $date, $userId): int {
            $currency = strtoupper($currency);
            abort_if(DB::table('accounting_fx_revaluations')->where('company_id', $companyId)->where('currency', $currency)->where('revaluation_date', $date)->exists(), 422, 'Revaluation mata uang dan tanggal ini sudah diposting.');
            TransactionPeriodLock::ensureOpen($companyId, 'accounting', $date);
            $rate = $this->rate($companyId, $currency, $date);
            $settings = DB::table('accounting_settings')->where('company_id', $companyId)->first();
            if (! $settings?->unrealized_fx_gain_account_id || ! $settings?->unrealized_fx_loss_account_id) {
                throw ValidationException::withMessages(['currency' => 'Unrealized FX gain/loss accounts belum dikonfigurasi.']);
            }
            $lines = [];
            $gain = 0.0;
            $loss = 0.0;
            $lineNumber = 1;
            $ap = DB::table('ap_invoices')->where('company_id', $companyId)->where('currency', $currency)->whereIn('status', ['posted', 'partially_paid'])->lockForUpdate()->get();
            foreach ($ap as $invoice) {
                $target = $this->toBase((float) $invoice->foreign_outstanding_amount, $rate);
                $difference = round($target - (float) $invoice->carrying_amount, 4);
                if (abs($difference) > .005) {
                    $lines[] = ['journal_entry_id' => 0, 'gl_account_id' => $invoice->ap_account_id, 'department_id' => null, 'description' => 'AP revaluation · '.$invoice->document_number, 'debit' => max(-$difference, 0), 'credit' => max($difference, 0), 'line_number' => $lineNumber++, 'created_at' => now(), 'updated_at' => now()];
                    $difference > 0 ? $loss += $difference : $gain += -$difference;
                    DB::table('ap_invoices')->where('id', $invoice->id)->update(['carrying_amount' => $target, 'outstanding_amount' => $target, 'updated_at' => now()]);
                }
            }
            $ar = DB::table('ar_invoices')->where('company_id', $companyId)->where('currency', $currency)->whereIn('status', ['posted', 'partially_received'])->lockForUpdate()->get();
            foreach ($ar as $invoice) {
                $target = $this->toBase((float) $invoice->foreign_outstanding_amount, $rate);
                $difference = round($target - (float) $invoice->carrying_amount, 4);
                if (abs($difference) > .005) {
                    $lines[] = ['journal_entry_id' => 0, 'gl_account_id' => $invoice->ar_account_id, 'department_id' => null, 'description' => 'AR revaluation · '.$invoice->document_number, 'debit' => max($difference, 0), 'credit' => max(-$difference, 0), 'line_number' => $lineNumber++, 'created_at' => now(), 'updated_at' => now()];
                    $difference > 0 ? $gain += $difference : $loss += -$difference;
                    DB::table('ar_invoices')->where('id', $invoice->id)->update(['carrying_amount' => $target, 'outstanding_amount' => $target, 'updated_at' => now()]);
                }
            }
            if ($lines === []) {
                throw ValidationException::withMessages(['currency' => 'Tidak ada outstanding balance yang memerlukan revaluation.']);
            }
            if ($loss > .005) {
                $lines[] = ['journal_entry_id' => 0, 'gl_account_id' => $settings->unrealized_fx_loss_account_id, 'department_id' => null, 'description' => 'Unrealized FX loss · '.$currency, 'debit' => $loss, 'credit' => 0, 'line_number' => $lineNumber++, 'created_at' => now(), 'updated_at' => now()];
            }
            if ($gain > .005) {
                $lines[] = ['journal_entry_id' => 0, 'gl_account_id' => $settings->unrealized_fx_gain_account_id, 'department_id' => null, 'description' => 'Unrealized FX gain · '.$currency, 'debit' => 0, 'credit' => $gain, 'line_number' => $lineNumber, 'created_at' => now(), 'updated_at' => now()];
            }
            $debit = round((float) collect($lines)->sum('debit'), 4);
            $credit = round((float) collect($lines)->sum('credit'), 4);
            abort_if(abs($debit - $credit) > .005, 500, 'FX revaluation journal tidak balance.');
            $journalId = DB::table('journal_entries')->insertGetId(['company_id' => $companyId, 'branch_id' => null, 'document_number' => 'FXR-'.str_replace('-', '', $date).'-'.$currency, 'journal_date' => $date, 'source_type' => 'fx_revaluation', 'status' => 'posted', 'is_adjustment' => true, 'memo' => 'Period-end FX revaluation · '.$currency, 'total_debit' => $debit, 'total_credit' => $credit, 'created_by' => $userId, 'posted_by' => $userId, 'posted_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            foreach ($lines as &$line) {
                $line['journal_entry_id'] = $journalId;
            }
            DB::table('journal_entry_lines')->insert($lines);

            return DB::table('accounting_fx_revaluations')->insertGetId(['company_id' => $companyId, 'revaluation_date' => $date, 'currency' => $currency, 'exchange_rate' => $rate, 'net_adjustment' => $gain - $loss, 'journal_entry_id' => $journalId, 'created_by' => $userId, 'created_at' => now(), 'updated_at' => now()]);
        });
    }
}
