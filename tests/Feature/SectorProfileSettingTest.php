<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Company\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SectorProfileSettingTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_sector_profile_can_be_saved_from_settings(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->put(route('settings.sector-profile.update'), [
                'sector_profile' => 'wholesale_distribution',
            ])
            ->assertRedirect(route('settings.index'));

        $setting = Setting::query()
            ->where('company_id', $manager->company_id)
            ->where('key', 'sector_profile')
            ->firstOrFail();

        $this->assertSame('wholesale_distribution', $setting->value['profile'] ?? null);

        $this->assertDatabaseHas('activity_logs', [
            'company_id' => $manager->company_id,
            'user_id' => $manager->id,
            'action' => 'settings.sector_profile.update',
        ]);
    }

    public function test_settings_page_surfaces_shortcuts_and_active_profile_summary(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Parametres generaux')
            ->assertSee('Profil societe')
            ->assertSee('Metier actif')
            ->assertSee('API et integrations');
    }

    public function test_dashboard_surfaces_active_sector_profile_and_recommended_modules(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        Setting::query()->updateOrCreate(
            ['company_id' => $manager->company_id, 'key' => 'sector_profile'],
            ['value' => ['profile' => 'hardware_store']]
        );

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee("Profil d'activite actif", false)
            ->assertSee('Quincaillerie')
            ->assertSee('Modules recommandes pour Quincaillerie')
            ->assertSee('tarif chantier');
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}
