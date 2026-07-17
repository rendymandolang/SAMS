<?php

namespace App\Http\Controllers;

use App\Support\AuditLogger;
use App\Support\CompanyContext;
use App\Support\TransactionPeriodLock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccountingController extends Controller
{
    public function index(CompanyContext $c): View
    {
        $entries = DB::table('journal_entries')->where('company_id', $c->id())->orderByDesc('journal_date')->orderByDesc('id')->limit(50)->get();

        return view('accounting.index', ['company' => $c->current(), 'entries' => $entries, 'accounts' => DB::table('gl_accounts')->where('company_id', $c->id())->orderBy('code')->get()]);
    }

    public function create(CompanyContext $c): View
    {
        return view('accounting.create', ['company' => $c->current(), 'accounts' => DB::table('gl_accounts')->where('company_id', $c->id())->where('is_active', true)->where('allow_posting', true)->orderBy('code')->get(), 'departments' => DB::table('departments')->where('company_id', $c->id())->orderBy('name')->get()]);
    }

    public function storeAccount(Request $request, CompanyContext $companyContext): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9.\-]+$/'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['asset', 'liability', 'equity', 'revenue', 'expense'])],
            'normal_balance' => ['required', Rule::in(['debit', 'credit'])],
            'allow_posting' => ['nullable', 'boolean'],
            'confirm_similar' => ['nullable', 'boolean'],
        ]);

        $companyId = $companyContext->id();
        $code = Str::upper(trim($validated['code']));
        $name = trim($validated['name']);

        if (DB::table('gl_accounts')->where('company_id', $companyId)->where('code', $code)->exists()) {
            throw ValidationException::withMessages([
                'code' => "Kode akun {$code} sudah digunakan di perusahaan ini.",
            ]);
        }

        $similarAccount = $this->findSimilarAccount($companyId, $name);
        if ($similarAccount && ! $request->boolean('confirm_similar')) {
            throw ValidationException::withMessages([
                'name' => "Peringatan duplikasi: nama ini mirip dengan {$similarAccount->code} — {$similarAccount->name}. Tinjau kembali atau centang konfirmasi jika memang akun berbeda.",
            ]);
        }

        $accountId = DB::table('gl_accounts')->insertGetId([
            'company_id' => $companyId,
            'code' => $code,
            'name' => $name,
            'type' => $validated['type'],
            'normal_balance' => $validated['normal_balance'],
            'allow_posting' => $request->boolean('allow_posting'),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        AuditLogger::log('gl_account_created', 'gl_account', $accountId, null, [
            'code' => $code,
            'name' => $name,
            'type' => $validated['type'],
        ], $companyId);

        return redirect()->route('accounting.index')->with('status', "Akun {$code} berhasil ditambahkan.");
    }

    public function store(Request $r, CompanyContext $c): RedirectResponse
    {
        $v = $r->validate(['journal_date' => ['required', 'date'], 'memo' => ['required', 'string', 'max:2000'], 'is_adjustment' => ['nullable', 'boolean'], 'lines' => ['required', 'array', 'min:2'], 'lines.*.gl_account_id' => ['required', 'integer'], 'lines.*.department_id' => ['nullable', 'integer'], 'lines.*.description' => ['nullable', 'string', 'max:1000'], 'lines.*.debit' => ['nullable', 'numeric', 'min:0'], 'lines.*.credit' => ['nullable', 'numeric', 'min:0']]);
        $accounts = DB::table('gl_accounts')->where('company_id', $c->id())->where('is_active', true)->where('allow_posting', true)->whereIn('id', collect($v['lines'])->pluck('gl_account_id'))->pluck('id');
        abort_unless($accounts->count() === collect($v['lines'])->pluck('gl_account_id')->unique()->count(), 422);
        $departmentIds = collect($v['lines'])->pluck('department_id')->filter()->unique();
        abort_unless($departmentIds->isEmpty() || DB::table('departments')->where('company_id', $c->id())->whereIn('id', $departmentIds)->count() === $departmentIds->count(), 422);

        foreach ($v['lines'] as $line) {
            $lineDebit = (float) ($line['debit'] ?? 0);
            $lineCredit = (float) ($line['credit'] ?? 0);
            if (($lineDebit <= 0 && $lineCredit <= 0) || ($lineDebit > 0 && $lineCredit > 0)) {
                throw ValidationException::withMessages(['lines' => 'Setiap baris wajib memiliki nilai hanya pada debit atau kredit.']);
            }
        }

        $debit = collect($v['lines'])->sum(fn ($x) => (float) ($x['debit'] ?? 0));
        $credit = collect($v['lines'])->sum(fn ($x) => (float) ($x['credit'] ?? 0));
        if ($debit <= 0 || abs($debit - $credit) > .005) {
            throw ValidationException::withMessages(['lines' => 'Total debit dan kredit wajib sama dan lebih dari nol.']);
        }$id = DB::transaction(function () use ($v, $c, $debit, $credit, $r) {
            $number = $this->nextJournalNumber($c->id(), $v['journal_date']);
            $id = DB::table('journal_entries')->insertGetId(['company_id' => $c->id(), 'branch_id' => $c->branch()?->id, 'document_number' => $number, 'journal_date' => $v['journal_date'], 'memo' => $v['memo'], 'is_adjustment' => $r->boolean('is_adjustment'), 'total_debit' => $debit, 'total_credit' => $credit, 'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
            foreach ($v['lines'] as $i => $line) {
                DB::table('journal_entry_lines')->insert(['journal_entry_id' => $id, 'gl_account_id' => $line['gl_account_id'], 'department_id' => $line['department_id'] ?? null, 'description' => $line['description'] ?? null, 'debit' => $line['debit'] ?? 0, 'credit' => $line['credit'] ?? 0, 'line_number' => $i + 1, 'created_at' => now(), 'updated_at' => now()]);
            }

            return $id;
        });
        AuditLogger::log('journal_created', 'journal_entry', $id, null, ['total' => $debit], $c->id());

        return redirect()->route('accounting.show', $id)->with('status', 'Journal Voucher berhasil dibuat sebagai draft.');
    }

    public function show(int $journal, CompanyContext $c): View
    {
        return $this->display($journal, $c, 'accounting.show');
    }

    public function print(int $journal, CompanyContext $c): View
    {
        return $this->display($journal, $c, 'accounting.print');
    }

    public function post(int $journal, CompanyContext $c): RedirectResponse
    {
        $e = DB::table('journal_entries')->where('company_id', $c->id())->where('id', $journal)->first();
        abort_unless($e, 404);
        abort_if($e->status !== 'draft', 422);
        TransactionPeriodLock::ensureOpen($c->id(), 'accounting', $e->journal_date);
        if (abs((float) $e->total_debit - (float) $e->total_credit) > .005) {
            throw ValidationException::withMessages(['journal' => 'Jurnal tidak seimbang.']);
        }DB::table('journal_entries')->where('id', $e->id)->update(['status' => 'posted', 'posted_by' => auth()->id(), 'posted_at' => now(), 'updated_at' => now()]);
        AuditLogger::log('journal_posted', 'journal_entry', (int) $e->id, ['status' => 'draft'], ['status' => 'posted'], $c->id());

        return back()->with('status', 'Journal Voucher berhasil diposting ke General Ledger.');
    }

    public function reverse(Request $request, int $journal, CompanyContext $companyContext): RedirectResponse
    {
        $validated = $request->validate([
            'reversal_date' => ['required', 'date'],
            'reversal_reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        $companyId = $companyContext->id();
        TransactionPeriodLock::ensureOpen($companyId, 'accounting', $validated['reversal_date']);

        $reversalId = DB::transaction(function () use ($companyId, $journal, $validated, $companyContext): int {
            $original = DB::table('journal_entries')
                ->where('company_id', $companyId)
                ->where('id', $journal)
                ->lockForUpdate()
                ->first();
            abort_unless($original, 404);
            abort_if($original->status !== 'posted', 422, 'Hanya jurnal posted yang dapat direversal.');
            abort_if($original->source_type !== 'manual', 422, 'Jurnal subledger harus direversal dari modul asal agar saldo subledger tetap sinkron.');
            abort_if($original->reversed_by_id !== null || $original->reversal_of_id !== null, 422, 'Jurnal ini sudah merupakan atau memiliki reversal.');
            abort_if($validated['reversal_date'] < $original->journal_date, 422, 'Tanggal reversal tidak boleh lebih awal dari jurnal asli.');

            $lines = DB::table('journal_entry_lines')->where('journal_entry_id', $original->id)->orderBy('line_number')->get();
            abort_if($lines->isEmpty(), 422, 'Detail jurnal asli tidak ditemukan.');

            $reversalId = DB::table('journal_entries')->insertGetId([
                'company_id' => $companyId,
                'branch_id' => $original->branch_id ?: $companyContext->branch()?->id,
                'document_number' => $this->nextJournalNumber($companyId, $validated['reversal_date']),
                'journal_date' => $validated['reversal_date'],
                'source_type' => 'reversal',
                'reversal_of_id' => $original->id,
                'status' => 'posted',
                'is_adjustment' => true,
                'reversal_reason' => $validated['reversal_reason'],
                'memo' => 'Reversal '.$original->document_number.' — '.$validated['reversal_reason'],
                'total_debit' => $original->total_credit,
                'total_credit' => $original->total_debit,
                'created_by' => auth()->id(),
                'posted_by' => auth()->id(),
                'posted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($lines as $line) {
                DB::table('journal_entry_lines')->insert([
                    'journal_entry_id' => $reversalId,
                    'gl_account_id' => $line->gl_account_id,
                    'department_id' => $line->department_id,
                    'description' => 'Reversal: '.($line->description ?: $original->memo),
                    'debit' => $line->credit,
                    'credit' => $line->debit,
                    'line_number' => $line->line_number,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('journal_entries')->where('id', $original->id)->update([
                'reversed_by_id' => $reversalId,
                'updated_at' => now(),
            ]);

            return $reversalId;
        });

        AuditLogger::log('journal_reversed', 'journal_entry', $journal, null, [
            'reversal_id' => $reversalId,
            'reversal_date' => $validated['reversal_date'],
            'reason' => $validated['reversal_reason'],
        ], $companyId);

        return redirect()->route('accounting.show', $reversalId)->with('status', 'Reversal berhasil dibuat dan diposting. Jurnal asli tetap tersimpan untuk audit.');
    }

    private function display(int $id, CompanyContext $c, string $view): View
    {
        $e = DB::table('journal_entries')->leftJoin('users as creator', 'creator.id', '=', 'journal_entries.created_by')->leftJoin('users as poster', 'poster.id', '=', 'journal_entries.posted_by')->where('journal_entries.company_id', $c->id())->where('journal_entries.id', $id)->select('journal_entries.*', 'creator.name as creator_name', 'poster.name as poster_name')->first();
        abort_unless($e, 404);
        $lines = DB::table('journal_entry_lines')->join('gl_accounts', 'gl_accounts.id', '=', 'journal_entry_lines.gl_account_id')->leftJoin('departments', 'departments.id', '=', 'journal_entry_lines.department_id')->where('journal_entry_id', $id)->select('journal_entry_lines.*', 'gl_accounts.code', 'gl_accounts.name as account_name', 'departments.name as department_name')->orderBy('line_number')->get();

        return view($view, ['company' => $c->current(), 'entry' => $e, 'lines' => $lines]);
    }

    private function findSimilarAccount(int $companyId, string $name): ?object
    {
        $normalizedName = $this->normalizeAccountName($name);

        foreach (DB::table('gl_accounts')->where('company_id', $companyId)->get(['code', 'name']) as $account) {
            $candidate = $this->normalizeAccountName($account->name);
            similar_text($normalizedName, $candidate, $similarity);

            if ($candidate === $normalizedName || (mb_strlen($normalizedName) >= 4 && $similarity >= 85)) {
                return $account;
            }
        }

        return null;
    }

    private function normalizeAccountName(string $name): string
    {
        return preg_replace('/[^a-z0-9]+/', '', Str::lower(Str::ascii($name))) ?? '';
    }

    private function nextJournalNumber(int $companyId, string $journalDate): string
    {
        $period = date('Ym', strtotime($journalDate));
        $lastDocumentNumber = DB::table('journal_entries')
            ->where('company_id', $companyId)
            ->where('document_number', 'like', 'JV-'.$period.'-%')
            ->max('document_number');
        $initialNumber = $lastDocumentNumber
            ? max(1, (int) Str::afterLast((string) $lastDocumentNumber, '-') + 1)
            : 1;
        DB::table('accounting_document_sequences')->insertOrIgnore([
            'company_id' => $companyId,
            'period' => $period,
            'next_number' => $initialNumber,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $sequence = DB::table('accounting_document_sequences')
            ->where('company_id', $companyId)
            ->where('period', $period)
            ->lockForUpdate()
            ->firstOrFail();
        DB::table('accounting_document_sequences')->where('id', $sequence->id)->update([
            'next_number' => (int) $sequence->next_number + 1,
            'updated_at' => now(),
        ]);

        return 'JV-'.$period.'-'.str_pad((string) $sequence->next_number, 5, '0', STR_PAD_LEFT);
    }
}
