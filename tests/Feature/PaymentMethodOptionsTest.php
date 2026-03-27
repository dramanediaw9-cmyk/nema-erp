<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Expenses\Models\ExpenseCategory;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Treasury\Models\CashAccount;
use App\Modules\Treasury\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentMethodOptionsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_payment_and_expense_forms_show_wave_and_moov_money(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('payments.create'))
            ->assertOk()
            ->assertSee('Wave')
            ->assertSee('Moov Money');

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('expenses.create'))
            ->assertOk()
            ->assertSee('Wave')
            ->assertSee('Moov Money');
    }

    public function test_manager_can_record_wave_payment_and_moov_money_expense(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $invoice = SalesInvoice::query()->where('company_id', $user->company_id)->where('notes', 'Facture de demonstration initiale')->firstOrFail();
        $waveAccount = CashAccount::query()->where('company_id', $user->company_id)->where('name', 'Wave')->firstOrFail();
        $moovAccount = CashAccount::query()->where('company_id', $user->company_id)->where('name', 'Moov Money')->firstOrFail();
        $category = ExpenseCategory::query()->where('company_id', $user->company_id)->firstOrFail();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('payments.store'), [
                'invoice_id' => $invoice->id,
                'cash_account_id' => $waveAccount->id,
                'payment_date' => now()->format('Y-m-d'),
                'amount' => 500,
                'method' => 'wave',
                'reference' => 'TEST-WAVE-001',
                'notes' => 'Paiement Wave test',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('payments', [
            'company_id' => $user->company_id,
            'cash_account_id' => $waveAccount->id,
            'method' => 'wave',
            'reference' => 'TEST-WAVE-001',
        ]);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('expenses.store'), [
                'expense_category_id' => $category->id,
                'supplier_id' => null,
                'cash_account_id' => $moovAccount->id,
                'expense_date' => now()->format('Y-m-d'),
                'description' => 'Depense Moov Money test',
                'total' => 12000,
                'payment_date' => now()->format('Y-m-d'),
                'payment_method' => 'moov_money',
                'payment_reference' => 'TEST-MOOV-001',
                'notes' => 'Depense Moov test',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('expenses', [
            'company_id' => $user->company_id,
            'cash_account_id' => $moovAccount->id,
            'description' => 'Depense Moov Money test',
            'payment_method' => 'moov_money',
            'payment_reference' => 'TEST-MOOV-001',
        ]);
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}
