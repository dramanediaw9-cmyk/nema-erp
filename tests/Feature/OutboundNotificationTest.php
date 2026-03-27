<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Company\Models\Setting;
use App\Modules\Core\Notifications\Models\OutboundNotification;
use App\Modules\Partners\Models\Partner;
use App\Modules\Sales\Models\SalesInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutboundNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_outbound_notifications_are_created_for_next_approval_step(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $director = User::query()->where('email', 'dg@nema-erp.test')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $manager->company_id)->firstOrFail();
        $product = Product::query()->where('company_id', $manager->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        Setting::query()->updateOrCreate(
            ['company_id' => $manager->company_id, 'key' => 'approval_notification_channels'],
            ['value' => [
                'email' => ['enabled' => true, 'copy_to' => ''],
                'whatsapp' => ['enabled' => true, 'copy_to' => ''],
            ]]
        );

        $this->actingAs($manager)
            ->withSession([
                'current_company_id' => $manager->company_id,
                'current_branch_id' => $manager->branch_id,
            ])
            ->post(route('sales.store'), [
                'customer_id' => $customer->id,
                'invoice_date' => now()->format('Y-m-d'),
                'notes' => 'OUTBOUND-SALE',
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

        $invoice = SalesInvoice::query()->where('company_id', $manager->company_id)->where('notes', 'OUTBOUND-SALE')->firstOrFail();

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
}
