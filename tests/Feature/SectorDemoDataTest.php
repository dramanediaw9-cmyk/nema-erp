<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Company\Models\PriceList;
use App\Modules\Core\Company\Models\PriceListItem;
use App\Modules\Core\Company\Models\Setting;
use App\Modules\Inventory\Models\ProductLot;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Partners\Models\Partner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SectorDemoDataTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_manager_can_apply_sector_demo_data_without_duplicates(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        Setting::query()->updateOrCreate(
            ['company_id' => $manager->company_id, 'key' => 'sector_profile'],
            ['value' => ['profile' => 'pharmacy_parapharmacy']]
        );

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->post(route('onboarding.sector-demo.apply'))
            ->assertRedirect();

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->post(route('onboarding.sector-demo.apply'))
            ->assertRedirect();

        $paracetamol = Product::query()
            ->where('company_id', $manager->company_id)
            ->where('sku', 'DEMO-PH-001')
            ->firstOrFail();

        $clinicalPriceList = PriceList::query()
            ->where('company_id', $manager->company_id)
            ->where('code', 'CLINIQUE')
            ->firstOrFail();

        $this->assertSame(1, Partner::query()->where('company_id', $manager->company_id)->where('code', 'DEMO-FOU-PHA-01')->count());
        $this->assertSame(1, Product::query()->where('company_id', $manager->company_id)->where('sku', 'DEMO-PH-001')->count());
        $this->assertSame(1, ProductLot::query()->where('company_id', $manager->company_id)->where('product_id', $paracetamol->id)->where('lot_number', 'LOT-PARA-2401')->count());
        $this->assertSame(1, StockMovement::query()->where('company_id', $manager->company_id)->where('notes', 'SECTOR-DEMO-LOT-LOT-PARA-2401')->count());
        $this->assertSame(1, PriceListItem::query()->where('price_list_id', $clinicalPriceList->id)->where('product_id', $paracetamol->id)->where('min_qty', 10)->count());

        $demoSetting = Setting::query()->where('company_id', $manager->company_id)->where('key', 'sector_demo_data')->firstOrFail();
        $this->assertSame('pharmacy_parapharmacy', $demoSetting->value['profile'] ?? null);
        $this->assertNotEmpty($demoSetting->value['branch_name'] ?? null);
        $this->assertNotEmpty($demoSetting->value['warehouse_name'] ?? null);
        $this->assertNotEmpty($demoSetting->value['catalog_highlights'] ?? []);

        $this->assertDatabaseHas('activity_logs', [
            'company_id' => $manager->company_id,
            'user_id' => $manager->id,
            'action' => 'onboarding.sector_demo.apply',
        ]);
    }

    public function test_onboarding_page_shows_sector_demo_section_and_playbooks(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        Setting::query()->updateOrCreate(
            ['company_id' => $manager->company_id, 'key' => 'sector_profile'],
            ['value' => ['profile' => 'food_store']]
        );

        Setting::query()->updateOrCreate(
            ['company_id' => $manager->company_id, 'key' => 'sector_demo_data'],
            [
                'value' => [
                    'profile' => 'food_store',
                    'applied_at' => now()->toDateTimeString(),
                    'branch_name' => 'Agence principale',
                    'warehouse_name' => 'Depot principal',
                    'catalog_highlights' => ['Jus mangue 1L', 'Yaourt nature 500g'],
                    'created' => [
                        'suppliers' => 2,
                        'customers' => 2,
                        'products' => 4,
                        'stock_entries' => 6,
                    ],
                ],
            ]
        );

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->get(route('onboarding.index'))
            ->assertOk()
            ->assertSee('Donnees de demonstration secteur')
            ->assertSee('Recharger la demo metier')
            ->assertSee('Jus mangue 1L')
            ->assertSee('Vente comptoir en boutique')
            ->assertSee('Controle peremption rayon');
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}
