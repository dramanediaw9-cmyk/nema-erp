<?php

namespace App\Modules\Commerce\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Commerce\Models\CommerceChannelAction;
use App\Modules\Commerce\Models\CommerceChannel;
use App\Modules\Commerce\Models\CommerceChannelSnapshot;
use App\Modules\Core\Branch\Models\Branch;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use Illuminate\Support\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CommerceController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger)
    {
    }

    public function index(CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $channels = CommerceChannel::query()
            ->with(['branch', 'latestSnapshot', 'actions.owner'])
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get()
            ->each(fn (CommerceChannel $channel) => $channel->setAttribute('execution_snapshot', $this->executionSnapshot($channel)));

        return view('commerce.index', [
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
            'channels' => $channels,
            'summary' => [
                'channels' => (int) CommerceChannel::query()->where('company_id', $companyId)->count(),
                'active' => (int) CommerceChannel::query()->where('company_id', $companyId)->where('status', 'active')->count(),
                'digital' => (int) CommerceChannel::query()->where('company_id', $companyId)->whereIn('channel_type', ['web', 'marketplace', 'mobile'])->count(),
                'target_revenue' => (float) CommerceChannel::query()->where('company_id', $companyId)->sum('target_monthly_revenue'),
                'current_revenue' => (float) CommerceChannelSnapshot::query()->where('company_id', $companyId)->whereDate('snapshot_date', '>=', now()->startOfMonth()->toDateString())->sum('gross_revenue'),
                'open_actions' => (int) CommerceChannelAction::query()->where('company_id', $companyId)->where('status', '!=', 'done')->count(),
                'at_risk' => $channels->filter(fn (CommerceChannel $channel) => ($channel->execution_snapshot['health'] ?? 'stable') === 'risk')->count(),
            ],
            'typeOptions' => $this->typeOptions(),
            'statusOptions' => $this->statusOptions(),
            'settlementOptions' => $this->settlementOptions(),
            'actionTypeOptions' => $this->actionTypeOptions(),
            'actionStatusOptions' => $this->actionStatusOptions(),
            'impactOptions' => $this->impactOptions(),
        ]);
    }

    public function store(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $payload = $request->validate([
            'code' => ['nullable', 'string', 'max:30', Rule::unique('commerce_channels', 'code')->where(fn ($query) => $query->where('company_id', $companyId))],
            'name' => ['required', 'string', 'max:255'],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'channel_type' => ['required', Rule::in(array_keys($this->typeOptions()))],
            'status' => ['required', Rule::in(array_keys($this->statusOptions()))],
            'connector_name' => ['nullable', 'string', 'max:255'],
            'settlement_mode' => ['required', Rule::in(array_keys($this->settlementOptions()))],
            'target_monthly_revenue' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $channel = CommerceChannel::query()->create([
            'company_id' => $companyId,
            'branch_id' => $payload['branch_id'] ?? null,
            'code' => ($payload['code'] ?? null) ?: $this->generateChannelCode($companyId),
            'name' => $payload['name'],
            'channel_type' => $payload['channel_type'],
            'status' => $payload['status'],
            'connector_name' => $payload['connector_name'] ?? null,
            'settlement_mode' => $payload['settlement_mode'],
            'target_monthly_revenue' => $payload['target_monthly_revenue'] ?? 0,
            'notes' => $payload['notes'] ?? null,
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        $this->activityLogger->log('commerce.channels.create', 'Creation canal commerce', $channel, [
            'code' => $channel->code,
            'type' => $channel->channel_type,
        ]);

        return redirect()->route('commerce.index')->with('success', 'Canal commerce enregistre avec succes.');
    }

    public function storeSnapshot(Request $request, CurrentWorkspace $workspace, CommerceChannel $commerceChannel): RedirectResponse
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId || $commerceChannel->company_id !== $companyId, 403);

        $payload = $request->validate([
            'snapshot_date' => ['required', 'date'],
            'gross_revenue' => ['nullable', 'numeric', 'min:0'],
            'orders_count' => ['nullable', 'integer', 'min:0'],
            'average_order_value' => ['nullable', 'numeric', 'min:0'],
            'conversion_rate' => ['nullable', 'numeric', 'between:0,100'],
            'service_level' => ['nullable', 'numeric', 'between:0,100'],
            'failed_orders_count' => ['nullable', 'integer', 'min:0'],
            'failed_payments_count' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $snapshotDate = date('Y-m-d', strtotime((string) $payload['snapshot_date']));
        $snapshot = CommerceChannelSnapshot::query()
            ->where('company_id', $companyId)
            ->where('commerce_channel_id', $commerceChannel->id)
            ->whereDate('snapshot_date', $snapshotDate)
            ->first();

        if ($snapshot) {
            $snapshot->update([
                'gross_revenue' => $payload['gross_revenue'] ?? 0,
                'orders_count' => $payload['orders_count'] ?? 0,
                'average_order_value' => $payload['average_order_value'] ?? 0,
                'conversion_rate' => $payload['conversion_rate'] ?? 0,
                'service_level' => $payload['service_level'] ?? 0,
                'failed_orders_count' => $payload['failed_orders_count'] ?? 0,
                'failed_payments_count' => $payload['failed_payments_count'] ?? 0,
                'notes' => $payload['notes'] ?? null,
                'updated_by' => $request->user()?->id,
            ]);
        } else {
            $snapshot = CommerceChannelSnapshot::query()->create([
                'company_id' => $companyId,
                'commerce_channel_id' => $commerceChannel->id,
                'snapshot_date' => $snapshotDate,
                'gross_revenue' => $payload['gross_revenue'] ?? 0,
                'orders_count' => $payload['orders_count'] ?? 0,
                'average_order_value' => $payload['average_order_value'] ?? 0,
                'conversion_rate' => $payload['conversion_rate'] ?? 0,
                'service_level' => $payload['service_level'] ?? 0,
                'failed_orders_count' => $payload['failed_orders_count'] ?? 0,
                'failed_payments_count' => $payload['failed_payments_count'] ?? 0,
                'notes' => $payload['notes'] ?? null,
                'created_by' => $request->user()?->id,
                'updated_by' => $request->user()?->id,
            ]);
        }

        $this->activityLogger->log('commerce.channels.snapshot', 'Mise a jour performance canal', $snapshot, [
            'channel_id' => $commerceChannel->id,
            'snapshot_date' => $snapshot->snapshot_date?->toDateString(),
            'gross_revenue' => $snapshot->gross_revenue,
        ]);

        return redirect()->route('commerce.index')->with('success', 'Snapshot commerce enregistre avec succes.');
    }

    public function storeAction(Request $request, CurrentWorkspace $workspace, CommerceChannel $commerceChannel): RedirectResponse
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId || $commerceChannel->company_id !== $companyId, 403);

        $payload = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'owner_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'action_type' => ['required', Rule::in(array_keys($this->actionTypeOptions()))],
            'status' => ['required', Rule::in(array_keys($this->actionStatusOptions()))],
            'impact_level' => ['required', Rule::in(array_keys($this->impactOptions()))],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $normalized = $this->normalizeActionPayload($payload);

        $action = $commerceChannel->actions()->create([
            'company_id' => $companyId,
            'owner_id' => $normalized['owner_id'],
            'title' => $normalized['title'],
            'action_type' => $normalized['action_type'],
            'status' => $normalized['status'],
            'impact_level' => $normalized['impact_level'],
            'due_date' => $normalized['due_date'],
            'completed_at' => $normalized['completed_at'],
            'notes' => $normalized['notes'],
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        $this->activityLogger->log('commerce.channels.action.create', 'Creation action canal commerce', $action, [
            'channel_id' => $commerceChannel->id,
            'action_type' => $action->action_type,
            'status' => $action->status,
        ]);

        return redirect()->route('commerce.index')->with('success', 'Action canal ajoutee avec succes.');
    }

    public function updateActionStatus(Request $request, CurrentWorkspace $workspace, CommerceChannelAction $commerceChannelAction): RedirectResponse
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId || $commerceChannelAction->company_id !== $companyId, 403);

        $payload = $request->validate([
            'status' => ['required', Rule::in(array_keys($this->actionStatusOptions()))],
        ]);

        $normalized = $this->normalizeActionPayload([
            'title' => $commerceChannelAction->title,
            'owner_id' => $commerceChannelAction->owner_id,
            'action_type' => $commerceChannelAction->action_type,
            'status' => $payload['status'],
            'impact_level' => $commerceChannelAction->impact_level,
            'due_date' => optional($commerceChannelAction->due_date)->toDateString(),
            'notes' => $commerceChannelAction->notes,
        ]);

        $commerceChannelAction->update([
            'status' => $normalized['status'],
            'completed_at' => $normalized['completed_at'],
            'updated_by' => $request->user()?->id,
        ]);

        $this->activityLogger->log('commerce.channels.action.status', 'Mise a jour action canal commerce', $commerceChannelAction, [
            'channel_id' => $commerceChannelAction->commerce_channel_id,
            'status' => $commerceChannelAction->status,
        ]);

        return redirect()->route('commerce.index')->with('success', 'Statut action canal mis a jour.');
    }

    private function typeOptions(): array
    {
        return [
            'b2b' => 'B2B',
            'retail' => 'Retail',
            'web' => 'Web',
            'marketplace' => 'Marketplace',
            'mobile' => 'Mobile / USSD',
        ];
    }

    private function statusOptions(): array
    {
        return [
            'pipeline' => 'En preparation',
            'active' => 'Actif',
            'paused' => 'En pause',
        ];
    }

    private function settlementOptions(): array
    {
        return [
            'cash' => 'Cash',
            'credit' => 'Credit',
            'mobile_money' => 'Mobile money',
            'mixed' => 'Mixte',
        ];
    }

    private function actionTypeOptions(): array
    {
        return [
            'campaign' => 'Campagne',
            'catalog' => 'Catalogue',
            'fulfillment' => 'Execution',
            'payment' => 'Paiement',
            'support' => 'Support',
        ];
    }

    private function actionStatusOptions(): array
    {
        return [
            'todo' => 'A lancer',
            'in_progress' => 'En cours',
            'blocked' => 'Bloque',
            'done' => 'Termine',
        ];
    }

    private function impactOptions(): array
    {
        return [
            'low' => 'Faible',
            'normal' => 'Normal',
            'high' => 'Fort',
            'critical' => 'Critique',
        ];
    }

    private function executionSnapshot(CommerceChannel $channel): array
    {
        $latestSnapshot = $channel->latestSnapshot;
        $actions = $channel->actions instanceof Collection ? $channel->actions : collect();
        $openActions = $actions->where('status', '!=', 'done');
        $overdueActions = $openActions->filter(fn (CommerceChannelAction $action) => $action->isOverdue());
        $criticalActions = $openActions->filter(fn (CommerceChannelAction $action) => $action->impact_level === 'critical');
        $targetRevenue = (float) $channel->target_monthly_revenue;
        $grossRevenue = (float) ($latestSnapshot?->gross_revenue ?? 0);
        $revenueRatio = $targetRevenue > 0 ? round(($grossRevenue / $targetRevenue) * 100, 1) : null;
        $health = 'stable';
        $healthLabel = 'Sous controle';

        if (
            $overdueActions->isNotEmpty()
            || ($latestSnapshot && ((float) $latestSnapshot->service_level < 90 || (int) $latestSnapshot->failed_payments_count > 3 || (int) $latestSnapshot->failed_orders_count > 5))
            || ($revenueRatio !== null && $revenueRatio < 70)
        ) {
            $health = 'risk';
            $healthLabel = 'Sous tension';
        } elseif (
            $criticalActions->isNotEmpty()
            || ($latestSnapshot && ((float) $latestSnapshot->service_level < 95 || (int) $latestSnapshot->failed_payments_count > 0 || (int) $latestSnapshot->failed_orders_count > 0))
            || ($revenueRatio !== null && $revenueRatio < 100)
        ) {
            $health = 'watch';
            $healthLabel = 'A surveiller';
        } elseif ($latestSnapshot && $grossRevenue > 0 && $openActions->isEmpty()) {
            $health = 'growth';
            $healthLabel = 'Canal en traction';
        }

        return [
            'health' => $health,
            'health_label' => $healthLabel,
            'gross_revenue' => $grossRevenue,
            'revenue_ratio' => $revenueRatio,
            'orders_count' => (int) ($latestSnapshot?->orders_count ?? 0),
            'average_order_value' => (float) ($latestSnapshot?->average_order_value ?? 0),
            'conversion_rate' => (float) ($latestSnapshot?->conversion_rate ?? 0),
            'service_level' => (float) ($latestSnapshot?->service_level ?? 0),
            'failed_orders_count' => (int) ($latestSnapshot?->failed_orders_count ?? 0),
            'failed_payments_count' => (int) ($latestSnapshot?->failed_payments_count ?? 0),
            'snapshot_date' => $latestSnapshot?->snapshot_date,
            'open_actions' => $openActions->count(),
            'overdue_actions' => $overdueActions->count(),
        ];
    }

    private function normalizeActionPayload(array $payload): array
    {
        $status = $payload['status'];

        return [
            'title' => trim((string) $payload['title']),
            'owner_id' => $payload['owner_id'] ?? null,
            'action_type' => $payload['action_type'],
            'status' => $status,
            'impact_level' => $payload['impact_level'],
            'due_date' => $payload['due_date'] ?? null,
            'completed_at' => $status === 'done' ? now() : null,
            'notes' => $payload['notes'] ?? null,
        ];
    }

    private function generateChannelCode(int $companyId): string
    {
        return app(\App\Modules\Core\Company\Services\DocumentNumberService::class)
            ->nextNumber($companyId, 'commerce_channel_code');
    }
}
