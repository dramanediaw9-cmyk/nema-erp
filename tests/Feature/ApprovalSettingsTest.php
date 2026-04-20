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

    public function test_settings_page_can_persist_sla_hours_for_approval_workflows(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $this->actingAs($manager)
            ->withSession([
                'current_company_id' => $manager->company_id,
                'current_branch_id' => $manager->branch_id,
            ])
            ->put(route('settings.approvals.update'), [
                'workflows' => [
                    'sales' => [
                        'step2_threshold' => 60000,
                        'critical_threshold' => 180000,
                        'step1_sla_hours' => 18,
                        'step2_sla_hours' => 6,
                    ],
                    'purchases' => [
                        'step2_threshold' => 70000,
                        'critical_threshold' => 190000,
                        'step1_sla_hours' => 20,
                        'step2_sla_hours' => 8,
                    ],
                    'expenses' => [
                        'step2_threshold' => 50000,
                        'critical_threshold' => 120000,
                        'step1_sla_hours' => 12,
                        'step2_sla_hours' => 4,
                    ],
                ],
            ])
            ->assertRedirect(route('settings.index'))
            ->assertSessionHas('success');

        $setting = Setting::query()
            ->where('company_id', $manager->company_id)
            ->where('key', 'approval_workflows')
            ->firstOrFail();

        $this->assertSame(18, data_get($setting->value, 'sales.step1_sla_hours'));
        $this->assertSame(6, data_get($setting->value, 'sales.step2_sla_hours'));
        $this->assertSame(20, data_get($setting->value, 'purchases.step1_sla_hours'));
        $this->assertSame(4, data_get($setting->value, 'expenses.step2_sla_hours'));
    }

    public function test_sales_thresholds_can_be_configured_from_settings(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $manager->company_id)->firstOrFail();
        $product = Product::query()->where('company_id', $manager->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        Setting::query()->updateOrCreate(
            ['company_id' => $manager->company_id, 'key' => 'approval_workflows'],
            ['value' => [
                'sales' => ['step2_threshold' => 50000, 'critical_threshold' => 200000, 'step1_sla_hours' => 24, 'step2_sla_hours' => 8],
                'purchases' => ['step2_threshold' => 100000, 'critical_threshold' => 500000, 'step1_sla_hours' => 24, 'step2_sla_hours' => 12],
                'expenses' => ['step2_threshold' => 100000, 'critical_threshold' => 500000, 'step1_sla_hours' => 24, 'step2_sla_hours' => 12],
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
        $this->assertNotNull($invoice->approvalSteps()->where('step_order', 2)->value('due_at'));
    }
}
