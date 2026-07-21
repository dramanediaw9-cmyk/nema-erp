<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\ProductCategory;
use App\Modules\Core\Company\Models\PaymentTerm;
use App\Modules\Core\Company\Models\PriceList;
use App\Modules\Core\Company\Models\Setting;
use App\Modules\Expenses\Models\ExpenseCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SectorOnboardingStarterTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_manager_can_apply_sector_starter_pack_from_onboarding(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        Setting::query()->updateOrCreate(
            ['company_id' => $manager->company_id, 'key' => 'sector_profile'],
            ['value' => ['profile' => 'hardware_store']]
        );

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->post(route('onboarding.sector-starter.apply'))
            ->assertRedirect();

        $this->assertDatabaseHas('product_categories', [
            'company_id' => $manager->company_id,
            'name' => 'Outillage',
        ]);

        $this->assertDatabaseHas('expense_categories', [
            'company_id' => $manager->company_id,
            'name' => 'Transport chantier',
        ]);

        $this->assertDatabaseHas('price_lists', [
            'company_id' => $manager->company_id,
            'code' => 'CHANTIER',
        ]);

        $this->assertDatabaseHas('payment_terms', [
            'company_id' => $manager->company_id,
            'code' => 'TERM-15',
        ]);

        $starterSetting = Setting::query()->where('company_id', $manager->company_id)->where('key', 'sector_onboarding')->firstOrFail();
        $gatewaySetting = Setting::query()->where('company_id', $manager->company_id)->where('key', 'payment_gateways')->firstOrFail();

        $this->assertSame('hardware_store', $starterSetting->value['profile'] ?? null);
        $this->assertContains('Unite', $starterSetting->value['units'] ?? []);
        $this->assertTrue((bool) data_get($gatewaySetting->value, 'orange_money.enabled'));
        $this->assertTrue((bool) data_get($gatewaySetting->value, 'bank_transfer.enabled'));

        $this->assertDatabaseHas('activity_logs', [
            'company_id' => $manager->company_id,
            'user_id' => $manager->id,
            'action' => 'onboarding.sector_starter.apply',
        ]);
    }

    public function test_onboarding_page_shows_sector_profile_and_starter_status(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        Setting::query()->updateOrCreate(
            ['company_id' => $manager->company_id, 'key' => 'sector_profile'],
            ['value' => ['profile' => 'cosmetics_beauty']]
        );

        Setting::query()->updateOrCreate(
            ['company_id' => $manager->company_id, 'key' => 'sector_onboarding'],
            ['value' => ['profile' => 'cosmetics_beauty', 'applied_at' => now()->toDateTimeString(), 'units' => ['Unite', 'Pack', 'Coffret']]]
        );

        ProductCategory::query()->firstOrCreate([
            'company_id' => $manager->company_id,
            'name' => 'Parfums',
        ], [
            'tenant_id' => $manager->tenant_id,
            'description' => 'Categorie test',
            'is_active' => true,
        ]);

        ExpenseCategory::query()->firstOrCreate([
            'company_id' => $manager->company_id,
            'name' => 'Marketing boutique',
        ], [
            'description' => 'Categorie test',
            'default_account_code' => '623400',
            'is_active' => true,
        ]);

        PaymentTerm::query()->firstOrCreate([
            'company_id' => $manager->company_id,
            'code' => 'CPT',
        ], [
            'tenant_id' => $manager->tenant_id,
            'name' => 'Comptant',
            'days' => 0,
            'description' => 'Paiement immediat',
            'is_default' => true,
            'is_active' => true,
        ]);

        PriceList::query()->firstOrCreate([
            'company_id' => $manager->company_id,
            'code' => 'PROMO',
        ], [
            'tenant_id' => $manager->tenant_id,
            'name' => 'Tarif promo',
            'currency_code' => 'XOF',
            'description' => 'Tarif promotionnel',
            'is_default' => false,
            'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->get(route('onboarding.index'))
            ->assertOk()
            ->assertSee('Metier actif')
            ->assertSee('Salon de coiffure')
            ->assertSee('Starter pack applique')
            ->assertSee('Appliquer le starter pack')
            ->assertSee('Configuration de depart');
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}
