<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Partners\Models\Partner;
use App\Modules\Purchases\Models\PurchaseOrder;
use App\Modules\Purchases\Models\PurchaseRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseRequestFlowTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_purchase_request_can_be_created_approved_and_converted_to_purchase_order(): void
    {
        $operations = User::query()->where('email', 'ops@nema-erp.test')->firstOrFail();
        $director = User::query()->where('email', 'dg@nema-erp.test')->firstOrFail();
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $warehouse = Warehouse::query()->where('company_id', $operations->company_id)->where('branch_id', $operations->branch_id)->where('is_default', true)->firstOrFail();
        $product = Product::query()->where('company_id', $operations->company_id)->where('sku', 'PRD-0001')->firstOrFail();
        $supplier = Partner::query()->suppliers()->where('company_id', $operations->company_id)->where('code', 'F0001')->firstOrFail();

        $this->actingAs($operations)
            ->withSession($this->workspaceSession($operations))
            ->post(route('purchase-requests.store'), [
                'warehouse_id' => $warehouse->id,
                'request_date' => now()->toDateString(),
                'needed_by_date' => now()->addDays(4)->toDateString(),
                'priority' => 'high',
                'notes' => 'Besoin magasin test',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'description' => 'Demande test eau',
                        'qty' => 12,
                        'estimated_unit_cost' => 250,
                    ],
                ],
            ])
            ->assertRedirect();

        $purchaseRequest = PurchaseRequest::query()->where('company_id', $operations->company_id)->firstOrFail();

        $this->assertSame('pending_approval', $purchaseRequest->status);
        $this->assertMatchesRegularExpression('/^DA-BKO-\d{4}-\d{5}$/', $purchaseRequest->request_number);

        $this->actingAs($director)
            ->withSession($this->workspaceSession($director))
            ->post(route('purchase-requests.approve', $purchaseRequest))
            ->assertRedirect(route('purchase-requests.show', $purchaseRequest));

        $purchaseRequest->refresh();
        $this->assertSame('approved', $purchaseRequest->status);
        $this->assertNotNull($purchaseRequest->approved_at);

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->post(route('purchase-requests.convert', $purchaseRequest), [
                'supplier_id' => $supplier->id,
            ])
            ->assertRedirect();

        $purchaseRequest->refresh();
        $order = PurchaseOrder::query()->findOrFail($purchaseRequest->converted_purchase_order_id);

        $this->assertSame('converted', $purchaseRequest->status);
        $this->assertSame($order->id, $purchaseRequest->converted_purchase_order_id);
        $this->assertSame($supplier->id, $order->supplier_id);
        $this->assertSame($purchaseRequest->id, $order->source_purchase_request_id);
        $this->assertCount(1, $order->items);
    }

    public function test_approved_purchase_request_can_auto_convert_into_supplier_recommended_orders(): void
    {
        $operations = User::query()->where('email', 'ops@nema-erp.test')->firstOrFail();
        $director = User::query()->where('email', 'dg@nema-erp.test')->firstOrFail();
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $warehouse = Warehouse::query()->where('company_id', $operations->company_id)->where('branch_id', $operations->branch_id)->where('is_default', true)->firstOrFail();
        $productA = Product::query()->where('company_id', $operations->company_id)->where('sku', 'PRD-0001')->firstOrFail();
        $productB = Product::query()->where('company_id', $operations->company_id)->where('sku', 'PRD-0002')->firstOrFail();
        $supplierA = Partner::query()->suppliers()->where('company_id', $operations->company_id)->where('code', 'F0001')->firstOrFail();
        $supplierB = Partner::query()->suppliers()->where('company_id', $operations->company_id)->where('code', 'F0002')->firstOrFail();

        $productA->supplierInfos()->delete();
        $productB->supplierInfos()->delete();

        $productA->supplierInfos()->create([
            'tenant_id' => $productA->tenant_id,
            'company_id' => $productA->company_id,
            'supplier_id' => $supplierA->id,
            'supplier_product_code' => 'EAU-MFP-01',
            'supplier_product_name' => 'Eau minerale palette Mali Fournitures Pro',
            'min_qty' => 6,
            'unit_cost' => 240,
            'lead_time_days' => 2,
            'is_preferred' => true,
        ]);

        $productB->supplierInfos()->create([
            'tenant_id' => $productB->tenant_id,
            'company_id' => $productB->company_id,
            'supplier_id' => $supplierB->id,
            'supplier_product_code' => 'SUC-AGRO-02',
            'supplier_product_name' => 'Sucre 1kg Agro Import',
            'min_qty' => 4,
            'unit_cost' => 470,
            'lead_time_days' => 5,
            'is_preferred' => true,
        ]);

        $this->actingAs($operations)
            ->withSession($this->workspaceSession($operations))
            ->post(route('purchase-requests.store'), [
                'warehouse_id' => $warehouse->id,
                'request_date' => now()->toDateString(),
                'needed_by_date' => now()->addDays(7)->toDateString(),
                'priority' => 'urgent',
                'notes' => 'AUTO-SPLIT-PR',
                'items' => [
                    [
                        'product_id' => $productA->id,
                        'description' => 'Besoin eau lot 1',
                        'qty' => 10,
                        'estimated_unit_cost' => 250,
                    ],
                    [
                        'product_id' => $productB->id,
                        'description' => 'Besoin sucre lot 2',
                        'qty' => 8,
                        'estimated_unit_cost' => 500,
                    ],
                ],
            ])
            ->assertRedirect();

        $purchaseRequest = PurchaseRequest::query()
            ->where('company_id', $operations->company_id)
            ->where('notes', 'AUTO-SPLIT-PR')
            ->firstOrFail();

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->get(route('purchase-requests.show', $purchaseRequest))
            ->assertOk()
            ->assertSeeText('Plan fournisseurs recommande')
            ->assertSeeText($supplierA->name)
            ->assertSeeText($supplierB->name);

        $this->actingAs($director)
            ->withSession($this->workspaceSession($director))
            ->post(route('purchase-requests.approve', $purchaseRequest))
            ->assertRedirect(route('purchase-requests.show', $purchaseRequest));

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->get(route('purchase-requests.show', $purchaseRequest))
            ->assertOk()
            ->assertSeeText('Generer 2 commandes recommandees');

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->post(route('purchase-requests.auto-convert', $purchaseRequest))
            ->assertRedirect(route('purchase-requests.show', $purchaseRequest));

        $purchaseRequest->refresh();
        $orders = PurchaseOrder::query()
            ->where('source_purchase_request_id', $purchaseRequest->id)
            ->with('items')
            ->orderBy('supplier_id')
            ->get();

        $this->assertSame('converted', $purchaseRequest->status);
        $this->assertCount(2, $orders);
        $this->assertTrue($orders->pluck('supplier_id')->contains($supplierA->id));
        $this->assertTrue($orders->pluck('supplier_id')->contains($supplierB->id));
        $this->assertTrue($orders->pluck('id')->contains($purchaseRequest->converted_purchase_order_id));

        $orderA = $orders->firstWhere('supplier_id', $supplierA->id);
        $orderB = $orders->firstWhere('supplier_id', $supplierB->id);

        $this->assertNotNull($orderA);
        $this->assertNotNull($orderB);
        $this->assertSame($purchaseRequest->id, $orderA->source_purchase_request_id);
        $this->assertSame($purchaseRequest->id, $orderB->source_purchase_request_id);
        $this->assertCount(1, $orderA->items);
        $this->assertCount(1, $orderB->items);
        $this->assertSame($productA->id, $orderA->items->firstOrFail()->product_id);
        $this->assertSame($productB->id, $orderB->items->firstOrFail()->product_id);
        $this->assertEqualsWithDelta(240, (float) $orderA->items->firstOrFail()->unit_cost, 0.001);
        $this->assertEqualsWithDelta(470, (float) $orderB->items->firstOrFail()->unit_cost, 0.001);

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->get(route('purchase-requests.show', $purchaseRequest))
            ->assertOk()
            ->assertSeeText('Commandes fournisseurs generees')
            ->assertSeeText($orderA->order_number)
            ->assertSeeText($orderB->order_number);
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}
