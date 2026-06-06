<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Pos\Models\PosSession;
use App\Modules\Treasury\Models\CashAccount;
use App\Modules\Treasury\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosSettlementWatchTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_merchant_routine_and_pos_report_surface_cash_and_mobile_money_settlement_watch(): void
    {
        $admin = User::query()->where('email', 'admin@nema-erp.test')->firstOrFail();
        $cashAccount = CashAccount::query()
            ->where('company_id', $admin->company_id)
            ->where('branch_id', $admin->branch_id)
            ->where('name', 'Caisse principale')
            ->firstOrFail();
        $waveAccount = CashAccount::query()
            ->where('company_id', $admin->company_id)
            ->where('branch_id', $admin->branch_id)
            ->where('name', 'Wave')
            ->firstOrFail();
        $warehouse = Warehouse::query()
            ->where('company_id', $admin->company_id)
            ->where('branch_id', $admin->branch_id)
            ->where('is_active', true)
            ->firstOrFail();

        $session = PosSession::query()->create([
            'company_id' => $admin->company_id,
            'branch_id' => $admin->branch_id,
            'warehouse_id' => $warehouse->id,
            'cash_account_id' => $cashAccount->id,
            'session_number' => 'POS-SETTLEMENT-001',
            'status' => 'open',
            'opening_amount' => 10000,
            'expected_amount' => 10600,
            'expected_breakdown' => [
                'cash' => 10000,
                'wave' => 600,
            ],
            'closing_amount' => 10400,
            'counted_breakdown' => [
                'cash' => 9800,
                'wave' => 600,
            ],
            'variance_amount' => -200,
            'variance_breakdown' => [
                'cash' => -200,
                'wave' => 0,
            ],
            'variance_notes' => [
                'cash' => 'Monnaie manquante en cloture',
                'wave' => '',
            ],
            'opened_at' => now()->startOfDay()->addHours(8),
            'closed_at' => now()->startOfDay()->addHours(19),
            'opened_by' => $admin->id,
            'closed_by' => $admin->id,
        ]);

        Payment::query()->create([
            'tenant_id' => $admin->tenant_id,
            'company_id' => $admin->company_id,
            'branch_id' => $admin->branch_id,
            'cash_account_id' => $waveAccount->id,
            'pos_session_id' => $session->id,
            'partner_id' => null,
            'payment_number' => 'PAY-SETTLEMENT-001',
            'direction' => 'in',
            'payment_type' => 'customer_receipt',
            'payment_date' => now()->toDateString(),
            'amount' => 600,
            'method' => 'wave',
            'reference' => '',
            'notes' => 'Test rapprochement Wave',
            'created_by' => $admin->id,
        ]);

        $session->forceFill(['status' => 'closed'])->saveQuietly();

        $sessionData = $this->workspaceSession($admin) + ['ui_mode' => 'merchant'];

        $this->actingAs($admin)
            ->withSession($sessionData)
            ->get(route('merchant.routine'))
            ->assertOk()
            ->assertSee('Cloture cash / mobile money')
            ->assertSee('Especes')
            ->assertSee('200 XOF d ecart')
            ->assertSee('Wave')
            ->assertSee('600 XOF a rapprocher')
            ->assertSee('1 reference manquante');

        $this->actingAs($admin)
            ->withSession($sessionData)
            ->get(route('pos.report', ['date' => now()->toDateString()]))
            ->assertOk()
            ->assertSee('Controle fin de journee')
            ->assertSee('Especes')
            ->assertSee('Wave')
            ->assertSee('Rapprocher')
            ->assertSee('600 XOF')
            ->assertSee('200 XOF');
    }

    public function test_reports_include_session_closed_today_even_if_it_opened_the_day_before(): void
    {
        $admin = User::query()->where('email', 'admin@nema-erp.test')->firstOrFail();
        $cashAccount = CashAccount::query()
            ->where('company_id', $admin->company_id)
            ->where('branch_id', $admin->branch_id)
            ->where('name', 'Caisse principale')
            ->firstOrFail();
        $waveAccount = CashAccount::query()
            ->where('company_id', $admin->company_id)
            ->where('branch_id', $admin->branch_id)
            ->where('name', 'Wave')
            ->firstOrFail();
        $warehouse = Warehouse::query()
            ->where('company_id', $admin->company_id)
            ->where('branch_id', $admin->branch_id)
            ->where('is_active', true)
            ->firstOrFail();

        $session = PosSession::query()->create([
            'company_id' => $admin->company_id,
            'branch_id' => $admin->branch_id,
            'warehouse_id' => $warehouse->id,
            'cash_account_id' => $cashAccount->id,
            'session_number' => 'POS-OVERNIGHT-001',
            'status' => 'open',
            'opening_amount' => 5000,
            'expected_amount' => 5800,
            'expected_breakdown' => [
                'cash' => 5000,
                'wave' => 800,
            ],
            'closing_amount' => 5800,
            'counted_breakdown' => [
                'cash' => 5000,
                'wave' => 800,
            ],
            'variance_amount' => 0,
            'variance_breakdown' => [
                'cash' => 0,
                'wave' => 0,
            ],
            'variance_notes' => [
                'cash' => '',
                'wave' => '',
            ],
            'opened_at' => now()->subDay()->setTime(23, 10),
            'closed_at' => now()->setTime(0, 35),
            'opened_by' => $admin->id,
            'closed_by' => $admin->id,
        ]);

        Payment::query()->create([
            'tenant_id' => $admin->tenant_id,
            'company_id' => $admin->company_id,
            'branch_id' => $admin->branch_id,
            'cash_account_id' => $waveAccount->id,
            'pos_session_id' => $session->id,
            'partner_id' => null,
            'payment_number' => 'PAY-OVERNIGHT-001',
            'direction' => 'in',
            'payment_type' => 'customer_receipt',
            'payment_date' => now()->toDateString(),
            'amount' => 800,
            'method' => 'wave',
            'reference' => '',
            'notes' => 'Flux wave sur cloture de nuit',
            'created_by' => $admin->id,
        ]);

        $session->forceFill(['status' => 'closed'])->saveQuietly();

        $sessionData = $this->workspaceSession($admin) + ['ui_mode' => 'merchant'];

        $this->actingAs($admin)
            ->withSession($sessionData)
            ->get(route('merchant.routine'))
            ->assertOk()
            ->assertSee('Wave')
            ->assertSee('800 XOF a rapprocher');

        $this->actingAs($admin)
            ->withSession($sessionData)
            ->get(route('pos.report', ['date' => now()->toDateString()]))
            ->assertOk()
            ->assertSee('POS-OVERNIGHT-001')
            ->assertSee('800 XOF');
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}
