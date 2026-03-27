<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Core\Integrations\Models\IntegrationEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpsHealthTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_operations_page_is_accessible_to_company_admin(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $this->actingAs($user)->withSession([
            'current_tenant_id' => $user->tenant_id,
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ]);

        $this->get(route('ops.index'))
            ->assertOk()
            ->assertSee('Sante systeme')
            ->assertSee('Outbox integration');
    }

    public function test_health_check_command_can_store_company_snapshot(): void
    {
        $company = Company::query()->where('name', 'Nema Distribution')->firstOrFail();

        $this->artisan('nema:ops:health-check', [
            '--store' => true,
            '--company' => [$company->id],
        ])->assertExitCode(0);

        $this->assertDatabaseHas('system_health_snapshots', [
            'company_id' => $company->id,
            'scope' => 'company',
        ]);
    }

    public function test_outbox_prune_command_removes_old_published_events_only(): void
    {
        $company = Company::query()->where('name', 'Nema Distribution')->firstOrFail();

        $oldPublished = IntegrationEvent::query()->create([
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'aggregate_type' => Company::class,
            'aggregate_id' => (string) $company->id,
            'event_name' => 'company.synced',
            'payload' => ['company' => $company->name],
            'status' => 'published',
            'available_at' => now()->subDays(40),
            'published_at' => now()->subDays(35),
            'attempts' => 1,
        ]);

        $pending = IntegrationEvent::query()->create([
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'aggregate_type' => Company::class,
            'aggregate_id' => (string) $company->id,
            'event_name' => 'company.pending',
            'payload' => ['company' => $company->name],
            'status' => 'pending',
            'available_at' => now(),
            'attempts' => 0,
        ]);

        $this->artisan('nema:ops:outbox-prune', [
            '--days' => 30,
        ])->assertExitCode(0);

        $this->assertDatabaseMissing('integration_events', ['id' => $oldPublished->id]);
        $this->assertDatabaseHas('integration_events', ['id' => $pending->id]);
    }
}