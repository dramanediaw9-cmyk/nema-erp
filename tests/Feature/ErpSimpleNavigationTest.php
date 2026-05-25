<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErpSimpleNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_full_mode_dashboard_surfaces_simple_module_navigation(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Modules ERP')
            ->assertSee('Tableau de bord')
            ->assertSee('Ventes')
            ->assertSee('POS / Caisse')
            ->assertSee('Achats')
            ->assertSee('Stock')
            ->assertSee('Facturation')
            ->assertSee('Comptabilite simple')
            ->assertSee('Plan comptable')
            ->assertSee('Roles et permissions');
    }

    public function test_facturation_pages_show_contextual_module_navigation(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->get(route('sales.index'))
            ->assertOk()
            ->assertSee('Applications')
            ->assertSee('Facturation')
            ->assertSee('Nouvelle facture')
            ->assertSee('Nouvel encaissement')
            ->assertSee('Avoirs')
            ->assertSee('Paiements')
            ->assertSee('Recouvrement');
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}
