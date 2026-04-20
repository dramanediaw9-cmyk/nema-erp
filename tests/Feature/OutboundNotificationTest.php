<?php

namespace Tests\Feature;

use App\Mail\OutboundApprovalMail;
use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Company\Models\Setting;
use App\Modules\Core\Notifications\Models\OutboundNotification;
use App\Modules\Partners\Models\Partner;
use App\Modules\Sales\Models\SalesInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OutboundNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_outbound_notifications_are_created_for_next_approval_step(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $director = User::query()->where('email', 'dg@nema-erp.test')->firstOrFail();

        $this->configureChannels($manager->company_id, true, true);
        $invoice = $this->createPendingApprovalInvoice($manager, 'OUTBOUND-SALE');

        $this->assertDatabaseHas('outbound_notifications', [
            'company_id' => $manager->company_id,
            'channel' => 'email',
            'recipient' => $director->email,
            'resource_type' => SalesInvoice::class,
            'resource_id' => $invoice->id,
            'step_order' => 2,
            'status' => 'queued',
        ]);

        $this->assertDatabaseHas('outbound_notifications', [
            'company_id' => $manager->company_id,
            'channel' => 'whatsapp',
            'recipient' => $director->phone,
            'resource_type' => SalesInvoice::class,
            'resource_id' => $invoice->id,
            'step_order' => 2,
            'status' => 'queued',
        ]);

        $this->assertSame(2, OutboundNotification::query()->where('resource_type', SalesInvoice::class)->where('resource_id', $invoice->id)->count());
    }

    public function test_dispatch_command_sends_queued_email_notifications(): void
    {
        Mail::fake();

        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $director = User::query()->where('email', 'dg@nema-erp.test')->firstOrFail();

        $this->configureChannels($manager->company_id, true, false);
        $invoice = $this->createPendingApprovalInvoice($manager, 'OUTBOUND-EMAIL-DISPATCH');

        $this->artisan('nema:notifications:dispatch-outbound', [
            '--company' => [$manager->company_id],
            '--limit' => 10,
        ])->assertExitCode(0);

        Mail::assertSent(OutboundApprovalMail::class, function (OutboundApprovalMail $mail) use ($director): bool {
            return $mail->hasTo($director->email);
        });

        $this->assertDatabaseHas('outbound_notifications', [
            'company_id' => $manager->company_id,
            'channel' => 'email',
            'resource_type' => SalesInvoice::class,
            'resource_id' => $invoice->id,
            'status' => 'sent',
        ]);
    }

    public function test_dispatch_command_posts_whatsapp_notifications(): void
    {
        Http::fake([
            'https://whatsapp.test/webhook' => Http::response(['id' => 'wa-123'], 202),
        ]);

        config([
            'services.whatsapp.webhook_url' => 'https://whatsapp.test/webhook',
            'services.whatsapp.api_token' => 'secret-token',
            'services.whatsapp.from' => 'NEMA-ERP',
            'services.whatsapp.timeout' => 10,
        ]);

        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $director = User::query()->where('email', 'dg@nema-erp.test')->firstOrFail();

        $this->configureChannels($manager->company_id, false, true);
        $invoice = $this->createPendingApprovalInvoice($manager, 'OUTBOUND-WHATSAPP-DISPATCH');

        $this->artisan('nema:notifications:dispatch-outbound', [
            '--company' => [$manager->company_id],
            '--limit' => 10,
        ])->assertExitCode(0);

        Http::assertSent(function (HttpRequest $request) use ($director, $invoice): bool {
            $payload = $request->data();

            return $request->url() === 'https://whatsapp.test/webhook'
                && $request->method() === 'POST'
                && ($payload['to'] ?? null) === $director->phone
                && ($payload['notification_id'] ?? null) !== null
                && ($payload['metadata']['resource_id'] ?? null) === $invoice->id;
        });

        $notification = OutboundNotification::query()
            ->where('company_id', $manager->company_id)
            ->where('channel', 'whatsapp')
            ->where('resource_type', SalesInvoice::class)
            ->where('resource_id', $invoice->id)
            ->firstOrFail();

        $this->assertSame('sent', $notification->status);
        $this->assertSame('wa-123', data_get($notification->meta, 'delivery.reference'));
    }

    public function test_dispatch_claims_whatsapp_notification_before_delivery(): void
    {
        Http::fake([
            'https://whatsapp.test/claim' => function (HttpRequest $request) {
                $notificationId = $request->data()['notification_id'] ?? null;
                $notification = OutboundNotification::query()->findOrFail($notificationId);

                $this->assertSame('processing', $notification->status);

                return Http::response(['id' => 'wa-claim-123'], 202);
            },
        ]);

        config([
            'services.whatsapp.webhook_url' => 'https://whatsapp.test/claim',
            'services.whatsapp.api_token' => 'secret-token',
            'services.whatsapp.from' => 'NEMA-ERP',
            'services.whatsapp.timeout' => 10,
        ]);

        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $this->configureChannels($manager->company_id, false, true);
        $invoice = $this->createPendingApprovalInvoice($manager, 'OUTBOUND-WHATSAPP-CLAIM');

        $this->artisan('nema:notifications:dispatch-outbound', [
            '--company' => [$manager->company_id],
            '--limit' => 10,
        ])->assertExitCode(0);

        $notification = OutboundNotification::query()
            ->where('company_id', $manager->company_id)
            ->where('channel', 'whatsapp')
            ->where('resource_type', SalesInvoice::class)
            ->where('resource_id', $invoice->id)
            ->firstOrFail();

        $this->assertSame('sent', $notification->status);
        $this->assertSame('wa-claim-123', data_get($notification->meta, 'delivery.reference'));
    }

    public function test_dispatch_command_marks_whatsapp_notifications_as_failed_when_webhook_is_missing(): void
    {
        config([
            'services.whatsapp.webhook_url' => null,
            'services.whatsapp.api_token' => null,
            'services.whatsapp.from' => null,
        ]);

        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $this->configureChannels($manager->company_id, false, true);
        $invoice = $this->createPendingApprovalInvoice($manager, 'OUTBOUND-WHATSAPP-FAIL');

        $this->artisan('nema:notifications:dispatch-outbound', [
            '--company' => [$manager->company_id],
            '--limit' => 10,
        ])->assertExitCode(0);

        $notification = OutboundNotification::query()
            ->where('company_id', $manager->company_id)
            ->where('channel', 'whatsapp')
            ->where('resource_type', SalesInvoice::class)
            ->where('resource_id', $invoice->id)
            ->firstOrFail();

        $this->assertSame('failed', $notification->status);
        $this->assertStringContainsString('webhook WhatsApp', (string) $notification->failure_reason);
    }

    public function test_settings_manager_can_requeue_failed_notifications_from_outbound_page(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        OutboundNotification::query()->create([
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'user_id' => $manager->id,
            'code' => 'failed-outbound-requeue',
            'channel' => 'email',
            'recipient' => 'dg@example.test',
            'subject' => 'Reprise outbound',
            'message' => 'Une notification en echec.',
            'status' => 'failed',
            'failed_at' => now(),
            'failure_reason' => 'SMTP timeout',
            'queued_at' => now()->subMinutes(5),
        ]);

        $this->actingAs($manager)
            ->withSession([
                'current_company_id' => $manager->company_id,
                'current_branch_id' => $manager->branch_id,
            ])
            ->post(route('notifications.outbound.retry-failed'))
            ->assertRedirect(route('notifications.outbound.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('outbound_notifications', [
            'company_id' => $manager->company_id,
            'code' => 'failed-outbound-requeue',
            'status' => 'queued',
            'failure_reason' => null,
        ]);
    }

    private function configureChannels(int $companyId, bool $emailEnabled, bool $whatsAppEnabled): void
    {
        Setting::query()->updateOrCreate(
            ['company_id' => $companyId, 'key' => 'approval_notification_channels'],
            ['value' => [
                'email' => ['enabled' => $emailEnabled, 'copy_to' => ''],
                'whatsapp' => ['enabled' => $whatsAppEnabled, 'copy_to' => ''],
            ]]
        );
    }

    private function createPendingApprovalInvoice(User $manager, string $note): SalesInvoice
    {
        $customer = Partner::query()->customers()->where('company_id', $manager->company_id)->firstOrFail();
        $product = Product::query()->where('company_id', $manager->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $this->actingAs($manager)
            ->withSession([
                'current_company_id' => $manager->company_id,
                'current_branch_id' => $manager->branch_id,
            ])
            ->post(route('sales.store'), [
                'customer_id' => $customer->id,
                'invoice_date' => now()->format('Y-m-d'),
                'notes' => $note,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'description' => 'Notification externe',
                        'qty' => 1,
                        'unit_price' => 120000,
                    ],
                ],
            ])
            ->assertRedirect();

        return SalesInvoice::query()->where('company_id', $manager->company_id)->where('notes', $note)->firstOrFail();
    }
}
