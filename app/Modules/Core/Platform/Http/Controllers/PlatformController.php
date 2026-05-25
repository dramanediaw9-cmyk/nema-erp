<?php

namespace App\Modules\Core\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Integrations\Models\IntegrationConnection;
use App\Modules\Core\Integrations\Services\IntegrationSecretGovernanceService;
use App\Modules\Core\Platform\Services\DeploymentProfileService;
use App\Modules\Core\Platform\Services\PlatformCatalogService;
use App\Modules\Core\Platform\Services\PlatformOpenApiService;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlatformController extends Controller
{
    public function __construct(
        private readonly PlatformCatalogService $platformCatalogService,
        private readonly PlatformOpenApiService $platformOpenApiService,
        private readonly DeploymentProfileService $deploymentProfileService,
        private readonly IntegrationSecretGovernanceService $integrationSecretGovernanceService,
        private readonly ActivityLogger $activityLogger,
    )
    {
    }

    public function index(CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        return view('platform.index', [
            'catalog' => $this->platformCatalogService->forCompany($companyId),
            'branches' => Branch::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'owners' => User::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'secretOwners' => User::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'connectionTypeOptions' => $this->connectionTypeOptions(),
            'syncModeOptions' => $this->syncModeOptions(),
            'statusOptions' => $this->statusOptions(),
            'healthOptions' => $this->healthOptions(),
            'authenticationModeOptions' => $this->integrationSecretGovernanceService->authenticationModes(),
            'secretHealthOptions' => $this->integrationSecretGovernanceService->secretHealthOptions(),
            'deploymentOfferOptions' => $this->deploymentProfileService->optionValues('commercial_offer'),
            'deploymentModeOptions' => $this->deploymentProfileService->optionValues('deployment_mode'),
            'lifecycleStageOptions' => $this->deploymentProfileService->optionValues('lifecycle_stage'),
            'hostingTargetOptions' => $this->deploymentProfileService->optionValues('hosting_target'),
            'supportTierOptions' => $this->deploymentProfileService->optionValues('support_tier'),
            'monitoringLevelOptions' => $this->deploymentProfileService->optionValues('monitoring_level'),
            'backupStrategyOptions' => $this->deploymentProfileService->optionValues('backup_strategy'),
            'updateChannelOptions' => $this->deploymentProfileService->optionValues('update_channel'),
        ]);
    }

    public function openApiSpec(CurrentWorkspace $workspace): JsonResponse
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        return response()
            ->json($this->platformOpenApiService->spec($companyId))
            ->withHeaders([
                'Content-Disposition' => 'inline; filename="nema-platform-openapi.json"',
            ]);
    }

    public function storeConnection(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $payload = $request->validate([
            'code' => ['nullable', 'string', 'max:40', Rule::unique('integration_connections', 'code')->where(fn ($query) => $query->where('company_id', $companyId))],
            'name' => ['required', 'string', 'max:255'],
            'partner_name' => ['required', 'string', 'max:255'],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'owner_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'connection_type' => ['required', Rule::in(array_keys($this->connectionTypeOptions()))],
            'sync_mode' => ['required', Rule::in(array_keys($this->syncModeOptions()))],
            'status' => ['required', Rule::in(array_keys($this->statusOptions()))],
            'health_status' => ['required', Rule::in(array_keys($this->healthOptions()))],
            'external_reference' => ['nullable', 'string', 'max:255'],
            'last_sync_at' => ['nullable', 'date'],
            'last_health_at' => ['nullable', 'date'],
            'scope_summary' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'authentication_mode' => ['nullable', Rule::in(array_keys($this->integrationSecretGovernanceService->authenticationModes()))],
            'secret_health_status' => ['nullable', Rule::in(array_keys($this->integrationSecretGovernanceService->secretHealthOptions()))],
            'secret_owner_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'secret_last_rotated_at' => ['nullable', 'date'],
            'secret_rotation_due_at' => ['nullable', 'date'],
            'secret_expires_at' => ['nullable', 'date'],
            'secret_notes' => ['nullable', 'string'],
        ]);

        $connection = IntegrationConnection::query()->create([
            'tenant_id' => $workspace->tenantId(),
            'company_id' => $companyId,
            'branch_id' => $payload['branch_id'] ?? null,
            'owner_id' => $payload['owner_id'] ?? null,
            'code' => ($payload['code'] ?? null) ?: $this->generateConnectionCode($companyId),
            'name' => $payload['name'],
            'partner_name' => $payload['partner_name'],
            'connection_type' => $payload['connection_type'],
            'sync_mode' => $payload['sync_mode'],
            'status' => $payload['status'],
            'health_status' => $payload['health_status'],
            'authentication_mode' => $payload['authentication_mode'] ?? 'api_key',
            'secret_health_status' => $payload['secret_health_status'] ?? 'watch',
            'secret_owner_id' => $payload['secret_owner_id'] ?? null,
            'external_reference' => $payload['external_reference'] ?? null,
            'last_sync_at' => $payload['last_sync_at'] ?? null,
            'last_health_at' => $payload['last_health_at'] ?? null,
            'secret_last_rotated_at' => $payload['secret_last_rotated_at'] ?? null,
            'secret_rotation_due_at' => $payload['secret_rotation_due_at'] ?? null,
            'secret_expires_at' => $payload['secret_expires_at'] ?? null,
            'scope_summary' => $payload['scope_summary'] ?? null,
            'secret_notes' => $payload['secret_notes'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        $this->activityLogger->log('platform.connections.create', 'Creation connexion partenaire', $connection, [
            'code' => $connection->code,
            'partner_name' => $connection->partner_name,
            'connection_type' => $connection->connection_type,
        ]);

        return redirect()->route('platform.index')->with('success', 'Connexion partenaire enregistree avec succes.');
    }

    public function updateDeploymentProfile(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $company = $workspace->company();
        abort_if(! $company, 403);

        $payload = $request->validate([
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

        $profile = $this->deploymentProfileService->upsertForCompany($company, $payload, $request->user());

        $this->activityLogger->log('platform.deployment_profile.update', 'Mise a jour profil de deploiement', $profile, [
            'commercial_offer' => $profile->commercial_offer,
            'deployment_mode' => $profile->deployment_mode,
            'lifecycle_stage' => $profile->lifecycle_stage,
            'support_tier' => $profile->support_tier,
        ]);

        return redirect()->route('platform.index')->with('success', 'Profil de deploiement mis a jour.');
    }

    public function updateConnectionStatus(Request $request, CurrentWorkspace $workspace, IntegrationConnection $integrationConnection): RedirectResponse
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId || $integrationConnection->company_id !== $companyId, 403);

        $payload = $request->validate([
            'status' => ['required', Rule::in(array_keys($this->statusOptions()))],
            'health_status' => ['required', Rule::in(array_keys($this->healthOptions()))],
        ]);

        $integrationConnection->update([
            'status' => $payload['status'],
            'health_status' => $payload['health_status'],
            'last_health_at' => now(),
            'updated_by' => $request->user()?->id,
        ]);

        $this->activityLogger->log('platform.connections.status', 'Mise a jour sante connexion partenaire', $integrationConnection, [
            'status' => $integrationConnection->status,
            'health_status' => $integrationConnection->health_status,
        ]);

        return redirect()->route('platform.index')->with('success', 'Statut connexion mis a jour.');
    }

    public function updateConnectionSecretGovernance(Request $request, CurrentWorkspace $workspace, IntegrationConnection $integrationConnection): RedirectResponse
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId || $integrationConnection->company_id !== $companyId, 403);

        $payload = $request->validate([
            'authentication_mode' => ['required', Rule::in(array_keys($this->integrationSecretGovernanceService->authenticationModes()))],
            'secret_health_status' => ['required', Rule::in(array_keys($this->integrationSecretGovernanceService->secretHealthOptions()))],
            'secret_owner_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'secret_last_rotated_at' => ['nullable', 'date'],
            'secret_rotation_due_at' => ['nullable', 'date'],
            'secret_expires_at' => ['nullable', 'date'],
            'secret_notes' => ['nullable', 'string'],
        ]);

        $connection = $this->integrationSecretGovernanceService->applyToConnection($integrationConnection, $payload, $request->user());

        $this->activityLogger->log('platform.connections.secret_governance', 'Mise a jour gouvernance secrets connecteur', $connection, [
            'authentication_mode' => $connection->authentication_mode,
            'secret_health_status' => $connection->secret_health_status,
        ]);

        return redirect()->route('platform.index')->with('success', 'Gouvernance des secrets mise a jour.');
    }

    private function connectionTypeOptions(): array
    {
        return [
            'api' => 'API',
            'webhook' => 'Webhook',
            'payment_gateway' => 'Paiement',
            'marketplace' => 'Marketplace',
            'bi' => 'BI / Reporting',
            'logistics' => 'Logistique',
        ];
    }

    private function syncModeOptions(): array
    {
        return [
            'inbound' => 'Entrant',
            'outbound' => 'Sortant',
            'bidirectional' => 'Bidirectionnel',
        ];
    }

    private function statusOptions(): array
    {
        return [
            'draft' => 'Preparation',
            'active' => 'Actif',
            'paused' => 'En pause',
            'deprecated' => 'A remplacer',
        ];
    }

    private function healthOptions(): array
    {
        return [
            'healthy' => 'Sain',
            'watch' => 'A surveiller',
            'critical' => 'Critique',
        ];
    }

    private function generateConnectionCode(int $companyId): string
    {
        return app(\App\Modules\Core\Company\Services\DocumentNumberService::class)
            ->nextNumber($companyId, 'integration_connection_code');
    }
}
