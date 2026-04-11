<?php

namespace App\Modules\Core\Platform\Services;

use App\Models\User;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Core\Platform\Models\DeploymentProfile;

class DeploymentProfileService
{
    public function profileForCompany(Company $company): DeploymentProfile
    {
        $profile = DeploymentProfile::query()
            ->with('owner')
            ->where('company_id', $company->id)
            ->first();

        if ($profile) {
            return $profile;
        }

        return new DeploymentProfile(array_merge($this->defaultAttributes($company), [
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
        ]));
    }

    public function upsertForCompany(Company $company, array $attributes, ?User $actor = null): DeploymentProfile
    {
        $profile = DeploymentProfile::query()->firstOrNew(['company_id' => $company->id]);

        if (! $profile->exists) {
            $profile->fill(array_merge($this->defaultAttributes($company), [
                'tenant_id' => $company->tenant_id,
                'company_id' => $company->id,
            ]));
        }

        $profile->fill([
            'tenant_id' => $company->tenant_id,
            'owner_id' => $attributes['owner_id'] ?? null,
            'commercial_offer' => $attributes['commercial_offer'],
            'deployment_mode' => $attributes['deployment_mode'],
            'lifecycle_stage' => $attributes['lifecycle_stage'],
            'hosting_target' => $attributes['hosting_target'],
            'support_tier' => $attributes['support_tier'],
            'monitoring_level' => $attributes['monitoring_level'],
            'backup_strategy' => $attributes['backup_strategy'],
            'update_channel' => $attributes['update_channel'],
            'target_users' => $attributes['target_users'] ?? null,
            'target_branches' => $attributes['target_branches'] ?? null,
            'go_live_target_at' => $attributes['go_live_target_at'] ?? null,
            'last_release_at' => $attributes['last_release_at'] ?? null,
            'last_backup_verified_at' => $attributes['last_backup_verified_at'] ?? null,
            'last_restore_drill_at' => $attributes['last_restore_drill_at'] ?? null,
            'notes' => $attributes['notes'] ?? null,
            'updated_by' => $actor?->id,
        ]);

        if (! $profile->exists && $actor) {
            $profile->created_by = $actor?->id;
        } elseif (! $profile->created_by && $actor) {
            $profile->created_by = $actor->id;
        }

        $profile->save();

        return $profile->fresh(['owner']);
    }

    public function labels(): array
    {
        return [
            'commercial_offer' => [
                'starter' => 'Starter',
                'growth' => 'Growth',
                'enterprise' => 'Enterprise',
            ],
            'deployment_mode' => [
                'local' => 'Local',
                'pilot' => 'Pilote',
                'cloud' => 'Cloud',
                'hybrid' => 'Hybride',
            ],
            'lifecycle_stage' => [
                'setup' => 'Preparation',
                'pilot' => 'Pilote',
                'live' => 'Ouvert au client',
                'scale' => 'Passage a l echelle',
            ],
            'hosting_target' => [
                'on_prem' => 'Poste local / on-prem',
                'laravel_cloud' => 'Laravel Cloud',
                'managed_vm' => 'VM administree',
                'shared_hosting' => 'Hebergement mutualise',
            ],
            'support_tier' => [
                'essential' => 'Essentiel',
                'proactive' => 'Proactif',
                'mission_critical' => 'Mission critique',
            ],
            'monitoring_level' => [
                'basic' => 'Basique',
                'standard' => 'Standard',
                'proactive' => 'Proactif',
            ],
            'backup_strategy' => [
                'manual' => 'Manuelle',
                'daily' => 'Quotidienne',
                'verified' => 'Verifiee',
            ],
            'update_channel' => [
                'manual' => 'Manuel',
                'scheduled' => 'Planifie',
                'continuous' => 'Continu',
            ],
        ];
    }

    public function label(string $group, ?string $value): ?string
    {
        return $this->labels()[$group][$value] ?? $value;
    }

    public function optionValues(string $group): array
    {
        return $this->labels()[$group] ?? [];
    }

    private function defaultAttributes(Company $company): array
    {
        return [
            'commercial_offer' => 'growth',
            'deployment_mode' => 'local',
            'lifecycle_stage' => 'setup',
            'hosting_target' => 'on_prem',
            'support_tier' => 'essential',
            'monitoring_level' => 'basic',
            'backup_strategy' => 'manual',
            'update_channel' => 'manual',
            'target_users' => $company->users()->where('is_active', true)->count() ?: null,
            'target_branches' => $company->branches()->where('is_active', true)->count() ?: null,
        ];
    }
}
