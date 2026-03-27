<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Company\Models\DocumentSequence;
use App\Modules\Partners\Models\Partner;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Treasury\Models\CashAccount;
use App\Modules\Treasury\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SalesFlowTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_company_admin_can_create_a_sale_and_reduce_stock(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $user->company_id)->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $initialStock = $this->stockBalance($user->company_id, $user->branch_id, $product->id);

        $response = $this->actingAs($user)
            ->withSession([
                'current_company_id' => $user->company_id,
                'current_branch_id' => $user->branch_id,
            ])
            ->post(route('sales.store'), [
                'customer_id' => $customer->id,
                'invoice_date' => now()->format('Y-m-d'),
                'due_date' => now()->addDays(7)->format('Y-m-d'),
                'notes' => 'TEST-SALE-AUTO',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'description' => 'Test vente automatique',
                        'qty' => 5,
                        'unit_price' => 400,
                    ],
                ],
            ]);

        $invoice = SalesInvoice::query()
            ->where('company_id', $user->company_id)
            ->where('notes', 'TEST-SALE-AUTO')
            ->firstOrFail();

        $response->assertRedirect(route('sales.show', $invoice));
        $this->assertSame('validated', $invoice->status);
        $this->assertSame('unpaid', $invoice->payment_status);
        $this->assertEqualsWithDelta(2000, (float) $invoice->total, 0.001);
        $this->assertEqualsWithDelta(2000, (float) $invoice->balance_due, 0.001);
        $this->assertMatchesRegularExpression('/^FAC-BKO-\d{4}-\d{5}$/', $invoice->invoice_number);

        $updatedStock = $this->stockBalance($user->company_id, $user->branch_id, $product->id);
        $this->assertEqualsWithDelta($initialStock - 5, $updatedStock, 0.001);

        $this->assertDatabaseHas('stock_movements', [
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'product_id' => $product->id,
            'movement_type' => 'sale',
            'reference_type' => SalesInvoice::class,
            'reference_id' => $invoice->id,
        ]);
    }

    public function test_sale_is_rejected_when_stock_is_insufficient(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $user->company_id)->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0002')->firstOrFail();

        $this->actingAs($user)
            ->withSession([
                'current_company_id' => $user->company_id,
                'current_branch_id' => $user->branch_id,
            ])
            ->from(route('sales.create'))
            ->post(route('sales.store'), [
                'customer_id' => $customer->id,
                'invoice_date' => now()->format('Y-m-d'),
                'notes' => 'TEST-SALE-FAIL',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'description' => 'Test rupture',
                        'qty' => 999,
                        'unit_price' => 700,
                    ],
                ],
            ])
            ->assertSessionHasErrors('items');

        $this->assertDatabaseMissing('sales_invoices', [
            'company_id' => $user->company_id,
            'notes' => 'TEST-SALE-FAIL',
        ]);
    }

    public function test_payment_updates_invoice_balance(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $invoice = SalesInvoice::query()->where('company_id', $user->company_id)->where('notes', 'Facture de demonstration initiale')->firstOrFail();
        $cashAccount = CashAccount::query()->where('company_id', $user->company_id)->where('name', 'Caisse principale')->firstOrFail();

        $response = $this->actingAs($user)
            ->withSession([
                'current_company_id' => $user->company_id,
                'current_branch_id' => $user->branch_id,
            ])
            ->post(route('payments.store'), [
                'invoice_id' => $invoice->id,
                'cash_account_id' => $cashAccount->id,
                'payment_date' => now()->format('Y-m-d'),
                'amount' => 1000,
                'method' => 'cash',
                'reference' => 'TEST-PAY-001',
                'notes' => 'Paiement test automatique',
            ]);

        $invoice->refresh();

        $response->assertRedirect(route('sales.show', $invoice));
        $this->assertEqualsWithDelta(4000, (float) $invoice->amount_paid, 0.001);
        $this->assertEqualsWithDelta(1400, (float) $invoice->balance_due, 0.001);
        $this->assertSame('partial', $invoice->payment_status);

        $this->assertDatabaseHas('payments', [
            'company_id' => $user->company_id,
            'partner_id' => $invoice->customer_id,
            'reference' => 'TEST-PAY-001',
            'amount' => 1000,
        ]);
    }
    public function test_payment_sequence_skips_existing_number_when_sequence_is_stale(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $invoice = SalesInvoice::query()->where('company_id', $user->company_id)->where('notes', 'Facture de demonstration initiale')->firstOrFail();
        $cashAccount = CashAccount::query()->where('company_id', $user->company_id)->where('name', 'Caisse principale')->firstOrFail();
        $existingNumbers = Payment::query()->where('company_id', $user->company_id)->pluck('payment_number')->all();

        DocumentSequence::query()
            ->where('company_id', $user->company_id)
            ->where('document_type', 'payment')
            ->update(['next_number' => 1]);

        $response = $this->actingAs($user)
            ->withSession([
                'current_company_id' => $user->company_id,
                'current_branch_id' => $user->branch_id,
            ])
            ->post(route('payments.store'), [
                'invoice_id' => $invoice->id,
                'cash_account_id' => $cashAccount->id,
                'payment_date' => now()->format('Y-m-d'),
                'amount' => 500,
                'method' => 'cash',
                'reference' => 'TEST-PAY-SEQ-001',
                'notes' => 'Paiement test sequence',
            ]);

        $payment = Payment::query()
            ->where('company_id', $user->company_id)
            ->where('reference', 'TEST-PAY-SEQ-001')
            ->firstOrFail();

        $response->assertRedirect(route('sales.show', $invoice->fresh()));
        $this->assertNotContains($payment->payment_number, $existingNumbers);
        $this->assertMatchesRegularExpression('/^ENC-BKO-\d{4}-\d{5}$/', $payment->payment_number);
        $this->assertGreaterThan(1, DocumentSequence::query()
            ->where('company_id', $user->company_id)
            ->where('document_type', 'payment')
            ->value('next_number'));
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
