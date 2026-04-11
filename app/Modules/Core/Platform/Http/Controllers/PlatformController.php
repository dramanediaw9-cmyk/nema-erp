<?php

namespace App\Modules\Core\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Integrations\Models\IntegrationConnection;
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
            'connectionTypeOptions' => $this->connectionTypeOptions(),
            'syncModeOptions' => $this->syncModeOptions(),
            'statusOptions' => $this->statusOptions(),
            'healthOptions' => $this->healthOptions(),
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
            'external_reference' => $payload['external_reference'] ?? null,
            'last_sync_at' => $payload['last_sync_at'] ?? null,
            'last_health_at' => $payload['last_health_at'] ?? null,
            'scope_summary' => $payload['scope_summary'] ?? null,
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
        $sequence = IntegrationConnection::query()->where('company_id', $companyId)->count() + 1;

        return 'INT-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
