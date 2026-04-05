<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Company\Models\PriceList;
use App\Modules\Core\Company\Models\PriceListItem;
use App\Modules\Partners\Models\Partner;
use App\Modules\Purchases\Models\PurchaseOrder;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Models\SalesQuote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductOrderOdooPropertiesTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_product_odoo_properties_are_saved_and_drive_sale_purchase_catalogs(): void
    {
        $user = User::query()->where('email', 'admin@nema-erp.test')->firstOrFail();

        $response = $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('products.store'), [
                'sku' => 'OD-PROD-001',
                'barcode' => '1234500000012',
                'name' => 'Produit Odoo Test',
                'category_id' => null,
                'type' => 'stockable',
                'unit' => 'unite',
                'sales_unit_name' => 'carton',
                'sales_unit_ratio' => 12,
                'purchase_unit_name' => 'pack fournisseur',
                'purchase_unit_ratio' => 24,
                'sale_ok' => '0',
                'purchase_ok' => '1',
                'invoice_policy' => 'delivered',
                'tracking_type' => 'lot',
                'sale_price' => 1500,
                'purchase_price' => 1000,
                'sale_tax_rule_id' => null,
                'purchase_tax_rule_id' => null,
                'min_stock' => 5,
                'description' => 'Description generale produit',
                'sales_description' => 'Description vente Odoo',
                'purchase_description' => 'Description achat Odoo',
                'internal_notes' => 'Note interne reservee equipe',
                'is_active' => '1',
            ]);

        $product = Product::query()
            ->where('company_id', $user->company_id)
            ->where('sku', 'OD-PROD-001')
            ->firstOrFail();

        $response->assertRedirect(route('products.show', $product));
        $this->assertFalse($product->sale_ok);
        $this->assertTrue($product->purchase_ok);
        $this->assertSame('delivered', $product->invoice_policy);
        $this->assertSame('lot', $product->tracking_type);
        $this->assertSame('Description vente Odoo', $product->sales_description);
        $this->assertSame('Description achat Odoo', $product->purchase_description);
        $this->assertSame('Note interne reservee equipe', $product->internal_notes);
        $this->assertSame('carton x 12', $product->salesUnitSummary());
        $this->assertSame('pack fournisseur x 24', $product->purchaseUnitSummary());

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('orders.create'))
            ->assertOk()
            ->assertDontSee('OD-PROD-001 - Produit Odoo Test');

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('purchase-orders.create'))
            ->assertOk()
            ->assertSee('OD-PROD-001 - Produit Odoo Test')
            ->assertSee('pack fournisseur x 24');
    }

    public function test_quote_uses_customer_price_list_when_catalog_price_is_submitted(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $user->company_id)->firstOrFail();
        $priceList = PriceList::query()->where('company_id', $user->company_id)->where('code', 'GROS')->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $customer->update(['price_list_id' => $priceList->id]);

        PriceListItem::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'price_list_id' => $priceList->id,
            'product_id' => $product->id,
            'min_qty' => 1,
            'price' => 777,
        ]);

        $response = $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('quotes.store'), [
                'customer_id' => $customer->id,
                'quote_date' => now()->format('Y-m-d'),
                'notes' => 'TEST-QUOTE-PRICE-LIST',
                'items' => [[
                    'product_id' => $product->id,
                    'description' => 'Ligne tarifee automatiquement',
                    'qty' => 2,
                    'unit_price' => (float) $product->sale_price,
                ]],
            ]);

        $quote = SalesQuote::query()
            ->where('company_id', $user->company_id)
            ->where('notes', 'TEST-QUOTE-PRICE-LIST')
            ->with('items')
            ->firstOrFail();

        $response->assertRedirect(route('quotes.show', $quote));
        $this->assertSame($priceList->id, $quote->price_list_id);
        $this->assertSame(777.0, (float) $quote->items->firstOrFail()->unit_price);
    }

    public function test_manager_can_create_customer_order_with_extended_odoo_like_properties(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $user->company_id)->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $response = $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('orders.store'), [
                'customer_id' => $customer->id,
                'order_date' => now()->format('Y-m-d'),
                'requested_delivery_date' => now()->addDays(5)->format('Y-m-d'),
                'customer_reference' => 'CLI-REF-778',
                'source_document' => 'APPEL-OFFRES-BKO',
                'salesperson_name' => 'Awa Diallo',
                'commitment_date' => now()->addDays(6)->format('Y-m-d'),
                'delivery_instruction' => 'Livrer cote magasin principal avant 16h.',
                'notes' => 'TEST-ORDER-ODOO-PROPS',
                'items' => [[
                    'product_id' => $product->id,
                    'description' => 'Commande test avec proprietes Odoo',
                    'qty' => 3,
                    'unit_price' => 500,
                ]],
            ]);

        $order = SalesOrder::query()
            ->where('company_id', $user->company_id)
            ->where('notes', 'TEST-ORDER-ODOO-PROPS')
            ->firstOrFail();

        $response->assertRedirect(route('orders.show', $order));
        $this->assertSame('CLI-REF-778', $order->customer_reference);
        $this->assertSame('APPEL-OFFRES-BKO', $order->source_document);
        $this->assertSame('Awa Diallo', $order->salesperson_name);
        $this->assertSame('Livrer cote magasin principal avant 16h.', $order->delivery_instruction);
        $this->assertSame(now()->addDays(6)->toDateString(), $order->commitment_date?->toDateString());

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('orders.index', ['search' => 'CLI-REF-778']))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Awa Diallo');
    }

    public function test_purchase_order_uses_supplier_price_list_when_catalog_cost_is_submitted(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $supplier = Partner::query()->suppliers()->where('company_id', $user->company_id)->firstOrFail();
        $priceList = PriceList::query()->where('company_id', $user->company_id)->where('code', 'GROS')->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $supplier->update(['price_list_id' => $priceList->id]);

        PriceListItem::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'price_list_id' => $priceList->id,
            'product_id' => $product->id,
            'min_qty' => 1,
            'price' => 555,
        ]);

        $warehouseId = $user->branch?->warehouses()->where('is_default', true)->value('id');
        if (! $warehouseId) {
            $warehouseId = $user->branch?->warehouses()->value('id');
        }

        $response = $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('purchase-orders.store'), [
                'supplier_id' => $supplier->id,
                'warehouse_id' => $warehouseId,
                'order_date' => now()->format('Y-m-d'),
                'notes' => 'TEST-PO-PRICE-LIST',
                'items' => [[
                    'product_id' => $product->id,
                    'description' => 'Ligne achat tarifee automatiquement',
                    'qty' => 4,
                    'unit_cost' => (float) $product->purchase_price,
                ]],
            ]);

        $order = PurchaseOrder::query()
            ->where('company_id', $user->company_id)
            ->where('notes', 'TEST-PO-PRICE-LIST')
            ->with('items')
            ->firstOrFail();

        $response->assertRedirect(route('purchase-orders.show', $order));
        $this->assertSame($priceList->id, $order->price_list_id);
        $this->assertSame(555.0, (float) $order->items->firstOrFail()->unit_cost);
    }

    public function test_product_supplier_infos_are_saved_and_drive_purchase_orders(): void
    {
        $user = User::query()->where('email', 'admin@nema-erp.test')->firstOrFail();
        $supplier = Partner::query()->suppliers()->where('company_id', $user->company_id)->where('is_active', true)->firstOrFail();
        $warehouseId = $user->branch?->warehouses()->where('is_default', true)->value('id')
            ?: $user->branch?->warehouses()->value('id');

        $response = $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('products.store'), [
                'sku' => 'OD-SUP-001',
                'barcode' => '1234500000991',
                'name' => 'Produit fournisseur prefere',
                'category_id' => null,
                'type' => 'stockable',
                'unit' => 'unite',
                'sale_ok' => '1',
                'purchase_ok' => '1',
                'invoice_policy' => 'ordered',
                'tracking_type' => 'none',
                'sale_price' => 1500,
                'purchase_price' => 1200,
                'sale_tax_rule_id' => null,
                'purchase_tax_rule_id' => null,
                'min_stock' => 4,
                'description' => 'Produit test fournisseur',
                'purchase_description' => 'Libelle achat standard',
                'is_active' => '1',
                'supplier_infos' => [[
                    'supplier_id' => (string) $supplier->id,
                    'supplier_product_code' => 'SUP-ODOO-77',
                    'supplier_product_name' => 'Libelle fournisseur prioritaire',
                    'min_qty' => 12,
                    'unit_cost' => 845,
                    'lead_time_days' => 6,
                    'is_preferred' => '1',
                ]],
            ]);

        $product = Product::query()
            ->where('company_id', $user->company_id)
            ->where('sku', 'OD-SUP-001')
            ->with('supplierInfos.supplier')
            ->firstOrFail();

        $response->assertRedirect(route('products.show', $product));
        $this->assertCount(1, $product->supplierInfos);
        $this->assertSame($supplier->id, $product->supplierInfos->first()->supplier_id);
        $this->assertTrue((bool) $product->supplierInfos->first()->is_preferred);
        $this->assertSame('SUP-ODOO-77', $product->supplierInfos->first()->supplier_product_code);
        $this->assertSame('Libelle fournisseur prioritaire', $product->supplierInfos->first()->supplier_product_name);
        $this->assertSame(845.0, (float) $product->supplierInfos->first()->unit_cost);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('products.show', $product))
            ->assertOk()
            ->assertSee($supplier->name)
            ->assertSee('SUP-ODOO-77');

        $orderResponse = $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('purchase-orders.store'), [
                'supplier_id' => $supplier->id,
                'warehouse_id' => $warehouseId,
                'order_date' => now()->format('Y-m-d'),
                'notes' => 'TEST-PO-SUPPLIER-INFO',
                'items' => [[
                    'product_id' => $product->id,
                    'description' => '',
                    'qty' => 15,
                    'unit_cost' => (float) $product->purchase_price,
                ]],
            ]);

        $order = PurchaseOrder::query()
            ->where('company_id', $user->company_id)
            ->where('notes', 'TEST-PO-SUPPLIER-INFO')
            ->with('items')
            ->firstOrFail();

        $orderResponse->assertRedirect(route('purchase-orders.show', $order));
        $this->assertSame(845.0, (float) $order->items->firstOrFail()->unit_cost);
        $this->assertSame('Libelle fournisseur prioritaire', $order->items->firstOrFail()->description);
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}
