<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Treasury\Models\CashAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PilotReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_onboarding_page_shows_pilot_readiness_section_and_runbook(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->get(route('onboarding.index'))
            ->assertOk()
            ->assertSee('Essai reel terrain')
            ->assertSee('Prerequis pilote')
            ->assertSee('Qualite des donnees pilotes')
            ->assertSee('Bloquants a lever')
            ->assertSee('Parcours terrain a valider')
            ->assertSee('Verrouiller l essai reel')
            ->assertSee('Valider l essai reel terrain');
    }

    public function test_onboarding_page_reports_cash_blocker_when_no_active_cash_account_exists(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        CashAccount::query()
            ->where('company_id', $manager->company_id)
            ->where('type', 'cash')
            ->update(['is_active' => false]);

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->get(route('onboarding.index'))
            ->assertOk()
            ->assertSee('Compte especes comptoir')
            ->assertSee('Aucun compte de tresorerie espece n est pret pour la caisse.')
            ->assertSee('Pilote encore a verrouiller');
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}
