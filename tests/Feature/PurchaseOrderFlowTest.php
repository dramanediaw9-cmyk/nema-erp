<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Partners\Models\Partner;
use App\Modules\Purchases\Models\GoodsReceipt;
use App\Modules\Purchases\Models\PurchaseOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderFlowTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_purchase_order_can_be_partially_received_then_fully_received(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $supplier = Partner::query()->suppliers()->where('company_id', $user->company_id)->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();
        $warehouse = Warehouse::query()
            ->where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->orderByDesc('is_default')
            ->firstOrFail();

        $stockBefore = $this->stockBalance($user->company_id, $user->branch_id, $product->id, $warehouse->id);

        $response = $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('purchase-orders.store'), [
                'supplier_id' => $supplier->id,
                'warehouse_id' => $warehouse->id,
                'order_date' => now()->format('Y-m-d'),
                'expected_receipt_date' => now()->addDays(5)->format('Y-m-d'),
                'notes' => 'TEST-PO-001',
                'items' => [[
                    'product_id' => $product->id,
                    'description' => 'Commande fournisseur partielle',
                    'qty' => 5,
                    'unit_cost' => 300,
                ]],
            ]);

        $order = PurchaseOrder::query()->where('company_id', $user->company_id)->where('notes', 'TEST-PO-001')->firstOrFail();
        $response->assertRedirect(route('purchase-orders.show', $order));
        $this->assertMatchesRegularExpression('/^BCF-BKO-\d{4}-\d{5}$/', $order->order_number);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('purchase-orders.confirm', $order))
            ->assertRedirect(route('purchase-orders.show', $order));

        $orderItem = $order->items()->firstOrFail();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('goods-receipts.store'), [
                'order_id' => $order->id,
                'receipt_date' => now()->format('Y-m-d'),
                'notes' => 'TEST-GR-001',
                'items' => [[
                    'purchase_order_item_id' => $orderItem->id,
                    'qty' => 2,
                ]],
            ])
            ->assertRedirect();

        $firstReceipt = GoodsReceipt::query()->where('company_id', $user->company_id)->where('notes', 'TEST-GR-001')->firstOrFail();
        $order->refresh();
        $orderItem->refresh();

        $this->assertSame('partial_received', $order->status);
        $this->assertEqualsWithDelta(2, (float) $orderItem->received_qty, 0.001);
        $this->assertEqualsWithDelta($stockBefore + 2, $this->stockBalance($user->company_id, $user->branch_id, $product->id, $warehouse->id), 0.001);
        $this->assertMatchesRegularExpression('/^BRF-BKO-\d{4}-\d{5}$/', $firstReceipt->receipt_number);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('goods-receipts.store'), [
                'order_id' => $order->id,
                'receipt_date' => now()->addDay()->format('Y-m-d'),
                'notes' => 'TEST-GR-002',
                'items' => [[
                    'purchase_order_item_id' => $orderItem->id,
                    'qty' => 3,
                ]],
            ])
            ->assertRedirect();

        $order->refresh();
        $orderItem->refresh();

        $this->assertSame('received', $order->status);
        $this->assertEqualsWithDelta(5, (float) $orderItem->received_qty, 0.001);
        $this->assertEqualsWithDelta($stockBefore + 5, $this->stockBalance($user->company_id, $user->branch_id, $product->id, $warehouse->id), 0.001);
        $this->assertSame(2, $order->goodsReceipts()->count());
        $this->assertDatabaseHas('stock_movements', [
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'movement_type' => 'purchase',
            'reference_type' => GoodsReceipt::class,
        ]);
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
