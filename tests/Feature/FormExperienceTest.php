<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Company\Services\SectorProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormExperienceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_manager_can_open_main_creation_forms_with_operational_helpers(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $vocabulary = app(SectorProfileService::class)->businessVocabularyForCompany($user->company_id);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('sales.create'))
            ->assertOk()
            ->assertSee('Nouvelle '.$vocabulary['sale'])
            ->assertSee('et la comptabilite se declencheront a la validation finale.')
            ->assertSee('Resume de la '.strtolower($vocabulary['sale']))
            ->assertSee("Impact a l'approbation finale", false);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('purchases.create'))
            ->assertOk()
            ->assertSee('Nouveau '.$vocabulary['purchase'])
            ->assertSee('Le stock suivra le workflow.')
            ->assertSee('Resume du '.strtolower($vocabulary['purchase']))
            ->assertSee('Lignes du '.strtolower($vocabulary['purchase']));

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('expenses.create'))
            ->assertOk()
            ->assertSee('Resume de la depense')
            ->assertSee('Statut de paiement');
    }

    public function test_manager_can_open_master_data_forms_with_clear_headers(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $vocabulary = app(SectorProfileService::class)->businessVocabularyForCompany($user->company_id);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('customers.create'))
            ->assertOk()
            ->assertSee('Nouveau '.$vocabulary['client'])
            ->assertSee('Renseigne ce dossier une fois pour l utiliser ensuite en facturation, paiement et suivi.');

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('suppliers.create'))
            ->assertOk()
            ->assertSee('Nouveau '.$vocabulary['supplier'])
            ->assertSee('Renseigne ce partenaire une fois');

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('products.create'))
            ->assertOk()
            ->assertSee('Nouveau '.$vocabulary['product'])
            ->assertSee('La fiche alimente le stock, les ventes, les achats et la caisse depuis un seul endroit.');
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}
