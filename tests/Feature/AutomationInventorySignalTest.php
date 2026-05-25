<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductCategory;
use App\Modules\Core\Automation\Models\AutomationRule;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\Setting;
use App\Modules\Core\Notifications\Models\InternalNotification;
use App\Modules\Inventory\Models\ProductLot;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomationInventorySignalTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_manager_can_create_and_run_tracked_products_without_saleable_stock_rule(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $warehouse = Warehouse::query()
            ->where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->where('is_default', true)
            ->firstOrFail();
        $category = ProductCategory::query()->firstOrCreate([
            'company_id' => $user->company_id,
            'name' => 'Produits traces automation',
        ], [
            'tenant_id' => $user->tenant_id,
            'description' => 'Categorie test automation inventaire',
            'is_active' => true,
        ]);
        $sikasso = Branch::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'name' => 'Agence Sikasso inventaire',
            'code' => 'SIK-TINV',
            'city' => 'Sikasso',
            'address' => 'Zone inventaire test',
            'is_active' => true,
            'is_default' => false,
        ]);
        $sikassoWarehouse = Warehouse::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'branch_id' => $sikasso->id,
            'name' => 'Depot Sikasso test',
            'code' => 'DEP-SIK-TINV',
            'is_default' => true,
            'is_active' => true,
        ]);

        $blockedProduct = Product::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'category_id' => $category->id,
            'sku' => 'AUTO-TRK-001',
            'name' => 'Insuline test',
            'unit' => 'flacon',
            'type' => 'stockable',
            'tracking_type' => 'lot',
            'sale_price' => 9800,
            'purchase_price' => 7400,
            'min_stock' => 2,
            'is_active' => true,
            'sale_ok' => true,
            'purchase_ok' => true,
        ]);

        ProductLot::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $blockedProduct->id,
            'tracking_type' => 'lot',
            'lot_number' => 'AUTO-TRK-LOT-001',
            'expires_at' => now()->subDay()->toDateString(),
            'received_at' => now()->subDays(6)->toDateString(),
            'unit_cost' => 7400,
            'quantity_received' => 4,
            'quantity_available' => 4,
            'notes' => 'Lot expire Bamako',
        ]);

        $healthyProduct = Product::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'category_id' => $category->id,
            'sku' => 'AUTO-TRK-002',
            'name' => 'Vitamine C test',
            'unit' => 'boite',
            'type' => 'stockable',
            'tracking_type' => 'lot',
            'sale_price' => 3200,
            'purchase_price' => 2100,
            'min_stock' => 1,
            'is_active' => true,
            'sale_ok' => true,
            'purchase_ok' => true,
        ]);

        ProductLot::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $healthyProduct->id,
            'tracking_type' => 'lot',
            'lot_number' => 'AUTO-TRK-LOT-002',
            'expires_at' => now()->addDays(40)->toDateString(),
            'received_at' => now()->subDays(2)->toDateString(),
            'unit_cost' => 2100,
            'quantity_received' => 8,
            'quantity_available' => 8,
            'notes' => 'Lot sain Bamako',
        ]);

        $otherBranchProduct = Product::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'category_id' => $category->id,
            'sku' => 'AUTO-TRK-003',
            'name' => 'Produit Sikasso hors scope',
            'unit' => 'boite',
            'type' => 'stockable',
            'tracking_type' => 'lot',
            'sale_price' => 4100,
            'purchase_price' => 3000,
            'min_stock' => 1,
            'is_active' => true,
            'sale_ok' => true,
            'purchase_ok' => true,
        ]);

        ProductLot::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'branch_id' => $sikasso->id,
            'warehouse_id' => $sikassoWarehouse->id,
            'product_id' => $otherBranchProduct->id,
            'tracking_type' => 'lot',
            'lot_number' => 'AUTO-TRK-LOT-003',
            'expires_at' => now()->subDays(2)->toDateString(),
            'received_at' => now()->subDays(12)->toDateString(),
            'unit_cost' => 3000,
            'quantity_received' => 3,
            'quantity_available' => 3,
            'notes' => 'Lot expire Sikasso',
        ]);

        $this->actingAs($user)->withSession($this->workspaceSession($user));

        $this->post(route('automation.store'), [
            'name' => 'Veille stock vendable trace Bamako',
            'signal_key' => 'inventory.tracked_products_saleable_zero',
            'status' => 'active',
            'severity' => 'danger',
            'action_type' => 'internal_alert',
            'threshold_value' => 1,
            'window_hours' => 24,
            'cooldown_minutes' => 60,
            'branch_id' => $user->branch_id,
            'description' => 'Remonter les produits traces qui n ont plus aucun lot vendable sur l agence.',
        ])->assertRedirect(route('automation.index'));

        $rule = AutomationRule::query()
            ->where('company_id', $user->company_id)
            ->where('name', 'Veille stock vendable trace Bamako')
            ->firstOrFail();

        $this->post(route('automation.run', $rule))
            ->assertRedirect(route('automation.index'));

        $this->assertDatabaseHas('automation_executions', [
            'company_id' => $user->company_id,
            'automation_rule_id' => $rule->id,
            'matched' => true,
            'observed_value' => 1,
        ]);

        $notification = InternalNotification::query()
            ->where('company_id', $user->company_id)
            ->where('code', 'automation-rule-'.$rule->id)
            ->firstOrFail();

        $this->assertStringContainsString('1 produit(s) traces n ont plus aucun lot non expire disponible pour la vente', (string) $notification->message);
        $this->assertStringContainsString('Insuline test', (string) $notification->message);
        $this->assertStringNotContainsString('Produit Sikasso hors scope', (string) $notification->message);

        $this->get(route('automation.index'))
            ->assertOk()
            ->assertSee('Produits traces sans stock vendable')
            ->assertSee('Veille stock vendable trace Bamako');
    }

    public function test_manager_can_create_and_run_food_store_short_dated_lots_rule(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $warehouse = Warehouse::query()
            ->where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->where('is_default', true)
            ->firstOrFail();
        $category = ProductCategory::query()->firstOrCreate([
            'company_id' => $user->company_id,
            'name' => 'Produits frais automation',
        ], [
            'tenant_id' => $user->tenant_id,
            'description' => 'Categorie test automation food',
            'is_active' => true,
        ]);

        Setting::query()->updateOrCreate(
            ['company_id' => $user->company_id, 'key' => 'sector_profile'],
            ['value' => ['profile' => 'food_store']]
        );

        $shortDatedProduct = Product::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'category_id' => $category->id,
            'sku' => 'AUTO-FOOD-LOT-001',
            'name' => 'Yaourt mangue test',
            'unit' => 'pot',
            'type' => 'stockable',
            'tracking_type' => 'lot',
            'sale_price' => 900,
            'purchase_price' => 610,
            'min_stock' => 4,
            'is_active' => true,
            'sale_ok' => true,
            'purchase_ok' => true,
        ]);

        ProductLot::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $shortDatedProduct->id,
            'tracking_type' => 'lot',
            'lot_number' => 'AUTO-FOOD-SHORT-001',
            'expires_at' => now()->addDays(2)->toDateString(),
            'received_at' => now()->subDays(2)->toDateString(),
            'unit_cost' => 610,
            'quantity_received' => 10,
            'quantity_available' => 10,
        ]);

        $this->actingAs($user)->withSession($this->workspaceSession($user));

        $this->post(route('automation.store'), [
            'name' => 'Veille lots courts boutique',
            'signal_key' => 'inventory.food_store_short_dated_lots',
            'status' => 'active',
            'severity' => 'warning',
            'action_type' => 'internal_alert',
            'threshold_value' => 1,
            'window_hours' => 24,
            'cooldown_minutes' => 60,
            'branch_id' => $user->branch_id,
            'description' => 'Remonter les lots encore vendables qui expirent sous 7 jours.',
        ])->assertRedirect(route('automation.index'));

        $rule = AutomationRule::query()
            ->where('company_id', $user->company_id)
            ->where('name', 'Veille lots courts boutique')
            ->firstOrFail();

        $this->post(route('automation.run', $rule))
            ->assertRedirect(route('automation.index'));

        $this->assertDatabaseHas('automation_executions', [
            'company_id' => $user->company_id,
            'automation_rule_id' => $rule->id,
            'matched' => true,
            'observed_value' => 1,
        ]);

        $notification = InternalNotification::query()
            ->where('company_id', $user->company_id)
            ->where('code', 'automation-rule-'.$rule->id)
            ->firstOrFail();

        $this->assertStringContainsString('1 lot(s) sur 1 produit(s) expirent sous 7 jours', (string) $notification->message);
        $this->assertStringContainsString('Yaourt mangue test', (string) $notification->message);
    }

    public function test_manager_can_create_and_run_food_store_saleable_stockout_rule(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $warehouse = Warehouse::query()
            ->where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->where('is_default', true)
            ->firstOrFail();
        $category = ProductCategory::query()->firstOrCreate([
            'company_id' => $user->company_id,
            'name' => 'Epicerie automation',
        ], [
            'tenant_id' => $user->tenant_id,
            'description' => 'Categorie test rupture food',
            'is_active' => true,
        ]);

        Setting::query()->updateOrCreate(
            ['company_id' => $user->company_id, 'key' => 'sector_profile'],
            ['value' => ['profile' => 'food_store']]
        );

        $stockoutProduct = Product::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'category_id' => $category->id,
            'sku' => 'AUTO-FOOD-STOCK-001',
            'name' => 'Jus tamarin test',
            'unit' => 'bouteille',
            'type' => 'stockable',
            'tracking_type' => 'none',
            'sale_price' => 650,
            'purchase_price' => 390,
            'min_stock' => 3,
            'is_active' => true,
            'sale_ok' => true,
            'purchase_ok' => true,
        ]);

        StockMovement::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $stockoutProduct->id,
            'movement_type' => 'opening',
            'quantity_in' => 6,
            'quantity_out' => 0,
            'unit_cost' => 390,
            'reason' => 'Stock initial food automation',
            'movement_date' => now()->subDays(3),
            'created_by' => $user->id,
        ]);

        StockMovement::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $stockoutProduct->id,
            'movement_type' => 'sale',
            'quantity_in' => 0,
            'quantity_out' => 6,
            'unit_cost' => 390,
            'reason' => 'Sortie food automation',
            'movement_date' => now()->subDay(),
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->withSession($this->workspaceSession($user));

        $this->post(route('automation.store'), [
            'name' => 'Veille ruptures vendables boutique',
            'signal_key' => 'inventory.food_store_saleable_stockouts',
            'status' => 'active',
            'severity' => 'danger',
            'action_type' => 'internal_alert',
            'threshold_value' => 1,
            'window_hours' => 24,
            'cooldown_minutes' => 60,
            'branch_id' => $user->branch_id,
            'description' => 'Remonter les references deja passees par stock mais vides au comptoir.',
        ])->assertRedirect(route('automation.index'));

        $rule = AutomationRule::query()
            ->where('company_id', $user->company_id)
            ->where('name', 'Veille ruptures vendables boutique')
            ->firstOrFail();

        $this->post(route('automation.run', $rule))
            ->assertRedirect(route('automation.index'));

        $this->assertDatabaseHas('automation_executions', [
            'company_id' => $user->company_id,
            'automation_rule_id' => $rule->id,
            'matched' => true,
            'observed_value' => 1,
        ]);

        $notification = InternalNotification::query()
            ->where('company_id', $user->company_id)
            ->where('code', 'automation-rule-'.$rule->id)
            ->firstOrFail();

        $this->assertStringContainsString('1 reference(s) ont deja tourne en stock mais n ont plus rien de vendable au comptoir', (string) $notification->message);
        $this->assertStringContainsString('Jus tamarin test', (string) $notification->message);
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_tenant_id' => $user->tenant_id,
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}
