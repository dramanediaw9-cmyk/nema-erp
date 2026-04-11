<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Integrations\Models\ApiToken;
use App\Modules\Hr\Models\HrDepartment;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiV1GrowthModuleTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_api_token_can_list_seeded_growth_resources(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $plainToken = $this->createApiToken($manager);

        $this->withToken($plainToken)
            ->getJson('/api/v1/hr/departments?search=Operations')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'DEP-0001');

        $this->withToken($plainToken)
            ->getJson('/api/v1/hr/employees?search=Awa')
            ->assertOk()
            ->assertJsonPath('data.0.full_name', 'Awa Diallo');

        $this->withToken($plainToken)
            ->getJson('/api/v1/payroll/runs?status=review')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'review');

        $this->withToken($plainToken)
            ->getJson('/api/v1/projects?status=active')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Ouverture canal B2B Mopti')
            ->assertJsonPath('data.0.execution_summary.total', 4);

        $this->withToken($plainToken)
            ->getJson('/api/v1/production-orders?status=in_progress')
            ->assertOk()
            ->assertJsonPath('data.0.item_name', 'Kit promo Ramadan');

        $this->withToken($plainToken)
            ->getJson('/api/v1/commerce/channels?channel_type=mobile')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Boutique WhatsApp Bamako');
    }

    public function test_api_token_can_show_and_create_growth_records(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $plainToken = $this->createApiToken($manager);

        $department = HrDepartment::query()->where('company_id', $manager->company_id)->where('code', 'DEP-0001')->firstOrFail();
        $this->withToken($plainToken)
            ->getJson('/api/v1/hr/departments/'.$department->id)
            ->assertOk()
            ->assertJsonPath('id', $department->id)
            ->assertJsonPath('name', 'Operations retail');

        $newDepartment = $this->withToken($plainToken)
            ->postJson('/api/v1/hr/departments', [
                'name' => 'Service qualite',
                'manager_name' => 'Responsable qualite',
                'status' => 'active',
                'headcount_target' => 4,
            ])
            ->assertCreated()
            ->assertJsonPath('name', 'Service qualite')
            ->json('id');

        $this->withToken($plainToken)
            ->postJson('/api/v1/hr/employees', [
                'full_name' => 'Fatoumata Keita',
                'email' => 'fatoumata.keita@example.test',
                'department_id' => $newDepartment,
                'job_title' => 'Controleuse qualite',
                'hire_date' => now()->toDateString(),
                'status' => 'active',
                'payroll_cycle' => 'monthly',
                'base_salary' => 240000,
            ])
            ->assertCreated()
            ->assertJsonPath('full_name', 'Fatoumata Keita');

        $this->withToken($plainToken)
            ->postJson('/api/v1/payroll/runs', [
                'label' => 'Paie API Mai '.now()->year,
                'period_start' => now()->startOfMonth()->toDateString(),
                'period_end' => now()->endOfMonth()->toDateString(),
                'scheduled_pay_date' => now()->endOfMonth()->toDateString(),
                'headcount' => 3,
                'gross_amount' => 900000,
                'net_amount' => 735000,
                'status' => 'draft',
            ])
            ->assertCreated()
            ->assertJsonPath('label', 'Paie API Mai '.now()->year);

        $projectResponse = $this->withToken($plainToken)
            ->postJson('/api/v1/projects', [
                'name' => 'Extension retail Kayes',
                'customer_name' => 'Kayes Superette',
                'owner_id' => $manager->id,
                'start_date' => now()->toDateString(),
                'target_end_date' => now()->addMonth()->toDateString(),
                'status' => 'planning',
                'progress' => 5,
                'budget_amount' => 2200000,
            ])
            ->assertCreated()
            ->assertJsonPath('name', 'Extension retail Kayes');

        $projectId = (int) $projectResponse->json('id');

        $this->withToken($plainToken)
            ->getJson('/api/v1/projects/'.$projectId)
            ->assertOk()
            ->assertJsonPath('id', $projectId)
            ->assertJsonPath('customer_name', 'Kayes Superette')
            ->assertJsonPath('execution_summary.total', 0);

        $projectTaskResponse = $this->withToken($plainToken)
            ->postJson('/api/v1/projects/'.$projectId.'/tasks', [
                'title' => 'Signer le planning de lancement Kayes',
                'item_type' => 'milestone',
                'owner_id' => $manager->id,
                'due_date' => now()->addDays(12)->toDateString(),
                'status' => 'in_progress',
                'priority' => 'critical',
                'progress' => 30,
                'notes' => 'Validation du retroplanning avec les relais grossistes.',
            ])
            ->assertCreated()
            ->assertJsonPath('title', 'Signer le planning de lancement Kayes')
            ->assertJsonPath('status', 'in_progress');

        $projectTaskId = (int) $projectTaskResponse->json('id');

        $this->withToken($plainToken)
            ->patchJson('/api/v1/projects/'.$projectId.'/tasks/'.$projectTaskId, [
                'status' => 'done',
            ])
            ->assertOk()
            ->assertJsonPath('id', $projectTaskId)
            ->assertJsonPath('status', 'done')
            ->assertJsonPath('progress', 100);

        $this->withToken($plainToken)
            ->postJson('/api/v1/production-orders', [
                'item_name' => 'Pack cereales famille',
                'planned_quantity' => 250,
                'completed_quantity' => 25,
                'planned_start_date' => now()->toDateString(),
                'due_date' => now()->addDays(10)->toDateString(),
                'status' => 'planned',
                'routing_stage' => 'assembly',
            ])
            ->assertCreated()
            ->assertJsonPath('item_name', 'Pack cereales famille');

        $this->withToken($plainToken)
            ->postJson('/api/v1/commerce/channels', [
                'name' => 'Marketplace B2C test',
                'channel_type' => 'marketplace',
                'status' => 'pipeline',
                'connector_name' => 'Jumia Sync',
                'settlement_mode' => 'mixed',
                'target_monthly_revenue' => 1800000,
            ])
            ->assertCreated()
            ->assertJsonPath('name', 'Marketplace B2C test');

        $this->assertDatabaseHas('hr_departments', [
            'company_id' => $manager->company_id,
            'name' => 'Service qualite',
        ]);
        $this->assertDatabaseHas('hr_employees', [
            'company_id' => $manager->company_id,
            'full_name' => 'Fatoumata Keita',
        ]);
        $this->assertDatabaseHas('payroll_runs', [
            'company_id' => $manager->company_id,
            'label' => 'Paie API Mai '.now()->year,
        ]);
        $this->assertDatabaseHas('projects', [
            'company_id' => $manager->company_id,
            'name' => 'Extension retail Kayes',
        ]);
        $this->assertDatabaseHas('project_tasks', [
            'company_id' => $manager->company_id,
            'project_id' => $projectId,
            'title' => 'Signer le planning de lancement Kayes',
            'status' => 'done',
        ]);
        $this->assertDatabaseHas('production_orders', [
            'company_id' => $manager->company_id,
            'item_name' => 'Pack cereales famille',
        ]);
        $this->assertDatabaseHas('commerce_channels', [
            'company_id' => $manager->company_id,
            'name' => 'Marketplace B2C test',
        ]);
    }

    public function test_database_seeder_populates_growth_foundation_records(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $this->assertDatabaseHas('hr_departments', [
            'company_id' => $manager->company_id,
            'code' => 'DEP-0001',
            'name' => 'Operations retail',
        ]);
        $this->assertDatabaseHas('hr_employees', [
            'company_id' => $manager->company_id,
            'employee_number' => 'EMP-2026-00001',
            'full_name' => 'Awa Diallo',
        ]);
        $this->assertDatabaseHas('payroll_runs', [
            'company_id' => $manager->company_id,
            'status' => 'review',
        ]);
        $this->assertDatabaseHas('projects', [
            'company_id' => $manager->company_id,
            'name' => 'Ouverture canal B2B Mopti',
        ]);
        $this->assertDatabaseHas('project_tasks', [
            'company_id' => $manager->company_id,
            'title' => 'Securiser le stock tampon Mopti',
            'status' => 'blocked',
        ]);
        $this->assertDatabaseHas('production_orders', [
            'company_id' => $manager->company_id,
            'item_name' => 'Kit promo Ramadan',
        ]);
        $this->assertDatabaseHas('commerce_channels', [
            'company_id' => $manager->company_id,
            'name' => 'Boutique WhatsApp Bamako',
        ]);
    }

    private function createApiToken(User $user): string
    {
        $plainToken = 'nema_growth_module_api_token_'.$user->id;

        ApiToken::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'name' => 'Growth module test API',
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addDay(),
            'created_by' => $user->id,
        ]);

        return $plainToken;
    }
}
