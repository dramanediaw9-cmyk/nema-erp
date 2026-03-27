<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Sales\Models\SalesInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerInsightsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_customer_detail_page_shows_sales_payments_and_accounting_context(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $invoice = SalesInvoice::query()->where('company_id', $user->company_id)->where('notes', 'Facture de demonstration initiale')->firstOrFail();
        $customer = $invoice->customer;

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee($customer->name)
            ->assertSee('Factures clients')
            ->assertSee('Paiements recus')
            ->assertSee('Ecritures comptables liees')
            ->assertSee($invoice->invoice_number);
    }

    public function test_supplier_detail_page_shows_purchases_expenses_payments_and_accounting_context(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $bill = PurchaseBill::query()->where('company_id', $user->company_id)->where('notes', 'Facture fournisseur de demonstration')->firstOrFail();
        $expense = Expense::query()->where('company_id', $user->company_id)->where('description', 'Achat de carburant pour livraison Bamako')->firstOrFail();
        $supplier = $bill->supplier;

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('suppliers.show', $supplier))
            ->assertOk()
            ->assertSee($supplier->name)
            ->assertSee('Factures fournisseurs')
            ->assertSee('Depenses liees')
            ->assertSee('Reglements')
            ->assertSee('Ecritures comptables liees')
            ->assertSee($bill->bill_number)
            ->assertSee($expense->expense_number);
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}
