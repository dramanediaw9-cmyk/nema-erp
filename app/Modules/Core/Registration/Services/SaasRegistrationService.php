<?php

namespace App\Modules\Core\Registration\Services;

use App\Models\User;
use App\Modules\Core\Access\Models\Permission;
use App\Modules\Core\Access\Models\Role;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Core\Company\Models\Setting;
use App\Modules\Core\Company\Models\Tenant;
use App\Modules\Core\Company\Services\CompanyProvisioningService;
use App\Modules\Core\Company\Services\SectorProfileService;
use App\Modules\Core\Platform\Models\DeploymentProfile;
use App\Modules\Core\Platform\Models\SaasSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SaasRegistrationService
{
    public function __construct(
        private readonly SectorProfileService $sectorProfiles,
        private readonly CompanyProvisioningService $provisioning,
    ) {}

    public function plans(): array
    {
        return [
            'starter' => [
                'label' => 'Essentiel',
                'summary' => 'Pour démarrer avec une petite équipe.',
                'users' => 3,
                'branches' => 1,
                'support' => 'essential',
            ],
            'growth' => [
                'label' => 'Croissance',
                'summary' => 'Le meilleur équilibre pour une PME en développement.',
                'users' => 15,
                'branches' => 3,
                'support' => 'priority',
                'recommended' => true,
            ],
            'business' => [
                'label' => 'Entreprise',
                'summary' => 'Pour plusieurs équipes, agences et responsables.',
                'users' => 50,
                'branches' => 10,
                'support' => 'business',
            ],
        ];
    }

    public function create(array $registration): array
    {
        $plan = $this->plans()[$registration['plan']];

        return DB::transaction(function () use ($registration, $plan): array {
            $tenant = Tenant::query()->create([
                'code' => $this->uniqueTenantCode(),
                'name' => $registration['company']['name'],
                'slug' => $registration['workspace']['slug'],
                'is_active' => true,
            ]);

            $company = Company::query()->create([
                'tenant_id' => $tenant->id,
                'name' => $registration['company']['name'],
                'legal_name' => $registration['company']['legal_name'] ?: null,
                'phone' => $registration['company']['phone'] ?: $registration['account']['phone'],
                'email' => $registration['company']['email'] ?: $registration['account']['email'],
                'address' => $registration['company']['address'] ?: null,
                'currency_code' => 'XOF',
                'is_active' => true,
            ]);

            $branch = Branch::query()->create([
                'tenant_id' => $tenant->id,
                'company_id' => $company->id,
                'name' => $registration['workspace']['branch_name'],
                'code' => 'SIEGE',
                'city' => $registration['workspace']['city'] ?: null,
                'address' => $registration['company']['address'] ?: null,
                'is_active' => true,
                'is_default' => true,
            ]);

            $user = User::query()->create([
                'tenant_id' => $tenant->id,
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'name' => $registration['account']['name'],
                'phone' => $registration['account']['phone'] ?: null,
                'email' => $registration['account']['email'],
                'password' => $registration['account']['password_hash'],
                'is_active' => true,
                'last_login_at' => now(),
            ]);

            $role = Role::query()->create([
                'tenant_id' => $tenant->id,
                'company_id' => $company->id,
                'name' => 'Administrateur entreprise',
                'slug' => 'company_admin',
                'description' => 'Responsable principal de l espace Nema.',
                'is_system' => true,
            ]);

            $role->permissions()->sync(Permission::query()->pluck('id'));
            $user->roles()->attach($role->id);

            $this->sectorProfiles->updateProfile(
                $company->id,
                $tenant->id,
                $registration['workspace']['sector_profile']
            );
            $this->provisioning->provision($company, [
                'branch_name' => $branch->name,
                'city' => $branch->city,
                'sector_profile' => $registration['workspace']['sector_profile'],
            ]);

            Setting::query()->create([
                'tenant_id' => $tenant->id,
                'company_id' => $company->id,
                'key' => 'registration_profile',
                'value' => [
                    'country_code' => 'ML',
                    'city' => $registration['workspace']['city'] ?: null,
                    'source' => 'public_registration',
                ],
            ]);

            DeploymentProfile::query()->create([
                'tenant_id' => $tenant->id,
                'company_id' => $company->id,
                'owner_id' => $user->id,
                'commercial_offer' => $registration['plan'],
                'deployment_mode' => 'saas',
                'lifecycle_stage' => 'trial',
                'hosting_target' => 'hostinger',
                'support_tier' => $plan['support'],
                'monitoring_level' => 'standard',
                'backup_strategy' => 'managed',
                'update_channel' => 'stable',
                'target_users' => $plan['users'],
                'target_branches' => $plan['branches'],
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $subscription = SaasSubscription::query()->create([
                'tenant_id' => $tenant->id,
                'company_id' => $company->id,
                'plan' => $registration['plan'],
                'status' => 'trialing',
                'user_limit' => $plan['users'],
                'branch_limit' => $plan['branches'],
                'starts_at' => now(),
                'trial_ends_at' => now()->addDays(14),
                'terms_accepted_at' => now(),
            ]);

            return compact('tenant', 'company', 'branch', 'user', 'subscription');
        });
    }

    private function uniqueTenantCode(): string
    {
        do {
            $code = 'TEN-'.Str::upper(Str::random(10));
        } while (Tenant::query()->where('code', $code)->exists());

        return $code;
    }
}
