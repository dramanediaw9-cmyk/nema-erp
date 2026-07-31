<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StockCountFlowTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_stock_count_can_be_created_and_posted_to_adjust_stock(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $warehouse = Warehouse::query()->where('company_id', $manager->company_id)->where('branch_id', $manager->branch_id)->where('is_default', true)->firstOrFail();
        $product = Product::query()->where('company_id', $manager->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $initialBalance = $this->stockBalance($manager->company_id, $manager->branch_id, $warehouse->id, $product->id);
        $counted = max($initialBalance - 2, 0);

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->post(route('stock-counts.store'), [
                'warehouse_id' => $warehouse->id,
                'count_date' => now()->toDateString(),
                'notes' => 'Comptage test',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'counted_qty' => $counted,
                    ],
                ],
            ])
            ->assertRedirect();

        $stockCount = StockCount::query()->where('company_id', $manager->company_id)->firstOrFail();

        $this->assertSame('draft', $stockCount->status);
        $this->assertMatchesRegularExpression('/^INV-BKO-\d{4}-\d{5}$/', $stockCount->count_number);

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->post(route('stock-counts.post', $stockCount))
            ->assertRedirect(route('stock-counts.show', $stockCount));

        $stockCount->refresh();
        $finalBalance = $this->stockBalance($manager->company_id, $manager->branch_id, $warehouse->id, $product->id);

        $this->assertSame('posted', $stockCount->status);
        $this->assertEqualsWithDelta($counted, $finalBalance, 0.001);

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->get(route('stock-counts.print', $stockCount))
            ->assertOk();
    }

    public function test_manager_can_record_stock_loss_from_dedicated_screen(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $warehouse = Warehouse::query()->where('company_id', $manager->company_id)->where('branch_id', $manager->branch_id)->where('is_default', true)->firstOrFail();
        $product = Product::query()->where('company_id', $manager->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $initialBalance = $this->stockBalance($manager->company_id, $manager->branch_id, $warehouse->id, $product->id);

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->get(route('stock.losses.create'))
            ->assertOk()
            ->assertSee('Declarer une perte stock')
            ->assertSee('Perte stock');

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->post(route('stock.adjustments.store'), [
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'movement_date' => now()->toDateString(),
                'direction' => 'out',
                'quantity' => 2,
                'unit_cost' => 300,
                'reason' => 'Perte stock',
                'notes' => 'Article casse pendant manutention',
            ])
            ->assertRedirect(route('stock.index'));

        $this->assertEqualsWithDelta(
            $initialBalance - 2,
            $this->stockBalance($manager->company_id, $manager->branch_id, $warehouse->id, $product->id),
            0.001
        );

        $this->assertDatabaseHas('stock_movements', [
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'movement_type' => 'adjustment_out',
            'quantity_out' => 2,
            'reason' => 'Perte stock',
        ]);
    }

    private function stockBalance(int $companyId, int $branchId, int $warehouseId, int $productId): float
    {
        return (float) DB::table('stock_movements')
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->selectRaw('COALESCE(SUM(quantity_in - quantity_out), 0) as balance')
            ->value('balance');
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}
