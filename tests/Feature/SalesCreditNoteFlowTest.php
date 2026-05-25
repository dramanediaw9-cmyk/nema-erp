<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Catalog\Models\Product;
use App\Modules\Partners\Models\Partner;
use App\Modules\Sales\Models\SalesCreditNote;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Treasury\Models\CashAccount;
use App\Modules\Treasury\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SalesCreditNoteFlowTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_manager_can_create_credit_note_restock_items_and_preserve_payment_balance_logic(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $user->company_id)->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();
        $cashAccount = CashAccount::query()->where('company_id', $user->company_id)->where('name', 'Caisse principale')->firstOrFail();

        $this->actingAs($user)->withSession($this->workspaceSession($user))->post(route('sales.store'), [
            'customer_id' => $customer->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'notes' => 'TEST-CREDIT-NOTE-SALE',
            'items' => [[
                'product_id' => $product->id,
                'description' => 'Facture test avoir',
                'qty' => 5,
                'unit_price' => 400,
            ]],
        ])->assertRedirect();

        $invoice = SalesInvoice::query()->where('company_id', $user->company_id)->where('notes', 'TEST-CREDIT-NOTE-SALE')->firstOrFail();
        $invoiceItem = $invoice->items()->firstOrFail();
        $stockBeforeCredit = $this->stockBalance($user->company_id, $user->branch_id, $product->id);

        $this->actingAs($user)->withSession($this->workspaceSession($user))->post(route('credit-notes.store', $invoice), [
            'credit_note_date' => now()->toDateString(),
            'restock_items' => 1,
            'notes' => 'Retour partiel test',
            'items' => [[
                'sales_invoice_item_id' => $invoiceItem->id,
                'qty' => 2,
            ]],
        ])->assertRedirect();

        $creditNote = SalesCreditNote::query()->where('company_id', $user->company_id)->where('notes', 'Retour partiel test')->firstOrFail();
        $invoice->refresh();

        $this->assertMatchesRegularExpression('/^AVO-BKO-\d{4}-\d{5}$/', $creditNote->credit_note_number);
        $this->assertEqualsWithDelta(800, (float) $creditNote->total, 0.001);
        $this->assertEqualsWithDelta(1200, (float) $invoice->balance_due, 0.001);
        $this->assertSame('unpaid', $invoice->payment_status);
        $this->assertEqualsWithDelta($stockBeforeCredit + 2, $this->stockBalance($user->company_id, $user->branch_id, $product->id), 0.001);

        $this->assertDatabaseHas('journal_entries', [
            'company_id' => $user->company_id,
            'source_type' => SalesCreditNote::class,
            'source_id' => $creditNote->id,
            'journal_code' => 'AVO',
        ]);

        $this->actingAs($user)->withSession($this->workspaceSession($user))->post(route('payments.store'), [
            'invoice_id' => $invoice->id,
            'cash_account_id' => $cashAccount->id,
            'payment_date' => now()->toDateString(),
            'amount' => 500,
            'method' => 'cash',
            'reference' => 'TEST-CREDIT-NOTE-PAY',
        ])->assertRedirect();

        $invoice->refresh();
        $this->assertEqualsWithDelta(700, (float) $invoice->balance_due, 0.001);
        $this->assertSame('partial', $invoice->payment_status);
    }

    public function test_paid_invoice_credit_note_can_create_customer_refund(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $user->company_id)->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();
        $cashAccount = CashAccount::query()->where('company_id', $user->company_id)->where('name', 'Caisse principale')->firstOrFail();

        $this->actingAs($user)->withSession($this->workspaceSession($user))->post(route('sales.store'), [
            'customer_id' => $customer->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'notes' => 'TEST-CUSTOMER-REFUND-SALE',
            'items' => [[
                'product_id' => $product->id,
                'description' => 'Facture test remboursement client',
                'qty' => 5,
                'unit_price' => 400,
            ]],
        ])->assertRedirect();

        $invoice = SalesInvoice::query()->where('company_id', $user->company_id)->where('notes', 'TEST-CUSTOMER-REFUND-SALE')->firstOrFail();
        $invoiceItem = $invoice->items()->firstOrFail();

        $this->actingAs($user)->withSession($this->workspaceSession($user))->post(route('payments.store'), [
            'payment_type' => 'customer_receipt',
            'invoice_id' => $invoice->id,
            'cash_account_id' => $cashAccount->id,
            'payment_date' => now()->toDateString(),
            'amount' => 2000,
            'method' => 'cash',
            'reference' => 'TEST-CUSTOMER-REFUND-RECEIPT',
        ])->assertRedirect();

        $invoice->refresh();
        $this->assertSame('paid', $invoice->payment_status);
        $this->assertEqualsWithDelta(0, (float) $invoice->balance_due, 0.001);

        $this->actingAs($user)->withSession($this->workspaceSession($user))->post(route('credit-notes.store', $invoice), [
            'credit_note_date' => now()->toDateString(),
            'restock_items' => 1,
            'notes' => 'Avoir avec remboursement client',
            'items' => [[
                'sales_invoice_item_id' => $invoiceItem->id,
                'qty' => 2,
            ]],
        ])->assertRedirect();

        $creditNote = SalesCreditNote::query()->where('company_id', $user->company_id)->where('notes', 'Avoir avec remboursement client')->firstOrFail();
        $invoice->refresh();

        $this->assertEqualsWithDelta(800, (float) $creditNote->total, 0.001);
        $this->assertEqualsWithDelta(-800, (float) $invoice->balance_due, 0.001);
        $this->assertSame('paid', $invoice->payment_status);

        $this->actingAs($user)->withSession($this->workspaceSession($user))->post(route('payments.store'), [
            'payment_type' => 'customer_refund',
            'invoice_id' => $invoice->id,
            'cash_account_id' => $cashAccount->id,
            'payment_date' => now()->toDateString(),
            'amount' => 800,
            'method' => 'cash',
            'reference' => 'TEST-CUSTOMER-REFUND-OUT',
        ])->assertRedirect(route('sales.show', $invoice));

        $refund = Payment::query()
            ->where('company_id', $user->company_id)
            ->where('reference', 'TEST-CUSTOMER-REFUND-OUT')
            ->firstOrFail();
        $invoice->refresh();

        $this->assertSame('customer_refund', $refund->payment_type);
        $this->assertSame('out', $refund->direction);
        $this->assertEqualsWithDelta(800, (float) $refund->amount, 0.001);
        $this->assertEqualsWithDelta(1200, (float) $invoice->amount_paid, 0.001);
        $this->assertEqualsWithDelta(0, (float) $invoice->balance_due, 0.001);
        $this->assertSame('paid', $invoice->payment_status);

        $entry = JournalEntry::query()
            ->where('company_id', $user->company_id)
            ->where('source_type', Payment::class)
            ->where('source_id', $refund->id)
            ->where('journal_code', 'TRE')
            ->firstOrFail();

        $this->assertEqualsWithDelta(800, (float) $entry->total_debit, 0.001);
        $this->assertEqualsWithDelta(800, (float) $entry->total_credit, 0.001);
    }

    private function workspaceSession(User $user): array
    {
        return ['current_company_id' => $user->company_id, 'current_branch_id' => $user->branch_id];
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
