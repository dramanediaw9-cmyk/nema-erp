<?php

namespace App\Modules\Core\Imports\Odoo\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Imports\Odoo\Jobs\ProcessOdooProductImportBatch;
use App\Modules\Core\Imports\Odoo\Models\OdooConnection;
use App\Modules\Core\Imports\Odoo\Models\OdooProductImportRun;
use App\Modules\Core\Imports\Odoo\Services\OdooClientFactory;
use App\Modules\Core\Imports\Odoo\Services\OdooProductImportService;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class OdooProductImportController extends Controller
{
    public function __construct(
        private readonly OdooClientFactory $clients,
        private readonly OdooProductImportService $imports,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function index(Request $request, CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId || ! $workspace->branchId(), 403);

        $connections = OdooConnection::query()
            ->where('company_id', $companyId)
            ->with(['branch', 'runs' => fn ($query) => $query->withCount('errors')->limit(12)])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        $runs = OdooProductImportRun::query()
            ->where('company_id', $companyId)
            ->with(['connection', 'requester'])
            ->withCount('errors')
            ->latest('id')
            ->limit(30)
            ->get();

        $editingConnection = $request->boolean('new')
            ? null
            : ($request->integer('connection') > 0
                ? $connections->firstWhere('id', $request->integer('connection'))
                : $connections->first());

        return view('imports.odoo', compact('connections', 'runs', 'editingConnection'));
    }

    public function saveConnection(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();
        abort_if(! $companyId || ! $branchId, 403);

        $connection = $request->integer('connection_id') > 0
            ? OdooConnection::query()->where('company_id', $companyId)->findOrFail($request->integer('connection_id'))
            : new OdooConnection;

        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('odoo_connections', 'name')->where(fn ($query) => $query->where('company_id', $companyId))->ignore($connection->id),
            ],
            'protocol' => ['required', Rule::in(['jsonrpc', 'xmlrpc'])],
            'url' => ['required', 'url:http,https', 'max:500'],
            'database' => ['required', 'string', 'max:190'],
            'username' => ['required', 'string', 'max:190'],
            'secret' => [$connection->exists ? 'nullable' : 'required', 'string', 'max:1000'],
            'batch_size' => ['required', 'integer', 'min:10', 'max:'.(int) config('odoo.max_batch_size', 1000)],
            'stock_location_ids' => ['nullable', 'string', 'max:1000'],
            'verify_ssl' => ['nullable', 'boolean'],
            'import_images' => ['nullable', 'boolean'],
            'import_stock' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $locationIds = collect(preg_split('/[\s,;]+/', (string) ($data['stock_location_ids'] ?? ''), -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn (string $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $connection->fill([
            'tenant_id' => $workspace->tenantId(),
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'created_by' => $connection->created_by ?: $request->user()->id,
            'updated_by' => $request->user()->id,
            'name' => $data['name'],
            'protocol' => $data['protocol'],
            'url' => rtrim($data['url'], '/'),
            'database' => $data['database'],
            'username' => $data['username'],
            'batch_size' => (int) $data['batch_size'],
            'stock_location_ids' => $locationIds ?: null,
            'verify_ssl' => $request->boolean('verify_ssl'),
            'import_images' => $request->boolean('import_images'),
            'import_stock' => $request->boolean('import_stock'),
            'is_active' => $request->boolean('is_active'),
            'health_status' => 'untested',
        ]);
        if (filled($data['secret'] ?? null)) {
            $connection->secret = $data['secret'];
        }
        $connection->save();

        $this->activityLogger->log('imports.odoo.connection_saved', 'Connexion Odoo enregistree', $connection, [
            'protocol' => $connection->protocol,
            'url' => $connection->url,
            'database' => $connection->database,
        ]);

        return redirect()->route('imports.odoo.index')->with('success', 'Connexion Odoo enregistree. Lancez le test avant la synchronisation.');
    }

    public function testConnection(OdooConnection $connection, CurrentWorkspace $workspace): RedirectResponse
    {
        $this->guardConnection($connection, $workspace);

        try {
            $client = $this->clients->make($connection);
            $uid = $client->authenticate();
            $version = $client->version();
            $connection->forceFill([
                'health_status' => 'healthy',
                'last_error' => null,
                'last_tested_at' => now(),
            ])->save();

            return redirect()->route('imports.odoo.index')->with(
                'success',
                'Connexion Odoo valide (utilisateur #'.$uid.', version '.($version['server_version'] ?? 'detectee').').'
            );
        } catch (Throwable $exception) {
            $connection->forceFill([
                'health_status' => 'failed',
                'last_error' => $exception->getMessage(),
                'last_tested_at' => now(),
            ])->save();

            return redirect()->route('imports.odoo.index')->withErrors(['odoo' => 'Test Odoo echoue : '.$exception->getMessage()]);
        }
    }

    public function start(Request $request, OdooConnection $connection, CurrentWorkspace $workspace): RedirectResponse
    {
        $this->guardConnection($connection, $workspace);
        abort_unless($connection->is_active, 422, 'Cette connexion Odoo est inactive.');

        $data = $request->validate(['mode' => ['required', Rule::in(['full', 'incremental'])]]);
        $running = $connection->runs()->whereIn('status', ['queued', 'running'])->first();
        if ($running) {
            return redirect()->route('imports.odoo.index')->withErrors(['odoo' => 'Une synchronisation est deja en cours pour cette connexion.']);
        }

        $run = $this->imports->createRun($connection, $data['mode'], $request->user());
        ProcessOdooProductImportBatch::dispatch($run->id);
        $this->activityLogger->log('imports.odoo.started', 'Synchronisation produits Odoo lancee', $run, ['mode' => $run->mode]);

        return redirect()->route('imports.odoo.index')->with('success', 'Synchronisation Odoo lancee. La progression se met a jour automatiquement.');
    }

    public function status(OdooProductImportRun $run, CurrentWorkspace $workspace): JsonResponse
    {
        $this->guardRun($run, $workspace);
        $run->load(['connection'])->loadCount('errors');

        return response()->json($this->runPayload($run));
    }

    public function advance(OdooProductImportRun $run, CurrentWorkspace $workspace): JsonResponse
    {
        $this->guardRun($run, $workspace);
        $run->refresh();

        if ($this->needsBrowserFallback($run)) {
            try {
                ProcessOdooProductImportBatch::dispatchSync($run->id, false);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        $run->refresh()->load(['connection'])->loadCount('errors');

        return response()->json($this->runPayload($run));
    }

    public function cancel(OdooProductImportRun $run, CurrentWorkspace $workspace): RedirectResponse
    {
        $this->guardRun($run, $workspace);
        if (! in_array($run->status, ['completed', 'cancelled'], true)) {
            $run->forceFill(['status' => 'cancelled', 'finished_at' => now(), 'heartbeat_at' => now()])->save();
        }

        return redirect()->route('imports.odoo.index')->with('success', 'Synchronisation annulee. Les donnees deja importees sont conservees.');
    }

    public function resume(OdooProductImportRun $run, CurrentWorkspace $workspace): RedirectResponse
    {
        $this->guardRun($run, $workspace);
        abort_unless(in_array($run->status, ['failed', 'queued', 'running'], true), 422, 'Cette synchronisation ne peut pas etre reprise.');

        $run->forceFill(['status' => 'queued', 'last_error' => null, 'finished_at' => null])->save();
        ProcessOdooProductImportBatch::dispatch($run->id);

        return redirect()->route('imports.odoo.index')->with('success', 'Synchronisation reprise depuis le dernier curseur enregistre.');
    }

    private function guardConnection(OdooConnection $connection, CurrentWorkspace $workspace): void
    {
        abort_unless($workspace->companyId() === (int) $connection->company_id, 404);
    }

    private function guardRun(OdooProductImportRun $run, CurrentWorkspace $workspace): void
    {
        abort_unless($workspace->companyId() === (int) $run->company_id, 404);
    }

    private function runPayload(OdooProductImportRun $run): array
    {
        return [
            'uuid' => $run->uuid,
            'connection' => $run->connection?->name,
            'mode' => $run->mode,
            'status' => $run->status,
            'phase' => $run->phase,
            'progress' => $run->progressPercent(),
            'source_total' => $run->source_total,
            'processed_count' => $run->processed_count,
            'created_count' => $run->created_count,
            'updated_count' => $run->updated_count,
            'skipped_count' => $run->skipped_count,
            'failed_count' => $run->failed_count,
            'errors_count' => $run->errors_count,
            'last_error' => $run->last_error,
            'heartbeat_at' => $run->heartbeat_at?->toIso8601String(),
            'finished_at' => $run->finished_at?->toIso8601String(),
            'worker_fallback' => $this->needsBrowserFallback($run),
        ];
    }

    private function needsBrowserFallback(OdooProductImportRun $run): bool
    {
        if (! in_array($run->status, ['queued', 'running'], true)) {
            return false;
        }

        $threshold = max(5, (int) config('odoo.browser_fallback_after', 5));

        return ! $run->heartbeat_at || $run->heartbeat_at->lte(now()->subSeconds($threshold));
    }
}
