<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Core\Access\Models\Role;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Core\Company\Models\DocumentSequence;
use App\Modules\Core\Company\Models\PaymentTerm;
use App\Modules\Core\Company\Models\PriceList;
use App\Modules\Core\Company\Models\Setting;
use App\Modules\Core\Company\Models\TaxRule;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Treasury\Models\CashAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyProvisioningTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_platform_admin_creates_an_operational_company_workspace(): void
    {
        $admin = User::query()->where('email', 'admin@nema-erp.test')->firstOrFail();

        $response = $this->actingAs($admin)
            ->withSession([
                'current_tenant_id' => $admin->tenant_id,
                'current_company_id' => $admin->company_id,
                'current_branch_id' => $admin->branch_id,
            ])
            ->post(route('companies.store'), [
                'name' => 'Entreprise Test Opérationnelle',
                'legal_name' => 'Entreprise Test Opérationnelle SARL',
                'currency_code' => 'XOF',
                'is_active' => '1',
            ]);

        $company = Company::query()->where('name', 'Entreprise Test Opérationnelle')->firstOrFail();
        $branch = $company->branches()->where('is_default', true)->firstOrFail();

        $response->assertRedirect(route('onboarding.index'));
        $this->assertSame($company->id, session('current_company_id'));
        $this->assertSame($branch->id, session('current_branch_id'));
        $this->assertTrue(Warehouse::query()->where('company_id', $company->id)->where('is_default', true)->exists());
        $this->assertTrue(CashAccount::query()->where('company_id', $company->id)->where('type', 'cash')->exists());
        $this->assertTrue(Role::query()->where('company_id', $company->id)->where('slug', 'company_admin')->exists());
        $this->assertTrue(Setting::query()->where('company_id', $company->id)->where('key', 'general')->exists());
        $this->assertTrue(Setting::query()->where('company_id', $company->id)->where('key', 'sector_onboarding')->exists());
        $this->assertTrue(PaymentTerm::query()->where('company_id', $company->id)->where('is_default', true)->exists());
        $this->assertTrue(PriceList::query()->where('company_id', $company->id)->where('is_default', true)->exists());
        $this->assertTrue(TaxRule::query()->where('company_id', $company->id)->where('code', 'TVA18')->exists());
        $this->assertGreaterThanOrEqual(12, AccountingPeriod::query()->where('company_id', $company->id)->count());
        $this->assertGreaterThanOrEqual(19, DocumentSequence::query()->where('company_id', $company->id)->count());
        $this->assertGreaterThanOrEqual(20, Account::query()->where('company_id', $company->id)->count());
    }
}
