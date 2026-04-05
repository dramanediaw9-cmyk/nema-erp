<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Integrations\Models\ApiToken;
use App\Modules\Core\Integrations\Models\IntegrationEvent;
use App\Modules\Core\Integrations\Models\IntegrationEventDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiV1IntegrationEventTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_api_token_can_show_and_filter_integration_events(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $plainToken = $this->createApiToken($manager);

        $event = IntegrationEvent::query()->create([
            'tenant_id' => $manager->tenant_id,
            'company_id' => $manager->company_id,
            'aggregate_type' => 'App\\Modules\\Sales\\Models\\SalesInvoice',
            'aggregate_id' => '42',
            'event_name' => 'sales.invoice.validated.api_test',
            'payload' => ['invoice_number' => 'FAC-BKO-2026-42424'],
            'status' => 'published',
            'available_at' => now()->subMinutes(10),
            'published_at' => now()->subMinutes(9),
            'attempts' => 1,
        ]);

        IntegrationEventDelivery::query()->create([
            'integration_event_id' => $event->id,
            'company_id' => $manager->company_id,
            'channel' => 'webhook',
            'target_url' => 'https://partner.test/nema/events',
            'status' => 'sent',
            'attempt_number' => 1,
            'requested_at' => now()->subMinutes(10),
            'responded_at' => now()->subMinutes(9),
            'request_payload' => ['id' => $event->id],
            'response_status' => 200,
            'response_headers' => ['content-type' => ['application/json']],
            'response_body' => '{"ok":true}',
        ]);

        $this->withToken($plainToken)
            ->getJson('/api/v1/integration-events?status=published&search=sales.invoice.validated.api_test')
            ->assertOk()
            ->assertJsonPath('data.0.id', $event->id)
            ->assertJsonPath('data.0.event_name', 'sales.invoice.validated.api_test')
            ->assertJsonPath('data.0.latest_delivery.status', 'sent')
            ->assertJsonPath('data.0.latest_delivery.response_status', 200);

        $this->withToken($plainToken)
            ->getJson('/api/v1/integration-events/'.$event->id)
            ->assertOk()
            ->assertJsonPath('id', $event->id)
            ->assertJsonPath('deliveries.0.target_url', 'https://partner.test/nema/events')
            ->assertJsonPath('deliveries.0.status', 'sent');
    }

    private function createApiToken(User $user): string
    {
        $plainToken = 'nema_test_integration_api_token_'.$user->id;

        ApiToken::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'name' => 'Test API Integration',
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addDay(),
            'created_by' => $user->id,
        ]);

        return $plainToken;
    }
}

