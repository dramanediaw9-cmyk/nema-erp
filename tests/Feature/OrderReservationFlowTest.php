<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Partners\Models\Partner;
use App\Modules\Purchases\Models\PurchaseOrder;
use App\Modules\Purchases\Models\PurchaseOrderItem;
use App\Modules\Purchases\Models\PurchaseRequest;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderReservationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_confirmed_order_reserves_stock_and_blocks_overbooking(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $user->company_id)->firstOrFail();
        $warehouse = $this->defaultWarehouse($user);
        $product = $this->stockedProduct($user, $warehouse, 'PRD-RES-0001', 'Huile reservee 1L', 5);

        $firstOrder = $this->createOrder($user, $customer, $warehouse, $product, 4, 'ORDER-RES-001');
        $secondOrder = $this->createOrder($user, $customer, $warehouse, $product, 2, 'ORDER-RES-002');

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('orders.confirm', $firstOrder))
            ->assertRedirect(route('orders.show', $firstOrder));

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->from(route('orders.show', $secondOrder))
            ->post(route('orders.confirm', $secondOrder))
            ->assertRedirect(route('orders.show', $secondOrder))
            ->assertSessionHasErrors('items');

        $firstOrder->refresh();
        $secondOrder->refresh();

        $this->assertSame('confirmed', $firstOrder->status);
        $this->assertSame('draft', $secondOrder->status);
        $this->assertEqualsWithDelta(4, (float) $firstOrder->items()->firstOrFail()->remainingQty(), 0.001);
    }

    public function test_stock_product_detail_page_shows_open_reservations_and_available_to_promise(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $user->company_id)->firstOrFail();
        $warehouse = $this->defaultWarehouse($user);
        $product = $this->stockedProduct($user, $warehouse, 'PRD-RES-0002', 'Lait ATP', 7);
        $order = $this->createOrder($user, $customer, $warehouse, $product, 3, 'ORDER-RES-DETAIL');

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('orders.confirm', $order))
            ->assertRedirect();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('stock.show', ['product' => $product->id, 'warehouse_id' => $warehouse->id]))
            ->assertOk()
            ->assertSee('Reservations ouvertes')
            ->assertSee($order->order_number)
            ->assertSee('Disponible a promettre');
    }

    public function test_order_show_highlights_reserved_stock_coverage(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $user->company_id)->firstOrFail();
        $warehouse = $this->defaultWarehouse($user);
        $product = $this->stockedProduct($user, $warehouse, 'PRD-RES-0004', 'Riz couverture reservee', 8);
        $order = $this->createOrder($user, $customer, $warehouse, $product, 5, 'ORDER-RES-COVERED');

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('orders.confirm', $order))
            ->assertRedirect();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee('Promesse logistique')
            ->assertSee('Reserve sur stock');
    }

    public function test_order_show_highlights_incoming_purchase_coverage(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $user->company_id)->firstOrFail();
        $supplier = Partner::query()->suppliers()->where('company_id', $user->company_id)->firstOrFail();
        $warehouse = $this->defaultWarehouse($user);
        $product = $this->stockedProduct($user, $warehouse, 'PRD-RES-0005', 'Sucre couverture achat', 2);
        $order = $this->createOrder($user, $customer, $warehouse, $product, 5, 'ORDER-RES-INCOMING');

        $this->createIncomingPurchaseOrder($user, $supplier, $warehouse, $product, 6, now()->addDays(4)->toDateString(), 'PO-RES-INCOMING');

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee('Couvert par approvisionnement')
            ->assertSee(now()->addDays(4)->format('d/m/Y'));
    }

    public function test_order_can_generate_purchase_request_from_at_risk_lines(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $user->company_id)->firstOrFail();
        $warehouse = $this->defaultWarehouse($user);
        $product = $this->stockedProduct($user, $warehouse, 'PRD-RES-0006', 'Farine couverture achat', 2);
        $order = $this->createOrder($user, $customer, $warehouse, $product, 5, 'ORDER-RES-PR');

        $response = $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('orders.generate-purchase-request', $order));

        $purchaseRequest = PurchaseRequest::query()
            ->where('company_id', $user->company_id)
            ->where('origin_sales_order_id', $order->id)
            ->firstOrFail();

        $response->assertRedirect(route('purchase-requests.show', $purchaseRequest));
        $this->assertSame('pending_approval', $purchaseRequest->status);
        $this->assertSame($order->id, $purchaseRequest->origin_sales_order_id);
        $this->assertStringContainsString($order->order_number, (string) $purchaseRequest->notes);
        $this->assertEqualsWithDelta(3, (float) $purchaseRequest->items()->firstOrFail()->qty, 0.001);
    }

    public function test_order_does_not_generate_duplicate_open_purchase_request(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $user->company_id)->firstOrFail();
        $warehouse = $this->defaultWarehouse($user);
        $product = $this->stockedProduct($user, $warehouse, 'PRD-RES-0007', 'Pates anti doublon', 1);
        $order = $this->createOrder($user, $customer, $warehouse, $product, 4, 'ORDER-RES-DUP');

        $firstResponse = $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('orders.generate-purchase-request', $order));

        $purchaseRequest = PurchaseRequest::query()
            ->where('company_id', $user->company_id)
            ->where('origin_sales_order_id', $order->id)
            ->firstOrFail();

        $firstResponse->assertRedirect(route('purchase-requests.show', $purchaseRequest));

        $secondResponse = $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('orders.generate-purchase-request', $order));

        $secondResponse->assertRedirect(route('purchase-requests.show', $purchaseRequest));
        $this->assertSame(1, PurchaseRequest::query()->where('origin_sales_order_id', $order->id)->count());
    }

    public function test_confirmed_order_can_convert_to_invoice_using_its_own_reservation(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $user->company_id)->firstOrFail();
        $warehouse = $this->defaultWarehouse($user);
        $product = $this->stockedProduct($user, $warehouse, 'PRD-RES-0003', 'Sucre conversion', 5);
        $order = $this->createOrder($user, $customer, $warehouse, $product, 5, 'ORDER-RES-CONVERT');

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('orders.confirm', $order))
            ->assertRedirect();

        $response = $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('orders.convert', $order), [
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays(5)->toDateString(),
                'notes' => 'FACTURE RESERVEE',
            ]);

        $order->refresh();
        $invoice = SalesInvoice::query()->where('company_id', $user->company_id)->where('id', $order->converted_sales_invoice_id)->firstOrFail();

        $response->assertRedirect(route('sales.show', $invoice));
        $this->assertSame('converted', $order->status);
        $this->assertSame($warehouse->id, $invoice->warehouse_id);
    }

    private function createOrder(User $user, Partner $customer, Warehouse $warehouse, Product $product, float $qty, string $notes): SalesOrder
    {
        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('orders.store'), [
                'customer_id' => $customer->id,
                'warehouse_id' => $warehouse->id,
                'order_date' => now()->toDateString(),
                'requested_delivery_date' => now()->addDays(3)->toDateString(),
                'notes' => $notes,
                'items' => [[
                    'product_id' => $product->id,
                    'description' => 'Commande reservee test',
                    'qty' => $qty,
                    'unit_price' => (float) $product->sale_price,
                ]],
            ])
            ->assertRedirect();

        return SalesOrder::query()->where('company_id', $user->company_id)->where('notes', $notes)->firstOrFail();
    }

    private function createIncomingPurchaseOrder(User $user, Partner $supplier, Warehouse $warehouse, Product $product, float $qty, string $expectedReceiptDate, string $orderNumber): PurchaseOrder
    {
        $order = PurchaseOrder::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'order_number' => $orderNumber,
            'order_date' => now()->toDateString(),
            'expected_receipt_date' => $expectedReceiptDate,
            'status' => 'confirmed',
            'subtotal' => $qty * (float) $product->purchase_price,
            'total' => $qty * (float) $product->purchase_price,
            'confirmed_at' => now(),
            'created_by' => $user->id,
        ]);

        PurchaseOrderItem::query()->create([
            'purchase_order_id' => $order->id,
            'product_id' => $product->id,
            'description' => 'Appro attente test',
            'qty' => $qty,
            'received_qty' => 0,
            'unit_cost' => (float) $product->purchase_price,
            'line_total' => $qty * (float) $product->purchase_price,
        ]);

        return $order;
    }

    private function stockedProduct(User $user, Warehouse $warehouse, string $sku, string $name, float $stock): Product
    {
        $product = Product::query()->create([
            'company_id' => $user->company_id,
            'sku' => $sku,
            'barcode' => '99'.str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT),
            'name' => $name,
            'unit' => 'piece',
            'type' => 'stockable',
            'tracking_type' => 'none',
            'sale_price' => 1000,
            'purchase_price' => 700,
            'min_stock' => 1,
            'is_active' => true,
            'sale_ok' => true,
            'purchase_ok' => true,
        ]);

        StockMovement::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'movement_type' => 'opening',
            'quantity_in' => $stock,
            'quantity_out' => 0,
            'unit_cost' => (float) $product->purchase_price,
            'reason' => 'Stock test reservation',
            'notes' => $sku,
            'movement_date' => now()->toDateString(),
            'created_by' => $user->id,
        ]);

        return $product;
    }

    private function defaultWarehouse(User $user): Warehouse
    {
        return Warehouse::query()
            ->where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->where('is_default', true)
            ->firstOrFail();
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}
