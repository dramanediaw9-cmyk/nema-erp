<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormExperienceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_manager_can_open_main_creation_forms_with_operational_helpers(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('sales.create'))
            ->assertOk()
            ->assertSee('Nouvelle facture client')
            ->assertSee('Le stock et la comptabilite se declencheront a la validation finale.')
            ->assertSee('Resume de la facture')
            ->assertSee("Impact a l'approbation finale", false);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('purchases.create'))
            ->assertOk()
            ->assertSee('Nouvelle facture fournisseur')
            ->assertSee('Le stock suivra le workflow d achat.')
            ->assertSee("Resume de l'achat", false)
            ->assertSee('Lignes de facture fournisseur');

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

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('customers.create'))
            ->assertOk()
            ->assertSee('Nouveau client')
            ->assertSee('Renseigne le tiers une fois pour l utiliser ensuite en vente, facturation et recouvrement.');

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('suppliers.create'))
            ->assertOk()
            ->assertSee('Nouveau fournisseur')
            ->assertSee('Renseigne le tiers une fois pour l utiliser ensuite en achat, depense et reglement fournisseur.');

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('products.create'))
            ->assertOk()
            ->assertSee('Nouveau produit')
            ->assertSee('La fiche produit alimente le stock, les ventes, les achats et le POS depuis un seul endroit.');
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}
