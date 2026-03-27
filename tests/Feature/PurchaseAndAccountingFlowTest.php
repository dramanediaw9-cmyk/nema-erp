<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Catalog\Models\Product;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Expenses\Models\ExpenseCategory;
use App\Modules\Partners\Models\Partner;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Treasury\Models\CashAccount;
use App\Modules\Treasury\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PurchaseAndAccountingFlowTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_company_admin_can_create_purchase_increase_stock_and_generate_accounting_entry(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $supplier = Partner::query()->suppliers()->where('company_id', $user->company_id)->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $initialStock = $this->stockBalance($user->company_id, $user->branch_id, $product->id);

        $response = $this->actingAs($user)
            ->withSession([
                'current_company_id' => $user->company_id,
                'current_branch_id' => $user->branch_id,
            ])
            ->post(route('purchases.store'), [
                'supplier_id' => $supplier->id,
                'bill_date' => now()->format('Y-m-d'),
                'due_date' => now()->addDays(15)->format('Y-m-d'),
                'notes' => 'TEST-PURCHASE-AUTO',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'description' => 'Test achat automatique',
                        'qty' => 7,
                        'unit_cost' => 300,
                    ],
                ],
            ]);

        $bill = PurchaseBill::query()
            ->where('company_id', $user->company_id)
            ->where('notes', 'TEST-PURCHASE-AUTO')
            ->firstOrFail();

        $response->assertRedirect(route('purchases.show', $bill));
        $this->assertSame('unpaid', $bill->payment_status);
        $this->assertEqualsWithDelta(2100, (float) $bill->total, 0.001);
        $this->assertEqualsWithDelta(2100, (float) $bill->balance_due, 0.001);
        $this->assertMatchesRegularExpression('/^ACH-BKO-\d{4}-\d{5}$/', $bill->bill_number);

        $updatedStock = $this->stockBalance($user->company_id, $user->branch_id, $product->id);
        $this->assertEqualsWithDelta($initialStock + 7, $updatedStock, 0.001);

        $this->assertDatabaseHas('stock_movements', [
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'product_id' => $product->id,
            'movement_type' => 'purchase',
            'reference_type' => PurchaseBill::class,
            'reference_id' => $bill->id,
        ]);

        $entry = JournalEntry::query()
            ->where('company_id', $user->company_id)
            ->where('source_type', PurchaseBill::class)
            ->where('source_id', $bill->id)
            ->where('journal_code', 'ACH')
            ->firstOrFail();

        $this->assertEqualsWithDelta(2100, (float) $entry->total_debit, 0.001);
        $this->assertEqualsWithDelta(2100, (float) $entry->total_credit, 0.001);
        $this->assertCount(2, $entry->lines);
        $this->assertMatchesRegularExpression('/^JRN-ACH-\d{4}-\d{5}$/', $entry->journal_number);
    }

    public function test_supplier_payment_updates_bill_balance_and_generates_treasury_entry(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $bill = PurchaseBill::query()->where('company_id', $user->company_id)->where('notes', 'Facture fournisseur de demonstration')->firstOrFail();
        $cashAccount = CashAccount::query()->where('company_id', $user->company_id)->where('name', 'Banque BDM')->firstOrFail();

        $initialPaid = (float) $bill->amount_paid;
        $initialBalance = (float) $bill->balance_due;
        $paymentAmount = min($initialBalance, 5000.0);

        $response = $this->actingAs($user)
            ->withSession([
                'current_company_id' => $user->company_id,
                'current_branch_id' => $user->branch_id,
            ])
            ->post(route('payments.store'), [
                'payment_type' => 'supplier_payment',
                'purchase_bill_id' => $bill->id,
                'cash_account_id' => $cashAccount->id,
                'payment_date' => now()->format('Y-m-d'),
                'amount' => $paymentAmount,
                'method' => 'bank_transfer',
                'reference' => 'TEST-SUP-PAY-001',
                'notes' => 'Paiement fournisseur test automatique',
            ]);

        $bill->refresh();
        $payment = Payment::query()->where('company_id', $user->company_id)->where('reference', 'TEST-SUP-PAY-001')->firstOrFail();

        $response->assertRedirect(route('purchases.show', $bill));
        $this->assertEqualsWithDelta($initialPaid + $paymentAmount, (float) $bill->amount_paid, 0.001);
        $this->assertEqualsWithDelta($initialBalance - $paymentAmount, (float) $bill->balance_due, 0.001);
        $this->assertSame($bill->balance_due <= 0 ? 'paid' : 'partial', $bill->payment_status);

        $this->assertSame('supplier_payment', $payment->payment_type);
        $this->assertSame('out', $payment->direction);
        $this->assertEqualsWithDelta($paymentAmount, (float) $payment->amount, 0.001);
        $this->assertMatchesRegularExpression('/^ENC-BKO-\d{4}-\d{5}$/', $payment->payment_number);

        $entry = JournalEntry::query()
            ->where('company_id', $user->company_id)
            ->where('source_type', Payment::class)
            ->where('source_id', $payment->id)
            ->where('journal_code', 'TRE')
            ->firstOrFail();

        $this->assertEqualsWithDelta($paymentAmount, (float) $entry->total_debit, 0.001);
        $this->assertEqualsWithDelta($paymentAmount, (float) $entry->total_credit, 0.001);
        $this->assertCount(2, $entry->lines);
        $this->assertMatchesRegularExpression('/^JRN-TRE-\d{4}-\d{5}$/', $entry->journal_number);
    }

    public function test_paid_expense_generates_accounting_entry(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $category = ExpenseCategory::query()->where('company_id', $user->company_id)->where('name', 'Carburant')->firstOrFail();
        $supplier = Partner::query()->suppliers()->where('company_id', $user->company_id)->firstOrFail();
        $cashAccount = CashAccount::query()->where('company_id', $user->company_id)->where('name', 'Caisse principale')->firstOrFail();

        $response = $this->actingAs($user)
            ->withSession([
                'current_company_id' => $user->company_id,
                'current_branch_id' => $user->branch_id,
            ])
            ->post(route('expenses.store'), [
                'expense_category_id' => $category->id,
                'supplier_id' => $supplier->id,
                'cash_account_id' => $cashAccount->id,
                'expense_date' => now()->format('Y-m-d'),
                'description' => 'TEST-EXPENSE-AUTO',
                'total' => 12000,
                'payment_date' => now()->format('Y-m-d'),
                'payment_method' => 'cash',
                'payment_reference' => 'TEST-EXP-001',
                'notes' => 'Depense test automatique',
            ]);

        $expense = Expense::query()
            ->where('company_id', $user->company_id)
            ->where('description', 'TEST-EXPENSE-AUTO')
            ->firstOrFail();

        $response->assertRedirect(route('expenses.show', $expense));
        $this->assertSame('paid', $expense->payment_status);
        $this->assertEqualsWithDelta(12000, (float) $expense->total, 0.001);
        $this->assertMatchesRegularExpression('/^DEP-BKO-\d{4}-\d{5}$/', $expense->expense_number);

        $entry = JournalEntry::query()
            ->where('company_id', $user->company_id)
            ->where('source_type', Expense::class)
            ->where('source_id', $expense->id)
            ->where('journal_code', 'OD')
            ->firstOrFail();

        $this->assertEqualsWithDelta(12000, (float) $entry->total_debit, 0.001);
        $this->assertEqualsWithDelta(12000, (float) $entry->total_credit, 0.001);

        $accountCodes = $entry->lines()->with('account')->get()->pluck('account.code')->filter()->values()->all();
        $this->assertContains('625100', $accountCodes);
        $this->assertContains('571000', $accountCodes);
        $this->assertMatchesRegularExpression('/^JRN-OD-\d{4}-\d{5}$/', $entry->journal_number);
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
