<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_dashboard_opens_on_compact_operational_data(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $response = $this->actingAs($manager)
            ->withSession([
                'current_company_id' => $manager->company_id,
                'current_branch_id' => $manager->branch_id,
            ])
            ->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertSee('Situation operationnelle')
            ->assertSee('References disponibles')
            ->assertSee('Ruptures et seuil mini')
            ->assertSee('Mouvements aujourd hui')
            ->assertSee('Inventaires ouverts')
            ->assertSee('Transferts du jour')
            ->assertSee('Derniers mouvements de stock')
            ->assertDontSee('Ouvre vite le bon espace metier');
    }
}
