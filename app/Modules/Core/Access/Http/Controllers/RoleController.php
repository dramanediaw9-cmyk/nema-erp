<?php

namespace App\Modules\Core\Access\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Access\Models\Permission;
use App\Modules\Core\Access\Models\Role;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(CurrentWorkspace $workspace): View
    {
        $roles = Role::query()
            ->with('permissions')
            ->where(fn ($query) => $query->whereNull('company_id')->orWhere('company_id', $workspace->companyId()))
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();

        return view('roles.index', [
            'roles' => $roles,
            'permissions' => Permission::query()->orderBy('module')->orderBy('name')->get()->groupBy('module'),
        ]);
    }

    public function create(CurrentWorkspace $workspace): View|RedirectResponse
    {
        if (! $workspace->company()) {
            return redirect()->route('companies.index')->with('error', 'Créez d\'abord une entreprise pour définir des rôles locaux.');
        }

        return view('roles.create', [
            'role' => new Role,
            'permissions' => Permission::query()->orderBy('module')->orderBy('name')->get()->groupBy('module'),
        ]);
    }

    public function store(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();

        abort_if(! $companyId, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:roles,slug,NULL,id,company_id,'.$companyId],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $role = Role::query()->create([
            'company_id' => $companyId,
            'name' => $data['name'],
            'slug' => $data['slug'] ?: Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'is_system' => false,
        ]);

        $role->permissions()->sync($data['permissions'] ?? []);
        $this->activityLogger->log('roles.create', 'Création rôle', $role, ['permissions' => $data['permissions'] ?? []]);

        return redirect()->route('roles.index')->with('success', 'Rôle créé avec succès.');
    }

    public function edit(Role $role, CurrentWorkspace $workspace): View
    {
        abort_if($role->company_id !== null && $workspace->companyId() !== $role->company_id, 403);
        abort_if($role->company_id === null, 403, 'Les rôles système ne peuvent pas être modifiés depuis cette interface.');

        return view('roles.edit', [
            'role' => $role->load('permissions'),
            'permissions' => Permission::query()->orderBy('module')->orderBy('name')->get()->groupBy('module'),
        ]);
    }

    public function update(Request $request, Role $role, CurrentWorkspace $workspace): RedirectResponse
    {
        abort_if($role->company_id !== null && $workspace->companyId() !== $role->company_id, 403);
        abort_if($role->company_id === null, 403, 'Les rôles système ne peuvent pas être modifiés depuis cette interface.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:roles,slug,'.$role->id.',id,company_id,'.$role->company_id],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $role->update([
            'name' => $data['name'],
            'slug' => $data['slug'] ?: Str::slug($data['name']),
            'description' => $data['description'] ?? null,
        ]);

        $role->permissions()->sync($data['permissions'] ?? []);
        $this->activityLogger->log('roles.update', 'Mise à jour rôle', $role, ['permissions' => $data['permissions'] ?? []]);

        return redirect()->route('roles.index')->with('success', 'Rôle mis à jour avec succès.');
    }
}
