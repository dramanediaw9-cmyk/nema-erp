<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\ProductLot;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Partners\Models\Partner;
use App\Modules\Purchases\Models\GoodsReceipt;
use App\Modules\Purchases\Models\PurchaseOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductLotTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_lot_tracked_receipt_requires_lot_number_and_creates_product_lot_with_expiry(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $supplier = Partner::query()->suppliers()->where('company_id', $manager->company_id)->firstOrFail();
        $warehouse = Warehouse::query()->where('company_id', $manager->company_id)->where('branch_id', $manager->branch_id)->where('is_default', true)->firstOrFail();

        $product = Product::query()->create([
            'company_id' => $manager->company_id,
            'sku' => 'PRD-LOT-0001',
            'barcode' => '770000000001',
            'name' => 'Yaourt trace lot',
            'unit' => 'pot',
            'type' => 'stockable',
            'tracking_type' => 'lot',
            'sale_price' => 600,
            'purchase_price' => 350,
            'min_stock' => 5,
            'is_active' => true,
            'sale_ok' => true,
            'purchase_ok' => true,
        ]);

        $order = $this->confirmedOrder($manager, $supplier, $warehouse, $product, 6, 'LOT-TRACK-PO');
        $orderItem = $order->items()->firstOrFail();

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->from(route('goods-receipts.create', ['order' => $order->id]))
            ->post(route('goods-receipts.store'), [
                'order_id' => $order->id,
                'receipt_date' => now()->toDateString(),
                'notes' => 'LOT-TRACK-GR-FAIL',
                'items' => [[
                    'purchase_order_item_id' => $orderItem->id,
                    'qty' => 6,
                ]],
            ])
            ->assertRedirect(route('goods-receipts.create', ['order' => $order->id]))
            ->assertSessionHasErrors('items');

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->post(route('goods-receipts.store'), [
                'order_id' => $order->id,
                'receipt_date' => now()->toDateString(),
                'notes' => 'LOT-TRACK-GR-OK',
                'items' => [[
                    'purchase_order_item_id' => $orderItem->id,
                    'qty' => 6,
                    'lot_number' => 'LOT-YAOURT-2404',
                    'expires_at' => now()->addMonths(2)->toDateString(),
                ]],
            ])
            ->assertRedirect();

        $receipt = GoodsReceipt::query()->where('company_id', $manager->company_id)->where('notes', 'LOT-TRACK-GR-OK')->firstOrFail();
        $receiptItem = $receipt->items()->firstOrFail();
        $lot = ProductLot::query()->where('company_id', $manager->company_id)->where('product_id', $product->id)->where('lot_number', 'LOT-YAOURT-2404')->firstOrFail();
        $movement = StockMovement::query()->where('reference_type', GoodsReceipt::class)->where('reference_id', $receipt->id)->where('product_id', $product->id)->firstOrFail();

        $this->assertSame('LOT-YAOURT-2404', $receiptItem->lot_number);
        $this->assertNotNull($receiptItem->expires_at);
        $this->assertSame('lot', $lot->tracking_type);
        $this->assertEqualsWithDelta(6, (float) $lot->quantity_received, 0.001);
        $this->assertSame($lot->id, $movement->product_lot_id);
    }

    public function test_serial_tracked_receipt_requires_matching_serial_count_and_creates_one_lot_per_serial(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $supplier = Partner::query()->suppliers()->where('company_id', $manager->company_id)->firstOrFail();
        $warehouse = Warehouse::query()->where('company_id', $manager->company_id)->where('branch_id', $manager->branch_id)->where('is_default', true)->firstOrFail();

        $product = Product::query()->create([
            'company_id' => $manager->company_id,
            'sku' => 'PRD-SER-0001',
            'barcode' => '770000000011',
            'name' => 'Scanner code-barres',
            'unit' => 'piece',
            'type' => 'stockable',
            'tracking_type' => 'serial',
            'sale_price' => 25000,
            'purchase_price' => 18000,
            'min_stock' => 1,
            'is_active' => true,
            'sale_ok' => true,
            'purchase_ok' => true,
        ]);

        $order = $this->confirmedOrder($manager, $supplier, $warehouse, $product, 2, 'SER-TRACK-PO');
        $orderItem = $order->items()->firstOrFail();

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->from(route('goods-receipts.create', ['order' => $order->id]))
            ->post(route('goods-receipts.store'), [
                'order_id' => $order->id,
                'receipt_date' => now()->toDateString(),
                'notes' => 'SER-TRACK-GR-FAIL',
                'items' => [[
                    'purchase_order_item_id' => $orderItem->id,
                    'qty' => 2,
                    'serial_numbers_text' => "SER-0001",
                ]],
            ])
            ->assertRedirect(route('goods-receipts.create', ['order' => $order->id]))
            ->assertSessionHasErrors('items');

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->post(route('goods-receipts.store'), [
                'order_id' => $order->id,
                'receipt_date' => now()->toDateString(),
                'notes' => 'SER-TRACK-GR-OK',
                'items' => [[
                    'purchase_order_item_id' => $orderItem->id,
                    'qty' => 2,
                    'serial_numbers_text' => "SER-0001\nSER-0002",
                ]],
            ])
            ->assertRedirect();

        $receipt = GoodsReceipt::query()->where('company_id', $manager->company_id)->where('notes', 'SER-TRACK-GR-OK')->firstOrFail();

        $this->assertSame(2, ProductLot::query()->where('company_id', $manager->company_id)->where('product_id', $product->id)->count());
        $this->assertDatabaseHas('product_lots', [
            'company_id' => $manager->company_id,
            'product_id' => $product->id,
            'serial_number' => 'SER-0001',
        ]);
        $this->assertDatabaseHas('product_lots', [
            'company_id' => $manager->company_id,
            'product_id' => $product->id,
            'serial_number' => 'SER-0002',
        ]);
        $this->assertSame(2, StockMovement::query()->where('reference_type', GoodsReceipt::class)->where('reference_id', $receipt->id)->where('product_id', $product->id)->count());
    }

    public function test_product_show_page_lists_tracked_lots(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $product = Product::query()->create([
            'company_id' => $manager->company_id,
            'sku' => 'PRD-LOT-SHOW-01',
            'barcode' => '770000000021',
            'name' => 'Lait perissable',
            'unit' => 'brique',
            'type' => 'stockable',
            'tracking_type' => 'lot',
            'sale_price' => 900,
            'purchase_price' => 650,
            'min_stock' => 2,
            'is_active' => true,
            'sale_ok' => true,
            'purchase_ok' => true,
        ]);

        ProductLot::query()->create([
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'warehouse_id' => Warehouse::query()->where('company_id', $manager->company_id)->where('branch_id', $manager->branch_id)->where('is_default', true)->value('id'),
            'product_id' => $product->id,
            'tracking_type' => 'lot',
            'lot_number' => 'LOT-LAIT-01',
            'expires_at' => now()->addDays(25)->toDateString(),
            'received_at' => now()->toDateString(),
            'unit_cost' => 650,
            'quantity_received' => 12,
            'quantity_available' => 12,
        ]);

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('Lots et suivi')
            ->assertSee('LOT-LAIT-01');
    }

    private function confirmedOrder(User $manager, Partner $supplier, Warehouse $warehouse, Product $product, float $qty, string $notes): PurchaseOrder
    {
        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->post(route('purchase-orders.store'), [
                'supplier_id' => $supplier->id,
                'warehouse_id' => $warehouse->id,
                'order_date' => now()->toDateString(),
                'expected_receipt_date' => now()->addDays(3)->toDateString(),
                'notes' => $notes,
                'items' => [[
                    'product_id' => $product->id,
                    'description' => 'Commande test traceabilite',
                    'qty' => $qty,
                    'unit_cost' => (float) $product->purchase_price,
                ]],
            ])
            ->assertRedirect();

        $order = PurchaseOrder::query()->where('company_id', $manager->company_id)->where('notes', $notes)->firstOrFail();

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->post(route('purchase-orders.confirm', $order))
            ->assertRedirect();

        return $order->fresh(['items.product']);
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}
