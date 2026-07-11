<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AuditLogger;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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

        return view('users.index', [
            'users' => $users,
            'roles' => self::ROLES,
        ]);
    }

    public function create(): View
    {
        return view('users.form', [
            'user' => null,
            'roles' => self::ROLES,
        ]);
    }

    public function store(Request $request, CompanyContext $context): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(array_keys(self::ROLES))],
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

        return view('users.form', [
            'user' => $user,
            'roles' => self::ROLES,
        ]);
    }

    public function update(Request $request, User $user, CompanyContext $context): RedirectResponse
    {
        $this->ensureCompanyUser($user, $context);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in(array_keys(self::ROLES))],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $isActive = $request->boolean('is_active');

        if ((int) $user->id === (int) auth()->id()) {
            $isActive = true;
        }

        $old = $user->only(['name', 'email', 'role', 'is_active']);

        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'is_active' => $isActive,
        ];

        if (filled($validated['password'] ?? null)) {
            $payload['password'] = $validated['password'];
        }

        $user->update($payload);

        AuditLogger::log('user_updated', 'user', (int) $user->id, $old, $user->fresh()->only(['name', 'email', 'role', 'is_active']));

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
}
