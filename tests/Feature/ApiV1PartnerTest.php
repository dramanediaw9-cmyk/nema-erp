<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Integrations\Models\ApiToken;
use App\Modules\Partners\Models\Partner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiV1PartnerTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_api_requires_a_token(): void
    {
        $this->getJson('/api/v1/partners')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Jeton API manquant.');
    }

    public function test_api_token_can_create_partner(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $plainToken = $this->createApiToken($manager);

        $response = $this->withToken($plainToken)
            ->postJson('/api/v1/partners', [
                'type' => 'customer',
                'name' => 'Client API Connecteur',
                'phone' => '+22370001111',
                'email' => 'client.api@example.test',
                'city' => 'Bamako',
                'notes' => 'Cree via API',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('name', 'Client API Connecteur')
            ->assertJsonPath('type', 'customer')
            ->assertJsonPath('city', 'Bamako');

        $this->assertStringStartsWith('C', (string) $response->json('code'));

        $this->assertDatabaseHas('partners', [
            'company_id' => $manager->company_id,
            'name' => 'Client API Connecteur',
            'type' => 'customer',
            'email' => 'client.api@example.test',
        ]);
    }

    public function test_api_token_can_update_partner(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $plainToken = $this->createApiToken($manager);
        $partner = Partner::query()->customers()->where('company_id', $manager->company_id)->firstOrFail();

        $this->withToken($plainToken)
            ->putJson('/api/v1/partners/'.$partner->id, [
                'type' => 'both',
                'name' => 'Partenaire API Modifie',
                'phone' => '+22370002222',
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('id', $partner->id)
            ->assertJsonPath('type', 'both')
            ->assertJsonPath('name', 'Partenaire API Modifie')
            ->assertJsonPath('is_active', false);

        $this->assertDatabaseHas('partners', [
            'id' => $partner->id,
            'company_id' => $manager->company_id,
            'type' => 'both',
            'name' => 'Partenaire API Modifie',
            'phone' => '+22370002222',
            'is_active' => false,
        ]);
    }

    public function test_api_token_can_show_and_filter_partners(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $plainToken = $this->createApiToken($manager);

        $partner = Partner::query()->create([
            'tenant_id' => $manager->tenant_id,
            'company_id' => $manager->company_id,
            'type' => 'supplier',
            'code' => 'FAPI9999',
            'name' => 'Fournisseur API Unique',
            'phone' => '+22370003333',
            'email' => 'fournisseur.api@example.test',
            'city' => 'Segou',
            'opening_balance' => 0,
            'is_active' => true,
        ]);

        $this->withToken($plainToken)
            ->getJson('/api/v1/partners/'.$partner->id)
            ->assertOk()
            ->assertJsonPath('id', $partner->id)
            ->assertJsonPath('name', 'Fournisseur API Unique')
            ->assertJsonPath('type', 'supplier');

        $this->withToken($plainToken)
            ->getJson('/api/v1/partners?type=supplier&search=Unique&code=FAPI9999')
            ->assertOk()
            ->assertJsonPath('data.0.id', $partner->id)
            ->assertJsonPath('data.0.code', 'FAPI9999');
    }

    private function createApiToken(User $user): string
    {
        $plainToken = 'nema_test_api_token_'.$user->id;

        ApiToken::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'name' => 'Test API',
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addDay(),
            'created_by' => $user->id,
        ]);

        return $plainToken;
    }
}
