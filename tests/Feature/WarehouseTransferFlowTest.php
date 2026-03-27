<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseTransferFlowTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_stock_transfer_moves_quantity_between_warehouses_without_changing_branch_total(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();
        $sourceWarehouse = Warehouse::query()
            ->where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->orderByDesc('is_default')
            ->firstOrFail();
        $destinationWarehouse = Warehouse::query()
            ->where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->whereKeyNot($sourceWarehouse->id)
            ->orderBy('name')
            ->firstOrFail();

        $sourceBefore = $this->stockBalance($user->company_id, $user->branch_id, $product->id, $sourceWarehouse->id);
        $destinationBefore = $this->stockBalance($user->company_id, $user->branch_id, $product->id, $destinationWarehouse->id);
        $branchBefore = $this->stockBalance($user->company_id, $user->branch_id, $product->id);

        $response = $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('transfers.store'), [
                'source_warehouse_id' => $sourceWarehouse->id,
                'destination_warehouse_id' => $destinationWarehouse->id,
                'transfer_date' => now()->format('Y-m-d'),
                'notes' => 'TEST-TRANSFER-001',
                'items' => [[
                    'product_id' => $product->id,
                    'description' => 'Transfert test',
                    'qty' => 4,
                    'unit_cost' => 300,
                ]],
            ]);

        $transfer = StockTransfer::query()->where('company_id', $user->company_id)->where('notes', 'TEST-TRANSFER-001')->firstOrFail();
        $response->assertRedirect(route('transfers.show', $transfer));

        $this->assertMatchesRegularExpression('/^TRF-BKO-\d{4}-\d{5}$/', $transfer->transfer_number);
        $this->assertEqualsWithDelta($sourceBefore - 4, $this->stockBalance($user->company_id, $user->branch_id, $product->id, $sourceWarehouse->id), 0.001);
        $this->assertEqualsWithDelta($destinationBefore + 4, $this->stockBalance($user->company_id, $user->branch_id, $product->id, $destinationWarehouse->id), 0.001);
        $this->assertEqualsWithDelta($branchBefore, $this->stockBalance($user->company_id, $user->branch_id, $product->id), 0.001);

        $this->assertSame(2, StockMovement::query()
            ->where('reference_type', StockTransfer::class)
            ->where('reference_id', $transfer->id)
            ->where('product_id', $product->id)
            ->count());
    }

    private function stockBalance(int $companyId, int $branchId, int $productId, ?int $warehouseId = null): float
    {
        return (float) StockMovement::query()
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->when($warehouseId, fn ($query, $selectedWarehouseId) => $query->where('warehouse_id', $selectedWarehouseId))
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
