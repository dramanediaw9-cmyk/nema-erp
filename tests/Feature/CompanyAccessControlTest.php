<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Company\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyAccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_company_admin_only_sees_current_company_in_registry(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->get(route('companies.index'))
            ->assertOk()
            ->assertSee('Nema Distribution')
            ->assertDontSee('Nema Retail Sud');
    }

    public function test_company_admin_cannot_create_or_edit_other_companies(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $otherCompany = Company::query()->where('name', 'Nema Retail Sud')->firstOrFail();

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->get(route('companies.create'))
            ->assertForbidden();

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->get(route('companies.edit', $otherCompany))
            ->assertForbidden();
    }

    public function test_platform_admin_can_view_company_registry_across_companies(): void
    {
        $admin = User::query()->where('email', 'admin@nema-erp.test')->firstOrFail();

        $this->actingAs($admin)
            ->withSession($this->workspaceSession($admin))
            ->get(route('companies.index'))
            ->assertOk()
            ->assertSee('Nema Distribution')
            ->assertSee('Nema Retail Sud');
    }

    public function test_forged_company_session_is_replaced_with_user_company(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $otherCompany = Company::query()->where('name', 'Nema Retail Sud')->firstOrFail();

        $this->actingAs($manager)
            ->withSession([
                'current_tenant_id' => $otherCompany->tenant_id,
                'current_company_id' => $otherCompany->id,
            ])
            ->get(route('companies.index'))
            ->assertOk();

        $this->assertSame((int) $manager->company_id, (int) session('current_company_id'));
    }

    public function test_company_admin_cannot_switch_to_another_company(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $otherCompany = Company::query()->where('name', 'Nema Retail Sud')->firstOrFail();

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->post(route('companies.switch', $otherCompany))
            ->assertForbidden();
    }

    public function test_platform_admin_can_switch_company_and_receives_a_matching_branch(): void
    {
        $admin = User::query()->where('email', 'admin@nema-erp.test')->firstOrFail();
        $otherCompany = Company::query()->where('name', 'Nema Retail Sud')->firstOrFail();

        $this->actingAs($admin)
            ->withSession($this->workspaceSession($admin))
            ->post(route('companies.switch', $otherCompany))
            ->assertRedirect(route('dashboard'));

        $this->assertSame((int) $otherCompany->id, (int) session('current_company_id'));
        $this->assertSame(
            (int) $otherCompany->id,
            (int) $otherCompany->branches()->whereKey(session('current_branch_id'))->value('company_id')
        );
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
