<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NavigationFavoriteTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_user_can_pin_and_unpin_module_favorites(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->from(route('search.index'))
            ->post(route('navigation.favorites.toggle'), [
                'module_key' => 'stock',
            ])
            ->assertRedirect(route('search.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('user_navigation_favorites', [
            'company_id' => $manager->company_id,
            'user_id' => $manager->id,
            'module_key' => 'stock',
        ]);

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->get(route('search.index'))
            ->assertOk()
            ->assertSee('Acces rapides')
            ->assertDontSee('Retirer favori')
            ->assertDontSee('Epingler');

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->from(route('search.index'))
            ->post(route('navigation.favorites.toggle'), [
                'module_key' => 'stock',
            ])
            ->assertRedirect(route('search.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('user_navigation_favorites', [
            'company_id' => $manager->company_id,
            'user_id' => $manager->id,
            'module_key' => 'stock',
        ]);
    }

    public function test_search_page_keeps_recent_searches_for_current_user(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->get(route('search.index', ['q' => 'Sahel Market']))
            ->assertOk();

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->get(route('search.index', ['q' => 'PRD-0001']))
            ->assertOk();

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->get(route('search.index'))
            ->assertOk()
            ->assertSee('Recherches recentes')
            ->assertSee('Sahel Market')
            ->assertSee('PRD-0001');
    }

    public function test_dashboard_stays_accessible_when_favorites_table_is_missing(): void
    {
        Schema::dropIfExists('user_navigation_favorites');

        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('user_navigation_favorites');
    }

    public function test_toggling_favorites_is_graceful_when_table_is_missing(): void
    {
        Schema::dropIfExists('user_navigation_favorites');

        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->from(route('search.index'))
            ->post(route('navigation.favorites.toggle'), [
                'module_key' => 'stock',
            ])
            ->assertRedirect(route('search.index'))
            ->assertSessionHas('error', 'Les favoris seront disponibles apres la mise a jour de la base.');
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}
