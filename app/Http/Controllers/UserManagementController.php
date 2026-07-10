<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function index(): View
    {
        $users = User::query()
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

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(array_keys(self::ROLES))],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'password' => $validated['password'],
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('users.index')
            ->with('status', 'User berhasil dibuat.');
    }

    public function edit(User $user): View
    {
        return view('users.form', [
            'user' => $user,
            'roles' => self::ROLES,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
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

        return redirect()
            ->route('users.index')
            ->with('status', 'User berhasil diperbarui.');
    }
}
