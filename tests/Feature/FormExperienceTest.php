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
            ->assertSee("Resume de la facture")
            ->assertSee("Impact a l'approbation finale", false);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('purchases.create'))
            ->assertOk()
            ->assertSee("Resume de l'achat", false)
            ->assertSee("Lignes de facture fournisseur");

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('expenses.create'))
            ->assertOk()
            ->assertSee('Resume de la depense')
            ->assertSee('Statut de paiement');
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}


