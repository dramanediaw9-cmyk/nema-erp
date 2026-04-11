<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesApiActor;
use App\Modules\Core\Platform\Services\DeploymentProfileService;
use App\Modules\Core\Platform\Services\DeploymentReadinessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlatformDeploymentProfileController
{
    use ResolvesApiActor;

    public function __construct(
        private readonly DeploymentProfileService $deploymentProfileService,
        private readonly DeploymentReadinessService $deploymentReadinessService,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('platform.view'), 403);

        $profile = $this->deploymentProfileService->profileForCompany($company)->loadMissing('owner');
        $readiness = $this->deploymentReadinessService->summary($company, $profile);

        return response()->json([
            'profile' => $profile,
            'readiness' => $readiness,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('settings.manage'), 403);

        $data = $request->validate([
            'owner_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->where('company_id', $company->id))],
            'commercial_offer' => ['required', Rule::in(array_keys($this->deploymentProfileService->optionValues('commercial_offer')))],
            'deployment_mode' => ['required', Rule::in(array_keys($this->deploymentProfileService->optionValues('deployment_mode')))],
            'lifecycle_stage' => ['required', Rule::in(array_keys($this->deploymentProfileService->optionValues('lifecycle_stage')))],
            'hosting_target' => ['required', Rule::in(array_keys($this->deploymentProfileService->optionValues('hosting_target')))],
            'support_tier' => ['required', Rule::in(array_keys($this->deploymentProfileService->optionValues('support_tier')))],
            'monitoring_level' => ['required', Rule::in(array_keys($this->deploymentProfileService->optionValues('monitoring_level')))],
            'backup_strategy' => ['required', Rule::in(array_keys($this->deploymentProfileService->optionValues('backup_strategy')))],
            'update_channel' => ['required', Rule::in(array_keys($this->deploymentProfileService->optionValues('update_channel')))],
            'target_users' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'target_branches' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'go_live_target_at' => ['nullable', 'date'],
            'last_release_at' => ['nullable', 'date'],
            'last_backup_verified_at' => ['nullable', 'date'],
            'last_restore_drill_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $profile = $this->deploymentProfileService->upsertForCompany($company, $data, $actor)->loadMissing('owner');
        $readiness = $this->deploymentReadinessService->summary($company, $profile);

        return response()->json([
            'profile' => $profile,
            'readiness' => $readiness,
        ]);
    }
}
