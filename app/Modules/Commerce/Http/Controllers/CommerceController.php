<?php

namespace App\Modules\Commerce\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Models\CommerceChannel;
use App\Modules\Core\Branch\Models\Branch;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
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

        return view('commerce.index', [
            'branches' => Branch::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'channels' => CommerceChannel::query()
                ->with('branch')
                ->where('company_id', $companyId)
                ->orderBy('name')
                ->get(),
            'summary' => [
                'channels' => (int) CommerceChannel::query()->where('company_id', $companyId)->count(),
                'active' => (int) CommerceChannel::query()->where('company_id', $companyId)->where('status', 'active')->count(),
                'digital' => (int) CommerceChannel::query()->where('company_id', $companyId)->whereIn('channel_type', ['web', 'marketplace', 'mobile'])->count(),
                'target_revenue' => (float) CommerceChannel::query()->where('company_id', $companyId)->sum('target_monthly_revenue'),
            ],
            'typeOptions' => $this->typeOptions(),
            'statusOptions' => $this->statusOptions(),
            'settlementOptions' => $this->settlementOptions(),
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

    private function generateChannelCode(int $companyId): string
    {
        $sequence = CommerceChannel::query()->where('company_id', $companyId)->count() + 1;

        return 'CH-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
