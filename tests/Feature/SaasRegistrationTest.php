<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Access\Models\Role;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Core\Company\Models\Setting;
use App\Modules\Core\Company\Models\Tenant;
use App\Modules\Core\Platform\Models\DeploymentProfile;
use App\Modules\Core\Platform\Models\SaasSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaasRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_public_registration_starts_before_login(): void
    {
        $this->get(route('saas.register.account'))
            ->assertOk()
            ->assertSee('Étape 1 sur 4')
            ->assertSee('Créons votre compte administrateur')
            ->assertSee('J’ai déjà un espace');
    }

    public function test_registration_steps_cannot_be_skipped(): void
    {
        $this->get(route('saas.register.plan'))
            ->assertRedirect(route('saas.register.account'));
    }

    public function test_company_can_create_an_isolated_trial_workspace(): void
    {
        $this->post(route('saas.register.account.store'), [
            'name' => 'Awa Traore',
            'email' => 'awa@example.com',
            'phone' => '+22370000000',
            'password' => 'NemaPass2026!',
            'password_confirmation' => 'NemaPass2026!',
        ])->assertRedirect(route('saas.register.company'));

        $this->post(route('saas.register.company.store'), [
            'name' => 'Traore Distribution',
            'legal_name' => 'Traore Distribution SARL',
            'phone' => '+22320000000',
            'email' => 'contact@traore.example',
            'address' => 'Hamdallaye ACI 2000',
        ])->assertRedirect(route('saas.register.workspace'));

        $this->post(route('saas.register.workspace.store'), [
            'slug' => 'traore-distribution',
            'sector_profile' => 'wholesale_distribution',
            'branch_name' => 'Siège Bamako',
            'city' => 'Bamako',
        ])->assertRedirect(route('saas.register.plan'));

        $this->post(route('saas.register.complete'), [
            'plan' => 'growth',
            'terms' => '1',
            'website' => '',
        ])->assertRedirect(route('onboarding.index'));

        $this->assertAuthenticated();

        $tenant = Tenant::query()->where('slug', 'traore-distribution')->firstOrFail();
        $company = Company::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $user = User::query()->where('email', 'awa@example.com')->firstOrFail();
        $role = Role::query()->where('company_id', $company->id)->where('slug', 'company_admin')->firstOrFail();

        $this->assertSame($tenant->id, $company->tenant_id);
        $this->assertSame($tenant->id, $user->tenant_id);
        $this->assertSame($company->id, $user->company_id);
        $this->assertTrue($user->roles()->whereKey($role->id)->exists());
        $this->assertSame($tenant->id, session('current_tenant_id'));
        $this->assertSame($company->id, session('current_company_id'));
        $this->assertSame($user->branch_id, session('current_branch_id'));

        $this->assertDatabaseHas('branches', [
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'code' => 'SIEGE',
            'is_default' => true,
        ]);
        $this->assertDatabaseHas('saas_subscriptions', [
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'plan' => 'growth',
            'status' => 'trialing',
            'user_limit' => 15,
            'branch_limit' => 3,
        ]);

        $profile = Setting::query()
            ->where('company_id', $company->id)
            ->where('key', 'sector_profile')
            ->firstOrFail();

        $this->assertSame('wholesale_distribution', $profile->value['profile']);
        $this->assertSame('trial', DeploymentProfile::query()->where('company_id', $company->id)->value('lifecycle_stage'));
        $this->assertNotNull(SaasSubscription::query()->where('company_id', $company->id)->value('trial_ends_at'));
    }

    public function test_existing_email_cannot_start_a_second_workspace(): void
    {
        $existing = User::query()->firstOrFail();

        $this->post(route('saas.register.account.store'), [
            'name' => 'Compte doublon',
            'email' => $existing->email,
            'password' => 'NemaPass2026',
            'password_confirmation' => 'NemaPass2026',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_invalid_sector_is_rejected(): void
    {
        $this->withSession([
            'saas_registration' => [
                'account' => [
                    'name' => 'Awa Traore',
                    'email' => 'awa@example.com',
                    'phone' => '',
                    'password_hash' => bcrypt('NemaPass2026'),
                ],
                'company' => [
                    'name' => 'Traore Distribution',
                    'legal_name' => '',
                    'phone' => '',
                    'email' => '',
                    'address' => '',
                ],
            ],
        ])->post(route('saas.register.workspace.store'), [
            'slug' => 'traore-distribution',
            'sector_profile' => 'forged-sector',
            'branch_name' => 'Siège',
            'city' => 'Bamako',
        ])->assertSessionHasErrors('sector_profile');

        $this->assertDatabaseMissing('tenants', ['slug' => 'traore-distribution']);
    }
}
