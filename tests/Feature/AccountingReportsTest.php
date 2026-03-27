<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingReportsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_company_admin_can_export_trial_balance_csv(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $response = $this->actingAs($user)
            ->withSession([
                'current_company_id' => $user->company_id,
                'current_branch_id' => $user->branch_id,
            ])
            ->get(route('accounting.balance.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_company_admin_can_open_printable_financial_reports(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $this->actingAs($user)
            ->withSession([
                'current_company_id' => $user->company_id,
                'current_branch_id' => $user->branch_id,
            ])
            ->get(route('accounting.profit-loss.print'))
            ->assertOk()
            ->assertSee('Compte de resultat');

        $this->actingAs($user)
            ->withSession([
                'current_company_id' => $user->company_id,
                'current_branch_id' => $user->branch_id,
            ])
            ->get(route('accounting.balance-sheet.print'))
            ->assertOk()
            ->assertSee('Bilan simplifie');
    }
}
