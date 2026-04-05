<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Pos\Models\PosSession;
use App\Modules\Treasury\Models\CashAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchantModeTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_company_admin_can_view_dashboard_in_merchant_mode_with_simplified_navigation(): void
    {
        $admin = User::query()->where('email', 'admin@nema-erp.test')->firstOrFail();

        $this->actingAs($admin)
            ->withSession($this->workspaceSession($admin) + ['ui_mode' => 'merchant'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Routine commerce')
            ->assertSee('Mode commercant')
            ->assertSee('Routine du jour')
            ->assertSee('Vendre et encaisser')
            ->assertSee('Rapport simple')
            ->assertDontSee('Plan comptable')
            ->assertDontSee('Journaux d\'activite')
            ->assertDontSee('Roles et permissions');
    }

    public function test_company_admin_can_switch_interface_mode(): void
    {
        $admin = User::query()->where('email', 'admin@nema-erp.test')->firstOrFail();

        $this->actingAs($admin)
            ->withSession($this->workspaceSession($admin) + ['ui_mode' => 'full'])
            ->from(route('dashboard'))
            ->post(route('ui-mode.update'), ['mode' => 'merchant'])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('ui_mode', 'merchant');

        $this->actingAs($admin)
            ->withSession($this->workspaceSession($admin) + ['ui_mode' => 'full'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Plan comptable')
            ->assertSee('Roles et permissions')
            ->assertSee('Pilotage en temps reel');
    }

    public function test_company_admin_can_open_merchant_routine_with_daily_steps(): void
    {
        $admin = User::query()->where('email', 'admin@nema-erp.test')->firstOrFail();

        $this->actingAs($admin)
            ->withSession($this->workspaceSession($admin) + ['ui_mode' => 'merchant'])
            ->get(route('merchant.routine'))
            ->assertOk()
            ->assertSee('Routine commerce')
            ->assertSee('Parcours du jour')
            ->assertSee('Ouvrir la caisse')
            ->assertSee('Vendre et encaisser')
            ->assertSee('Sortir le resume du jour');
    }

    public function test_merchant_routine_highlights_active_pos_session(): void
    {
        $admin = User::query()->where('email', 'admin@nema-erp.test')->firstOrFail();
        $cashAccount = CashAccount::query()
            ->where('company_id', $admin->company_id)
            ->where('branch_id', $admin->branch_id)
            ->where('is_active', true)
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
            'session_number' => 'POS-TEST-001',
            'status' => 'open',
            'opening_amount' => 25000,
            'opened_at' => now(),
            'opened_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->withSession($this->workspaceSession($admin) + ['ui_mode' => 'merchant'])
            ->get(route('merchant.routine'))
            ->assertOk()
            ->assertSee('Session ouverte sur ton poste')
            ->assertSee($session->session_number)
            ->assertSee('Continuer la caisse')
            ->assertSee('Preparer la cloture');
    }

    public function test_cashier_can_open_every_visible_merchant_mode_module(): void
    {
        $cashier = User::query()->where('email', 'caissier@nema-erp.test')->firstOrFail();
        $session = $this->workspaceSession($cashier) + ['ui_mode' => 'merchant'];

        $this->actingAs($cashier)->withSession($session)->get(route('merchant.routine'))->assertOk();
        $this->actingAs($cashier)->withSession($session)->get(route('pos.index'))->assertOk();
        $this->actingAs($cashier)->withSession($session)->get(route('pos.report'))->assertOk();
        $this->actingAs($cashier)->withSession($session)->get(route('payments.index'))->assertOk();
        $this->actingAs($cashier)->withSession($session)->get(route('stock.index'))->assertOk();
        $this->actingAs($cashier)->withSession($session)->get(route('stock.lots'))->assertOk();
        $this->actingAs($cashier)->withSession($session)->get(route('customers.index'))->assertOk();
        $this->actingAs($cashier)->withSession($session)->get(route('products.index'))->assertOk();
    }

    public function test_cashier_sees_simplified_stock_screen_in_merchant_mode(): void
    {
        $cashier = User::query()->where('email', 'caissier@nema-erp.test')->firstOrFail();

        $this->actingAs($cashier)
            ->withSession($this->workspaceSession($cashier) + ['ui_mode' => 'merchant'])
            ->get(route('stock.index'))
            ->assertOk()
            ->assertSee('Stock boutique')
            ->assertSee('Ruptures visibles')
            ->assertSee('Recherche simple')
            ->assertDontSee('Valorisation visible')
            ->assertDontSee('Disponible a promettre');
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}
