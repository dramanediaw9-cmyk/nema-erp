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

    public function test_company_admin_can_open_financial_reports_as_pdf(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $trialBalance = $this->actingAs($user)
            ->withSession([
                'current_company_id' => $user->company_id,
                'current_branch_id' => $user->branch_id,
            ])
            ->get(route('accounting.balance.print'));

        $trialBalance->assertOk();
        $this->assertPdfResponse($trialBalance);

        $generalLedger = $this->actingAs($user)
            ->withSession([
                'current_company_id' => $user->company_id,
                'current_branch_id' => $user->branch_id,
            ])
            ->get(route('accounting.general-ledger.print'));

        $generalLedger->assertOk();
        $this->assertPdfResponse($generalLedger);

        $profitLoss = $this->actingAs($user)
            ->withSession([
                'current_company_id' => $user->company_id,
                'current_branch_id' => $user->branch_id,
            ])
            ->get(route('accounting.profit-loss.print'));

        $profitLoss->assertOk();
        $this->assertPdfResponse($profitLoss);

        $balanceSheet = $this->actingAs($user)
            ->withSession([
                'current_company_id' => $user->company_id,
                'current_branch_id' => $user->branch_id,
            ])
            ->get(route('accounting.balance-sheet.print'));

        $balanceSheet->assertOk();
        $this->assertPdfResponse($balanceSheet);
    }

    private function assertPdfResponse($response): void
    {
        $contentType = $response->headers->get('content-type', '');
        $content = $response->getContent();

        $this->assertStringStartsWith('application/pdf', $contentType);
        $this->assertIsString($content);
        $this->assertStringStartsWith('%PDF-', $content);
    }
}
