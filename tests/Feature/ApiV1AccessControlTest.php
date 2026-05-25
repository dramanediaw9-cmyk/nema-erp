<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Core\Integrations\Models\ApiToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiV1AccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_api_actor_without_product_permission_cannot_list_products(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $branch = Branch::query()->whereKey($manager->branch_id)->firstOrFail();
        $company = Company::query()->whereKey($manager->company_id)->firstOrFail();

        $user = User::factory()->create([
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);

        $plainToken = $this->createApiToken($user, 'nema_test_catalog_api_token_'.$user->id);

        $this->withToken($plainToken)
            ->getJson('/api/v1/products')
            ->assertForbidden();
    }

    public function test_api_actor_without_platform_permission_cannot_read_platform_capabilities(): void
    {
        $cashier = User::query()->where('email', 'caissier@nema-erp.test')->firstOrFail();
        $plainToken = $this->createApiToken($cashier, 'nema_test_platform_api_token_'.$cashier->id);

        $this->withToken($plainToken)
            ->getJson('/api/v1/platform/capabilities')
            ->assertForbidden();
    }

    private function createApiToken(User $user, string $plainToken): string
    {
        ApiToken::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'name' => 'Test API Access',
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addDay(),
            'created_by' => $user->id,
        ]);

        return $plainToken;
    }
}
