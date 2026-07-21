<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Audit\Models\ActivityLog;
use App\Modules\Core\Company\Services\SectorProfileService;
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
        $vocabulary = app(SectorProfileService::class)->businessVocabularyForCompany($user->company_id);

        ActivityLog::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'user_id' => $user->id,
            'action' => 'customers.update',
            'description' => 'Mise a jour client test',
            'subject_type' => $customer->getMorphClass(),
            'subject_id' => $customer->id,
            'properties' => ['source' => 'test'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee($customer->name)
            ->assertSee('Historique des actions')
            ->assertSee('Mise a jour client test')
            ->assertSee($vocabulary['sales'])
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
        $vocabulary = app(SectorProfileService::class)->businessVocabularyForCompany($user->company_id);

        ActivityLog::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'user_id' => $user->id,
            'action' => 'suppliers.update',
            'description' => 'Mise a jour fournisseur test',
            'subject_type' => $supplier->getMorphClass(),
            'subject_id' => $supplier->id,
            'properties' => ['source' => 'test'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('suppliers.show', $supplier))
            ->assertOk()
            ->assertSee($supplier->name)
            ->assertSee('Historique des actions')
            ->assertSee('Mise a jour fournisseur test')
            ->assertSee('Factures '.strtolower($vocabulary['supplier']))
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
