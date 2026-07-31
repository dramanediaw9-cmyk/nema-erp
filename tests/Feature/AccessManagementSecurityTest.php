<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Access\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessManagementSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_company_administrator_cannot_assign_platform_administrator_role(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $operator = User::query()->where('email', 'ops@nema-erp.test')->firstOrFail();
        $platformRole = Role::query()->whereNull('company_id')->where('slug', 'platform_admin')->firstOrFail();

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->from(route('users.edit', $operator))
            ->put(route('users.update', $operator), [
                'name' => $operator->name,
                'phone' => $operator->phone,
                'email' => $operator->email,
                'branch_id' => $operator->branch_id,
                'roles' => [$platformRole->id],
                'is_active' => 1,
            ])
            ->assertRedirect(route('users.edit', $operator))
            ->assertSessionHasErrors([
                'roles' => 'Un rôle sélectionné n\'est pas autorisé pour cette entreprise.',
            ]);

        $this->assertFalse($operator->fresh()->hasRole('platform_admin'));
    }

    public function test_company_administrator_does_not_see_platform_role_in_user_form(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->get(route('users.create'))
            ->assertOk()
            ->assertDontSee('Administrateur plateforme');
    }

    public function test_platform_administrator_can_assign_platform_role(): void
    {
        $platformAdmin = User::query()->where('email', 'admin@nema-erp.test')->firstOrFail();
        $operator = User::query()->where('email', 'ops@nema-erp.test')->firstOrFail();
        $platformRole = Role::query()->whereNull('company_id')->where('slug', 'platform_admin')->firstOrFail();

        $this->actingAs($platformAdmin)
            ->withSession($this->workspaceSession($platformAdmin))
            ->put(route('users.update', $operator), [
                'name' => $operator->name,
                'phone' => $operator->phone,
                'email' => $operator->email,
                'branch_id' => $operator->branch_id,
                'roles' => [$platformRole->id],
                'is_active' => 1,
            ])
            ->assertRedirect(route('users.index'));

        $this->assertTrue($operator->fresh()->hasRole('platform_admin'));
    }

    public function test_system_role_edit_url_is_forbidden_even_to_platform_administrator(): void
    {
        $platformAdmin = User::query()->where('email', 'admin@nema-erp.test')->firstOrFail();
        $platformRole = Role::query()->whereNull('company_id')->where('slug', 'platform_admin')->firstOrFail();

        $this->actingAs($platformAdmin)
            ->withSession($this->workspaceSession($platformAdmin))
            ->get(route('roles.edit', $platformRole))
            ->assertForbidden();
    }

    public function test_access_management_rejects_missing_permissions_and_cross_company_records(): void
    {
        $cashier = User::query()->where('email', 'caissier@nema-erp.test')->firstOrFail();
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $retailManager = User::query()->where('email', 'retail.manager@nema-erp.test')->firstOrFail();
        $retailRole = Role::query()
            ->where('company_id', $retailManager->company_id)
            ->where('slug', 'company_admin')
            ->firstOrFail();

        $this->actingAs($cashier)
            ->withSession($this->workspaceSession($cashier))
            ->get(route('users.index'))
            ->assertForbidden();

        $this->actingAs($cashier)
            ->withSession($this->workspaceSession($cashier))
            ->get(route('roles.index'))
            ->assertForbidden();

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->get(route('users.edit', $retailManager))
            ->assertForbidden();

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->get(route('roles.edit', $retailRole))
            ->assertForbidden();
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_tenant_id' => $user->tenant_id,
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}
