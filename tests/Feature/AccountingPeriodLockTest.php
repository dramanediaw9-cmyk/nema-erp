<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Catalog\Models\Product;
use App\Modules\Partners\Models\Partner;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Treasury\Models\CashAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingPeriodLockTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_period_cannot_be_closed_when_pending_approval_documents_exist(): void
    {
        $operator = User::query()->where('email', 'ops@nema-erp.test')->firstOrFail();
        $director = User::query()->where('email', 'dg@nema-erp.test')->firstOrFail();
        $period = AccountingPeriod::query()
            ->where('company_id', $director->company_id)
            ->whereDate('start_date', '<=', now()->toDateString())
            ->whereDate('end_date', '>=', now()->toDateString())
            ->firstOrFail();
        $supplier = Partner::query()->suppliers()->where('company_id', $operator->company_id)->firstOrFail();
        $product = Product::query()->where('company_id', $operator->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $this->actingAs($operator)
            ->withSession([
                'current_company_id' => $operator->company_id,
                'current_branch_id' => $operator->branch_id,
            ])
            ->post(route('purchases.store'), [
                'supplier_id' => $supplier->id,
                'bill_date' => now()->toDateString(),
                'due_date' => now()->addDays(10)->toDateString(),
                'notes' => 'BLOCK-CLOSE-PERIOD',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'description' => 'Achat bloquant cloture',
                        'qty' => 2,
                        'unit_cost' => 300,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('purchase_bills', [
            'company_id' => $operator->company_id,
            'notes' => 'BLOCK-CLOSE-PERIOD',
            'status' => 'pending_approval',
        ]);

        $this->actingAs($director)
            ->withSession([
                'current_company_id' => $director->company_id,
                'current_branch_id' => $director->branch_id,
            ])
            ->post(route('accounting.periods.close', $period))
            ->assertRedirect(route('accounting.periods.index'))
            ->assertSessionHas('error');

        $period->refresh();
        $this->assertSame('open', $period->status);
    }

    public function test_closed_period_blocks_sales_and_payments(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $period = AccountingPeriod::query()
            ->where('company_id', $user->company_id)
            ->whereDate('start_date', '<=', now()->toDateString())
            ->whereDate('end_date', '>=', now()->toDateString())
            ->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $user->company_id)->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();
        $invoice = SalesInvoice::query()
            ->where('company_id', $user->company_id)
            ->where('balance_due', '>', 0)
            ->firstOrFail();
        $cashAccount = CashAccount::query()->where('company_id', $user->company_id)->where('name', 'Caisse principale')->firstOrFail();

        $this->actingAs($user)
            ->withSession([
                'current_company_id' => $user->company_id,
                'current_branch_id' => $user->branch_id,
            ])
            ->post(route('accounting.periods.close', $period))
            ->assertRedirect(route('accounting.periods.index'));

        $period->refresh();
        $this->assertSame('closed', $period->status);

        $this->actingAs($user)
            ->withSession([
                'current_company_id' => $user->company_id,
                'current_branch_id' => $user->branch_id,
            ])
            ->post(route('sales.store'), [
                'customer_id' => $customer->id,
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays(7)->toDateString(),
                'notes' => 'LOCKED-PERIOD-SALE',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'description' => 'Vente bloquee',
                        'qty' => 1,
                        'unit_price' => 700,
                    ],
                ],
            ])
            ->assertSessionHasErrors('invoice_date');

        $this->assertDatabaseMissing('sales_invoices', [
            'company_id' => $user->company_id,
            'notes' => 'LOCKED-PERIOD-SALE',
        ]);

        $this->actingAs($user)
            ->withSession([
                'current_company_id' => $user->company_id,
                'current_branch_id' => $user->branch_id,
            ])
            ->post(route('payments.store'), [
                'payment_type' => 'customer_receipt',
                'invoice_id' => $invoice->id,
                'cash_account_id' => $cashAccount->id,
                'payment_date' => now()->toDateString(),
                'amount' => 500,
                'method' => 'cash',
                'reference' => 'LOCK-PAYMENT-001',
            ])
            ->assertSessionHasErrors('payment_date');
    }

    public function test_reopened_period_allows_new_sale(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $period = AccountingPeriod::query()
            ->where('company_id', $user->company_id)
            ->whereDate('start_date', '<=', now()->toDateString())
            ->whereDate('end_date', '>=', now()->toDateString())
            ->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $user->company_id)->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $this->actingAs($user)
            ->withSession([
                'current_company_id' => $user->company_id,
                'current_branch_id' => $user->branch_id,
            ])
            ->post(route('accounting.periods.close', $period))
            ->assertRedirect(route('accounting.periods.index'));

        $this->actingAs($user)
            ->withSession([
                'current_company_id' => $user->company_id,
                'current_branch_id' => $user->branch_id,
            ])
            ->post(route('accounting.periods.reopen', $period))
            ->assertRedirect(route('accounting.periods.index'));

        $period->refresh();
        $this->assertSame('open', $period->status);

        $response = $this->actingAs($user)
            ->withSession([
                'current_company_id' => $user->company_id,
                'current_branch_id' => $user->branch_id,
            ])
            ->post(route('sales.store'), [
                'customer_id' => $customer->id,
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays(7)->toDateString(),
                'notes' => 'REOPENED-PERIOD-SALE',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'description' => 'Vente apres reouverture',
                        'qty' => 1,
                        'unit_price' => 700,
                    ],
                ],
            ]);

        $invoice = SalesInvoice::query()
            ->where('company_id', $user->company_id)
            ->where('notes', 'REOPENED-PERIOD-SALE')
            ->firstOrFail();

        $response->assertRedirect(route('sales.show', $invoice));
        $this->assertSame('validated', $invoice->status);
    }
}
