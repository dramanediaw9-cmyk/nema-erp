<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductSupplier;
use App\Modules\Core\Access\Models\Permission;
use App\Modules\Core\Access\Models\Role;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\PriceList;
use App\Modules\Core\Company\Models\PriceListItem;
use App\Modules\Partners\Models\Partner;
use App\Modules\Purchases\Models\GoodsReceipt;
use App\Modules\Purchases\Models\GoodsReceiptItem;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Purchases\Models\PurchaseBillItem;
use App\Modules\Purchases\Models\PurchaseOrder;
use App\Modules\Purchases\Models\PurchaseOrderItem;
use App\Modules\Purchases\Models\PurchaseRequest;
use App\Modules\Purchases\Services\PurchaseRequestService;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Services\SalesPortalLinkService;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StrategicUpgradesTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_operations_cannot_override_sales_price_without_permission(): void
    {
        $ops = User::query()->where('email', 'ops@nema-erp.test')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $ops->company_id)->firstOrFail();
        $priceList = PriceList::query()->where('company_id', $ops->company_id)->where('code', 'GROS')->firstOrFail();
        $product = Product::query()->where('company_id', $ops->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $customer->update(['price_list_id' => $priceList->id]);
        PriceListItem::query()->updateOrCreate(
            [
                'price_list_id' => $priceList->id,
                'product_id' => $product->id,
                'min_qty' => 1,
            ],
            [
                'tenant_id' => $ops->tenant_id,
                'company_id' => $ops->company_id,
                'price' => 777,
            ]
        );

        $this->actingAs($ops)
            ->withSession($this->workspaceSession($ops))
            ->from(route('quotes.create'))
            ->post(route('quotes.store'), [
                'customer_id' => $customer->id,
                'quote_date' => now()->toDateString(),
                'notes' => 'TEST-PRICE-OVERRIDE-DENIED',
                'items' => [[
                    'product_id' => $product->id,
                    'description' => 'Prix force interdit',
                    'qty' => 2,
                    'unit_price' => 950,
                ]],
            ])
            ->assertRedirect(route('quotes.create'))
            ->assertSessionHasErrors('items');

        $this->assertDatabaseMissing('sales_quotes', [
            'company_id' => $ops->company_id,
            'notes' => 'TEST-PRICE-OVERRIDE-DENIED',
        ]);
    }

    public function test_branch_limited_report_user_does_not_see_margin_or_cross_branch_scope(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $branch = Branch::query()->where('company_id', $manager->company_id)->where('code', 'BKO')->firstOrFail();

        $user = UserFactory::new()->create([
            'tenant_id' => $manager->tenant_id,
            'company_id' => $manager->company_id,
            'branch_id' => $branch->id,
            'email' => 'report-limited@nema-erp.test',
            'name' => 'Report Limited',
            'is_active' => true,
        ]);

        $role = Role::query()->create([
            'tenant_id' => $manager->tenant_id,
            'company_id' => $manager->company_id,
            'name' => 'Lecteur rapports agence',
            'slug' => 'branch_report_reader',
            'description' => 'Lecture restreinte des rapports',
            'is_system' => false,
        ]);
        $role->permissions()->sync(Permission::query()->where('slug', 'reports.view')->pluck('id')->all());
        $user->roles()->attach($role);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('reports.index', [
                'date_from' => now()->startOfMonth()->toDateString(),
                'date_to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Indicateurs sensibles')
            ->assertSee('Perimetre agence verrouille')
            ->assertDontSee('Marge par categorie')
            ->assertDontSee('Toutes agences')
            ->assertDontSee('Ventes par agence');
    }

    public function test_payment_gateways_are_visible_on_invoice_and_public_portal(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $invoice = $this->createInvoice($manager, 'TEST-TERRAIN-PAYMENTS');

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->put(route('settings.payment-gateways.update'), [
                'channels' => [
                    'wave' => [
                        'enabled' => '1',
                        'label' => 'Wave Collecte',
                        'account_name' => 'Nema Distribution',
                        'collection_number' => '+22370001111',
                        'instructions' => 'Utilise la reference facture dans le commentaire.',
                    ],
                    'orange_money' => [
                        'enabled' => '1',
                        'label' => 'Orange Money Pro',
                        'account_name' => 'Nema Orange',
                        'collection_number' => '+22370002222',
                        'instructions' => 'Envoie la capture du transfert a l equipe.',
                    ],
                    'moov_money' => [
                        'enabled' => '0',
                        'label' => 'Moov Money',
                        'account_name' => '',
                        'collection_number' => '',
                        'instructions' => '',
                    ],
                    'bank_transfer' => [
                        'enabled' => '1',
                        'label' => 'Virement BDM',
                        'account_name' => 'Nema Distribution BDM',
                        'collection_number' => 'ML12-1234-5678-9999',
                        'instructions' => 'Rappelle le numero de facture dans le motif.',
                    ],
                ],
            ])
            ->assertRedirect(route('settings.index'));

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->get(route('sales.show', $invoice))
            ->assertOk()
            ->assertSee('Wave Collecte')
            ->assertSee('+22370001111')
            ->assertSee('Orange Money Pro')
            ->assertSee('+22370002222')
            ->assertSee($invoice->invoice_number);

        $portal = app(SalesPortalLinkService::class)->invoicePaymentPortalData($invoice->fresh(['customer', 'branch', 'company', 'items.product', 'creator', 'approver', 'latestPortalAction']));

        $this->get($portal['view_url'])
            ->assertOk()
            ->assertSee('Wave Collecte')
            ->assertSee('+22370001111')
            ->assertSee('Orange Money Pro')
            ->assertSee('+22370002222')
            ->assertSee($invoice->invoice_number);
    }

    public function test_supplier_performance_is_used_in_purchase_recommendations_and_reporting(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $warehouseId = $manager->branch?->warehouses()->where('is_default', true)->value('id')
            ?: $manager->branch?->warehouses()->value('id');
        $product = Product::query()->where('company_id', $manager->company_id)->where('sku', 'PRD-0001')->firstOrFail();
        $strongSupplier = Partner::query()->suppliers()->where('company_id', $manager->company_id)->firstOrFail();
        $weakSupplier = Partner::query()->create([
            'company_id' => $manager->company_id,
            'tenant_id' => $manager->tenant_id,
            'type' => 'supplier',
            'code' => 'SUP-RISK-001',
            'name' => 'Fournisseur en retard',
            'phone' => '70009999',
            'is_active' => true,
        ]);

        ProductSupplier::query()->create([
            'tenant_id' => $manager->tenant_id,
            'company_id' => $manager->company_id,
            'product_id' => $product->id,
            'supplier_id' => $strongSupplier->id,
            'supplier_product_code' => 'SUP-STRONG-01',
            'supplier_product_name' => 'Produit fiable',
            'min_qty' => 1,
            'unit_cost' => 1200,
            'lead_time_days' => 5,
            'is_preferred' => false,
        ]);
        ProductSupplier::query()->create([
            'tenant_id' => $manager->tenant_id,
            'company_id' => $manager->company_id,
            'product_id' => $product->id,
            'supplier_id' => $weakSupplier->id,
            'supplier_product_code' => 'SUP-WEAK-01',
            'supplier_product_name' => 'Produit a risque',
            'min_qty' => 1,
            'unit_cost' => 1200,
            'lead_time_days' => 5,
            'is_preferred' => false,
        ]);

        $this->seedSupplierHistory($manager, $warehouseId, $product, $strongSupplier, 'STRONG', now()->subDays(20), now()->subDays(15), now()->subDays(15), 65000, 0);
        $this->seedSupplierHistory($manager, $warehouseId, $product, $weakSupplier, 'WEAK', now()->subDays(20), now()->subDays(15), now()->subDays(8), 65000, 30000);

        $purchaseRequest = PurchaseRequest::query()->create([
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'warehouse_id' => $warehouseId,
            'request_number' => 'REQ-PERF-001',
            'request_date' => now()->toDateString(),
            'priority' => 'high',
            'status' => 'approved',
            'subtotal' => 12000,
            'total' => 12000,
            'created_by' => $manager->id,
            'approved_by' => $manager->id,
            'approved_at' => now(),
        ]);
        $purchaseRequest->items()->create([
            'product_id' => $product->id,
            'description' => 'Besoin de reappro',
            'qty' => 10,
            'estimated_unit_cost' => 1200,
            'line_total' => 12000,
        ]);

        $plan = app(PurchaseRequestService::class)->supplierRecommendationPlan(
            $purchaseRequest->fresh(['items.product.supplierInfos.supplier', 'generatedPurchaseOrders.supplier'])
        );

        $this->assertSame($strongSupplier->id, $plan['items']->first()['recommended_supplier_id']);

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->get(route('reports.index', [
                'date_from' => now()->subDays(30)->toDateString(),
                'date_to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Performance fournisseurs')
            ->assertSee($strongSupplier->name);

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->get(route('suppliers.show', $strongSupplier))
            ->assertOk()
            ->assertSee('Performance fournisseur')
            ->assertSee('Score')
            ->assertSee($strongSupplier->name);
    }

    private function createInvoice(User $user, string $notes): SalesInvoice
    {
        $customer = Partner::query()->customers()->where('company_id', $user->company_id)->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('sales.store'), [
                'customer_id' => $customer->id,
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays(7)->toDateString(),
                'notes' => $notes,
                'items' => [[
                    'product_id' => $product->id,
                    'description' => 'Facture test paiement terrain',
                    'qty' => 2,
                    'unit_price' => (float) $product->sale_price,
                ]],
            ])
            ->assertRedirect();

        return SalesInvoice::query()->where('company_id', $user->company_id)->where('notes', $notes)->firstOrFail();
    }

    private function seedSupplierHistory(User $user, int $warehouseId, Product $product, Partner $supplier, string $suffix, $orderDate, $expectedDate, $receiptDate, float $billTotal, float $openBalance): void
    {
        $order = PurchaseOrder::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'warehouse_id' => $warehouseId,
            'supplier_id' => $supplier->id,
            'order_number' => 'PO-'.$suffix,
            'order_date' => $orderDate->toDateString(),
            'expected_receipt_date' => $expectedDate->toDateString(),
            'status' => 'received',
            'subtotal' => $billTotal,
            'total' => $billTotal,
            'created_by' => $user->id,
        ]);

        $orderItem = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $order->id,
            'product_id' => $product->id,
            'description' => 'Ligne achat '.$suffix,
            'qty' => 10,
            'received_qty' => 10,
            'unit_cost' => $billTotal / 10,
            'line_total' => $billTotal,
        ]);

        $receipt = GoodsReceipt::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'warehouse_id' => $warehouseId,
            'purchase_order_id' => $order->id,
            'supplier_id' => $supplier->id,
            'receipt_number' => 'GR-'.$suffix,
            'receipt_date' => $receiptDate->toDateString(),
            'status' => 'received',
            'subtotal' => $billTotal,
            'total' => $billTotal,
            'received_at' => $receiptDate,
            'created_by' => $user->id,
        ]);

        GoodsReceiptItem::query()->create([
            'goods_receipt_id' => $receipt->id,
            'purchase_order_item_id' => $orderItem->id,
            'product_id' => $product->id,
            'description' => 'Reception '.$suffix,
            'qty' => 10,
            'unit_cost' => $billTotal / 10,
            'line_total' => $billTotal,
        ]);

        $bill = PurchaseBill::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'warehouse_id' => $warehouseId,
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $order->id,
            'goods_receipt_id' => $receipt->id,
            'bill_number' => 'BIL-'.$suffix,
            'bill_date' => $receiptDate->toDateString(),
            'due_date' => $receiptDate->copy()->addDays(15)->toDateString(),
            'status' => 'validated',
            'payment_status' => $openBalance > 0 ? 'partial' : 'paid',
            'subtotal' => $billTotal,
            'net_total' => $billTotal,
            'tax_total' => 0,
            'total' => $billTotal,
            'amount_paid' => $billTotal - $openBalance,
            'balance_due' => $openBalance,
            'validated_at' => $receiptDate,
            'approved_at' => $receiptDate,
            'approved_by' => $user->id,
            'created_by' => $user->id,
        ]);

        PurchaseBillItem::query()->create([
            'purchase_bill_id' => $bill->id,
            'product_id' => $product->id,
            'description' => 'Facture '.$suffix,
            'qty' => 10,
            'unit_cost' => $billTotal / 10,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'line_total' => $billTotal,
        ]);
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}

