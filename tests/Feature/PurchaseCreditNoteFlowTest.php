<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Partners\Models\Partner;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Purchases\Models\PurchaseCreditNote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PurchaseCreditNoteFlowTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_manager_can_create_supplier_credit_note_destock_items_and_generate_accounting_entry(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $supplier = Partner::query()->suppliers()->where('company_id', $user->company_id)->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();
        $warehouse = Warehouse::query()->where('company_id', $user->company_id)->where('branch_id', $user->branch_id)->where('is_default', true)->firstOrFail();

        $initialStock = $this->stockBalance($user->company_id, $user->branch_id, $product->id, $warehouse->id);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('purchases.store'), [
                'supplier_id' => $supplier->id,
                'bill_date' => now()->toDateString(),
                'due_date' => now()->addDays(15)->toDateString(),
                'warehouse_id' => $warehouse->id,
                'notes' => 'TEST-SUPPLIER-CREDIT-NOTE',
                'items' => [[
                    'product_id' => $product->id,
                    'description' => 'Achat test avoir fournisseur',
                    'qty' => 5,
                    'unit_cost' => 1000,
                ]],
            ])
            ->assertRedirect();

        $bill = PurchaseBill::query()
            ->where('company_id', $user->company_id)
            ->where('notes', 'TEST-SUPPLIER-CREDIT-NOTE')
            ->firstOrFail();
        $billItem = $bill->items()->firstOrFail();
        $stockAfterPurchase = $this->stockBalance($user->company_id, $user->branch_id, $product->id, $bill->warehouse_id);

        $creditQty = 2.0;
        $expectedNetTotal = round($creditQty * (float) $billItem->unit_cost, 2);
        $expectedTaxTotal = round($expectedNetTotal * ((float) $billItem->tax_rate / 100), 2);
        $expectedTotal = round($expectedNetTotal + $expectedTaxTotal, 2);

        $this->assertEqualsWithDelta($initialStock + 5, $stockAfterPurchase, 0.001);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('purchase-credit-notes.store', $bill), [
                'credit_note_date' => now()->toDateString(),
                'destock_items' => 1,
                'notes' => 'Retour fournisseur test automatique',
                'items' => [[
                    'purchase_bill_item_id' => $billItem->id,
                    'qty' => $creditQty,
                ]],
            ])
            ->assertRedirect();

        $creditNote = PurchaseCreditNote::query()
            ->where('company_id', $user->company_id)
            ->where('notes', 'Retour fournisseur test automatique')
            ->firstOrFail();
        $bill->refresh();

        $this->assertMatchesRegularExpression('/^AVF-BKO-\d{4}-\d{5}$/', $creditNote->credit_note_number);
        $this->assertTrue($creditNote->destock_items);
        $this->assertEqualsWithDelta($expectedNetTotal, (float) $creditNote->net_total, 0.001);
        $this->assertEqualsWithDelta($expectedTaxTotal, (float) $creditNote->tax_total, 0.001);
        $this->assertEqualsWithDelta($expectedTotal, (float) $creditNote->total, 0.001);
        $this->assertEqualsWithDelta((float) $bill->total - $expectedTotal, (float) $bill->balance_due, 0.001);
        $this->assertSame('unpaid', $bill->payment_status);

        $this->assertEqualsWithDelta(
            $stockAfterPurchase - $creditQty,
            $this->stockBalance($user->company_id, $user->branch_id, $product->id, $bill->warehouse_id),
            0.001
        );

        $this->assertDatabaseHas('stock_movements', [
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'warehouse_id' => $bill->warehouse_id,
            'product_id' => $product->id,
            'movement_type' => 'adjustment_out',
            'quantity_out' => $creditQty,
            'reference_type' => PurchaseCreditNote::class,
            'reference_id' => $creditNote->id,
        ]);

        $entry = JournalEntry::query()
            ->where('company_id', $user->company_id)
            ->where('source_type', PurchaseCreditNote::class)
            ->where('source_id', $creditNote->id)
            ->where('journal_code', 'AVF')
            ->firstOrFail();

        $this->assertEqualsWithDelta($expectedTotal, (float) $entry->total_debit, 0.001);
        $this->assertEqualsWithDelta($expectedTotal, (float) $entry->total_credit, 0.001);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('purchase-credit-notes.show', $creditNote))
            ->assertOk()
            ->assertSeeText('Avoir fournisseur')
            ->assertSeeText($creditNote->credit_note_number);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('purchase-credit-notes.print', $creditNote))
            ->assertOk();
    }

    private function workspaceSession(User $user): array
    {
        return ['current_company_id' => $user->company_id, 'current_branch_id' => $user->branch_id];
    }

    private function stockBalance(int $companyId, int $branchId, int $productId, ?int $warehouseId = null): float
    {
        return (float) DB::table('stock_movements')
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->when($warehouseId, fn ($query, int $id) => $query->where('warehouse_id', $id))
            ->selectRaw('COALESCE(SUM(quantity_in - quantity_out), 0) as balance')
            ->value('balance');
    }
}
