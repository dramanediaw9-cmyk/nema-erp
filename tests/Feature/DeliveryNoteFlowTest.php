<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Partners\Models\Partner;
use App\Modules\Sales\Models\DeliveryNote;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryNoteFlowTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_manager_can_generate_partial_delivery_notes_from_confirmed_order(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $user->company_id)->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();
        $warehouse = Warehouse::query()
            ->where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->orderByDesc('is_default')
            ->firstOrFail();

        $stockBefore = $this->stockBalance($user->company_id, $user->branch_id, $product->id, $warehouse->id);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('orders.store'), [
                'customer_id' => $customer->id,
                'order_date' => now()->format('Y-m-d'),
                'requested_delivery_date' => now()->addDays(3)->format('Y-m-d'),
                'notes' => 'ORDER-PARTIAL-DELIVERY-TEST',
                'items' => [[
                    'product_id' => $product->id,
                    'description' => 'Commande partielle',
                    'qty' => 5,
                    'unit_price' => 500,
                ]],
            ])
            ->assertRedirect();

        $order = SalesOrder::query()->where('company_id', $user->company_id)->where('notes', 'ORDER-PARTIAL-DELIVERY-TEST')->firstOrFail();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('orders.confirm', $order))
            ->assertRedirect(route('orders.show', $order));

        $orderItem = $order->items()->firstOrFail();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('delivery-notes.store'), [
                'order_id' => $order->id,
                'warehouse_id' => $warehouse->id,
                'delivery_date' => now()->format('Y-m-d'),
                'notes' => 'PARTIAL-DELIVERY-1',
                'items' => [[
                    'sales_order_item_id' => $orderItem->id,
                    'qty' => 2,
                ]],
            ])
            ->assertRedirect();

        $firstDelivery = DeliveryNote::query()->where('company_id', $user->company_id)->where('notes', 'PARTIAL-DELIVERY-1')->firstOrFail();
        $order->refresh();
        $orderItem->refresh();

        $this->assertSame('partial_delivered', $order->status);
        $this->assertEqualsWithDelta(2, (float) $orderItem->delivered_qty, 0.001);
        $this->assertEqualsWithDelta($stockBefore - 2, $this->stockBalance($user->company_id, $user->branch_id, $product->id, $warehouse->id), 0.001);
        $this->assertSame($warehouse->id, $firstDelivery->warehouse_id);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('delivery-notes.store'), [
                'order_id' => $order->id,
                'warehouse_id' => $warehouse->id,
                'delivery_date' => now()->addDay()->format('Y-m-d'),
                'notes' => 'PARTIAL-DELIVERY-2',
                'items' => [[
                    'sales_order_item_id' => $orderItem->id,
                    'qty' => 3,
                ]],
            ])
            ->assertRedirect();

        $order->refresh();
        $orderItem->refresh();

        $this->assertSame('delivered', $order->status);
        $this->assertEqualsWithDelta(5, (float) $orderItem->delivered_qty, 0.001);
        $this->assertEqualsWithDelta($stockBefore - 5, $this->stockBalance($user->company_id, $user->branch_id, $product->id, $warehouse->id), 0.001);
        $this->assertSame(2, $order->deliveryNotes()->count());
    }

    public function test_delivery_note_can_be_converted_to_invoice_without_double_stock(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $user->company_id)->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();
        $warehouse = Warehouse::query()
            ->where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->orderByDesc('is_default')
            ->firstOrFail();

        $stockBefore = $this->stockBalance($user->company_id, $user->branch_id, $product->id, $warehouse->id);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('orders.store'), [
                'customer_id' => $customer->id,
                'order_date' => now()->format('Y-m-d'),
                'requested_delivery_date' => now()->addDays(2)->format('Y-m-d'),
                'notes' => 'ORDER-DELIVERY-INVOICE-TEST',
                'items' => [[
                    'product_id' => $product->id,
                    'description' => 'Commande pour livraison complete',
                    'qty' => 3,
                    'unit_price' => 500,
                ]],
            ])
            ->assertRedirect();

        $order = SalesOrder::query()->where('company_id', $user->company_id)->where('notes', 'ORDER-DELIVERY-INVOICE-TEST')->firstOrFail();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('orders.confirm', $order))
            ->assertRedirect(route('orders.show', $order));

        $orderItem = $order->items()->firstOrFail();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('delivery-notes.store'), [
                'order_id' => $order->id,
                'warehouse_id' => $warehouse->id,
                'delivery_date' => now()->format('Y-m-d'),
                'notes' => 'DELIVERY-INVOICE-TEST',
                'items' => [[
                    'sales_order_item_id' => $orderItem->id,
                    'qty' => 3,
                ]],
            ])
            ->assertRedirect();

        $deliveryNote = DeliveryNote::query()->where('company_id', $user->company_id)->where('notes', 'DELIVERY-INVOICE-TEST')->firstOrFail();
        $stockAfterDelivery = $this->stockBalance($user->company_id, $user->branch_id, $product->id, $warehouse->id);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('delivery-notes.convert', $deliveryNote), [
                'invoice_date' => now()->format('Y-m-d'),
                'due_date' => now()->addDays(10)->format('Y-m-d'),
                'notes' => 'INVOICE-FROM-DELIVERY',
            ])
            ->assertRedirect();

        $deliveryNote->refresh();
        $order->refresh();
        $invoice = SalesInvoice::query()->where('company_id', $user->company_id)->findOrFail($deliveryNote->converted_sales_invoice_id);

        $this->assertSame('invoiced', $deliveryNote->status);
        $this->assertSame('delivered', $order->status);
        $this->assertSame('validated', $invoice->status);
        $this->assertTrue((bool) $invoice->stock_posted);
        $this->assertSame($deliveryNote->id, $invoice->origin_delivery_note_id);
        $this->assertSame($warehouse->id, $invoice->warehouse_id);
        $this->assertEqualsWithDelta($stockBefore - 3, $stockAfterDelivery, 0.001);
        $this->assertEqualsWithDelta($stockAfterDelivery, $this->stockBalance($user->company_id, $user->branch_id, $product->id, $warehouse->id), 0.001);

        $deliveryMovements = StockMovement::query()
            ->where('reference_type', DeliveryNote::class)
            ->where('reference_id', $deliveryNote->id)
            ->count();

        $invoiceMovements = StockMovement::query()
            ->where('reference_type', SalesInvoice::class)
            ->where('reference_id', $invoice->id)
            ->count();

        $this->assertGreaterThan(0, $deliveryMovements);
        $this->assertSame(0, $invoiceMovements);
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
