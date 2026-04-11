<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesApiActor;
use App\Modules\Commerce\Models\CommerceChannelAction;
use App\Modules\Commerce\Models\CommerceChannel;
use App\Modules\Commerce\Models\CommerceChannelSnapshot;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CommerceChannelController
{
    use ResolvesApiActor;

    public function index(Request $request): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('commerce.view'), 403);

        $status = $request->string('status')->trim()->value();
        $type = $request->string('channel_type')->trim()->value();
        $search = $request->string('search')->trim()->value();

        $channels = CommerceChannel::query()
            ->with(['branch', 'latestSnapshot', 'actions'])
            ->where('company_id', $company->id)
            ->when($request->integer('branch_id') > 0, fn (Builder $query) => $query->where('branch_id', $request->integer('branch_id')))
            ->when(in_array($status, ['pipeline', 'active', 'paused'], true), fn (Builder $query) => $query->where('status', $status))
            ->when(in_array($type, ['b2b', 'retail', 'web', 'marketplace', 'mobile'], true), fn (Builder $query) => $query->where('channel_type', $type))
            ->when($search !== '', function (Builder $query) use ($search) {
                $like = '%'.$search.'%';

                $query->where(function (Builder $nested) use ($like) {
                    $nested->where('code', 'like', $like)
                        ->orWhere('name', 'like', $like)
                        ->orWhere('connector_name', 'like', $like);
                });
            })
            ->orderBy('name')
            ->paginate(min(max((int) $request->integer('per_page', 50), 1), 200));

        $channels->getCollection()->transform(function (CommerceChannel $channel) {
            $channel->setAttribute('execution_summary', $this->executionSummary($channel));

            return $channel;
        });

        return response()->json($channels);
    }

    public function show(Request $request, CommerceChannel $commerceChannel): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        abort_unless($commerceChannel->company_id === $company->id, 404);

        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('commerce.view'), 403);

        return response()->json(
            $commerceChannel->load(['branch', 'creator', 'latestSnapshot', 'actions.owner'])
                ->setAttribute('execution_summary', $this->executionSummary($commerceChannel))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('commerce.manage'), 403);

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:30', Rule::unique('commerce_channels', 'code')->where(fn ($query) => $query->where('company_id', $company->id))],
            'name' => ['required', 'string', 'max:255'],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where(fn ($query) => $query->where('company_id', $company->id))],
            'channel_type' => ['nullable', Rule::in(['b2b', 'retail', 'web', 'marketplace', 'mobile'])],
            'status' => ['nullable', Rule::in(['pipeline', 'active', 'paused'])],
            'connector_name' => ['nullable', 'string', 'max:255'],
            'settlement_mode' => ['nullable', Rule::in(['cash', 'credit', 'mobile_money', 'mixed'])],
            'target_monthly_revenue' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $channel = CommerceChannel::query()->create([
            'company_id' => $company->id,
            'branch_id' => $data['branch_id'] ?? null,
            'code' => ($data['code'] ?? null) ?: $this->generateCode($company->id),
            'name' => $data['name'],
            'channel_type' => $data['channel_type'] ?? 'b2b',
            'status' => $data['status'] ?? 'pipeline',
            'connector_name' => $data['connector_name'] ?? null,
            'settlement_mode' => $data['settlement_mode'] ?? 'mixed',
            'target_monthly_revenue' => $data['target_monthly_revenue'] ?? 0,
            'notes' => $data['notes'] ?? null,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        return response()->json($channel->load('branch'), 201);
    }

    public function storeSnapshot(Request $request, CommerceChannel $commerceChannel): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        abort_unless($commerceChannel->company_id === $company->id, 404);

        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('commerce.manage'), 403);

        $data = $request->validate([
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

        $snapshotDate = date('Y-m-d', strtotime((string) $data['snapshot_date']));
        $snapshot = CommerceChannelSnapshot::query()
            ->where('company_id', $company->id)
            ->where('commerce_channel_id', $commerceChannel->id)
            ->whereDate('snapshot_date', $snapshotDate)
            ->first();

        if ($snapshot) {
            $snapshot->update([
                'gross_revenue' => $data['gross_revenue'] ?? 0,
                'orders_count' => $data['orders_count'] ?? 0,
                'average_order_value' => $data['average_order_value'] ?? 0,
                'conversion_rate' => $data['conversion_rate'] ?? 0,
                'service_level' => $data['service_level'] ?? 0,
                'failed_orders_count' => $data['failed_orders_count'] ?? 0,
                'failed_payments_count' => $data['failed_payments_count'] ?? 0,
                'notes' => $data['notes'] ?? null,
                'updated_by' => $actor->id,
            ]);
        } else {
            $snapshot = CommerceChannelSnapshot::query()->create([
                'company_id' => $company->id,
                'commerce_channel_id' => $commerceChannel->id,
                'snapshot_date' => $snapshotDate,
                'gross_revenue' => $data['gross_revenue'] ?? 0,
                'orders_count' => $data['orders_count'] ?? 0,
                'average_order_value' => $data['average_order_value'] ?? 0,
                'conversion_rate' => $data['conversion_rate'] ?? 0,
                'service_level' => $data['service_level'] ?? 0,
                'failed_orders_count' => $data['failed_orders_count'] ?? 0,
                'failed_payments_count' => $data['failed_payments_count'] ?? 0,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
        }

        return response()->json($snapshot->fresh(), 201);
    }

    public function storeAction(Request $request, CommerceChannel $commerceChannel): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        abort_unless($commerceChannel->company_id === $company->id, 404);

        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('commerce.manage'), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'owner_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->where('company_id', $company->id))],
            'action_type' => ['required', Rule::in(['campaign', 'catalog', 'fulfillment', 'payment', 'support'])],
            'status' => ['nullable', Rule::in(['todo', 'in_progress', 'blocked', 'done'])],
            'impact_level' => ['nullable', Rule::in(['low', 'normal', 'high', 'critical'])],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $normalized = $this->normalizeActionPayload($data);

        $action = $commerceChannel->actions()->create([
            'company_id' => $company->id,
            'owner_id' => $normalized['owner_id'],
            'title' => $normalized['title'],
            'action_type' => $normalized['action_type'],
            'status' => $normalized['status'],
            'impact_level' => $normalized['impact_level'],
            'due_date' => $normalized['due_date'],
            'completed_at' => $normalized['completed_at'],
            'notes' => $normalized['notes'],
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        return response()->json($action->load(['owner', 'channel']), 201);
    }

    public function updateAction(Request $request, CommerceChannel $commerceChannel, CommerceChannelAction $commerceChannelAction): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        abort_unless(
            $commerceChannel->company_id === $company->id
            && $commerceChannelAction->company_id === $company->id
            && $commerceChannelAction->commerce_channel_id === $commerceChannel->id,
            404
        );

        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('commerce.manage'), 403);

        $data = $request->validate([
            'status' => ['required', Rule::in(['todo', 'in_progress', 'blocked', 'done'])],
        ]);

        $normalized = $this->normalizeActionPayload([
            'title' => $commerceChannelAction->title,
            'owner_id' => $commerceChannelAction->owner_id,
            'action_type' => $commerceChannelAction->action_type,
            'status' => $data['status'],
            'impact_level' => $commerceChannelAction->impact_level,
            'due_date' => optional($commerceChannelAction->due_date)->toDateString(),
            'notes' => $commerceChannelAction->notes,
        ]);

        $commerceChannelAction->update([
            'status' => $normalized['status'],
            'completed_at' => $normalized['completed_at'],
            'updated_by' => $actor->id,
        ]);

        return response()->json($commerceChannelAction->fresh(['owner', 'channel']));
    }

    private function executionSummary(CommerceChannel $channel): array
    {
        $latestSnapshot = $channel->latestSnapshot;
        $actions = $channel->actions instanceof Collection ? $channel->actions : collect();
        $openActions = $actions->where('status', '!=', 'done');
        $overdueActions = $openActions->filter(fn (CommerceChannelAction $action) => $action->isOverdue());
        $targetRevenue = (float) $channel->target_monthly_revenue;
        $grossRevenue = (float) ($latestSnapshot?->gross_revenue ?? 0);
        $revenueRatio = $targetRevenue > 0 ? round(($grossRevenue / $targetRevenue) * 100, 1) : null;

        return [
            'gross_revenue' => $grossRevenue,
            'orders_count' => (int) ($latestSnapshot?->orders_count ?? 0),
            'conversion_rate' => (float) ($latestSnapshot?->conversion_rate ?? 0),
            'service_level' => (float) ($latestSnapshot?->service_level ?? 0),
            'failed_orders_count' => (int) ($latestSnapshot?->failed_orders_count ?? 0),
            'failed_payments_count' => (int) ($latestSnapshot?->failed_payments_count ?? 0),
            'revenue_ratio' => $revenueRatio,
            'open_actions' => $openActions->count(),
            'overdue_actions' => $overdueActions->count(),
        ];
    }

    private function normalizeActionPayload(array $payload): array
    {
        $status = $payload['status'] ?? 'todo';

        return [
            'title' => trim((string) $payload['title']),
            'owner_id' => $payload['owner_id'] ?? null,
            'action_type' => $payload['action_type'],
            'status' => $status,
            'impact_level' => $payload['impact_level'] ?? 'normal',
            'due_date' => $payload['due_date'] ?? null,
            'completed_at' => $status === 'done' ? now() : null,
            'notes' => $payload['notes'] ?? null,
        ];
    }

    private function generateCode(int $companyId): string
    {
        $number = CommerceChannel::query()->where('company_id', $companyId)->count() + 1;

        return 'CH-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }
}
