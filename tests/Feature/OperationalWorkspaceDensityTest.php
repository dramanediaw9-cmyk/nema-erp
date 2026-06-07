<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalWorkspaceDensityTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_core_lists_open_with_compact_operational_controls(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $this->actingAs($manager)->withSession([
            'current_company_id' => $manager->company_id,
            'current_branch_id' => $manager->branch_id,
        ]);

        foreach ([
            route('products.index'),
            route('stock.index'),
            route('sales.index'),
            route('purchases.index'),
            route('stock.lots'),
        ] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('data-layout-mode="compact"', false)
                ->assertSee('erp-work-toolbar', false)
                ->assertSee('erp-kpi-strip', false)
                ->assertSee('erp-filter-panel', false);
        }
    }

    public function test_global_navigation_and_search_do_not_render_pin_controls(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $this->actingAs($manager)->withSession([
            'current_company_id' => $manager->company_id,
            'current_branch_id' => $manager->branch_id,
        ]);

        $this->get(route('products.index'))
            ->assertOk()
            ->assertSee('erp-module-bar', false)
            ->assertDontSee('module-favorite-button', false)
            ->assertDontSee('navigation.favorites.toggle', false);

        $this->get(route('search.index'))
            ->assertOk()
            ->assertSee('search-workbar', false)
            ->assertDontSee('Epingler')
            ->assertDontSee('Retirer favori');
    }
}
