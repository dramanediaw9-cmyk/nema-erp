<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Integrations\Models\ApiToken;
use App\Modules\Hr\Models\HrDepartment;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GrowthFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_manager_can_open_platform_and_expansion_hubs(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $this->actingAs($user)->withSession($this->workspaceSession($user));

        $this->get(route('platform.index'))->assertOk()->assertSee('Socle produit et ecosysteme');
        $this->get(route('hr.index'))->assertOk()->assertSee('Capital humain');
        $this->get(route('payroll.index'))->assertOk()->assertSee('Executions de paie');
        $this->get(route('projects.index'))->assertOk()->assertSee('Pilotage projets');
        $this->get(route('manufacturing.index'))->assertOk()->assertSee('Ordres de production');
        $this->get(route('commerce.index'))->assertOk()->assertSee('Commerce unifie');
    }

    public function test_manager_can_register_expansion_records(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $this->actingAs($user)->withSession($this->workspaceSession($user));

        $this->post(route('hr.departments.store'), [
            'name' => 'Operations terrain',
            'manager_name' => 'Chef Zone Bamako',
            'headcount_target' => 12,
            'status' => 'active',
        ])->assertRedirect(route('hr.index'));

        $department = HrDepartment::query()->where('company_id', $user->company_id)->where('name', 'Operations terrain')->firstOrFail();

        $this->post(route('hr.employees.store'), [
            'full_name' => 'Moussa Traore',
            'email' => 'moussa.traore@example.test',
            'job_title' => 'Superviseur retail',
            'department_id' => $department->id,
            'contract_type' => 'permanent',
            'hire_date' => now()->toDateString(),
            'status' => 'active',
            'payroll_cycle' => 'monthly',
            'base_salary' => 350000,
        ])->assertRedirect(route('hr.index'));

        $this->post(route('payroll.store'), [
            'label' => 'Paie Avril '.now()->year,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'scheduled_pay_date' => now()->endOfMonth()->toDateString(),
            'headcount' => 1,
            'gross_amount' => 420000,
            'net_amount' => 350000,
            'status' => 'review',
        ])->assertRedirect(route('payroll.index'));

        $this->post(route('projects.store'), [
            'name' => 'Ouverture canal B2B Segou',
            'customer_name' => 'Reseau Segou Market',
            'start_date' => now()->toDateString(),
            'target_end_date' => now()->addMonth()->toDateString(),
            'status' => 'active',
            'progress' => 15,
            'budget_amount' => 950000,
        ])->assertRedirect(route('projects.index'));

        $this->post(route('manufacturing.store'), [
            'item_name' => 'Pack promo Ramadan',
            'planned_quantity' => 150,
            'completed_quantity' => 10,
            'planned_start_date' => now()->toDateString(),
            'due_date' => now()->addWeek()->toDateString(),
            'status' => 'in_progress',
            'routing_stage' => 'assembly',
        ])->assertRedirect(route('manufacturing.index'));

        $this->post(route('commerce.store'), [
            'name' => 'Boutique WhatsApp Bamako',
            'channel_type' => 'mobile',
            'status' => 'active',
            'connector_name' => 'WhatsApp Commerce',
            'settlement_mode' => 'mobile_money',
            'target_monthly_revenue' => 2500000,
        ])->assertRedirect(route('commerce.index'));

        $this->assertDatabaseHas('hr_employees', [
            'company_id' => $user->company_id,
            'full_name' => 'Moussa Traore',
            'department_id' => $department->id,
        ]);
        $this->assertDatabaseHas('payroll_runs', [
            'company_id' => $user->company_id,
            'label' => 'Paie Avril '.now()->year,
            'status' => 'review',
        ]);
        $this->assertDatabaseHas('projects', [
            'company_id' => $user->company_id,
            'name' => 'Ouverture canal B2B Segou',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('production_orders', [
            'company_id' => $user->company_id,
            'item_name' => 'Pack promo Ramadan',
            'status' => 'in_progress',
        ]);
        $this->assertDatabaseHas('commerce_channels', [
            'company_id' => $user->company_id,
            'name' => 'Boutique WhatsApp Bamako',
            'status' => 'active',
        ]);
    }

    public function test_manager_can_pilot_project_execution_tasks(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $project = Project::query()->where('company_id', $user->company_id)->where('name', 'Ouverture canal B2B Mopti')->firstOrFail();

        $this->actingAs($user)->withSession($this->workspaceSession($user));

        $this->post(route('projects.tasks.store', $project), [
            'title' => 'Former les commerciaux Mopti',
            'item_type' => 'task',
            'owner_id' => $user->id,
            'due_date' => now()->addDays(10)->toDateString(),
            'status' => 'in_progress',
            'priority' => 'high',
            'progress' => 35,
            'notes' => 'Formation argumentaire, outils et parcours client.',
        ])->assertRedirect(route('projects.index'));

        $task = ProjectTask::query()
            ->where('company_id', $user->company_id)
            ->where('project_id', $project->id)
            ->where('title', 'Former les commerciaux Mopti')
            ->firstOrFail();

        $this->post(route('projects.tasks.status', $task), [
            'status' => 'done',
        ])->assertRedirect(route('projects.index'));

        $this->assertDatabaseHas('project_tasks', [
            'company_id' => $user->company_id,
            'project_id' => $project->id,
            'title' => 'Former les commerciaux Mopti',
            'status' => 'done',
            'progress' => 100,
        ]);
    }

    public function test_capabilities_endpoint_lists_new_modules(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $plainToken = $this->createApiToken($manager);

        $this->withToken($plainToken)
            ->getJson('/api/v1/platform/capabilities')
            ->assertOk()
            ->assertJsonPath('workspace.company_id', $manager->company_id)
            ->assertJsonPath('catalog.product.edition', 'Growth Foundation')
            ->assertJsonFragment(['path' => '/capital-humain'])
            ->assertJsonFragment(['path' => '/paie'])
            ->assertJsonFragment(['path' => '/projets'])
            ->assertJsonFragment(['path' => '/production'])
            ->assertJsonFragment(['path' => '/commerce-unifie']);
    }

    private function createApiToken(User $user): string
    {
        $plainToken = 'nema_growth_api_token_'.$user->id;

        ApiToken::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'name' => 'Growth foundation test API',
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addDay(),
            'created_by' => $user->id,
        ]);

        return $plainToken;
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
