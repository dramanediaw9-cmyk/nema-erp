<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Partners\Models\Partner;
use App\Modules\Purchases\Models\GoodsReceipt;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Purchases\Models\PurchaseOrder;
use App\Modules\Purchases\Models\PurchaseRequest;
use App\Modules\Treasury\Models\CashAccount;
use App\Modules\Treasury\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseProcureToPayFlowTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_purchase_request_can_flow_until_receipt_bill_and_payment_without_double_stock(): void
    {
        $operations = User::query()->where('email', 'ops@nema-erp.test')->firstOrFail();
        $director = User::query()->where('email', 'dg@nema-erp.test')->firstOrFail();
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $warehouse = Warehouse::query()->where('company_id', $operations->company_id)->where('branch_id', $operations->branch_id)->where('is_default', true)->firstOrFail();
        $product = Product::query()->where('company_id', $operations->company_id)->where('sku', 'PRD-0001')->firstOrFail();
        $supplier = Partner::query()->suppliers()->where('company_id', $operations->company_id)->where('code', 'F0001')->firstOrFail();
        $cashAccount = CashAccount::query()->where('company_id', $manager->company_id)->where('name', 'Banque BDM')->firstOrFail();

        $initialStock = $this->stockBalance($operations->company_id, $operations->branch_id, $product->id, $warehouse->id);

        $this->actingAs($operations)
            ->withSession($this->workspaceSession($operations))
            ->post(route('purchase-requests.store'), [
                'warehouse_id' => $warehouse->id,
                'request_date' => now()->toDateString(),
                'needed_by_date' => now()->addDays(5)->toDateString(),
                'priority' => 'high',
                'notes' => 'FLOW-PROC-001',
                'items' => [[
                    'product_id' => $product->id,
                    'description' => 'Flux achat complet',
                    'qty' => 6,
                    'estimated_unit_cost' => 250,
                ]],
            ])
            ->assertRedirect();

        $purchaseRequest = PurchaseRequest::query()->where('company_id', $operations->company_id)->where('notes', 'FLOW-PROC-001')->firstOrFail();

        $this->actingAs($director)
            ->withSession($this->workspaceSession($director))
            ->post(route('purchase-requests.approve', $purchaseRequest))
            ->assertRedirect(route('purchase-requests.show', $purchaseRequest));

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->post(route('purchase-requests.convert', $purchaseRequest), [
                'supplier_id' => $supplier->id,
            ])
            ->assertRedirect();

        $purchaseRequest->refresh();
        $order = PurchaseOrder::query()->findOrFail($purchaseRequest->converted_purchase_order_id);

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->post(route('purchase-orders.confirm', $order))
            ->assertRedirect(route('purchase-orders.show', $order));

        $orderItem = $order->items()->firstOrFail();

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->post(route('goods-receipts.store'), [
                'order_id' => $order->id,
                'receipt_date' => now()->toDateString(),
                'notes' => 'FLOW-GR-001',
                'items' => [[
                    'purchase_order_item_id' => $orderItem->id,
                    'qty' => 6,
                ]],
            ])
            ->assertRedirect();

        $receipt = GoodsReceipt::query()->where('company_id', $manager->company_id)->where('notes', 'FLOW-GR-001')->firstOrFail();
        $stockAfterReceipt = $this->stockBalance($manager->company_id, $manager->branch_id, $product->id, $warehouse->id);

        $this->assertEqualsWithDelta($initialStock + 6, $stockAfterReceipt, 0.001);
        $this->assertSame('received', $order->fresh()->status);

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->get(route('purchases.create', ['receipt' => $receipt->id]))
            ->assertOk()
            ->assertSee($receipt->receipt_number)
            ->assertSee($order->order_number);

        $receiptItem = $receipt->items()->firstOrFail();

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->post(route('purchases.store'), [
                'goods_receipt_id' => $receipt->id,
                'supplier_id' => $supplier->id,
                'warehouse_id' => $warehouse->id,
                'bill_date' => now()->toDateString(),
                'due_date' => now()->addDays(15)->toDateString(),
                'notes' => 'FLOW-BILL-001',
                'items' => [[
                    'goods_receipt_item_id' => $receiptItem->id,
                    'product_id' => $product->id,
                    'description' => 'Facture receptionnee test',
                    'qty' => 6,
                    'unit_cost' => 260,
                ]],
            ])
            ->assertRedirect();

        $bill = PurchaseBill::query()->where('company_id', $manager->company_id)->where('notes', 'FLOW-BILL-001')->firstOrFail();
        $stockAfterBill = $this->stockBalance($manager->company_id, $manager->branch_id, $product->id, $warehouse->id);

        $this->assertSame($order->id, $bill->purchase_order_id);
        $this->assertSame($receipt->id, $bill->goods_receipt_id);
        $this->assertSame('validated', $bill->status);
        $this->assertEqualsWithDelta($stockAfterReceipt, $stockAfterBill, 0.001);
        $this->assertNotNull($receipt->fresh()->purchaseBill);

        $entry = JournalEntry::query()
            ->where('company_id', $manager->company_id)
            ->where('source_type', PurchaseBill::class)
            ->where('source_id', $bill->id)
            ->where('journal_code', 'ACH')
            ->firstOrFail();

        $this->assertEqualsWithDelta((float) $bill->total, (float) $entry->total_debit, 0.001);
        $this->assertEqualsWithDelta((float) $bill->total, (float) $entry->total_credit, 0.001);

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->post(route('payments.store'), [
                'payment_type' => 'supplier_payment',
                'purchase_bill_id' => $bill->id,
                'cash_account_id' => $cashAccount->id,
                'payment_date' => now()->toDateString(),
                'amount' => (float) $bill->balance_due,
                'method' => 'bank_transfer',
                'reference' => 'FLOW-SUP-PAY-001',
                'notes' => 'Reglement complet flux achat',
            ])
            ->assertRedirect(route('purchases.show', $bill));

        $bill->refresh();
        $payment = Payment::query()->where('company_id', $manager->company_id)->where('reference', 'FLOW-SUP-PAY-001')->firstOrFail();

        $this->assertSame('paid', $bill->payment_status);
        $this->assertEqualsWithDelta(0, (float) $bill->balance_due, 0.001);
        $this->assertDatabaseHas('journal_entries', [
            'company_id' => $manager->company_id,
            'source_type' => Payment::class,
            'source_id' => $payment->id,
            'journal_code' => 'TRE',
        ]);

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->get(route('purchases.show', $bill))
            ->assertOk()
            ->assertSee($receipt->receipt_number)
            ->assertSee($order->order_number)
            ->assertSee('Stock deja receptionne avant facturation');
    }

    public function test_goods_receipt_cannot_generate_duplicate_purchase_bill(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $supplier = Partner::query()->suppliers()->where('company_id', $manager->company_id)->firstOrFail();
        $product = Product::query()->where('company_id', $manager->company_id)->where('sku', 'PRD-0001')->firstOrFail();
        $warehouse = Warehouse::query()->where('company_id', $manager->company_id)->where('branch_id', $manager->branch_id)->where('is_default', true)->firstOrFail();

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->post(route('purchase-orders.store'), [
                'supplier_id' => $supplier->id,
                'warehouse_id' => $warehouse->id,
                'order_date' => now()->toDateString(),
                'expected_receipt_date' => now()->addDays(3)->toDateString(),
                'notes' => 'FLOW-DUP-PO',
                'items' => [[
                    'product_id' => $product->id,
                    'description' => 'Commande doublon facture',
                    'qty' => 4,
                    'unit_cost' => 300,
                ]],
            ])
            ->assertRedirect();

        $order = PurchaseOrder::query()->where('company_id', $manager->company_id)->where('notes', 'FLOW-DUP-PO')->firstOrFail();

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->post(route('purchase-orders.confirm', $order))
            ->assertRedirect();

        $orderItem = $order->items()->firstOrFail();

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->post(route('goods-receipts.store'), [
                'order_id' => $order->id,
                'receipt_date' => now()->toDateString(),
                'notes' => 'FLOW-DUP-GR',
                'items' => [[
                    'purchase_order_item_id' => $orderItem->id,
                    'qty' => 4,
                ]],
            ])
            ->assertRedirect();

        $receipt = GoodsReceipt::query()->where('company_id', $manager->company_id)->where('notes', 'FLOW-DUP-GR')->firstOrFail();
        $receiptItem = $receipt->items()->firstOrFail();

        $payload = [
            'goods_receipt_id' => $receipt->id,
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
            'notes' => 'FLOW-DUP-BILL-1',
            'items' => [[
                'goods_receipt_item_id' => $receiptItem->id,
                'product_id' => $product->id,
                'description' => 'Premiere facture reception',
                'qty' => 4,
                'unit_cost' => 300,
            ]],
        ];

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->post(route('purchases.store'), $payload)
            ->assertRedirect();

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->from(route('purchases.create', ['receipt' => $receipt->id]))
            ->post(route('purchases.store'), array_replace($payload, ['notes' => 'FLOW-DUP-BILL-2']))
            ->assertRedirect(route('purchases.create', ['receipt' => $receipt->id]))
            ->assertSessionHasErrors('goods_receipt_id');
    }

    private function stockBalance(int $companyId, int $branchId, int $productId, int $warehouseId): float
    {
        return (float) StockMovement::query()
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
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
