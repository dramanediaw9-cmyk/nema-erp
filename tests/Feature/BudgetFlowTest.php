<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Budgets\Models\Budget;
use App\Modules\Sales\Models\SalesInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetFlowTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_manager_can_create_budget_with_lines(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $response = $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('budgets.store'), [
                'name' => 'Budget test '.now()->year,
                'fiscal_year' => now()->year,
                'status' => 'active',
                'lines' => [
                    ['metric' => 'sales', 'period_month' => now()->month, 'amount' => 250000, 'notes' => 'Objectif ventes'],
                    ['metric' => 'collections', 'period_month' => now()->month, 'amount' => 180000, 'notes' => 'Objectif encaissements'],
                ],
            ]);

        $budget = Budget::query()->where('company_id', $user->company_id)->where('name', 'Budget test '.now()->year)->firstOrFail();

        $response->assertRedirect(route('budgets.show', $budget));
        $this->assertSame('active', $budget->status);
        $this->assertCount(2, $budget->lines);
    }

    public function test_budget_detail_shows_actuals_for_current_month(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $actualSales = (float) SalesInvoice::query()
            ->where('company_id', $user->company_id)
            ->where('status', 'validated')
            ->whereMonth('invoice_date', now()->month)
            ->whereYear('invoice_date', now()->year)
            ->sum('total');

        $budget = Budget::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => null,
            'name' => 'Budget realise '.now()->year,
            'fiscal_year' => now()->year,
            'status' => 'active',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $budget->lines()->create([
            'metric' => 'sales',
            'period_month' => now()->month,
            'amount' => max(1000, $actualSales + 1000),
            'notes' => 'Suivi ventes du mois',
        ]);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('budgets.show', $budget))
            ->assertOk()
            ->assertSee('Detail mensuel objectif / realise')
            ->assertSee(number_format($actualSales, 0, ',', ' '), false)
            ->assertSee('Ventes');
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}
