<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Partners\Models\Partner;
use App\Modules\Sales\Models\SalesInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DecisionReportingTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_reports_page_shows_decision_sections(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('reports.index', [
                'date_from' => now()->startOfMonth()->format('Y-m-d'),
                'date_to' => now()->format('Y-m-d'),
            ]))
            ->assertOk()
            ->assertSee('Comparaison periode precedente')
            ->assertSee('Signaux de pilotage')
            ->assertSee('Top produits')
            ->assertSee('Marge par categorie')
            ->assertSee('Top clients')
            ->assertSee('Ventes par agence')
            ->assertSee('Produits dormants');
    }

    public function test_reports_page_can_filter_decision_sections_by_branch(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $bko = Branch::query()->where('company_id', $user->company_id)->where('code', 'BKO')->firstOrFail();
        $sik = Branch::query()->where('company_id', $user->company_id)->where('code', 'SIK')->firstOrFail();

        $sikCustomer = Partner::query()->create([
            'company_id' => $user->company_id,
            'type' => 'customer',
            'code' => 'CLI-RPT-SIK',
            'name' => 'Client Sikasso Reporting',
            'phone' => '70000001',
            'is_active' => true,
        ]);

        $bkoCustomer = Partner::query()->create([
            'company_id' => $user->company_id,
            'type' => 'customer',
            'code' => 'CLI-RPT-BKO',
            'name' => 'Client Bamako Reporting',
            'phone' => '70000002',
            'is_active' => true,
        ]);

        $this->createSalesInvoice($user, $sikCustomer->id, $sik->id, 'RPT-SIK-001', 220000);
        $this->createSalesInvoice($user, $bkoCustomer->id, $bko->id, 'RPT-BKO-001', 150000);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('reports.index', [
                'date_from' => now()->toDateString(),
                'date_to' => now()->toDateString(),
                'branch_id' => $sik->id,
            ]))
            ->assertOk()
            ->assertSee('perimetre '.$sik->name)
            ->assertSee('Client Sikasso Reporting')
            ->assertDontSee('Client Bamako Reporting');
    }

    private function createSalesInvoice(User $user, int $customerId, int $branchId, string $number, float $total): SalesInvoice
    {
        return SalesInvoice::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $branchId,
            'customer_id' => $customerId,
            'invoice_number' => $number,
            'invoice_date' => now()->toDateString(),
            'status' => 'validated',
            'payment_status' => 'paid',
            'subtotal' => $total,
            'net_total' => $total,
            'tax_total' => 0,
            'total' => $total,
            'amount_paid' => $total,
            'balance_due' => 0,
            'created_by' => $user->id,
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
