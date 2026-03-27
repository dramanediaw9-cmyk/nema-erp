<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Catalog\Models\Product;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Expenses\Models\ExpenseCategory;
use App\Modules\Partners\Models\Partner;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Treasury\Models\CashAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_sale_created_by_operations_user_waits_for_single_level_approval_before_stock_and_accounting(): void
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
                'due_date' => now()->addDays(10)->format('Y-m-d'),
                'notes' => 'PENDING-SALE-APPROVAL',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'description' => 'Vente en attente',
                        'qty' => 2,
                        'unit_price' => 600,
                    ],
                ],
            ])
            ->assertRedirect();

        $invoice = SalesInvoice::query()->where('company_id', $operator->company_id)->where('notes', 'PENDING-SALE-APPROVAL')->firstOrFail();

        $this->assertSame('pending_approval', $invoice->status);
        $this->assertNull($invoice->approved_at);
        $this->assertDatabaseCount('approval_steps', 1);
        $this->assertDatabaseHas('approval_steps', [
            'approvable_type' => SalesInvoice::class,
            'approvable_id' => $invoice->id,
            'step_order' => 1,
            'status' => 'pending',
        ]);
        $this->assertEqualsWithDelta($initialStock, $this->stockBalance($operator->company_id, $operator->branch_id, $product->id), 0.001);
        $this->assertDatabaseMissing('stock_movements', [
            'reference_type' => SalesInvoice::class,
            'reference_id' => $invoice->id,
            'movement_type' => 'sale',
        ]);
        $this->assertDatabaseMissing('journal_entries', [
            'source_type' => SalesInvoice::class,
            'source_id' => $invoice->id,
        ]);

        $this->actingAs($director)
            ->withSession([
                'current_company_id' => $director->company_id,
                'current_branch_id' => $director->branch_id,
            ])
            ->post(route('sales.approve', $invoice))
            ->assertRedirect(route('sales.show', $invoice));

        $invoice->refresh();

        $this->assertSame('validated', $invoice->status);
        $this->assertNotNull($invoice->approved_at);
        $this->assertSame($director->id, $invoice->approved_by);
        $this->assertEqualsWithDelta($initialStock - 2, $this->stockBalance($operator->company_id, $operator->branch_id, $product->id), 0.001);
        $this->assertDatabaseHas('journal_entries', [
            'source_type' => SalesInvoice::class,
            'source_id' => $invoice->id,
            'journal_code' => 'VEN',
        ]);
    }

    public function test_medium_sale_requires_two_levels_of_approval(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $director = User::query()->where('email', 'dg@nema-erp.test')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $manager->company_id)->firstOrFail();
        $product = Product::query()->where('company_id', $manager->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $initialStock = $this->stockBalance($manager->company_id, $manager->branch_id, $product->id);

        $this->actingAs($manager)
            ->withSession([
                'current_company_id' => $manager->company_id,
                'current_branch_id' => $manager->branch_id,
            ])
            ->post(route('sales.store'), [
                'customer_id' => $customer->id,
                'invoice_date' => now()->format('Y-m-d'),
                'due_date' => now()->addDays(15)->format('Y-m-d'),
                'notes' => 'MULTI-SALE-APPROVAL',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'description' => 'Vente a double validation',
                        'qty' => 2,
                        'unit_price' => 60000,
                    ],
                ],
            ])
            ->assertRedirect();

        $invoice = SalesInvoice::query()->where('company_id', $manager->company_id)->where('notes', 'MULTI-SALE-APPROVAL')->firstOrFail();

        $this->assertSame('pending_approval', $invoice->status);
        $this->assertDatabaseHas('approval_steps', [
            'approvable_type' => SalesInvoice::class,
            'approvable_id' => $invoice->id,
            'step_order' => 1,
            'status' => 'approved',
            'approved_by' => $manager->id,
        ]);
        $this->assertDatabaseHas('approval_steps', [
            'approvable_type' => SalesInvoice::class,
            'approvable_id' => $invoice->id,
            'step_order' => 2,
            'status' => 'pending',
        ]);
        $this->assertEqualsWithDelta($initialStock, $this->stockBalance($manager->company_id, $manager->branch_id, $product->id), 0.001);
        $this->assertDatabaseMissing('journal_entries', [
            'source_type' => SalesInvoice::class,
            'source_id' => $invoice->id,
        ]);

        $this->actingAs($director)
            ->withSession([
                'current_company_id' => $director->company_id,
                'current_branch_id' => $director->branch_id,
            ])
            ->post(route('sales.approve', $invoice))
            ->assertRedirect(route('sales.show', $invoice));

        $invoice->refresh();

        $this->assertSame('validated', $invoice->status);
        $this->assertSame($director->id, $invoice->approved_by);
        $this->assertEqualsWithDelta($initialStock - 2, $this->stockBalance($manager->company_id, $manager->branch_id, $product->id), 0.001);
        $this->assertDatabaseHas('approval_steps', [
            'approvable_type' => SalesInvoice::class,
            'approvable_id' => $invoice->id,
            'step_order' => 2,
            'status' => 'approved',
            'approved_by' => $director->id,
        ]);
        $this->assertDatabaseHas('journal_entries', [
            'source_type' => SalesInvoice::class,
            'source_id' => $invoice->id,
            'journal_code' => 'VEN',
        ]);
    }

    public function test_pending_sale_cannot_receive_payment_before_approval(): void
    {
        $operator = User::query()->where('email', 'ops@nema-erp.test')->firstOrFail();
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $operator->company_id)->firstOrFail();
        $product = Product::query()->where('company_id', $operator->company_id)->where('sku', 'PRD-0001')->firstOrFail();
        $cashAccount = CashAccount::query()->where('company_id', $operator->company_id)->where('name', 'Caisse principale')->firstOrFail();

        $this->actingAs($operator)
            ->withSession([
                'current_company_id' => $operator->company_id,
                'current_branch_id' => $operator->branch_id,
            ])
            ->post(route('sales.store'), [
                'customer_id' => $customer->id,
                'invoice_date' => now()->format('Y-m-d'),
                'notes' => 'PENDING-SALE-PAYMENT-BLOCK',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'description' => 'Vente bloquee',
                        'qty' => 1,
                        'unit_price' => 450,
                    ],
                ],
            ])
            ->assertRedirect();

        $invoice = SalesInvoice::query()->where('company_id', $operator->company_id)->where('notes', 'PENDING-SALE-PAYMENT-BLOCK')->firstOrFail();

        $this->actingAs($manager)
            ->withSession([
                'current_company_id' => $manager->company_id,
                'current_branch_id' => $manager->branch_id,
            ])
            ->from(route('sales.show', $invoice))
            ->post(route('payments.store'), [
                'invoice_id' => $invoice->id,
                'cash_account_id' => $cashAccount->id,
                'payment_date' => now()->format('Y-m-d'),
                'amount' => 100,
                'method' => 'cash',
                'reference' => 'PENDING-SALE-PAY-001',
            ])
            ->assertSessionHasErrors('invoice_id');

        $this->assertDatabaseMissing('payments', [
            'company_id' => $operator->company_id,
            'reference' => 'PENDING-SALE-PAY-001',
        ]);
    }

    public function test_purchase_created_by_operations_user_waits_for_single_level_approval_before_stock_and_accounting(): void
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
                'due_date' => now()->addDays(10)->format('Y-m-d'),
                'notes' => 'PENDING-PURCHASE-APPROVAL',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'description' => 'Achat en attente',
                        'qty' => 4,
                        'unit_cost' => 280,
                    ],
                ],
            ])
            ->assertRedirect();

        $bill = PurchaseBill::query()->where('company_id', $operator->company_id)->where('notes', 'PENDING-PURCHASE-APPROVAL')->firstOrFail();

        $this->assertSame('pending_approval', $bill->status);
        $this->assertDatabaseCount('approval_steps', 1);
        $this->assertDatabaseHas('approval_steps', [
            'approvable_type' => PurchaseBill::class,
            'approvable_id' => $bill->id,
            'step_order' => 1,
            'status' => 'pending',
        ]);
        $this->assertEqualsWithDelta($initialStock, $this->stockBalance($operator->company_id, $operator->branch_id, $product->id), 0.001);
        $this->assertDatabaseMissing('stock_movements', [
            'reference_type' => PurchaseBill::class,
            'reference_id' => $bill->id,
            'movement_type' => 'purchase',
        ]);
        $this->assertDatabaseMissing('journal_entries', [
            'source_type' => PurchaseBill::class,
            'source_id' => $bill->id,
        ]);

        $this->actingAs($director)
            ->withSession([
                'current_company_id' => $director->company_id,
                'current_branch_id' => $director->branch_id,
            ])
            ->post(route('purchases.approve', $bill))
            ->assertRedirect(route('purchases.show', $bill));

        $bill->refresh();

        $this->assertSame('validated', $bill->status);
        $this->assertNotNull($bill->approved_at);
        $this->assertSame($director->id, $bill->approved_by);
        $this->assertEqualsWithDelta($initialStock + 4, $this->stockBalance($operator->company_id, $operator->branch_id, $product->id), 0.001);
        $this->assertDatabaseHas('journal_entries', [
            'source_type' => PurchaseBill::class,
            'source_id' => $bill->id,
            'journal_code' => 'ACH',
        ]);
    }

    public function test_medium_purchase_requires_two_levels_of_approval(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $director = User::query()->where('email', 'dg@nema-erp.test')->firstOrFail();
        $supplier = Partner::query()->suppliers()->where('company_id', $manager->company_id)->firstOrFail();
        $product = Product::query()->where('company_id', $manager->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $initialStock = $this->stockBalance($manager->company_id, $manager->branch_id, $product->id);

        $this->actingAs($manager)
            ->withSession([
                'current_company_id' => $manager->company_id,
                'current_branch_id' => $manager->branch_id,
            ])
            ->post(route('purchases.store'), [
                'supplier_id' => $supplier->id,
                'bill_date' => now()->format('Y-m-d'),
                'due_date' => now()->addDays(15)->format('Y-m-d'),
                'notes' => 'MULTI-PURCHASE-APPROVAL',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'description' => 'Achat a double validation',
                        'qty' => 2,
                        'unit_cost' => 60000,
                    ],
                ],
            ])
            ->assertRedirect();

        $bill = PurchaseBill::query()->where('company_id', $manager->company_id)->where('notes', 'MULTI-PURCHASE-APPROVAL')->firstOrFail();

        $this->assertSame('pending_approval', $bill->status);
        $this->assertDatabaseHas('approval_steps', [
            'approvable_type' => PurchaseBill::class,
            'approvable_id' => $bill->id,
            'step_order' => 1,
            'status' => 'approved',
            'approved_by' => $manager->id,
        ]);
        $this->assertDatabaseHas('approval_steps', [
            'approvable_type' => PurchaseBill::class,
            'approvable_id' => $bill->id,
            'step_order' => 2,
            'status' => 'pending',
        ]);
        $this->assertEqualsWithDelta($initialStock, $this->stockBalance($manager->company_id, $manager->branch_id, $product->id), 0.001);
        $this->assertDatabaseMissing('journal_entries', [
            'source_type' => PurchaseBill::class,
            'source_id' => $bill->id,
        ]);

        $this->actingAs($director)
            ->withSession([
                'current_company_id' => $director->company_id,
                'current_branch_id' => $director->branch_id,
            ])
            ->post(route('purchases.approve', $bill))
            ->assertRedirect(route('purchases.show', $bill));

        $bill->refresh();

        $this->assertSame('validated', $bill->status);
        $this->assertSame($director->id, $bill->approved_by);
        $this->assertEqualsWithDelta($initialStock + 2, $this->stockBalance($manager->company_id, $manager->branch_id, $product->id), 0.001);
        $this->assertDatabaseHas('journal_entries', [
            'source_type' => PurchaseBill::class,
            'source_id' => $bill->id,
            'journal_code' => 'ACH',
        ]);
    }

    public function test_medium_expense_requires_two_levels_of_approval(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $director = User::query()->where('email', 'dg@nema-erp.test')->firstOrFail();
        $category = ExpenseCategory::query()->where('company_id', $manager->company_id)->where('name', 'Carburant')->firstOrFail();
        $supplier = Partner::query()->suppliers()->where('company_id', $manager->company_id)->firstOrFail();
        $cashAccount = CashAccount::query()->where('company_id', $manager->company_id)->where('name', 'Caisse principale')->firstOrFail();

        $this->actingAs($manager)
            ->withSession([
                'current_company_id' => $manager->company_id,
                'current_branch_id' => $manager->branch_id,
            ])
            ->post(route('expenses.store'), [
                'expense_category_id' => $category->id,
                'supplier_id' => $supplier->id,
                'cash_account_id' => $cashAccount->id,
                'expense_date' => now()->format('Y-m-d'),
                'description' => 'DEPENSE-DOUBLE-APPROVAL',
                'total' => 150000,
                'payment_date' => now()->format('Y-m-d'),
                'payment_method' => 'cash',
                'payment_reference' => 'DUAL-EXP-001',
                'notes' => 'Depense a double validation',
            ])
            ->assertRedirect();

        $expense = Expense::query()->where('company_id', $manager->company_id)->where('description', 'DEPENSE-DOUBLE-APPROVAL')->firstOrFail();

        $this->assertSame('pending_approval', $expense->status);
        $this->assertSame('paid', $expense->payment_status);
        $this->assertDatabaseHas('approval_steps', [
            'approvable_type' => Expense::class,
            'approvable_id' => $expense->id,
            'step_order' => 1,
            'status' => 'approved',
            'approved_by' => $manager->id,
        ]);
        $this->assertDatabaseHas('approval_steps', [
            'approvable_type' => Expense::class,
            'approvable_id' => $expense->id,
            'step_order' => 2,
            'status' => 'pending',
        ]);
        $this->assertDatabaseMissing('journal_entries', [
            'source_type' => Expense::class,
            'source_id' => $expense->id,
        ]);

        $this->actingAs($director)
            ->withSession([
                'current_company_id' => $director->company_id,
                'current_branch_id' => $director->branch_id,
            ])
            ->post(route('expenses.approve', $expense))
            ->assertRedirect(route('expenses.show', $expense));

        $expense->refresh();

        $this->assertSame('validated', $expense->status);
        $this->assertSame($director->id, $expense->approved_by);
        $this->assertDatabaseHas('journal_entries', [
            'source_type' => Expense::class,
            'source_id' => $expense->id,
            'journal_code' => 'OD',
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
