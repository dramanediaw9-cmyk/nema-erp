<?php

namespace Tests\Feature;

use App\Modules\Core\Company\Models\Company;
use App\Modules\Core\Company\Models\Setting;
use App\Modules\Core\Integrations\Models\IntegrationEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InboundIntegrationWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_inbound_webhook_endpoint_marks_related_event_as_published_on_success_callback(): void
    {
        $company = Company::query()->where('name', 'Nema Distribution')->firstOrFail();
        $this->configureInboundWebhook($company->id, $company->tenant_id, 'inbound-secret');

        $event = IntegrationEvent::query()->create([
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'aggregate_type' => Company::class,
            'aggregate_id' => (string) $company->id,
            'event_name' => 'sales.invoice.validated',
            'payload' => ['invoice_number' => 'FAC-BKO-TEST-001'],
            'status' => 'failed',
            'available_at' => now()->subMinute(),
            'attempts' => 1,
            'last_error' => 'Ancien echec',
        ]);

        $payload = [
            'integration_event_id' => $event->id,
            'id' => 'ext-success-001',
            'event_name' => 'sales.invoice.validated',
            'status' => 'processed',
            'message' => 'Partner accepted payload',
        ];

        $response = $this->signedWebhookRequest($company, 'inbound-secret', $payload);

        $response->assertAccepted()
            ->assertJsonPath('status', 'accepted')
            ->assertJsonPath('integration_event_id', $event->id);

        $event->refresh();

        $this->assertSame('published', $event->status);
        $this->assertNull($event->available_at);
        $this->assertNull($event->last_error);
        $this->assertNotNull($event->published_at);

        $this->assertDatabaseHas('integration_inbound_webhooks', [
            'company_id' => $company->id,
            'integration_event_id' => $event->id,
            'external_id' => 'ext-success-001',
            'status' => 'accepted',
        ]);
    }

    public function test_inbound_webhook_endpoint_marks_related_event_as_failed_on_failure_callback(): void
    {
        $company = Company::query()->where('name', 'Nema Distribution')->firstOrFail();
        $this->configureInboundWebhook($company->id, $company->tenant_id, 'inbound-secret');

        $event = IntegrationEvent::query()->create([
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'aggregate_type' => Company::class,
            'aggregate_id' => (string) $company->id,
            'event_name' => 'platform.connection.synced',
            'payload' => ['connection' => 'Partner API'],
            'status' => 'processing',
            'available_at' => null,
            'attempts' => 2,
        ]);

        $payload = [
            'integration_event_id' => $event->id,
            'id' => 'ext-fail-001',
            'event_name' => 'platform.connection.synced',
            'status' => 'failed',
            'reason' => 'Partner rejected payload',
        ];

        $response = $this->signedWebhookRequest($company, 'inbound-secret', $payload);

        $response->assertAccepted()
            ->assertJsonPath('status', 'accepted')
            ->assertJsonPath('integration_event_id', $event->id);

        $event->refresh();

        $this->assertSame('failed', $event->status);
        $this->assertStringContainsString('Partner rejected payload', (string) $event->last_error);
        $this->assertNotNull($event->available_at);
    }

    private function configureInboundWebhook(int $companyId, ?int $tenantId, string $secret): void
    {
        Setting::query()->updateOrCreate(
            ['company_id' => $companyId, 'key' => 'integrations'],
            [
                'tenant_id' => $tenantId,
                'value' => [
                    'inbound' => [
                        'enabled' => true,
                        'secret' => $secret,
                        'source_name' => 'partner-gateway',
                    ],
                ],
            ]
        );
    }

    private function signedWebhookRequest(Company $company, string $secret, array $payload)
    {
        $rawBody = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return $this->call(
            'POST',
            route('integrations.webhooks.inbound.receive', $company),
            [],
            [],
            [],
            $this->transformHeadersToServerVars([
                'X-Nema-Signature' => 'sha256='.hash_hmac('sha256', $rawBody, $secret),
                'X-Nema-Event' => (string) ($payload['event_name'] ?? ''),
                'X-Webhook-Source' => 'partner-gateway',
                'CONTENT_TYPE' => 'application/json',
                'Accept' => 'application/json',
            ]),
            $rawBody,
        );
    }
}
