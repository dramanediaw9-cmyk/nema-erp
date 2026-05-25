<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Expenses\Models\ExpenseCategory;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Sales\Models\SalesInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalUrgencyTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_sales_index_highlights_overdue_invoice_and_collection_action(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $invoice = SalesInvoice::query()
            ->where('company_id', $user->company_id)
            ->where('notes', 'Facture de demonstration initiale')
            ->firstOrFail();

        $invoice->update([
            'due_date' => now()->subDays(5)->toDateString(),
            'status' => 'validated',
            'payment_status' => 'partial',
        ]);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('sales.index', [
                'search' => $invoice->invoice_number,
                'due_state' => 'overdue',
            ]))
            ->assertOk()
            ->assertSee($invoice->invoice_number)
            ->assertSee('En retard')
            ->assertSee('Encaisser');
    }

    public function test_purchases_index_highlights_due_soon_bill_and_settlement_action(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $bill = PurchaseBill::query()
            ->where('company_id', $user->company_id)
            ->where('notes', 'Facture fournisseur de demonstration')
            ->firstOrFail();

        $bill->update([
            'due_date' => now()->addDays(3)->toDateString(),
            'status' => 'validated',
            'payment_status' => 'partial',
        ]);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('purchases.index', [
                'search' => $bill->bill_number,
                'due_state' => 'due_soon',
            ]))
            ->assertOk()
            ->assertSee($bill->bill_number)
            ->assertSee('Echeance proche')
            ->assertSee('Regler');
    }

    public function test_sales_index_can_render_kanban_view_for_collection_priorities(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $invoice = SalesInvoice::query()
            ->where('company_id', $user->company_id)
            ->where('notes', 'Facture de demonstration initiale')
            ->firstOrFail();

        $invoice->update([
            'due_date' => now()->subDays(2)->toDateString(),
            'status' => 'validated',
            'payment_status' => 'partial',
        ]);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('sales.index', [
                'search' => $invoice->invoice_number,
                'view' => 'kanban',
            ]))
            ->assertOk()
            ->assertSee($invoice->invoice_number)
            ->assertSee($invoice->customer->name)
            ->assertSee('Voir la facture')
            ->assertSee('Encaisser');
    }

    public function test_purchases_index_can_render_kanban_view_for_supplier_priorities(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $bill = PurchaseBill::query()
            ->where('company_id', $user->company_id)
            ->where('notes', 'Facture fournisseur de demonstration')
            ->firstOrFail();

        $bill->update([
            'due_date' => now()->addDays(2)->toDateString(),
            'status' => 'validated',
            'payment_status' => 'partial',
        ]);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('purchases.index', [
                'search' => $bill->bill_number,
                'view' => 'kanban',
            ]))
            ->assertOk()
            ->assertSee($bill->bill_number)
            ->assertSee($bill->supplier->name)
            ->assertSee('Voir la facture')
            ->assertSee('Regler');
    }

    public function test_expenses_index_filters_old_unpaid_expenses(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $category = ExpenseCategory::query()->where('company_id', $user->company_id)->firstOrFail();

        Expense::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'expense_category_id' => $category->id,
            'expense_number' => 'DEP-AGE-001',
            'expense_date' => now()->subDays(40)->toDateString(),
            'description' => 'Depense ancienne test',
            'total' => 75000,
            'status' => 'validated',
            'payment_status' => 'unpaid',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('expenses.index', [
                'aging_state' => 'age_31_plus',
            ]))
            ->assertOk()
            ->assertSee('Depense ancienne test')
            ->assertSee('A regler');
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}
