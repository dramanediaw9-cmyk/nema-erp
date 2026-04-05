<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Partners\Models\Partner;
use App\Modules\Purchases\Models\PurchaseOrder;
use App\Modules\Purchases\Models\PurchaseRequest;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Models\SalesQuote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductBlockingTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_sales_invoice_creation_rejects_sale_blocked_product(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $user->company_id)->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();
        $product->update([
            'sale_blocked' => true,
            'sale_block_reason' => 'Controle qualite',
        ]);

        $response = $this->from(route('sales.create'))
            ->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('sales.store'), [
                'customer_id' => $customer->id,
                'invoice_date' => now()->toDateString(),
                'notes' => 'BLOCK-SALE-INVOICE',
                'items' => [[
                    'product_id' => $product->id,
                    'description' => 'Produit bloque a la vente',
                    'qty' => 1,
                    'unit_price' => 500,
                ]],
            ]);

        $response->assertRedirect(route('sales.create'));
        $response->assertSessionHasErrors('items');
        $this->assertDatabaseMissing('sales_invoices', [
            'company_id' => $user->company_id,
            'notes' => 'BLOCK-SALE-INVOICE',
        ]);
    }

    public function test_accepted_quote_cannot_convert_to_order_when_product_becomes_sale_blocked(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $user->company_id)->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('quotes.store'), [
                'customer_id' => $customer->id,
                'quote_date' => now()->toDateString(),
                'valid_until' => now()->addDays(7)->toDateString(),
                'notes' => 'BLOCK-QUOTE-ORDER',
                'items' => [[
                    'product_id' => $product->id,
                    'description' => 'Conversion en commande bloquee',
                    'qty' => 2,
                    'unit_price' => 600,
                ]],
            ])
            ->assertRedirect();

        $quote = SalesQuote::query()->where('company_id', $user->company_id)->where('notes', 'BLOCK-QUOTE-ORDER')->firstOrFail();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('quotes.accept', $quote))
            ->assertRedirect(route('quotes.show', $quote));

        $product->update([
            'sale_blocked' => true,
            'sale_block_reason' => 'Retenu pour verification',
        ]);

        $response = $this->from(route('quotes.show', $quote))
            ->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('quotes.convert-order', $quote), [
                'order_date' => now()->toDateString(),
                'requested_delivery_date' => now()->addDays(3)->toDateString(),
                'notes' => 'Essai conversion bloquee',
            ]);

        $response->assertRedirect(route('quotes.show', $quote));
        $response->assertSessionHasErrors('items');
        $quote->refresh();
        $this->assertNull($quote->converted_sales_order_id);
        $this->assertSame('accepted', $quote->status);
        $this->assertSame(0, SalesOrder::query()->where('company_id', $user->company_id)->where('origin_sales_quote_id', $quote->id)->count());
    }

    public function test_purchase_request_creation_rejects_purchase_blocked_product(): void
    {
        $user = User::query()->where('email', 'ops@nema-erp.test')->firstOrFail();
        $warehouse = Warehouse::query()->where('company_id', $user->company_id)->where('branch_id', $user->branch_id)->where('is_default', true)->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();
        $product->update([
            'purchase_blocked' => true,
            'purchase_block_reason' => 'Fournisseur suspendu',
        ]);

        $response = $this->from(route('purchase-requests.create'))
            ->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('purchase-requests.store'), [
                'warehouse_id' => $warehouse->id,
                'request_date' => now()->toDateString(),
                'needed_by_date' => now()->addDays(3)->toDateString(),
                'priority' => 'high',
                'notes' => 'BLOCK-PURCHASE-REQUEST',
                'items' => [[
                    'product_id' => $product->id,
                    'description' => 'Produit bloque a l achat',
                    'qty' => 5,
                    'estimated_unit_cost' => 300,
                ]],
            ]);

        $response->assertRedirect(route('purchase-requests.create'));
        $response->assertSessionHasErrors('items');
        $this->assertDatabaseMissing('purchase_requests', [
            'company_id' => $user->company_id,
            'notes' => 'BLOCK-PURCHASE-REQUEST',
        ]);
    }

    public function test_approved_purchase_request_cannot_convert_to_order_when_product_becomes_purchase_blocked(): void
    {
        $operations = User::query()->where('email', 'ops@nema-erp.test')->firstOrFail();
        $director = User::query()->where('email', 'dg@nema-erp.test')->firstOrFail();
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $warehouse = Warehouse::query()->where('company_id', $operations->company_id)->where('branch_id', $operations->branch_id)->where('is_default', true)->firstOrFail();
        $product = Product::query()->where('company_id', $operations->company_id)->where('sku', 'PRD-0001')->firstOrFail();
        $supplier = Partner::query()->suppliers()->where('company_id', $operations->company_id)->firstOrFail();

        $this->actingAs($operations)
            ->withSession($this->workspaceSession($operations))
            ->post(route('purchase-requests.store'), [
                'warehouse_id' => $warehouse->id,
                'request_date' => now()->toDateString(),
                'needed_by_date' => now()->addDays(4)->toDateString(),
                'priority' => 'normal',
                'notes' => 'BLOCK-REQUEST-CONVERT',
                'items' => [[
                    'product_id' => $product->id,
                    'description' => 'Demande avant blocage achat',
                    'qty' => 8,
                    'estimated_unit_cost' => 280,
                ]],
            ])
            ->assertRedirect();

        $purchaseRequest = PurchaseRequest::query()->where('company_id', $operations->company_id)->where('notes', 'BLOCK-REQUEST-CONVERT')->firstOrFail();

        $this->actingAs($director)
            ->withSession($this->workspaceSession($director))
            ->post(route('purchase-requests.approve', $purchaseRequest))
            ->assertRedirect(route('purchase-requests.show', $purchaseRequest));

        $product->update([
            'purchase_blocked' => true,
            'purchase_block_reason' => 'Article suspendu provisoirement',
        ]);

        $response = $this->from(route('purchase-requests.show', $purchaseRequest))
            ->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->post(route('purchase-requests.convert', $purchaseRequest), [
                'supplier_id' => $supplier->id,
            ]);

        $response->assertRedirect(route('purchase-requests.show', $purchaseRequest));
        $response->assertSessionHasErrors('items');
        $purchaseRequest->refresh();
        $this->assertNull($purchaseRequest->converted_purchase_order_id);
        $this->assertSame('approved', $purchaseRequest->status);
        $this->assertSame(0, PurchaseOrder::query()->where('company_id', $operations->company_id)->where('notes', 'like', '%'.$purchaseRequest->request_number.'%')->count());
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}