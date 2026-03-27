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
        $this->assertCount(1, $order->items);
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}



