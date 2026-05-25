<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesApiActor;
use App\Modules\Core\Integrations\Models\IntegrationConnection;
use App\Modules\Core\Integrations\Services\IntegrationSecretGovernanceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlatformConnectionController
{
    use ResolvesApiActor;

    public function __construct(
        private readonly IntegrationSecretGovernanceService $integrationSecretGovernanceService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('platform.view'), 403);

        $status = $request->string('status')->trim()->value();
        $health = $request->string('health_status')->trim()->value();
        $type = $request->string('connection_type')->trim()->value();
        $search = $request->string('search')->trim()->value();

        $connections = IntegrationConnection::query()
            ->with(['branch', 'owner', 'secretOwner'])
            ->where('company_id', $company->id)
            ->when(in_array($status, ['draft', 'active', 'paused', 'deprecated'], true), fn (Builder $query) => $query->where('status', $status))
            ->when(in_array($health, ['healthy', 'watch', 'critical'], true), fn (Builder $query) => $query->where('health_status', $health))
            ->when(in_array($type, ['api', 'webhook', 'payment_gateway', 'marketplace', 'bi', 'logistics'], true), fn (Builder $query) => $query->where('connection_type', $type))
            ->when($search !== '', function (Builder $query) use ($search) {
                $like = '%'.$search.'%';

                $query->where(function (Builder $nested) use ($like) {
                    $nested->where('code', 'like', $like)
                        ->orWhere('name', 'like', $like)
                        ->orWhere('partner_name', 'like', $like)
                        ->orWhere('scope_summary', 'like', $like)
                        ->orWhere('external_reference', 'like', $like);
                });
            })
            ->orderBy('partner_name')
            ->orderBy('name')
            ->paginate(min(max((int) $request->integer('per_page', 50), 1), 200));

        return response()->json($connections);
    }

    public function show(Request $request, IntegrationConnection $integrationConnection): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        abort_unless($integrationConnection->company_id === $company->id, 404);

        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('platform.view'), 403);

        return response()->json($integrationConnection->load(['branch', 'owner', 'creator', 'secretOwner']));
    }

    public function store(Request $request): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('settings.integrations.manage'), 403);

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:40', Rule::unique('integration_connections', 'code')->where(fn ($query) => $query->where('company_id', $company->id))],
            'name' => ['required', 'string', 'max:255'],
            'partner_name' => ['required', 'string', 'max:255'],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where(fn ($query) => $query->where('company_id', $company->id))],
            'owner_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->where('company_id', $company->id))],
            'connection_type' => ['required', Rule::in(['api', 'webhook', 'payment_gateway', 'marketplace', 'bi', 'logistics'])],
            'authentication_mode' => ['nullable', Rule::in(array_keys($this->integrationSecretGovernanceService->authenticationModes()))],
            'sync_mode' => ['required', Rule::in(['inbound', 'outbound', 'bidirectional'])],
            'status' => ['required', Rule::in(['draft', 'active', 'paused', 'deprecated'])],
            'health_status' => ['required', Rule::in(['healthy', 'watch', 'critical'])],
            'secret_health_status' => ['nullable', Rule::in(array_keys($this->integrationSecretGovernanceService->secretHealthOptions()))],
            'external_reference' => ['nullable', 'string', 'max:255'],
            'secret_owner_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->where('company_id', $company->id))],
            'last_sync_at' => ['nullable', 'date'],
            'last_health_at' => ['nullable', 'date'],
            'secret_last_rotated_at' => ['nullable', 'date'],
            'secret_rotation_due_at' => ['nullable', 'date'],
            'secret_expires_at' => ['nullable', 'date'],
            'scope_summary' => ['nullable', 'string'],
            'secret_notes' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $connection = IntegrationConnection::query()->create([
            'tenant_id' => $request->attributes->get('apiTenantId'),
            'company_id' => $company->id,
            'branch_id' => $data['branch_id'] ?? null,
            'owner_id' => $data['owner_id'] ?? null,
            'code' => ($data['code'] ?? null) ?: $this->generateCode($company->id),
            'name' => $data['name'],
            'partner_name' => $data['partner_name'],
            'connection_type' => $data['connection_type'],
            'authentication_mode' => $data['authentication_mode'] ?? 'api_key',
            'sync_mode' => $data['sync_mode'],
            'status' => $data['status'],
            'health_status' => $data['health_status'],
            'secret_health_status' => $data['secret_health_status'] ?? 'watch',
            'external_reference' => $data['external_reference'] ?? null,
            'secret_owner_id' => $data['secret_owner_id'] ?? null,
            'last_sync_at' => $data['last_sync_at'] ?? null,
            'last_health_at' => $data['last_health_at'] ?? null,
            'secret_last_rotated_at' => $data['secret_last_rotated_at'] ?? null,
            'secret_rotation_due_at' => $data['secret_rotation_due_at'] ?? null,
            'secret_expires_at' => $data['secret_expires_at'] ?? null,
            'scope_summary' => $data['scope_summary'] ?? null,
            'secret_notes' => $data['secret_notes'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        return response()->json($connection->load(['branch', 'owner', 'secretOwner']), 201);
    }

    public function updateStatus(Request $request, IntegrationConnection $integrationConnection): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        abort_unless($integrationConnection->company_id === $company->id, 404);

        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('settings.integrations.manage'), 403);

        $data = $request->validate([
            'status' => ['required', Rule::in(['draft', 'active', 'paused', 'deprecated'])],
            'health_status' => ['required', Rule::in(['healthy', 'watch', 'critical'])],
        ]);

        $integrationConnection->update([
            'status' => $data['status'],
            'health_status' => $data['health_status'],
            'last_health_at' => now(),
            'updated_by' => $actor->id,
        ]);

        return response()->json($integrationConnection->fresh(['branch', 'owner', 'secretOwner']));
    }

    public function updateSecrets(Request $request, IntegrationConnection $integrationConnection): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        abort_unless($integrationConnection->company_id === $company->id, 404);

        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('settings.integrations.manage'), 403);

        $data = $request->validate([
            'authentication_mode' => ['required', Rule::in(array_keys($this->integrationSecretGovernanceService->authenticationModes()))],
            'secret_health_status' => ['required', Rule::in(array_keys($this->integrationSecretGovernanceService->secretHealthOptions()))],
            'secret_owner_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->where('company_id', $company->id))],
            'secret_last_rotated_at' => ['nullable', 'date'],
            'secret_rotation_due_at' => ['nullable', 'date'],
            'secret_expires_at' => ['nullable', 'date'],
            'secret_notes' => ['nullable', 'string'],
        ]);

        $connection = $this->integrationSecretGovernanceService->applyToConnection($integrationConnection, $data, $actor);

        return response()->json($connection);
    }

    private function generateCode(int $companyId): string
    {
        return app(\App\Modules\Core\Company\Services\DocumentNumberService::class)
            ->nextNumber($companyId, 'integration_connection_code');
    }
}
