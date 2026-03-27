<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Company\Models\Setting;
use App\Modules\Partners\Models\Partner;
use App\Modules\Sales\Models\SalesInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_sales_thresholds_can_be_configured_from_settings(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $manager->company_id)->firstOrFail();
        $product = Product::query()->where('company_id', $manager->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        Setting::query()->updateOrCreate(
            ['company_id' => $manager->company_id, 'key' => 'approval_workflows'],
            ['value' => [
                'sales' => ['step2_threshold' => 50000, 'critical_threshold' => 200000],
                'purchases' => ['step2_threshold' => 100000, 'critical_threshold' => 500000],
                'expenses' => ['step2_threshold' => 100000, 'critical_threshold' => 500000],
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
                'notes' => 'CONFIG-APPROVAL-SALE',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'description' => 'Seuil personnalise',
                        'qty' => 1,
                        'unit_price' => 60000,
                    ],
                ],
            ])
            ->assertRedirect();

        $invoice = SalesInvoice::query()->where('company_id', $manager->company_id)->where('notes', 'CONFIG-APPROVAL-SALE')->firstOrFail();

        $this->assertDatabaseHas('approval_steps', [
            'approvable_type' => SalesInvoice::class,
            'approvable_id' => $invoice->id,
            'step_order' => 2,
            'status' => 'pending',
        ]);
    }
}
