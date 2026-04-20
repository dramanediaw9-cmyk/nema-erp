<?php

namespace Tests\Feature;

use App\Modules\Core\Approvals\Models\ApprovalStep;
use App\Modules\Core\Automation\Services\AutomationEngineService;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Core\Company\Models\Setting;
use App\Modules\Core\Company\Models\Tenant;
use App\Modules\Core\Integrations\Services\IntegrationOutboxService;
use App\Modules\Core\Platform\Services\CorePulseService;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Expenses\Models\ExpenseCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoreKernelHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_core_pulse_store_persists_snapshot_and_returns_current_history(): void
    {
        $company = Company::query()->where('name', 'Nema Distribution')->firstOrFail();

        $summary = app(CorePulseService::class)->summary($company, true);

        $this->assertDatabaseHas('core_pulse_snapshots', [
            'company_id' => $company->id,
            'status' => $summary['status'],
            'score' => $summary['score'],
        ]);
        $this->assertNotEmpty($summary['history']);
        $this->assertSame($summary['score'], data_get($summary, 'history.0.score'));
    }

    public function test_outbox_webhook_configuration_update_preserves_existing_inbound_settings(): void
    {
        $company = Company::query()->where('name', 'Nema Distribution')->firstOrFail();

        Setting::query()->updateOrCreate(
            ['company_id' => $company->id, 'key' => 'integrations'],
            [
                'tenant_id' => $company->tenant_id,
                'value' => [
                    'inbound' => [
                        'enabled' => true,
                        'secret' => 'keep-this-secret',
                        'source_name' => 'legacy-partner',
                    ],
                ],
            ]
        );

        app(IntegrationOutboxService::class)->updateConfiguration($company->id, $company->tenant_id, [
            'enabled' => true,
            'url' => 'https://partner.example.test/webhook',
            'secret' => 'shared-secret',
            'timeout' => 7,
        ]);

        $setting = Setting::query()
            ->where('company_id', $company->id)
            ->where('key', 'integrations')
            ->firstOrFail();

        $this->assertTrue((bool) data_get($setting->value, 'inbound.enabled'));
        $this->assertSame('keep-this-secret', data_get($setting->value, 'inbound.secret'));
        $this->assertSame('legacy-partner', data_get($setting->value, 'inbound.source_name'));
        $this->assertTrue((bool) data_get($setting->value, 'webhook.enabled'));
        $this->assertSame('https://partner.example.test/webhook', data_get($setting->value, 'webhook.url'));
        $this->assertSame(7, data_get($setting->value, 'webhook.timeout'));
    }

    public function test_stale_approval_signal_respects_branch_scope(): void
    {
        $tenant = Tenant::query()->create([
            'code' => 'tenant-kernel',
            'name' => 'Tenant Kernel',
            'slug' => 'tenant-kernel',
            'is_active' => true,
        ]);

        $company = Company::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Kernel Test Company',
            'legal_name' => 'Kernel Test Company SARL',
            'currency_code' => 'XOF',
            'is_active' => true,
        ]);

        $branchA = Branch::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'name' => 'Bamako',
            'code' => 'BKO-K',
            'city' => 'Bamako',
            'address' => 'ACI 2000',
            'is_active' => true,
            'is_default' => true,
        ]);

        $branchB = Branch::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'name' => 'Sikasso',
            'code' => 'SIK-K',
            'city' => 'Sikasso',
            'address' => 'Centre',
            'is_active' => true,
            'is_default' => false,
        ]);

        $category = ExpenseCategory::query()->create([
            'company_id' => $company->id,
            'name' => 'Charges noyau',
            'is_active' => true,
        ]);

        $this->createStaleExpenseApproval($company, $branchA, $category, 'EXP-K-001');
        $this->createStaleExpenseApproval($company, $branchB, $category, 'EXP-K-002');

        $service = app(AutomationEngineService::class);

        $global = $service->evaluateSignal($company->id, 'approvals.stale_steps', 1, 48, null);
        $branchAOnly = $service->evaluateSignal($company->id, 'approvals.stale_steps', 1, 48, $branchA->id);
        $branchBOnly = $service->evaluateSignal($company->id, 'approvals.stale_steps', 1, 48, $branchB->id);

        $this->assertSame(2, $global['value']);
        $this->assertSame(1, $branchAOnly['value']);
        $this->assertSame(1, $branchBOnly['value']);
        $this->assertSame($branchA->id, data_get($branchAOnly, 'details.branch_id'));
        $this->assertSame($branchB->id, data_get($branchBOnly, 'details.branch_id'));
    }

    private function createStaleExpenseApproval(Company $company, Branch $branch, ExpenseCategory $category, string $expenseNumber): void
    {
        $expense = Expense::query()->create([
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'expense_category_id' => $category->id,
            'expense_number' => $expenseNumber,
            'expense_date' => now()->subDays(3)->toDateString(),
            'description' => 'Depense de test automation',
            'total' => 25000,
            'status' => 'pending_approval',
            'payment_status' => 'unpaid',
        ]);

        $step = ApprovalStep::query()->create([
            'company_id' => $company->id,
            'approvable_type' => $expense::class,
            'approvable_id' => $expense->id,
            'module' => 'expenses',
            'step_order' => 1,
            'code' => 'operational_review',
            'label' => 'Validation operationnelle',
            'rule' => 'module_approver',
            'status' => 'pending',
        ]);

        $step->forceFill([
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ])->saveQuietly();
    }
}
