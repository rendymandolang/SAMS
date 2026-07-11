@extends('layouts.app', ['title' => __('access.title').' · SAMS'])

@section('body')
    <div class="app-shell">
        @include('partials.sidebar')

        <main class="main">
            <header class="topbar page-intro">
                <div class="page-intro__copy">
                    <p class="eyebrow">{{ __('access.eyebrow') }}</p>
                    <h1>{{ __('access.title') }}</h1>
                    <p class="muted">{{ __('access.subtitle') }}</p>
                </div>
                @if (auth()->user()->hasPermission('core.users.manage'))
                    <a class="button secondary inline" href="{{ route('users.index') }}">{{ __('access.actions.manage_users') }}</a>
                @endif
            </header>

            @if (session('status'))
                <div class="notice">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="error"><strong>{{ __('access.errors.not_saved') }}</strong><div style="margin-top:6px;">{{ $errors->first() }}</div></div>
            @endif

            <section class="grid stats access-summary">
                <div class="card metric-card"><div class="muted">{{ __('access.summary.enabled_modules') }}</div><div class="stat-value">{{ $summary['enabled_modules'] }}</div><div class="muted">{{ __('access.summary.of_modules', ['count' => $summary['total_modules']]) }}</div></div>
                <div class="card metric-card"><div class="muted">{{ __('access.summary.available_permissions') }}</div><div class="stat-value">{{ $summary['permissions'] }}</div><div class="muted">{{ __('access.summary.across_enabled_modules') }}</div></div>
                <div class="card metric-card"><div class="muted">{{ __('access.summary.managed_roles') }}</div><div class="stat-value">{{ $summary['roles'] }}</div><div class="muted">{{ __('access.summary.including_system_roles') }}</div></div>
                <div class="card metric-card"><div class="muted">{{ __('access.summary.company_users') }}</div><div class="stat-value">{{ $summary['users'] }}</div><div class="muted">{{ __('access.summary.with_active_membership') }}</div></div>
            </section>

            <section class="card access-section">
                <div class="toolbar section-heading">
                    <div>
                        <h2>{{ __('access.sections.modules') }}</h2>
                        <p class="muted">{{ __('access.sections.modules_description') }}</p>
                    </div>
                    <span class="badge">{{ $company->name }}</span>
                </div>

                <form method="POST" action="{{ route('access-control.modules.update') }}">
                    @csrf
                    @method('PUT')
                    <div class="entitlement-grid">
                        @forelse ($modules as $module)
                            @php
                                $isCore = $module->key === 'core';
                                $isPlanned = $module->status === 'planned';
                                $moduleNameKey = 'access.modules.'.$module->key.'.name';
                                $moduleDescriptionKey = 'access.modules.'.$module->key.'.description';
                                $moduleName = \Illuminate\Support\Facades\Lang::has($moduleNameKey) ? __($moduleNameKey) : $module->name;
                                $moduleDescription = \Illuminate\Support\Facades\Lang::has($moduleDescriptionKey) ? __($moduleDescriptionKey) : $module->description;
                            @endphp
                            <label class="entitlement-card {{ $module->is_enabled ? 'enabled' : '' }} {{ $isPlanned ? 'planned' : '' }}">
                                <span class="entitlement-card__top">
                                    <span class="entitlement-icon"><x-icon name="{{ match($module->key) { 'assets' => 'asset', 'inventory' => 'inventory', 'procurement' => 'procurement', 'core' => 'settings', default => 'reports' } }}" /></span>
                                    <span class="badge {{ $isPlanned ? 'next' : '' }}">{{ $isPlanned ? __('access.labels.planned') : ($module->is_enabled ? __('access.labels.enabled') : __('access.labels.disabled')) }}</span>
                                </span>
                                <span class="entitlement-card__body">
                                    <strong>{{ $moduleName }}</strong>
                                    <span class="muted">{{ $moduleDescription }}</span>
                                </span>
                                <span class="entitlement-card__footer">
                                    @if ($isCore)
                                        <input type="hidden" name="modules[]" value="{{ $module->id }}">
                                    @endif
                                    <input name="modules[]" type="checkbox" value="{{ $module->id }}" @checked($module->is_enabled) @disabled($isCore || $isPlanned)>
                                    <span>{{ $isCore ? __('access.labels.locked') : ($isPlanned ? __('access.help.planned_locked') : __('access.labels.active')) }}</span>
                                </span>
                            </label>
                        @empty
                            <div class="empty-state">{{ __('access.empty.modules') }}</div>
                        @endforelse
                    </div>
                    <div class="access-savebar">
                        <span class="muted">{{ __('access.help.module_changes') }}</span>
                        <button class="button inline" type="submit">{{ __('access.actions.save_modules') }}</button>
                    </div>
                </form>
            </section>

            <section class="card access-section">
                <div class="toolbar section-heading">
                    <div>
                        <h2>{{ __('access.sections.permissions') }}</h2>
                        <p class="muted">{{ __('access.sections.permissions_description') }}</p>
                    </div>
                </div>

                <div class="role-stack">
                    @forelse ($roles as $role)
                        @php
                            $locked = $role->key === 'super_admin';
                            $roleNameKey = 'access.roles.'.$role->key;
                            $roleDescriptionKey = 'access.role_descriptions.'.$role->key;
                            $roleName = \Illuminate\Support\Facades\Lang::has($roleNameKey) ? __($roleNameKey) : $role->name;
                            $roleDescription = \Illuminate\Support\Facades\Lang::has($roleDescriptionKey) ? __($roleDescriptionKey) : $role->description;
                        @endphp
                        <form class="role-card" method="POST" action="{{ route('access-control.roles.permissions.update', $role->id) }}" data-role-form="{{ $role->id }}">
                            @csrf
                            @method('PUT')
                            <div class="role-card__heading">
                                <div>
                                    <div class="role-title-row">
                                        <h3>{{ $roleName }}</h3>
                                        @if ($locked)<span class="badge">{{ __('access.labels.locked') }}</span>@endif
                                    </div>
                                    <p class="muted">{{ $roleDescription }}</p>
                                </div>
                                <span class="role-count">{{ __('access.labels.permission_count', ['granted' => count($role->permission_ids), 'total' => $summary['permissions']]) }}</span>
                            </div>

                            @if ($locked)
                                <div class="access-callout"><x-icon name="settings" /> <span>{{ __('access.help.super_admin_locked') }}</span></div>
                            @endif

                            <div class="permission-groups">
                                @foreach ($permissionGroups as $moduleKey => $permissions)
                                    @php $moduleRow = $modules->firstWhere('key', $moduleKey); @endphp
                                    @continue(! $moduleRow || ! $moduleRow->is_enabled)
                                    <fieldset class="permission-group" data-permission-group="{{ $moduleKey }}">
                                        <legend>{{ __('access.modules.'.$moduleKey.'.name') }}</legend>
                                        @unless ($locked)
                                            <div class="permission-group__actions">
                                                <button type="button" data-permission-action="select">{{ __('access.actions.select_group') }}</button>
                                                <button type="button" data-permission-action="clear">{{ __('access.actions.clear_group') }}</button>
                                            </div>
                                        @endunless
                                        <div class="permission-list">
                                            @foreach ($permissions as $permission)
                                                @php
                                                    $permissionTranslation = 'access.permissions.'.str_replace('.', '_', $permission->key);
                                                    $permissionName = \Illuminate\Support\Facades\Lang::has($permissionTranslation) ? __($permissionTranslation) : $permission->name;
                                                    $required = $permission->key === 'core.dashboard.view';
                                                @endphp
                                                <label class="permission-option">
                                                    @if ($required && ! $locked)
                                                        <input name="permissions[]" type="hidden" value="{{ $permission->id }}">
                                                    @endif
                                                    <input name="permissions[]" type="checkbox" value="{{ $permission->id }}" @checked($required || in_array((int) $permission->id, $role->permission_ids, true)) @disabled($locked || $required)>
                                                    <span><strong>{{ $permissionName }} @if($required)<em>{{ __('access.labels.required') }}</em>@endif</strong><small>{{ $permission->key }}</small></span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </fieldset>
                                @endforeach
                            </div>

                            <div class="role-card__footer">
                                <span class="muted">{{ __('access.help.role_scope', ['company' => $company->name]) }}</span>
                                @unless ($locked)
                                    <button class="button inline" type="submit">{{ __('access.actions.save_permissions', ['role' => $roleName]) }}</button>
                                @endunless
                            </div>
                        </form>
                    @empty
                        <div class="empty-state">{{ __('access.empty.roles') }}</div>
                    @endforelse
                </div>
            </section>
        </main>
    </div>

    <style>
        .access-summary { margin-bottom:18px; }
        .access-section { margin-bottom:18px; }
        .access-section .section-heading p { margin:6px 0 0; }
        .entitlement-grid { display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,250px),1fr));gap:14px; }
        .entitlement-card { display:flex;min-height:220px;flex-direction:column;gap:16px;border:1px solid var(--line);border-radius:17px;padding:17px;background:var(--surface-muted);cursor:pointer;transition:.2s ease; }
        .entitlement-card:hover { border-color:var(--primary-line);transform:translateY(-1px); }
        .entitlement-card.enabled { border-color:var(--primary-line);background:linear-gradient(145deg,var(--primary-soft),var(--card) 48%); }
        .entitlement-card.planned { cursor:not-allowed;opacity:.76; }
        .entitlement-card.planned:hover { transform:none; }
        .entitlement-card__top,.entitlement-card__footer,.role-card__heading,.role-card__footer,.role-title-row { display:flex;align-items:center;justify-content:space-between;gap:12px; }
        .entitlement-icon { display:grid;width:42px;height:42px;place-items:center;border-radius:13px;color:var(--primary);background:var(--primary-soft); }
        .entitlement-icon svg { width:21px;height:21px; }
        .entitlement-card__body { display:grid;gap:7px;flex:1; }
        .entitlement-card__body .muted { font-size:13px;line-height:1.55; }
        .entitlement-card__footer { border-top:1px solid var(--line);padding-top:13px;font-size:12px;font-weight:750; }
        .entitlement-card__footer input,.permission-option input { width:18px;height:18px;accent-color:var(--primary); }
        .access-savebar { display:flex;align-items:center;justify-content:space-between;gap:16px;margin-top:18px;border-top:1px solid var(--line);padding-top:18px; }
        .role-stack { display:grid;gap:16px; }
        .role-card { border:1px solid var(--line);border-radius:18px;padding:20px;background:var(--surface-muted); }
        .role-card__heading { align-items:flex-start;margin-bottom:16px; }
        .role-card__heading h3 { margin:0; }
        .role-card__heading p { margin:6px 0 0; }
        .role-title-row { justify-content:flex-start; }
        .role-count { border-radius:999px;padding:7px 10px;color:var(--muted);background:var(--card);font-size:11px;font-weight:800;white-space:nowrap; }
        .access-callout { display:flex;align-items:center;gap:10px;margin-bottom:14px;border-radius:13px;padding:11px 13px;color:var(--primary);background:var(--primary-soft);font-size:12px;font-weight:750; }
        .access-callout svg { width:18px;height:18px; }
        .permission-groups { display:grid;gap:12px; }
        .permission-group { min-width:0;border:1px solid var(--line);border-radius:14px;padding:14px;background:var(--card); }
        .permission-group legend { padding:0 7px;color:var(--ink);font-size:12px;font-weight:900; }
        .permission-group__actions { display:flex;justify-content:flex-end;gap:7px;margin:-5px 0 10px; }
        .permission-group__actions button { border:0;border-radius:999px;padding:5px 9px;color:var(--primary);background:var(--primary-soft);font:inherit;font-size:10px;font-weight:800;cursor:pointer; }
        .permission-list { display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,250px),1fr));gap:8px; }
        .permission-option { display:flex;align-items:flex-start;gap:9px;border-radius:11px;padding:9px;background:var(--surface-muted);cursor:pointer; }
        .permission-option span { display:grid;gap:3px; }
        .permission-option strong { font-size:12px; }
        .permission-option strong em { margin-left:5px;color:var(--primary);font-size:9px;font-style:normal;text-transform:uppercase; }
        .permission-option small { color:var(--muted);font-size:9px; }
        .role-card__footer { margin-top:16px;border-top:1px solid var(--line);padding-top:15px; }
        @media (max-width:720px) { .access-savebar,.role-card__heading,.role-card__footer { align-items:stretch;flex-direction:column; }.access-savebar .button,.role-card__footer .button { width:100%; }.role-count { align-self:flex-start; } }
    </style>

    <script>
        document.querySelectorAll('[data-permission-action]').forEach((button) => {
            button.addEventListener('click', () => {
                const group = button.closest('[data-permission-group]');
                const shouldSelect = button.dataset.permissionAction === 'select';

                group?.querySelectorAll('input[type="checkbox"]:not(:disabled)').forEach((checkbox) => {
                    checkbox.checked = shouldSelect;
                });
            });
        });
    </script>
@endsection
