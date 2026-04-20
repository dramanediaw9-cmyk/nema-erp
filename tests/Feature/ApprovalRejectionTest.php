<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Expenses\Models\ExpenseCategory;
use App\Modules\Partners\Models\Partner;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Sales\Models\SalesInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApprovalRejectionTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_pending_sale_can_be_rejected_with_reason_without_stock_or_accounting_side_effects(): void
    {
        $operator = User::query()->where('email', 'ops@nema-erp.test')->firstOrFail();
        $director = User::query()->where('email', 'dg@nema-erp.test')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $operator->company_id)->firstOrFail();
        $product = Product::query()->where('company_id', $operator->company_id)->where('sku', 'PRD-0001')->firstOrFail();
        $initialStock = $this->stockBalance($operator->company_id, $operator->branch_id, $product->id);

        $this->actingAs($operator)
            ->withSession([
                'current_company_id' => $operator->company_id,
                'current_branch_id' => $operator->branch_id,
            ])
            ->post(route('sales.store'), [
                'customer_id' => $customer->id,
                'invoice_date' => now()->format('Y-m-d'),
                'notes' => 'REJECT-SALE-APPROVAL',
                'items' => [[
                    'product_id' => $product->id,
                    'description' => 'Vente a rejeter',
                    'qty' => 1,
                    'unit_price' => 650,
                ]],
            ])
            ->assertRedirect();

        $invoice = SalesInvoice::query()
            ->where('company_id', $operator->company_id)
            ->where('notes', 'REJECT-SALE-APPROVAL')
            ->firstOrFail();

        $this->actingAs($director)
            ->withSession([
                'current_company_id' => $director->company_id,
                'current_branch_id' => $director->branch_id,
            ])
            ->post(route('sales.reject', $invoice), [
                'rejection_reason' => 'Montant a reprendre avant validation.',
            ])
            ->assertRedirect(route('sales.show', $invoice));

        $invoice->refresh();

        $this->assertSame('rejected', $invoice->status);
        $this->assertSame($director->id, $invoice->rejected_by);
        $this->assertSame('Montant a reprendre avant validation.', $invoice->rejection_reason);
        $this->assertEqualsWithDelta($initialStock, $this->stockBalance($operator->company_id, $operator->branch_id, $product->id), 0.001);

        $this->assertDatabaseHas('approval_steps', [
            'approvable_type' => SalesInvoice::class,
            'approvable_id' => $invoice->id,
            'status' => 'rejected',
            'rejected_by' => $director->id,
            'rejection_reason' => 'Montant a reprendre avant validation.',
        ]);
        $this->assertDatabaseMissing('stock_movements', [
            'reference_type' => SalesInvoice::class,
            'reference_id' => $invoice->id,
            'movement_type' => 'sale',
        ]);
        $this->assertDatabaseMissing('journal_entries', [
            'source_type' => SalesInvoice::class,
            'source_id' => $invoice->id,
        ]);
    }

    public function test_pending_purchase_can_be_rejected_without_stock_or_accounting_side_effects(): void
    {
        $operator = User::query()->where('email', 'ops@nema-erp.test')->firstOrFail();
        $director = User::query()->where('email', 'dg@nema-erp.test')->firstOrFail();
        $supplier = Partner::query()->suppliers()->where('company_id', $operator->company_id)->firstOrFail();
        $product = Product::query()->where('company_id', $operator->company_id)->where('sku', 'PRD-0001')->firstOrFail();
        $initialStock = $this->stockBalance($operator->company_id, $operator->branch_id, $product->id);

        $this->actingAs($operator)
            ->withSession([
                'current_company_id' => $operator->company_id,
                'current_branch_id' => $operator->branch_id,
            ])
            ->post(route('purchases.store'), [
                'supplier_id' => $supplier->id,
                'bill_date' => now()->format('Y-m-d'),
                'notes' => 'REJECT-PURCHASE-APPROVAL',
                'items' => [[
                    'product_id' => $product->id,
                    'description' => 'Achat a rejeter',
                    'qty' => 3,
                    'unit_cost' => 300,
                ]],
            ])
            ->assertRedirect();

        $bill = PurchaseBill::query()
            ->where('company_id', $operator->company_id)
            ->where('notes', 'REJECT-PURCHASE-APPROVAL')
            ->firstOrFail();

        $this->actingAs($director)
            ->withSession([
                'current_company_id' => $director->company_id,
                'current_branch_id' => $director->branch_id,
            ])
            ->post(route('purchases.reject', $bill), [
                'rejection_reason' => 'Pieces justificatives fournisseur manquantes.',
            ])
            ->assertRedirect(route('purchases.show', $bill));

        $bill->refresh();

        $this->assertSame('rejected', $bill->status);
        $this->assertSame($director->id, $bill->rejected_by);
        $this->assertSame('Pieces justificatives fournisseur manquantes.', $bill->rejection_reason);
        $this->assertEqualsWithDelta($initialStock, $this->stockBalance($operator->company_id, $operator->branch_id, $product->id), 0.001);

        $this->assertDatabaseHas('approval_steps', [
            'approvable_type' => PurchaseBill::class,
            'approvable_id' => $bill->id,
            'status' => 'rejected',
            'rejected_by' => $director->id,
            'rejection_reason' => 'Pieces justificatives fournisseur manquantes.',
        ]);
        $this->assertDatabaseMissing('stock_movements', [
            'reference_type' => PurchaseBill::class,
            'reference_id' => $bill->id,
            'movement_type' => 'purchase',
        ]);
        $this->assertDatabaseMissing('journal_entries', [
            'source_type' => PurchaseBill::class,
            'source_id' => $bill->id,
        ]);
    }

    public function test_pending_expense_can_be_rejected_without_accounting_side_effects(): void
    {
        $operator = User::query()->where('email', 'ops@nema-erp.test')->firstOrFail();
        $director = User::query()->where('email', 'dg@nema-erp.test')->firstOrFail();
        $category = ExpenseCategory::query()->where('company_id', $operator->company_id)->where('name', 'Carburant')->firstOrFail();
        $supplier = Partner::query()->suppliers()->where('company_id', $operator->company_id)->firstOrFail();

        $this->actingAs($operator)
            ->withSession([
                'current_company_id' => $operator->company_id,
                'current_branch_id' => $operator->branch_id,
            ])
            ->post(route('expenses.store'), [
                'expense_category_id' => $category->id,
                'supplier_id' => $supplier->id,
                'expense_date' => now()->format('Y-m-d'),
                'description' => 'REJECT-EXPENSE-APPROVAL',
                'total' => 18000,
                'notes' => 'Depense a rejeter',
            ])
            ->assertRedirect();

        $expense = Expense::query()
            ->where('company_id', $operator->company_id)
            ->where('description', 'REJECT-EXPENSE-APPROVAL')
            ->firstOrFail();

        $this->actingAs($director)
            ->withSession([
                'current_company_id' => $director->company_id,
                'current_branch_id' => $director->branch_id,
            ])
            ->post(route('expenses.reject', $expense), [
                'rejection_reason' => 'Budget non valide pour cette ligne.',
            ])
            ->assertRedirect(route('expenses.show', $expense));

        $expense->refresh();

        $this->assertSame('rejected', $expense->status);
        $this->assertSame($director->id, $expense->rejected_by);
        $this->assertSame('Budget non valide pour cette ligne.', $expense->rejection_reason);

        $this->assertDatabaseHas('approval_steps', [
            'approvable_type' => Expense::class,
            'approvable_id' => $expense->id,
            'status' => 'rejected',
            'rejected_by' => $director->id,
            'rejection_reason' => 'Budget non valide pour cette ligne.',
        ]);
        $this->assertDatabaseMissing('journal_entries', [
            'source_type' => Expense::class,
            'source_id' => $expense->id,
        ]);
    }

    private function stockBalance(int $companyId, int $branchId, int $productId): float
    {
        return (float) DB::table('stock_movements')
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->selectRaw('COALESCE(SUM(quantity_in - quantity_out), 0) as balance')
            ->value('balance');
    }
}
