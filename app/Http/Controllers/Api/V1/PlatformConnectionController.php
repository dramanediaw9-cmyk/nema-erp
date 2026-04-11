<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesApiActor;
use App\Modules\Core\Integrations\Models\IntegrationConnection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlatformConnectionController
{
    use ResolvesApiActor;

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
            ->with(['branch', 'owner'])
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

        return response()->json($integrationConnection->load(['branch', 'owner', 'creator']));
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
            'sync_mode' => ['required', Rule::in(['inbound', 'outbound', 'bidirectional'])],
            'status' => ['required', Rule::in(['draft', 'active', 'paused', 'deprecated'])],
            'health_status' => ['required', Rule::in(['healthy', 'watch', 'critical'])],
            'external_reference' => ['nullable', 'string', 'max:255'],
            'last_sync_at' => ['nullable', 'date'],
            'last_health_at' => ['nullable', 'date'],
            'scope_summary' => ['nullable', 'string'],
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
            'sync_mode' => $data['sync_mode'],
            'status' => $data['status'],
            'health_status' => $data['health_status'],
            'external_reference' => $data['external_reference'] ?? null,
            'last_sync_at' => $data['last_sync_at'] ?? null,
            'last_health_at' => $data['last_health_at'] ?? null,
            'scope_summary' => $data['scope_summary'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        return response()->json($connection->load(['branch', 'owner']), 201);
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

        return response()->json($integrationConnection->fresh(['branch', 'owner']));
    }

    private function generateCode(int $companyId): string
    {
        $number = IntegrationConnection::query()->where('company_id', $companyId)->count() + 1;

        return 'INT-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }
}
