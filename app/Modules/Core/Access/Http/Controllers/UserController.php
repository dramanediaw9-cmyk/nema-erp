<?php

namespace App\Modules\Core\Access\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Core\Access\Models\Role;
use App\Modules\Core\Branch\Models\Branch;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        return view('users.index', [
            'users' => User::query()
                ->with(['branch', 'roles'])
                ->where('company_id', $companyId)
                ->orderBy('name')
                ->paginate(15),
        ]);
    }

    public function create(CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        return view('users.create', [
            'userModel' => new User(),
            'branches' => Branch::query()->where('company_id', $companyId)->orderByDesc('is_default')->orderBy('name')->get(),
            'roles' => $this->availableRoles($companyId),
        ]);
    }

    public function store(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'password' => ['required', 'string', Password::defaults()->uncompromised(), 'confirmed'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['integer', 'exists:roles,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $branch = Branch::query()->where('company_id', $companyId)->findOrFail($data['branch_id']);
        $roleIds = $this->validatedRoleIds($data['roles'], $companyId);

        $user = User::query()->create([
            'company_id' => $companyId,
            'branch_id' => $branch->id,
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'],
            'password' => $data['password'],
            'is_active' => $request->boolean('is_active', true),
            'email_verified_at' => now(),
        ]);

        $user->roles()->sync($roleIds);
        $this->activityLogger->log('users.create', 'Création utilisateur', $user, ['roles' => $roleIds]);

        return redirect()->route('users.index')->with('success', 'Utilisateur créé avec succès.');
    }

    public function edit(User $user, CurrentWorkspace $workspace): View
    {
        abort_if($workspace->companyId() !== $user->company_id, 403);

        return view('users.edit', [
            'userModel' => $user->load('roles'),
            'branches' => Branch::query()->where('company_id', $workspace->companyId())->orderByDesc('is_default')->orderBy('name')->get(),
            'roles' => $this->availableRoles($workspace->companyId()),
        ]);
    }

    public function update(Request $request, User $user, CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        abort_if($companyId !== $user->company_id, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'password' => ['nullable', 'string', Password::defaults()->uncompromised(), 'confirmed'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['integer', 'exists:roles,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $branch = Branch::query()->where('company_id', $companyId)->findOrFail($data['branch_id']);
        $roleIds = $this->validatedRoleIds($data['roles'], $companyId);

        $payload = [
            'branch_id' => $branch->id,
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'],
            'is_active' => $request->boolean('is_active', true),
        ];

        if (! empty($data['password'])) {
            $payload['password'] = $data['password'];
        }

        $user->update($payload);
        $user->roles()->sync($roleIds);
        $this->activityLogger->log('users.update', 'Mise à jour utilisateur', $user, ['roles' => $roleIds]);

        return redirect()->route('users.index')->with('success', 'Utilisateur mis à jour avec succès.');
    }

    private function availableRoles(int $companyId)
    {
        return Role::query()
            ->where(fn($query) => $query->whereNull('company_id')->orWhere('company_id', $companyId))
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();
    }

    private function validatedRoleIds(array $roleIds, int $companyId): array
    {
        $availableIds = $this->availableRoles($companyId)->pluck('id')->all();

        foreach ($roleIds as $roleId) {
            abort_unless(in_array((int) $roleId, $availableIds, true), 422, 'Un rôle sélectionné n\'est pas autorisé pour cette entreprise.');
        }

        return array_map('intval', $roleIds);
    }
}
