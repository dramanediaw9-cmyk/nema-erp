<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\ProductLot;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductLotManagementTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_stock_lots_page_lists_and_filters_expiry_statuses(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $warehouse = Warehouse::query()
            ->where('company_id', $manager->company_id)
            ->where('branch_id', $manager->branch_id)
            ->where('is_default', true)
            ->firstOrFail();

        $product = Product::query()->create([
            'company_id' => $manager->company_id,
            'sku' => 'PRD-LOT-MGMT-01',
            'barcode' => '770000000031',
            'name' => 'Lait UHT lot pilote',
            'unit' => 'brique',
            'type' => 'stockable',
            'tracking_type' => 'lot',
            'sale_price' => 950,
            'purchase_price' => 700,
            'min_stock' => 3,
            'is_active' => true,
            'sale_ok' => true,
            'purchase_ok' => true,
        ]);

        ProductLot::query()->create([
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'tracking_type' => 'lot',
            'lot_number' => 'LOT-EXP-001',
            'expires_at' => now()->subDay()->toDateString(),
            'received_at' => now()->subDays(10)->toDateString(),
            'unit_cost' => 700,
            'quantity_received' => 12,
            'quantity_available' => 8,
        ]);

        ProductLot::query()->create([
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'tracking_type' => 'lot',
            'lot_number' => 'LOT-SOON-001',
            'expires_at' => now()->addDays(8)->toDateString(),
            'received_at' => now()->subDays(4)->toDateString(),
            'unit_cost' => 700,
            'quantity_received' => 10,
            'quantity_available' => 5,
        ]);

        ProductLot::query()->create([
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'tracking_type' => 'lot',
            'lot_number' => 'LOT-STABLE-001',
            'expires_at' => now()->addDays(90)->toDateString(),
            'received_at' => now()->subDays(2)->toDateString(),
            'unit_cost' => 700,
            'quantity_received' => 7,
            'quantity_available' => 7,
        ]);

        ProductLot::query()->create([
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'tracking_type' => 'lot',
            'lot_number' => 'LOT-NOEXP-001',
            'expires_at' => null,
            'received_at' => now()->subDay()->toDateString(),
            'unit_cost' => 700,
            'quantity_received' => 6,
            'quantity_available' => 6,
        ]);

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->get(route('stock.lots'))
            ->assertOk()
            ->assertSee('Lots et peremption')
            ->assertSee('LOT-EXP-001')
            ->assertSee('LOT-SOON-001')
            ->assertSee('LOT-STABLE-001')
            ->assertSee('LOT-NOEXP-001');

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->get(route('stock.lots', ['status' => 'expired']))
            ->assertOk()
            ->assertSee('LOT-EXP-001')
            ->assertDontSee('LOT-SOON-001')
            ->assertDontSee('LOT-STABLE-001');

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->get(route('stock.lots', ['status' => 'expiring']))
            ->assertOk()
            ->assertSee('LOT-SOON-001')
            ->assertDontSee('LOT-EXP-001')
            ->assertDontSee('LOT-STABLE-001');
    }

    public function test_stock_lots_page_supports_short_expiry_window_filter(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $warehouse = Warehouse::query()
            ->where('company_id', $manager->company_id)
            ->where('branch_id', $manager->branch_id)
            ->where('is_default', true)
            ->firstOrFail();

        $product = Product::query()->create([
            'company_id' => $manager->company_id,
            'sku' => 'PRD-LOT-MGMT-02',
            'barcode' => '770000000032',
            'name' => 'Yaourt date courte',
            'unit' => 'pot',
            'type' => 'stockable',
            'tracking_type' => 'lot',
            'sale_price' => 700,
            'purchase_price' => 480,
            'min_stock' => 4,
            'is_active' => true,
            'sale_ok' => true,
            'purchase_ok' => true,
        ]);

        ProductLot::query()->create([
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'tracking_type' => 'lot',
            'lot_number' => 'LOT-7J-001',
            'expires_at' => now()->addDays(5)->toDateString(),
            'received_at' => now()->subDay()->toDateString(),
            'unit_cost' => 480,
            'quantity_received' => 9,
            'quantity_available' => 9,
        ]);

        ProductLot::query()->create([
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'tracking_type' => 'lot',
            'lot_number' => 'LOT-14J-001',
            'expires_at' => now()->addDays(12)->toDateString(),
            'received_at' => now()->subDay()->toDateString(),
            'unit_cost' => 480,
            'quantity_received' => 7,
            'quantity_available' => 7,
        ]);

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->get(route('stock.lots', [
                'status' => 'expiring',
                'expiry_window_days' => 7,
            ]))
            ->assertOk()
            ->assertSee('Horizon surveille : 7 jours')
            ->assertSee('LOT-7J-001')
            ->assertDontSee('LOT-14J-001');
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}
