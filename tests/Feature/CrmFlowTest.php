<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Crm\Models\Opportunity;
use App\Modules\Partners\Models\Partner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmFlowTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_manager_can_create_opportunity_and_convert_it_to_customer(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $response = $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('crm.store'), [
                'lead_name' => 'Superette Faso Kanu',
                'title' => 'Ouverture compte distribution',
                'contact_name' => 'Mme Diallo',
                'contact_phone' => '+22370000111',
                'source' => 'Visite terrain',
                'stage' => 'proposal',
                'expected_amount' => 950000,
            ]);

        $opportunity = Opportunity::query()->where('company_id', $user->company_id)->where('lead_name', 'Superette Faso Kanu')->firstOrFail();

        $response->assertRedirect(route('crm.show', $opportunity));

        $convertResponse = $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('crm.convert-customer', $opportunity));

        $customer = Partner::query()->customers()->where('company_id', $user->company_id)->where('name', 'Superette Faso Kanu')->firstOrFail();
        $opportunity->refresh();

        $convertResponse->assertRedirect(route('customers.show', $customer));
        $this->assertSame($customer->id, $opportunity->partner_id);
    }

    public function test_manager_can_update_opportunity_stage(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $opportunity = Opportunity::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'lead_name' => 'Entreprise Kora',
            'title' => 'Projet approvisionnement mensuel',
            'stage' => 'new',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('crm.update-stage', $opportunity), ['stage' => 'negotiation'])
            ->assertRedirect(route('crm.show', $opportunity));

        $opportunity->refresh();
        $this->assertSame('negotiation', $opportunity->stage);
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}
