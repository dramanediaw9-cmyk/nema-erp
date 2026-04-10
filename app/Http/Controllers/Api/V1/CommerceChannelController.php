<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesApiActor;
use App\Modules\Commerce\Models\CommerceChannel;
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
            ->with('branch')
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

        return response()->json($channels);
    }

    public function show(Request $request, CommerceChannel $commerceChannel): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        abort_unless($commerceChannel->company_id === $company->id, 404);

        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('commerce.view'), 403);

        return response()->json($commerceChannel->load(['branch', 'creator']));
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

    private function generateCode(int $companyId): string
    {
        $number = CommerceChannel::query()->where('company_id', $companyId)->count() + 1;

        return 'CH-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }
}
