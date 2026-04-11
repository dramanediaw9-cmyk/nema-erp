<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Commerce\Models\CommerceChannel;
use App\Modules\Core\Integrations\Models\ApiToken;
use App\Modules\Core\Integrations\Models\IntegrationConnection;
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
            ->getJson('/api/v1/commerce/channels?search=WhatsApp')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Boutique WhatsApp Bamako')
            ->assertJsonPath('data.0.execution_summary.open_actions', 2);

        $this->withToken($plainToken)
            ->getJson('/api/v1/platform/connections?health_status=critical')
            ->assertOk()
            ->assertJsonPath('data.0.partner_name', 'Nema Middleware')
            ->assertJsonPath('data.0.health_status', 'critical');

        $this->withToken($plainToken)
            ->getJson('/api/v1/platform/deployment-profile')
            ->assertOk()
            ->assertJsonPath('profile.deployment_mode', 'pilot')
            ->assertJsonPath('profile.commercial_offer', 'growth')
            ->assertJsonPath('readiness.lifecycle_stage', 'pilot');

        $this->withToken($plainToken)
            ->getJson('/api/v1/platform/openapi')
            ->assertOk()
            ->assertJsonPath('openapi', '3.0.3')
            ->assertJsonPath('info.title', 'Nema ERP Integrator API')
            ->assertJsonPath('paths./platform/connections.get.summary', 'Lister les connexions partenaires')
            ->assertJsonPath('paths./platform/deployment-profile.get.summary', 'Lire le profil de deploiement');
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

        $commerceResponse = $this->withToken($plainToken)
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

        $commerceChannelId = (int) $commerceResponse->json('id');

        $this->withToken($plainToken)
            ->postJson('/api/v1/commerce/channels/'.$commerceChannelId.'/snapshots', [
                'snapshot_date' => now()->toDateString(),
                'gross_revenue' => 1950000,
                'orders_count' => 74,
                'average_order_value' => 26351,
                'conversion_rate' => 13.8,
                'service_level' => 94.2,
                'failed_orders_count' => 2,
                'failed_payments_count' => 1,
                'notes' => 'Premiere mesure marketplace.',
            ])
            ->assertCreated()
            ->assertJsonPath('gross_revenue', '1950000.00');

        $commerceActionResponse = $this->withToken($plainToken)
            ->postJson('/api/v1/commerce/channels/'.$commerceChannelId.'/actions', [
                'title' => 'Stabiliser le mapping stock marketplace',
                'owner_id' => $manager->id,
                'action_type' => 'fulfillment',
                'status' => 'blocked',
                'impact_level' => 'critical',
                'due_date' => now()->addDays(6)->toDateString(),
                'notes' => 'Flux de stock encore en ecart sur quelques SKU.',
            ])
            ->assertCreated()
            ->assertJsonPath('title', 'Stabiliser le mapping stock marketplace')
            ->assertJsonPath('status', 'blocked');

        $commerceActionId = (int) $commerceActionResponse->json('id');

        $this->withToken($plainToken)
            ->patchJson('/api/v1/commerce/channels/'.$commerceChannelId.'/actions/'.$commerceActionId, [
                'status' => 'done',
            ])
            ->assertOk()
            ->assertJsonPath('id', $commerceActionId)
            ->assertJsonPath('status', 'done');

        $connectionResponse = $this->withToken($plainToken)
            ->postJson('/api/v1/platform/connections', [
                'name' => 'Connecteur data warehouse',
                'partner_name' => 'Fabric Lakehouse',
                'owner_id' => $manager->id,
                'connection_type' => 'bi',
                'sync_mode' => 'outbound',
                'status' => 'draft',
                'health_status' => 'watch',
                'external_reference' => 'fabric-warehouse-nema',
                'last_sync_at' => now()->subDay()->toDateString(),
                'last_health_at' => now()->toDateString(),
                'scope_summary' => 'Ventes, achats, projections stock et marge.',
                'notes' => 'Canal de replication analytique.',
            ])
            ->assertCreated()
            ->assertJsonPath('partner_name', 'Fabric Lakehouse');

        $connectionId = (int) $connectionResponse->json('id');

        $this->withToken($plainToken)
            ->patchJson('/api/v1/platform/connections/'.$connectionId.'/status', [
                'status' => 'active',
                'health_status' => 'healthy',
            ])
            ->assertOk()
            ->assertJsonPath('id', $connectionId)
            ->assertJsonPath('status', 'active')
            ->assertJsonPath('health_status', 'healthy');

        $this->withToken($plainToken)
            ->getJson('/api/v1/platform/connections/'.$connectionId)
            ->assertOk()
            ->assertJsonPath('id', $connectionId)
            ->assertJsonPath('partner_name', 'Fabric Lakehouse');

        $deploymentProfileResponse = $this->withToken($plainToken)
            ->patchJson('/api/v1/platform/deployment-profile', [
                'owner_id' => $manager->id,
                'commercial_offer' => 'enterprise',
                'deployment_mode' => 'hybrid',
                'lifecycle_stage' => 'live',
                'hosting_target' => 'managed_vm',
                'support_tier' => 'mission_critical',
                'monitoring_level' => 'proactive',
                'backup_strategy' => 'verified',
                'update_channel' => 'continuous',
                'target_users' => 120,
                'target_branches' => 7,
                'go_live_target_at' => now()->addDays(20)->toDateString(),
                'last_release_at' => now()->toDateString(),
                'last_backup_verified_at' => now()->toDateString(),
                'last_restore_drill_at' => now()->subDays(5)->toDateString(),
                'notes' => 'Passage a une exploitation hybride avec SLA renforce.',
            ])
            ->assertOk()
            ->assertJsonPath('profile.commercial_offer', 'enterprise')
            ->assertJsonPath('profile.lifecycle_stage', 'live')
            ->assertJsonPath('profile.target_users', 120);

        $this->assertSame('live', $deploymentProfileResponse->json('readiness.lifecycle_stage'));

        $this->withToken($plainToken)
            ->getJson('/api/v1/commerce/channels/'.$commerceChannelId)
            ->assertOk()
            ->assertJsonPath('id', $commerceChannelId)
            ->assertJsonPath('execution_summary.gross_revenue', 1950000)
            ->assertJsonPath('execution_summary.open_actions', 0);

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
        $this->assertDatabaseHas('integration_connections', [
            'company_id' => $manager->company_id,
            'id' => $connectionId,
            'partner_name' => 'Fabric Lakehouse',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('deployment_profiles', [
            'company_id' => $manager->company_id,
            'commercial_offer' => 'enterprise',
            'deployment_mode' => 'hybrid',
            'lifecycle_stage' => 'live',
        ]);
        $this->assertDatabaseHas('commerce_channel_snapshots', [
            'company_id' => $manager->company_id,
            'commerce_channel_id' => $commerceChannelId,
            'gross_revenue' => 1950000.00,
        ]);
        $this->assertDatabaseHas('commerce_channel_actions', [
            'company_id' => $manager->company_id,
            'commerce_channel_id' => $commerceChannelId,
            'title' => 'Stabiliser le mapping stock marketplace',
            'status' => 'done',
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
        $this->assertDatabaseHas('commerce_channel_snapshots', [
            'company_id' => $manager->company_id,
            'gross_revenue' => 2825000.00,
        ]);
        $this->assertDatabaseHas('commerce_channel_actions', [
            'company_id' => $manager->company_id,
            'title' => 'Corriger les echecs Wave sur commandes soir',
            'status' => 'in_progress',
        ]);
        $this->assertDatabaseHas('integration_connections', [
            'company_id' => $manager->company_id,
            'code' => 'INT-0001',
            'partner_name' => 'Microsoft Power BI',
        ]);
        $this->assertDatabaseHas('deployment_profiles', [
            'company_id' => $manager->company_id,
            'commercial_offer' => 'growth',
            'deployment_mode' => 'pilot',
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
