<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CompanyContext
{
    private ?object $company = null;

    private ?object $branch = null;

    public function current(): object
    {
        if ($this->company) {
            return $this->company;
        }

        $user = auth()->user();

        abort_unless($user, 401);

        $selectedCompanyId = (int) session('company_id', 0);
        $membershipQuery = DB::table('company_user')
            ->join('companies', 'companies.id', '=', 'company_user.company_id')
            ->where('company_user.user_id', $user->id)
            ->where('company_user.is_active', true)
            ->where('companies.is_active', true)
            ->whereNull('companies.deleted_at');

        $membership = $selectedCompanyId > 0
            ? (clone $membershipQuery)->where('companies.id', $selectedCompanyId)->first()
            : null;

        $membership ??= $membershipQuery
            ->orderByDesc('company_user.is_default')
            ->orderBy('companies.name')
            ->first();

        abort_unless($membership, 403, 'User belum terhubung ke perusahaan aktif.');

        session(['company_id' => (int) $membership->company_id]);

        return $this->company = DB::table('companies')
            ->where('id', $membership->company_id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->firstOrFail();
    }

    public function id(): int
    {
        return (int) $this->current()->id;
    }

    public function branch(): ?object
    {
        if ($this->branch) {
            return $this->branch;
        }

        $company = $this->current();
        $membership = DB::table('company_user')
            ->where('company_id', $company->id)
            ->where('user_id', auth()->id())
            ->where('is_active', true)
            ->first();

        $branchQuery = DB::table('branches')
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->whereNull('deleted_at');

        if ($membership?->branch_id) {
            $this->branch = (clone $branchQuery)->where('id', $membership->branch_id)->first();
        }

        return $this->branch ??= $branchQuery->orderBy('name')->first();
    }

    public function memberships(): Collection
    {
        if (! auth()->check()) {
            return collect();
        }

        return DB::table('company_user')
            ->join('companies', 'companies.id', '=', 'company_user.company_id')
            ->where('company_user.user_id', auth()->id())
            ->where('company_user.is_active', true)
            ->where('companies.is_active', true)
            ->whereNull('companies.deleted_at')
            ->orderByDesc('company_user.is_default')
            ->orderBy('companies.name')
            ->select('companies.id', 'companies.name', 'companies.code', 'companies.logo_path')
            ->get();
    }

    public function switchTo(int $companyId): void
    {
        $allowed = DB::table('company_user')
            ->join('companies', 'companies.id', '=', 'company_user.company_id')
            ->where('company_user.user_id', auth()->id())
            ->where('company_user.company_id', $companyId)
            ->where('company_user.is_active', true)
            ->where('companies.is_active', true)
            ->whereNull('companies.deleted_at')
            ->exists();

        abort_unless($allowed, 403, 'Anda tidak memiliki akses ke perusahaan tersebut.');

        session(['company_id' => $companyId]);
        $this->company = null;
        $this->branch = null;
    }
}
