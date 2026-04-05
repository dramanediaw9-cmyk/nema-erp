<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Core\Company\Models\Setting;
use App\Modules\Core\Integrations\Models\IntegrationEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IntegrationOutboxDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_dispatch_command_publishes_pending_event_and_logs_delivery(): void
    {
        $company = Company::query()->where('name', 'Nema Distribution')->firstOrFail();
        $this->configureWebhook($company, true, 'https://partner.test/nema/webhooks');

        $event = IntegrationEvent::query()->create([
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'aggregate_type' => Company::class,
            'aggregate_id' => (string) $company->id,
            'event_name' => 'sales.invoice.validated',
            'payload' => ['invoice_number' => 'FAC-BKO-2026-00001'],
            'status' => 'pending',
            'available_at' => now()->subMinute(),
            'attempts' => 0,
        ]);

        Http::fake([
            'https://partner.test/nema/webhooks' => Http::response(['received' => true], 202),
        ]);

        $this->artisan('nema:integrations:dispatch-outbox', [
            '--company' => [$company->id],
            '--limit' => 10,
        ])->assertExitCode(0);

        $event->refresh();

        $this->assertSame('published', $event->status);
        $this->assertSame(1, $event->attempts);
        $this->assertNotNull($event->published_at);
        $this->assertNull($event->last_error);

        Http::assertSent(function ($request) use ($event) {
            return $request->url() === 'https://partner.test/nema/webhooks'
                && $request['id'] === $event->id
                && $request['event_name'] === 'sales.invoice.validated'
                && $request->hasHeader('X-Nema-Event-Id', (string) $event->id)
                && $request->hasHeader('X-Nema-Event', 'sales.invoice.validated');
        });

        $this->assertDatabaseHas('integration_event_deliveries', [
            'integration_event_id' => $event->id,
            'company_id' => $company->id,
            'status' => 'sent',
            'response_status' => 202,
        ]);
    }

    public function test_dispatch_command_marks_event_as_failed_when_webhook_url_is_missing(): void
    {
        $company = Company::query()->where('name', 'Nema Distribution')->firstOrFail();
        $this->configureWebhook($company, true, '');

        $event = IntegrationEvent::query()->create([
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'aggregate_type' => Company::class,
            'aggregate_id' => (string) $company->id,
            'event_name' => 'company.export.failed',
            'payload' => ['company' => $company->name],
            'status' => 'pending',
            'available_at' => now()->subMinute(),
            'attempts' => 0,
        ]);

        Http::fake();

        $this->artisan('nema:integrations:dispatch-outbox', [
            '--company' => [$company->id],
            '--limit' => 10,
        ])->assertExitCode(0);

        $event->refresh();

        $this->assertSame('failed', $event->status);
        $this->assertSame(1, $event->attempts);
        $this->assertStringContainsString('aucune URL', (string) $event->last_error);

        Http::assertNothingSent();

        $this->assertDatabaseHas('integration_event_deliveries', [
            'integration_event_id' => $event->id,
            'company_id' => $company->id,
            'status' => 'failed',
        ]);
    }

    public function test_manager_can_process_outbox_from_operations_screen(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $company = Company::query()->whereKey($user->company_id)->firstOrFail();
        $this->configureWebhook($company, true, 'https://partner.test/nema/manual');

        $event = IntegrationEvent::query()->create([
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'aggregate_type' => Company::class,
            'aggregate_id' => (string) $company->id,
            'event_name' => 'treasury.customer_receipt.recorded',
            'payload' => ['payment_number' => 'ENC-BKO-2026-00099'],
            'status' => 'pending',
            'available_at' => now()->subMinute(),
            'attempts' => 0,
        ]);

        Http::fake([
            'https://partner.test/nema/manual' => Http::response(['received' => true], 200),
        ]);

        $this->actingAs($user)->withSession([
            'current_tenant_id' => $user->tenant_id,
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ]);

        $this->from(route('ops.index'))
            ->post(route('ops.outbox.process'))
            ->assertRedirect(route('ops.index'));

        $event->refresh();

        $this->assertSame('published', $event->status);
        $this->assertDatabaseHas('integration_event_deliveries', [
            'integration_event_id' => $event->id,
            'status' => 'sent',
            'response_status' => 200,
        ]);
    }

    private function configureWebhook(Company $company, bool $enabled, string $url): void
    {
        Setting::query()->updateOrCreate(
            ['company_id' => $company->id, 'key' => 'integrations'],
            [
                'tenant_id' => $company->tenant_id,
                'value' => [
                    'webhook' => [
                        'enabled' => $enabled,
                        'url' => $url,
                        'secret' => 'shared-secret',
                        'timeout' => 5,
                    ],
                ],
            ]
        );
    }
}
