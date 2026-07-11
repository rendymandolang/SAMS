<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AuditLogger;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    private const ROLES = [
        'super_admin' => 'Super Admin',
        'purchasing' => 'Purchasing',
        'warehouse' => 'Warehouse',
        'finance' => 'Finance',
        'staff' => 'Staff',
    ];

    public function index(CompanyContext $context): View
    {
        $companyId = $context->id();
        $users = User::query()
            ->whereIn('id', DB::table('company_user')->where('company_id', $companyId)->where('is_active', true)->select('user_id'))
            ->orderBy('name')
            ->paginate(12);
        $companyRoles = DB::table('company_user_roles')
            ->join('roles', 'roles.id', '=', 'company_user_roles.role_id')
            ->where('company_user_roles.company_id', $companyId)
            ->where('roles.company_id', $companyId)
            ->pluck('roles.key', 'company_user_roles.user_id');
        $users->getCollection()->each(function (User $user) use ($companyRoles): void {
            $user->company_role_key = $companyRoles->get($user->id, $user->role);
        });

        return view('users.index', [
            'users' => $users,
            'roles' => self::ROLES,
        ]);
    }

    public function create(): View
    {
        return view('users.form', [
            'user' => null,
            'roles' => $this->assignableRoles(),
            'currentRole' => null,
        ]);
    }

    public function store(Request $request, CompanyContext $context): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(array_keys($this->assignableRoles()))],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $company = $context->current();
        $branch = $context->branch();
        $departmentId = DB::table('company_user')
            ->where('company_id', $company->id)
            ->where('user_id', auth()->id())
            ->value('department_id');

        $user = DB::transaction(function () use ($validated, $request, $company, $branch, $departmentId): User {
            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'role' => $validated['role'],
                'password' => $validated['password'],
                'is_active' => $request->boolean('is_active'),
            ]);

            DB::table('company_user')->insert([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'branch_id' => $branch?->id,
                'department_id' => $departmentId,
                'is_default' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->syncCompanyRole($user, (int) $company->id, $validated['role']);

            AuditLogger::log('user_created', 'user', (int) $user->id, null, $user->only(['name', 'email', 'role', 'is_active']), (int) $company->id);

            return $user;
        });

        return redirect()
            ->route('users.index')
            ->with('status', 'User berhasil dibuat.');
    }

    public function edit(User $user, CompanyContext $context): View
    {
        $this->ensureCompanyUser($user, $context);
        $currentRole = $this->companyRoleKey($user, $context->id());
        $this->ensureRoleCanBeManaged($currentRole);

        return view('users.form', [
            'user' => $user,
            'roles' => $this->assignableRoles(),
            'currentRole' => $currentRole,
        ]);
    }

    public function update(Request $request, User $user, CompanyContext $context): RedirectResponse
    {
        $this->ensureCompanyUser($user, $context);
        $companyId = $context->id();
        $currentRole = $this->companyRoleKey($user, $companyId);
        $this->ensureRoleCanBeManaged($currentRole);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in(array_keys($this->assignableRoles()))],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $isActive = $request->boolean('is_active');

        if ((int) $user->id === (int) auth()->id()) {
            $isActive = true;
        }

        if ($currentRole === 'super_admin' && ($validated['role'] !== 'super_admin' || ! $isActive)) {
            $this->ensureAnotherSuperAdminExists($companyId, (int) $user->id);
        }

        $old = $user->only(['name', 'email', 'is_active']) + ['company_role' => $currentRole];

        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'is_active' => $isActive,
        ];

        if (filled($validated['password'] ?? null)) {
            $payload['password'] = $validated['password'];
        }

        DB::transaction(function () use ($user, $payload, $companyId, $validated, $old): void {
            $user->update($payload);
            $this->syncCompanyRole($user, $companyId, $validated['role']);

            AuditLogger::log(
                'user_updated',
                'user',
                (int) $user->id,
                $old,
                $user->fresh()->only(['name', 'email', 'is_active']) + ['company_role' => $validated['role']],
                $companyId,
            );
        });

        return redirect()
            ->route('users.index')
            ->with('status', 'User berhasil diperbarui.');
    }

    private function ensureCompanyUser(User $user, CompanyContext $context): void
    {
        $isMember = DB::table('company_user')
            ->where('company_id', $context->id())
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->exists();

        abort_unless($isMember, 404);
    }

    private function syncCompanyRole(User $user, int $companyId, string $roleKey): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('company_user_roles')) {
            return;
        }

        $roleId = DB::table('roles')
            ->where('company_id', $companyId)
            ->where('key', $roleKey)
            ->value('id');

        if (! $roleId) {
            return;
        }

        DB::table('company_user_roles')
            ->where('company_id', $companyId)
            ->where('user_id', $user->id)
            ->delete();

        DB::table('company_user_roles')->insert([
            'company_id' => $companyId,
            'user_id' => $user->id,
            'role_id' => $roleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array<string, string> */
    private function assignableRoles(): array
    {
        if (auth()->user()?->hasRole('super_admin')) {
            return self::ROLES;
        }

        return collect(self::ROLES)->except('super_admin')->all();
    }

    private function companyRoleKey(User $user, int $companyId): string
    {
        return (string) (DB::table('company_user_roles')
            ->join('roles', 'roles.id', '=', 'company_user_roles.role_id')
            ->where('company_user_roles.company_id', $companyId)
            ->where('company_user_roles.user_id', $user->id)
            ->where('roles.company_id', $companyId)
            ->orderByRaw("CASE roles.`key` WHEN 'super_admin' THEN 1 ELSE 2 END")
            ->value('roles.key') ?? $user->role);
    }

    private function ensureRoleCanBeManaged(string $roleKey): void
    {
        abort_if($roleKey === 'super_admin' && ! auth()->user()?->hasRole('super_admin'), 403);
    }

    private function ensureAnotherSuperAdminExists(int $companyId, int $excludedUserId): void
    {
        $hasAnotherAdmin = DB::table('company_user_roles')
            ->join('roles', 'roles.id', '=', 'company_user_roles.role_id')
            ->join('company_user', function ($join): void {
                $join->on('company_user.company_id', '=', 'company_user_roles.company_id')
                    ->on('company_user.user_id', '=', 'company_user_roles.user_id');
            })
            ->join('users', 'users.id', '=', 'company_user_roles.user_id')
            ->where('company_user_roles.company_id', $companyId)
            ->where('roles.company_id', $companyId)
            ->where('roles.key', 'super_admin')
            ->where('company_user_roles.user_id', '!=', $excludedUserId)
            ->where('company_user.is_active', true)
            ->where('users.is_active', true)
            ->exists();

        if (! $hasAnotherAdmin) {
            throw ValidationException::withMessages([
                'role' => app()->getLocale() === 'id'
                    ? 'Perusahaan harus memiliki setidaknya satu Super Admin aktif.'
                    : 'The company must keep at least one active Super Admin.',
            ]);
        }
    }
}
