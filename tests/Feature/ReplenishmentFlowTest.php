<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Partners\Models\Partner;
use App\Modules\Purchases\Models\PurchaseOrder;
use App\Modules\Purchases\Models\PurchaseRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReplenishmentFlowTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_replenishment_page_suggests_only_products_really_to_restock(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $warehouse = Warehouse::query()
            ->where('company_id', $manager->company_id)
            ->where('branch_id', $manager->branch_id)
            ->where('is_default', true)
            ->firstOrFail();

        $stockService = app(StockService::class);

        $suggestedProduct = $this->makeTrackedProduct($manager, [
            'sku' => 'PRD-REAPP-001',
            'name' => 'Riz sac 25kg',
            'min_stock' => 10,
            'reorder_max_qty' => 30,
            'reorder_multiple_qty' => 6,
            'purchase_lead_time_days' => 5,
        ]);
        $stockService->recordAdjustment($suggestedProduct, $manager->company_id, $manager->branch_id, 'in', 7, 16000, 'Stock test', 'Initial', $manager, now(), $warehouse->id);

        $coveredByOrder = $this->makeTrackedProduct($manager, [
            'sku' => 'PRD-REAPP-002',
            'name' => 'Huile 20L',
            'min_stock' => 10,
            'reorder_max_qty' => 20,
        ]);
        $stockService->recordAdjustment($coveredByOrder, $manager->company_id, $manager->branch_id, 'in', 8, 12500, 'Stock test', 'Initial', $manager, now(), $warehouse->id);

        $order = PurchaseOrder::query()->create([
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => 1,
            'order_number' => 'CF-BKO-TEST-REAPP-01',
            'order_date' => now()->toDateString(),
            'expected_receipt_date' => now()->addDays(3)->toDateString(),
            'status' => 'confirmed',
            'subtotal' => 62500,
            'total' => 62500,
            'notes' => 'Commande en cours',
            'confirmed_at' => now(),
            'created_by' => $manager->id,
        ]);
        $order->items()->create([
            'product_id' => $coveredByOrder->id,
            'description' => $coveredByOrder->name,
            'qty' => 5,
            'received_qty' => 0,
            'unit_cost' => 12500,
            'line_total' => 62500,
        ]);

        $coveredByRequest = $this->makeTrackedProduct($manager, [
            'sku' => 'PRD-REAPP-003',
            'name' => 'Sucre blanc 50kg',
            'min_stock' => 5,
            'reorder_max_qty' => 20,
        ]);
        $stockService->recordAdjustment($coveredByRequest, $manager->company_id, $manager->branch_id, 'in', 1, 22000, 'Stock test', 'Initial', $manager, now(), $warehouse->id);

        $request = PurchaseRequest::query()->create([
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'warehouse_id' => $warehouse->id,
            'request_number' => 'DA-BKO-TEST-REAPP-01',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(2)->toDateString(),
            'priority' => 'high',
            'status' => 'approved',
            'subtotal' => 330000,
            'total' => 330000,
            'notes' => 'Demande deja ouverte',
            'approved_at' => now(),
            'approved_by' => $manager->id,
            'created_by' => $manager->id,
        ]);
        $request->items()->create([
            'product_id' => $coveredByRequest->id,
            'description' => $coveredByRequest->name,
            'qty' => 15,
            'estimated_unit_cost' => 22000,
            'line_total' => 330000,
        ]);

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->get(route('replenishments.index', ['warehouse_id' => $warehouse->id]))
            ->assertOk()
            ->assertSee('Reapprovisionnement automatique')
            ->assertSee($suggestedProduct->name)
            ->assertSee('24,000')
            ->assertDontSee($coveredByOrder->name)
            ->assertDontSee($coveredByRequest->name);
    }

    public function test_manager_can_generate_purchase_request_from_replenishment_suggestions(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $warehouse = Warehouse::query()
            ->where('company_id', $manager->company_id)
            ->where('branch_id', $manager->branch_id)
            ->where('is_default', true)
            ->firstOrFail();

        $product = $this->makeTrackedProduct($manager, [
            'sku' => 'PRD-REAPP-010',
            'name' => 'Lait poudre 25kg',
            'min_stock' => 10,
            'reorder_max_qty' => 30,
            'reorder_multiple_qty' => 6,
            'purchase_lead_time_days' => 5,
            'purchase_price' => 18000,
        ]);

        app(StockService::class)->recordAdjustment($product, $manager->company_id, $manager->branch_id, 'in', 7, 18000, 'Stock test', 'Initial', $manager, now(), $warehouse->id);

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->post(route('replenishments.generate'), [
                'warehouse_id' => $warehouse->id,
                'selected' => [$product->id],
            ])
            ->assertRedirect();

        $purchaseRequest = PurchaseRequest::query()
            ->where('company_id', $manager->company_id)
            ->where('notes', 'like', 'Reappro automatique genere%')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('pending_approval', $purchaseRequest->status);
        $this->assertSame('normal', $purchaseRequest->priority);
        $this->assertSame(now()->addDays(5)->toDateString(), $purchaseRequest->needed_by_date?->toDateString());
        $this->assertCount(1, $purchaseRequest->items);
        $this->assertSame($product->id, $purchaseRequest->items->first()->product_id);
        $this->assertSame(24.0, (float) $purchaseRequest->items->first()->qty);
    }

    public function test_replenishment_uses_preferred_supplier_rules_when_available(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $warehouse = Warehouse::query()
            ->where('company_id', $manager->company_id)
            ->where('branch_id', $manager->branch_id)
            ->where('is_default', true)
            ->firstOrFail();
        $supplier = Partner::query()->suppliers()->where('company_id', $manager->company_id)->where('is_active', true)->firstOrFail();

        $product = $this->makeTrackedProduct($manager, [
            'sku' => 'PRD-REAPP-SUP-01',
            'name' => 'Semoule 10kg',
            'min_stock' => 10,
            'reorder_max_qty' => 12,
            'reorder_multiple_qty' => null,
            'purchase_lead_time_days' => 3,
            'purchase_price' => 18000,
        ]);

        $product->supplierInfos()->create([
            'tenant_id' => $manager->tenant_id,
            'company_id' => $manager->company_id,
            'supplier_id' => $supplier->id,
            'supplier_product_code' => 'SEM-FOUR-10',
            'supplier_product_name' => 'Semoule fournisseur 10kg',
            'min_qty' => 12,
            'unit_cost' => 13250,
            'lead_time_days' => 9,
            'is_preferred' => true,
        ]);

        app(StockService::class)->recordAdjustment($product, $manager->company_id, $manager->branch_id, 'in', 1, 13250, 'Stock test', 'Initial', $manager, now(), $warehouse->id);

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->get(route('replenishments.index', ['warehouse_id' => $warehouse->id]))
            ->assertOk()
            ->assertSee($supplier->name)
            ->assertSee('SEM-FOUR-10');

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->post(route('replenishments.generate'), [
                'warehouse_id' => $warehouse->id,
                'selected' => [$product->id],
            ])
            ->assertRedirect();

        $purchaseRequest = PurchaseRequest::query()
            ->where('company_id', $manager->company_id)
            ->where('notes', 'like', 'Reappro automatique genere%')
            ->latest('id')
            ->firstOrFail();

        $item = $purchaseRequest->items->firstOrFail();
        $this->assertSame(now()->addDays(9)->toDateString(), $purchaseRequest->needed_by_date?->toDateString());
        $this->assertSame(12.0, (float) $item->qty);
        $this->assertSame(13250.0, (float) $item->estimated_unit_cost);
        $this->assertSame('Semoule fournisseur 10kg', $item->description);
    }

    private function makeTrackedProduct(User $user, array $overrides = []): Product
    {
        return Product::query()->create(array_merge([
            'company_id' => $user->company_id,
            'sku' => 'PRD-REAPP-X',
            'name' => 'Produit reappro test',
            'unit' => 'unite',
            'type' => 'stockable',
            'sale_ok' => true,
            'purchase_ok' => true,
            'invoice_policy' => 'ordered',
            'tracking_type' => 'none',
            'sale_price' => 25000,
            'purchase_price' => 15000,
            'min_stock' => 5,
            'auto_replenish' => true,
            'reorder_max_qty' => 15,
            'reorder_multiple_qty' => null,
            'purchase_lead_time_days' => 3,
            'is_active' => true,
        ], $overrides));
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}
