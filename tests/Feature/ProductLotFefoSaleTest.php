<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\ProductLot;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Partners\Models\Partner;
use App\Modules\Sales\Models\SalesInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductLotFefoSaleTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_lot_tracked_sale_consumes_non_expired_lots_in_fefo_order(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $manager->company_id)->firstOrFail();
        $warehouse = $this->defaultWarehouse($manager);

        $product = Product::query()->create([
            'company_id' => $manager->company_id,
            'sku' => 'PRD-LOT-FEFO-01',
            'barcode' => '770000000041',
            'name' => 'Jus orange FEFO',
            'unit' => 'bouteille',
            'type' => 'stockable',
            'tracking_type' => 'lot',
            'sale_price' => 1800,
            'purchase_price' => 1100,
            'min_stock' => 2,
            'is_active' => true,
            'sale_ok' => true,
            'purchase_ok' => true,
        ]);

        $expiredLot = $this->seedLotStock(
            manager: $manager,
            warehouse: $warehouse,
            product: $product,
            lotNumber: 'LOT-EXP-VENTE',
            quantity: 4,
            expiresAt: now()->subDay()->toDateString(),
            receivedAt: now()->subDays(15)->toDateString(),
        );
        $soonLot = $this->seedLotStock(
            manager: $manager,
            warehouse: $warehouse,
            product: $product,
            lotNumber: 'LOT-SOON-VENTE',
            quantity: 6,
            expiresAt: now()->addDays(7)->toDateString(),
            receivedAt: now()->subDays(10)->toDateString(),
        );
        $healthyLot = $this->seedLotStock(
            manager: $manager,
            warehouse: $warehouse,
            product: $product,
            lotNumber: 'LOT-HEALTHY-VENTE',
            quantity: 5,
            expiresAt: now()->addDays(60)->toDateString(),
            receivedAt: now()->subDays(5)->toDateString(),
        );

        $initialStock = $this->stockBalance($manager->company_id, $manager->branch_id, $product->id);

        $response = $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->post(route('sales.store'), [
                'customer_id' => $customer->id,
                'warehouse_id' => $warehouse->id,
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays(7)->toDateString(),
                'notes' => 'TEST-SALE-FEFO',
                'items' => [[
                    'product_id' => $product->id,
                    'description' => 'Vente FEFO lots',
                    'qty' => 8,
                    'unit_price' => 1800,
                ]],
            ]);

        $invoice = SalesInvoice::query()
            ->where('company_id', $manager->company_id)
            ->where('notes', 'TEST-SALE-FEFO')
            ->firstOrFail();

        $response->assertRedirect(route('sales.show', $invoice));

        $saleMovements = StockMovement::query()
            ->where('company_id', $manager->company_id)
            ->where('reference_type', SalesInvoice::class)
            ->where('reference_id', $invoice->id)
            ->where('product_id', $product->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $saleMovements);
        $this->assertSame([$soonLot->id, $healthyLot->id], $saleMovements->pluck('product_lot_id')->all());
        $this->assertEqualsWithDelta(6, (float) $saleMovements[0]->quantity_out, 0.001);
        $this->assertEqualsWithDelta(2, (float) $saleMovements[1]->quantity_out, 0.001);

        $this->assertEqualsWithDelta(4, (float) $expiredLot->fresh()->quantity_available, 0.001);
        $this->assertEqualsWithDelta(0, (float) $soonLot->fresh()->quantity_available, 0.001);
        $this->assertEqualsWithDelta(3, (float) $healthyLot->fresh()->quantity_available, 0.001);

        $updatedStock = $this->stockBalance($manager->company_id, $manager->branch_id, $product->id);
        $this->assertEqualsWithDelta($initialStock - 8, $updatedStock, 0.001);
    }

    public function test_lot_tracked_sale_is_rejected_when_only_expired_stock_is_available(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $manager->company_id)->firstOrFail();
        $warehouse = $this->defaultWarehouse($manager);

        $product = Product::query()->create([
            'company_id' => $manager->company_id,
            'sku' => 'PRD-LOT-FEFO-02',
            'barcode' => '770000000042',
            'name' => 'Lait bloque expiré',
            'unit' => 'brique',
            'type' => 'stockable',
            'tracking_type' => 'lot',
            'sale_price' => 900,
            'purchase_price' => 650,
            'min_stock' => 1,
            'is_active' => true,
            'sale_ok' => true,
            'purchase_ok' => true,
        ]);

        $this->seedLotStock(
            manager: $manager,
            warehouse: $warehouse,
            product: $product,
            lotNumber: 'LOT-ONLY-EXPIRED',
            quantity: 5,
            expiresAt: now()->subDays(2)->toDateString(),
            receivedAt: now()->subDays(20)->toDateString(),
        );

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->from(route('sales.create'))
            ->post(route('sales.store'), [
                'customer_id' => $customer->id,
                'warehouse_id' => $warehouse->id,
                'invoice_date' => now()->toDateString(),
                'notes' => 'TEST-SALE-ONLY-EXPIRED',
                'items' => [[
                    'product_id' => $product->id,
                    'description' => 'Vente stock expiré',
                    'qty' => 2,
                    'unit_price' => 900,
                ]],
            ])
            ->assertRedirect(route('sales.create'))
            ->assertSessionHasErrors('items');

        $this->assertDatabaseMissing('sales_invoices', [
            'company_id' => $manager->company_id,
            'notes' => 'TEST-SALE-ONLY-EXPIRED',
        ]);

        $this->assertSame(0, StockMovement::query()
            ->where('company_id', $manager->company_id)
            ->where('reference_type', SalesInvoice::class)
            ->where('product_id', $product->id)
            ->count());
    }

    private function defaultWarehouse(User $manager): Warehouse
    {
        return Warehouse::query()
            ->where('company_id', $manager->company_id)
            ->where('branch_id', $manager->branch_id)
            ->where('is_default', true)
            ->firstOrFail();
    }

    private function seedLotStock(User $manager, Warehouse $warehouse, Product $product, string $lotNumber, float $quantity, ?string $expiresAt, string $receivedAt): ProductLot
    {
        $lot = ProductLot::query()->create([
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'tracking_type' => 'lot',
            'lot_number' => $lotNumber,
            'expires_at' => $expiresAt,
            'received_at' => $receivedAt,
            'unit_cost' => (float) $product->purchase_price,
            'quantity_received' => $quantity,
            'quantity_available' => $quantity,
        ]);

        StockMovement::query()->create([
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'product_lot_id' => $lot->id,
            'movement_type' => 'purchase',
            'quantity_in' => $quantity,
            'quantity_out' => 0,
            'unit_cost' => (float) $product->purchase_price,
            'reason' => 'Stock test FEFO',
            'notes' => $lotNumber,
            'movement_date' => $receivedAt,
            'created_by' => $manager->id,
        ]);

        return $lot;
    }

    private function stockBalance(int $companyId, int $branchId, int $productId): float
    {
        return (float) DB::table('stock_movements')
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
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
