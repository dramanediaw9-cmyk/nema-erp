<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Sales\Models\SalesInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentInsightsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_dashboard_shows_operational_watchlist(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Suivi operationnel')
            ->assertSee('Ventes en attente')
            ->assertSee('Stock a surveiller');
    }

    public function test_document_detail_pages_show_related_operational_and_accounting_sections(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $invoice = SalesInvoice::query()->where('company_id', $user->company_id)->where('notes', 'Facture de demonstration initiale')->firstOrFail();
        $bill = PurchaseBill::query()->where('company_id', $user->company_id)->where('notes', 'Facture fournisseur de demonstration')->firstOrFail();
        $expense = Expense::query()->where('company_id', $user->company_id)->where('description', 'Achat de carburant pour livraison Bamako')->firstOrFail();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('sales.show', $invoice))
            ->assertOk()
            ->assertSee('Mouvements de stock lies')
            ->assertSee('Ecritures comptables liees');

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('purchases.show', $bill))
            ->assertOk()
            ->assertSee('Mouvements de stock lies')
            ->assertSee('Ecritures comptables liees');

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('expenses.show', $expense))
            ->assertOk()
            ->assertSee('Ecritures comptables liees')
            ->assertSee('Informations generales');
    }

    public function test_journal_entries_support_source_filter_and_back_navigation_to_document(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $invoice = SalesInvoice::query()->where('company_id', $user->company_id)->where('notes', 'Facture de demonstration initiale')->firstOrFail();
        $bill = PurchaseBill::query()->where('company_id', $user->company_id)->where('notes', 'Facture fournisseur de demonstration')->firstOrFail();
        $entry = JournalEntry::query()
            ->where('company_id', $user->company_id)
            ->where('source_type', SalesInvoice::class)
            ->where('source_id', $invoice->id)
            ->firstOrFail();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('accounting.journal-entries.index', [
                'source_type' => 'sales',
                'search' => $invoice->invoice_number,
            ]))
            ->assertOk()
            ->assertSee($invoice->invoice_number)
            ->assertDontSee($bill->bill_number);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('accounting.journal-entries.show', $entry))
            ->assertOk()
            ->assertSee('Ouvrir le document source')
            ->assertSee($invoice->invoice_number);
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}
