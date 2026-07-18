<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountingConsolidationService
{
    public function syncMappings(int $groupId, int $companyId): void
    {
        $group = DB::table('accounting_consolidation_groups')->where('id', $groupId)->firstOrFail();
        abort_unless(DB::table('accounting_consolidation_members')->where('group_id', $groupId)->where('company_id', $companyId)->exists(), 404);
        foreach (DB::table('gl_accounts')->where('company_id', $companyId)->get() as $account) {
            $conflict = DB::table('accounting_consolidation_mappings')->where('group_id', $groupId)->where('consolidation_code', $account->code)->where('account_type', '!=', $account->type)->exists();
            $code = $conflict ? DB::table('companies')->where('id', $companyId)->value('code').'-'.$account->code : $account->code;
            DB::table('accounting_consolidation_mappings')->updateOrInsert(['group_id' => $group->id, 'gl_account_id' => $account->id], ['company_id' => $companyId, 'consolidation_code' => $code, 'consolidation_name' => $account->name, 'account_type' => $account->type, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    /** @param array<int|string, mixed> $rates */
    public function createRun(int $groupId, string $from, string $to, array $rates, int $userId): int
    {
        return DB::transaction(function () use ($groupId, $from, $to, $rates, $userId): int {
            $group = DB::table('accounting_consolidation_groups')->where('id', $groupId)->where('is_active', true)->lockForUpdate()->firstOrFail();
            if (DB::table('accounting_consolidation_runs')->where('group_id', $groupId)->where('period_from', $from)->where('period_to', $to)->exists()) {
                throw ValidationException::withMessages(['period_to' => 'Consolidation run untuk periode ini sudah tersedia.']);
            }
            $members = DB::table('accounting_consolidation_members')->join('companies', 'companies.id', '=', 'accounting_consolidation_members.company_id')->where('group_id', $groupId)->select('accounting_consolidation_members.*', 'companies.currency', 'companies.name as company_name')->get();
            abort_if($members->isEmpty(), 422, 'Consolidation group belum memiliki anggota.');
            foreach ($members as $member) {
                $this->syncMappings($groupId, (int) $member->company_id);
            }
            $runId = DB::table('accounting_consolidation_runs')->insertGetId(['group_id' => $groupId, 'period_from' => $from, 'period_to' => $to, 'status' => 'draft', 'created_by' => $userId, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($members as $member) {
                $rate = strtoupper($member->currency) === strtoupper($group->presentation_currency) ? 1.0 : (float) ($rates[$member->company_id] ?? 0);
                if ($rate <= 0) {
                    throw ValidationException::withMessages(['rates.'.$member->company_id => 'Translation rate wajib untuk '.$member->company_name.'.']);
                }
                DB::table('accounting_consolidation_run_members')->insert(['run_id' => $runId, 'company_id' => $member->company_id, 'source_currency' => $member->currency, 'translation_rate' => $rate, 'created_at' => now(), 'updated_at' => now()]);
                $mappings = DB::table('accounting_consolidation_mappings')->where('group_id', $groupId)->where('company_id', $member->company_id)->get()->keyBy('gl_account_id');
                $source = DB::table('journal_entry_lines')->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')->where('journal_entries.company_id', $member->company_id)->where('journal_entries.status', 'posted')->where('journal_entries.journal_date', '<=', $to)->select('journal_entry_lines.gl_account_id', 'journal_entry_lines.debit', 'journal_entry_lines.credit', 'journal_entries.journal_date')->get()->groupBy('gl_account_id');
                foreach ($source as $accountId => $lines) {
                    $mapping = $mappings->get($accountId);
                    abort_unless($mapping, 422, 'Terdapat GL account yang belum dipetakan.');
                    $periodLines = $lines->where('journal_date', '>=', $from);
                    $debit = round((float) $lines->sum('debit') * $rate, 4);
                    $credit = round((float) $lines->sum('credit') * $rate, 4);
                    $periodDebit = round((float) $periodLines->sum('debit') * $rate, 4);
                    $periodCredit = round((float) $periodLines->sum('credit') * $rate, 4);
                    if (abs($debit) > .005 || abs($credit) > .005) {
                        DB::table('accounting_consolidation_lines')->insert(['run_id' => $runId, 'source_company_id' => $member->company_id, 'consolidation_code' => $mapping->consolidation_code, 'consolidation_name' => $mapping->consolidation_name, 'account_type' => $mapping->account_type, 'description' => $member->company_name, 'debit' => $debit, 'credit' => $credit, 'period_debit' => $periodDebit, 'period_credit' => $periodCredit, 'is_elimination' => false, 'created_at' => now(), 'updated_at' => now()]);
                    }
                }
            }
            $this->refreshTotals($runId);

            return $runId;
        });
    }

    /** @param array<int, array<string, mixed>> $lines */
    public function addElimination(int $runId, array $lines, int $userId): void
    {
        DB::transaction(function () use ($runId, $lines, $userId): void {
            $run = DB::table('accounting_consolidation_runs')->where('id', $runId)->where('status', 'draft')->lockForUpdate()->firstOrFail();
            $debit = round((float) collect($lines)->sum('debit'), 4);
            $credit = round((float) collect($lines)->sum('credit'), 4);
            if ($debit <= 0 || abs($debit - $credit) > .005) {
                throw ValidationException::withMessages(['lines' => 'Elimination entry harus balance.']);
            }
            foreach ($lines as $line) {
                if ((float) ($line['debit'] ?? 0) > 0 && (float) ($line['credit'] ?? 0) > 0) {
                    throw ValidationException::withMessages(['lines' => 'Satu elimination line hanya boleh debit atau credit.']);
                }
                DB::table('accounting_consolidation_lines')->insert(['run_id' => $run->id, 'source_company_id' => null, 'consolidation_code' => $line['code'], 'consolidation_name' => $line['name'], 'account_type' => $line['type'], 'description' => $line['description'] ?? 'Intercompany elimination', 'debit' => $line['debit'] ?? 0, 'credit' => $line['credit'] ?? 0, 'period_debit' => $line['debit'] ?? 0, 'period_credit' => $line['credit'] ?? 0, 'is_elimination' => true, 'created_by' => $userId, 'created_at' => now(), 'updated_at' => now()]);
            }
            $this->refreshTotals($runId);
        });
    }

    public function finalize(int $runId, int $userId): void
    {
        DB::transaction(function () use ($runId, $userId): void {
            $run = DB::table('accounting_consolidation_runs')->where('id', $runId)->where('status', 'draft')->lockForUpdate()->firstOrFail();
            $this->refreshTotals($runId);
            $run = DB::table('accounting_consolidation_runs')->find($run->id);
            abort_if(abs((float) $run->total_debit - (float) $run->total_credit) > .005, 422, 'Consolidated trial balance tidak balance.');
            DB::table('accounting_consolidation_runs')->where('id', $run->id)->update(['status' => 'completed', 'finalized_by' => $userId, 'finalized_at' => now(), 'updated_at' => now()]);
        });
    }

    private function refreshTotals(int $runId): void
    {
        $query = DB::table('accounting_consolidation_lines')->where('run_id', $runId);
        DB::table('accounting_consolidation_runs')->where('id', $runId)->update(['total_debit' => (clone $query)->sum('debit'), 'total_credit' => (clone $query)->sum('credit'), 'updated_at' => now()]);
    }
}
