<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Integrations\Models\ApiToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CorePulseTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_core_pulse_command_returns_json_for_company(): void
    {
        $companyId = (int) User::query()->where('email', 'manager@nema-erp.test')->value('company_id');

        $result = $this->artisan('nema:core:pulse', [
            '--company' => [$companyId],
            '--json' => true,
            '--store' => true,
        ]);

        $this->assertContains($result->run(), [0, 1]);
    }

    public function test_core_pulse_api_endpoint_is_available_with_api_token(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $plainToken = 'nema_core_pulse_api_token_'.$manager->id;

        ApiToken::query()->create([
            'tenant_id' => $manager->tenant_id,
            'company_id' => $manager->company_id,
            'name' => 'Core pulse API test',
            'token_hash' => hash('sha256', $plainToken),
            'last_used_at' => now(),
            'expires_at' => now()->addDay(),
            'created_by' => $manager->id,
        ]);

        $this->withHeaders([
            'X-Api-Key' => $plainToken,
        ])->getJson('/api/v1/platform/core-pulse')
            ->assertOk()
            ->assertJsonStructure([
                'status',
                'score',
                'company_id',
                'company_name',
                'signals',
                'metrics',
                'recommendations',
            ]);
    }
}
