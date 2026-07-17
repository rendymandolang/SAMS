<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BankReconciliationService
{
    private const MAX_IMPORT_LINES = 20000;

    private const MAX_AMOUNT = 999999999999999.9999;

    /** @return array{import_id:int,reconciliation_id:int,line_count:int,auto_matched:int} */
    public function import(int $companyId, int $bankAccountId, int $userId, UploadedFile $file, ?float $closingBalance = null): array
    {
        $bank = DB::table('accounting_bank_accounts')->where('company_id', $companyId)->where('id', $bankAccountId)->where('is_active', true)->first();
        abort_unless($bank, 404);

        $content = file_get_contents($file->getRealPath());
        if (! is_string($content) || trim($content) === '') {
            throw ValidationException::withMessages(['statement' => 'File rekening koran kosong atau tidak dapat dibaca.']);
        }
        $hash = hash('sha256', $content);
        if (DB::table('bank_statement_imports')->where('bank_account_id', $bankAccountId)->where('file_hash', $hash)->exists()) {
            throw ValidationException::withMessages(['statement' => 'File rekening koran yang sama sudah pernah diimpor.']);
        }

        $rows = $this->parseCsv($content);
        $periodStart = collect($rows)->min('transaction_date');
        $periodEnd = collect($rows)->max('transaction_date');
        $lastBalance = data_get(collect($rows)->whereNotNull('running_balance')->last(), 'running_balance');
        $statementBalance = $closingBalance ?? $lastBalance;
        if ($statementBalance === null) {
            throw ValidationException::withMessages(['closing_balance' => 'Closing balance wajib diisi jika file tidak memiliki kolom saldo.']);
        }

        return DB::transaction(function () use ($companyId, $bankAccountId, $userId, $file, $hash, $rows, $periodStart, $periodEnd, $statementBalance): array {
            DB::table('accounting_bank_accounts')->where('company_id', $companyId)->where('id', $bankAccountId)->lockForUpdate()->firstOrFail();
            $importId = DB::table('bank_statement_imports')->insertGetId([
                'company_id' => $companyId, 'bank_account_id' => $bankAccountId,
                'original_filename' => Str::limit($file->getClientOriginalName(), 255, ''), 'file_hash' => $hash,
                'period_start' => $periodStart, 'period_end' => $periodEnd, 'closing_balance' => $statementBalance,
                'line_count' => count($rows), 'status' => 'imported', 'created_by' => $userId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($rows as $row) {
                DB::table('bank_statement_lines')->insert($row + [
                    'bank_statement_import_id' => $importId, 'bank_account_id' => $bankAccountId,
                    'status' => 'unmatched', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $bookBalance = $this->bookBalance($companyId, $bankAccountId, $periodEnd);
            $reconciliationId = DB::table('bank_reconciliations')->insertGetId([
                'company_id' => $companyId, 'bank_account_id' => $bankAccountId, 'bank_statement_import_id' => $importId,
                'statement_date' => $periodEnd, 'statement_balance' => $statementBalance, 'book_balance' => $bookBalance,
                'difference' => round((float) $statementBalance - $bookBalance, 4), 'status' => 'draft',
                'created_at' => now(), 'updated_at' => now(),
            ]);

            return ['import_id' => $importId, 'reconciliation_id' => $reconciliationId, 'line_count' => count($rows), 'auto_matched' => $this->autoMatch($companyId, $importId, $userId)];
        });
    }

    public function match(int $companyId, int $lineId, int $journalLineId, int $userId): void
    {
        DB::transaction(function () use ($companyId, $lineId, $journalLineId, $userId): void {
            $line = $this->statementLine($companyId, $lineId, true);
            abort_if($line->reconciliation_status === 'completed', 422, 'Rekonsiliasi yang sudah selesai tidak dapat diubah.');
            abort_if($line->status === 'matched', 422, 'Baris rekening koran sudah dicocokkan.');
            DB::table('journal_entry_lines')->where('id', $journalLineId)->lockForUpdate()->firstOrFail();
            $journalLine = $this->journalCandidate($companyId, (int) $line->bank_account_id, $journalLineId);
            if (! $journalLine || abs(((float) $journalLine->debit - (float) $journalLine->credit) - (float) $line->amount) > .005) {
                throw ValidationException::withMessages(['journal_entry_line_id' => 'Nilai transaksi jurnal tidak sesuai dengan rekening koran.']);
            }
            if (DB::table('bank_statement_lines')->where('matched_journal_entry_line_id', $journalLineId)->where('id', '!=', $lineId)->exists()) {
                throw ValidationException::withMessages(['journal_entry_line_id' => 'Transaksi jurnal ini sudah digunakan pada rekonsiliasi lain.']);
            }
            DB::table('bank_statement_lines')->where('id', $lineId)->update([
                'status' => 'matched', 'matched_journal_entry_line_id' => $journalLineId,
                'resolved_by' => $userId, 'resolved_at' => now(), 'resolution_note' => 'Manual match', 'updated_at' => now(),
            ]);
        });
    }

    public function unmatch(int $companyId, int $lineId): void
    {
        DB::transaction(function () use ($companyId, $lineId): void {
            $line = $this->statementLine($companyId, $lineId, true);
            abort_if($line->reconciliation_status === 'completed', 422, 'Rekonsiliasi yang sudah selesai tidak dapat diubah.');
            DB::table('bank_statement_lines')->where('id', $lineId)->update([
                'status' => 'unmatched', 'matched_journal_entry_line_id' => null, 'resolved_by' => null,
                'resolved_at' => null, 'resolution_note' => null, 'updated_at' => now(),
            ]);
        });
    }

    public function exclude(int $companyId, int $lineId, int $userId, string $reason): void
    {
        DB::transaction(function () use ($companyId, $lineId, $userId, $reason): void {
            $line = $this->statementLine($companyId, $lineId, true);
            abort_if($line->reconciliation_status === 'completed', 422, 'Rekonsiliasi yang sudah selesai tidak dapat diubah.');
            DB::table('bank_statement_lines')->where('id', $lineId)->update([
                'status' => 'excluded', 'matched_journal_entry_line_id' => null, 'resolved_by' => $userId,
                'resolved_at' => now(), 'resolution_note' => $reason, 'updated_at' => now(),
            ]);
        });
    }

    public function complete(int $companyId, int $reconciliationId, int $userId): void
    {
        DB::transaction(function () use ($companyId, $reconciliationId, $userId): void {
            $reconciliation = DB::table('bank_reconciliations')->where('company_id', $companyId)->where('id', $reconciliationId)->lockForUpdate()->firstOrFail();
            abort_if($reconciliation->status === 'completed', 422, 'Rekonsiliasi sudah selesai.');
            $statementLines = DB::table('bank_statement_lines')->where('bank_statement_import_id', $reconciliation->bank_statement_import_id)->lockForUpdate()->get();
            $unresolved = $statementLines->where('status', 'unmatched')->count();
            if ($unresolved > 0) {
                throw ValidationException::withMessages(['reconciliation' => $unresolved.' transaksi masih belum diselesaikan.']);
            }
            $bookBalance = $this->bookBalance($companyId, (int) $reconciliation->bank_account_id, $reconciliation->statement_date);
            $difference = round((float) $reconciliation->statement_balance - $bookBalance, 4);
            if (abs($difference) > .005) {
                throw ValidationException::withMessages(['reconciliation' => 'Rekonsiliasi belum balance. Selisih '.number_format($difference, 2, ',', '.')]);
            }
            DB::table('bank_reconciliations')->where('id', $reconciliation->id)->update([
                'book_balance' => $bookBalance, 'difference' => $difference, 'status' => 'completed',
                'completed_by' => $userId, 'completed_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('bank_statement_imports')->where('id', $reconciliation->bank_statement_import_id)->update(['status' => 'reconciled', 'updated_at' => now()]);
        });
    }

    public function bookBalance(int $companyId, int $bankAccountId, string $throughDate): float
    {
        $glAccountId = DB::table('accounting_bank_accounts')->where('company_id', $companyId)->where('id', $bankAccountId)->value('gl_account_id');
        abort_unless($glAccountId, 404);

        return round((float) DB::table('journal_entry_lines')->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.company_id', $companyId)->where('journal_entries.status', 'posted')
            ->whereDate('journal_entries.journal_date', '<=', $throughDate)->where('journal_entry_lines.gl_account_id', $glAccountId)
            ->selectRaw('COALESCE(SUM(journal_entry_lines.debit - journal_entry_lines.credit), 0) as balance')->value('balance'), 4);
    }

    private function autoMatch(int $companyId, int $importId, int $userId): int
    {
        $matched = 0;
        $lines = DB::table('bank_statement_lines')->where('bank_statement_import_id', $importId)->where('status', 'unmatched')->get();
        foreach ($lines as $line) {
            $from = CarbonImmutable::parse($line->transaction_date)->subDays(3)->toDateString();
            $to = CarbonImmutable::parse($line->transaction_date)->addDays(3)->toDateString();
            $candidates = $this->availableCandidates($companyId, (int) $line->bank_account_id, $from, $to)
                ->filter(fn (object $candidate): bool => abs(((float) $candidate->debit - (float) $candidate->credit) - (float) $line->amount) <= .005);
            if ($candidates->count() === 1) {
                DB::table('bank_statement_lines')->where('id', $line->id)->update([
                    'status' => 'matched', 'matched_journal_entry_line_id' => $candidates->first()->id,
                    'resolved_by' => $userId, 'resolved_at' => now(), 'resolution_note' => 'Auto-matched by amount and date', 'updated_at' => now(),
                ]);
                $matched++;
            }
        }

        return $matched;
    }

    private function availableCandidates(int $companyId, int $bankAccountId, string $from, string $to)
    {
        $glAccountId = DB::table('accounting_bank_accounts')->where('company_id', $companyId)->where('id', $bankAccountId)->value('gl_account_id');

        return DB::table('journal_entry_lines')->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->leftJoin('bank_statement_lines as matched_bank_lines', 'matched_bank_lines.matched_journal_entry_line_id', '=', 'journal_entry_lines.id')
            ->where('journal_entries.company_id', $companyId)->where('journal_entries.status', 'posted')->where('journal_entry_lines.gl_account_id', $glAccountId)
            ->whereBetween('journal_entries.journal_date', [$from, $to])->whereNull('matched_bank_lines.id')
            ->select('journal_entry_lines.*', 'journal_entries.document_number', 'journal_entries.journal_date', 'journal_entries.memo')->get();
    }

    private function journalCandidate(int $companyId, int $bankAccountId, int $journalLineId): ?object
    {
        $glAccountId = DB::table('accounting_bank_accounts')->where('company_id', $companyId)->where('id', $bankAccountId)->value('gl_account_id');

        return DB::table('journal_entry_lines')->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.company_id', $companyId)->where('journal_entries.status', 'posted')
            ->where('journal_entry_lines.gl_account_id', $glAccountId)->where('journal_entry_lines.id', $journalLineId)
            ->select('journal_entry_lines.*', 'journal_entries.document_number', 'journal_entries.journal_date')->first();
    }

    private function statementLine(int $companyId, int $lineId, bool $lock = false): object
    {
        $query = DB::table('bank_statement_lines')->join('bank_statement_imports', 'bank_statement_imports.id', '=', 'bank_statement_lines.bank_statement_import_id')
            ->join('bank_reconciliations', 'bank_reconciliations.bank_statement_import_id', '=', 'bank_statement_imports.id')
            ->where('bank_statement_imports.company_id', $companyId)->where('bank_statement_lines.id', $lineId)
            ->select('bank_statement_lines.*', 'bank_reconciliations.status as reconciliation_status');
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }

    /** @return array<int, array<string, mixed>> */
    private function parseCsv(string $content): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        $firstLine = strtok($content, "\r\n") ?: '';
        $delimiter = collect([',', ';', "\t"])->sortByDesc(fn (string $candidate): int => substr_count($firstLine, $candidate))->first();
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);
        $headers = fgetcsv($handle, 0, $delimiter);
        if (! is_array($headers)) {
            throw ValidationException::withMessages(['statement' => 'Header CSV tidak valid.']);
        }
        $normalized = array_map(fn (string $header): string => trim(preg_replace('/[^a-z0-9]+/', '_', Str::lower(Str::ascii($header))), '_'), $headers);
        $columns = $this->mapColumns($normalized);
        $rows = [];
        $rowNumber = 1;
        while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNumber++;
            if (count(array_filter($values, fn ($value): bool => trim((string) $value) !== '')) === 0) {
                continue;
            }
            $get = fn (string $column): ?string => isset($columns[$column]) ? trim((string) ($values[$columns[$column]] ?? '')) : null;
            try {
                $date = $this->parseDate((string) $get('date'));
                $amount = isset($columns['amount'])
                    ? $this->parseNumber((string) $get('amount'))
                    : $this->parseNumber((string) $get('credit')) - $this->parseNumber((string) $get('debit'));
                if (abs($amount) < .00005) {
                    throw new \InvalidArgumentException('Nominal tidak boleh nol.');
                }
                if (! is_finite($amount) || abs($amount) > self::MAX_AMOUNT) {
                    throw new \InvalidArgumentException('Nominal melampaui batas sistem.');
                }
                $rows[] = [
                    'transaction_date' => $date, 'value_date' => $get('value_date') ? $this->parseDate((string) $get('value_date')) : null,
                    'reference' => Str::limit($get('reference') ?: '', 150, '') ?: null,
                    'description' => Str::limit($get('description') ?: 'Bank transaction', 2000, ''), 'amount' => round($amount, 4),
                    'running_balance' => $get('balance') !== null && $get('balance') !== '' ? round($this->parseNumber((string) $get('balance')), 4) : null,
                ];
                if (count($rows) > self::MAX_IMPORT_LINES) {
                    throw ValidationException::withMessages(['statement' => 'Maksimal '.number_format(self::MAX_IMPORT_LINES).' transaksi per file.']);
                }
            } catch (\Throwable $exception) {
                if ($exception instanceof ValidationException) {
                    throw $exception;
                }
                throw ValidationException::withMessages(['statement' => 'Baris '.$rowNumber.' tidak valid: '.$exception->getMessage()]);
            }
        }
        fclose($handle);
        if ($rows === []) {
            throw ValidationException::withMessages(['statement' => 'CSV tidak memiliki transaksi yang dapat diimpor.']);
        }

        return $rows;
    }

    /** @param array<int, string> $headers @return array<string, int> */
    private function mapColumns(array $headers): array
    {
        $aliases = [
            'date' => ['date', 'tanggal', 'transaction_date', 'tanggal_transaksi'], 'value_date' => ['value_date', 'tanggal_valuta'],
            'reference' => ['reference', 'referensi', 'ref', 'no_referensi'], 'description' => ['description', 'keterangan', 'narrative', 'deskripsi'],
            'amount' => ['amount', 'jumlah', 'nominal'], 'debit' => ['debit', 'debet'], 'credit' => ['credit', 'kredit'],
            'balance' => ['balance', 'saldo', 'running_balance'],
        ];
        $mapped = [];
        foreach ($aliases as $target => $names) {
            foreach ($names as $name) {
                $index = array_search($name, $headers, true);
                if ($index !== false) {
                    $mapped[$target] = $index;
                    break;
                }
            }
        }
        if (! isset($mapped['date']) || (! isset($mapped['amount']) && (! isset($mapped['debit']) || ! isset($mapped['credit'])))) {
            throw ValidationException::withMessages(['statement' => 'CSV wajib memiliki kolom date/tanggal dan amount, atau pasangan debit-credit.']);
        }

        return $mapped;
    }

    private function parseDate(string $value): string
    {
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'm/d/Y'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat('!'.$format, trim($value));
                if ($date->format($format) === trim($value)) {
                    return $date->toDateString();
                }
            } catch (\Throwable) {
                // Try the next explicitly supported format.
            }
        }
        throw new \InvalidArgumentException('Format tanggal tidak dikenali.');
    }

    private function parseNumber(string $value): float
    {
        $clean = preg_replace('/[^0-9,\.\-]/', '', trim($value)) ?? '';
        if ($clean === '' || $clean === '-') {
            return 0.0;
        }
        $commaCount = substr_count($clean, ',');
        $dotCount = substr_count($clean, '.');
        if ($commaCount > 0 && $dotCount > 0) {
            $decimal = strrpos($clean, ',') > strrpos($clean, '.') ? ',' : '.';
            $thousand = $decimal === ',' ? '.' : ',';
            $clean = str_replace($thousand, '', $clean);
            $clean = str_replace($decimal, '.', $clean);
        } elseif ($commaCount === 1 || $dotCount === 1) {
            $separator = $commaCount === 1 ? ',' : '.';
            $digitsAfter = strlen($clean) - strrpos($clean, $separator) - 1;
            $clean = in_array($digitsAfter, [1, 2, 4], true) ? str_replace($separator, '.', $clean) : str_replace($separator, '', $clean);
        } else {
            $clean = str_replace([',', '.'], '', $clean);
        }
        if (! is_numeric($clean)) {
            throw new \InvalidArgumentException('Nominal tidak dikenali.');
        }

        return (float) $clean;
    }
}
