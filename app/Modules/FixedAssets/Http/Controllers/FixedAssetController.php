<?php

namespace App\Modules\FixedAssets\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Services\DocumentNumberService;
use App\Modules\FixedAssets\Models\FixedAsset;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FixedAssetController extends Controller
{
    public function __construct(
        private readonly DocumentNumberService $documentNumberService,
        private readonly ActivityLogger $activityLogger,
    ) {
    }

    public function index(CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $assets = FixedAsset::query()
            ->with(['branch'])
            ->where('company_id', $companyId)
            ->orderByDesc('acquisition_date')
            ->orderByDesc('id')
            ->paginate(15);

        $allAssets = FixedAsset::query()->where('company_id', $companyId)->get();

        return view('fixed-assets.index', [
            'assets' => $assets,
            'summary' => [
                'asset_count' => $allAssets->count(),
                'active_count' => $allAssets->where('status', 'active')->count(),
                'acquisition_total' => (float) $allAssets->sum(fn (FixedAsset $asset) => (float) $asset->acquisition_cost),
                'net_book_total' => (float) $allAssets->sum(fn (FixedAsset $asset) => $this->assetMetrics($asset)['net_book_value']),
            ],
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function create(CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        return view('fixed-assets.create', [
            'branches' => Branch::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'statusOptions' => $this->statusOptions(),
            'methodOptions' => $this->methodOptions(),
        ]);
    }

    public function store(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();
        abort_if(! $companyId, 403);

        $payload = $request->validate([
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'acquisition_date' => ['required', 'date'],
            'commissioning_date' => ['nullable', 'date', 'after_or_equal:acquisition_date'],
            'depreciation_start_date' => ['required', 'date'],
            'depreciation_method' => ['required', Rule::in(array_keys($this->methodOptions()))],
            'useful_life_months' => ['required', 'integer', 'min:1', 'max:600'],
            'acquisition_cost' => ['required', 'numeric', 'min:0'],
            'salvage_value' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(array_keys($this->statusOptions()))],
            'notes' => ['nullable', 'string'],
        ]);

        $salvageValue = (float) ($payload['salvage_value'] ?? 0);
        if ($salvageValue > (float) $payload['acquisition_cost']) {
            return back()->withInput()->withErrors(['salvage_value' => 'La valeur residuelle ne peut pas depasser le cout d acquisition.']);
        }

        $asset = FixedAsset::query()->create([
            'company_id' => $companyId,
            'branch_id' => $payload['branch_id'] ?? $branchId,
            'asset_number' => $this->documentNumberService->nextNumber(
                companyId: $companyId,
                documentType: 'fixed_asset',
                branchId: $payload['branch_id'] ?? $branchId,
                date: $payload['acquisition_date'],
            ),
            'name' => $payload['name'],
            'category' => $payload['category'] ?? null,
            'acquisition_date' => $payload['acquisition_date'],
            'commissioning_date' => $payload['commissioning_date'] ?? null,
            'depreciation_start_date' => $payload['depreciation_start_date'],
            'depreciation_method' => $payload['depreciation_method'],
            'useful_life_months' => $payload['useful_life_months'],
            'acquisition_cost' => $payload['acquisition_cost'],
            'salvage_value' => $salvageValue,
            'status' => $payload['status'],
            'notes' => $payload['notes'] ?? null,
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        $this->activityLogger->log('fixed_assets.create', 'Creation immobilisation', $asset, [
            'asset_number' => $asset->asset_number,
            'status' => $asset->status,
            'acquisition_cost' => $asset->acquisition_cost,
            'useful_life_months' => $asset->useful_life_months,
        ]);

        return redirect()->route('fixed-assets.show', $asset)->with('success', 'Immobilisation enregistree avec succes.');
    }

    public function show(FixedAsset $fixedAsset, CurrentWorkspace $workspace): View
    {
        abort_if($workspace->companyId() !== $fixedAsset->company_id, 403);

        $fixedAsset->load(['branch', 'creator', 'updater']);

        return view('fixed-assets.show', [
            'asset' => $fixedAsset,
            'metrics' => $this->assetMetrics($fixedAsset),
            'schedule' => $this->depreciationSchedule($fixedAsset),
            'statusOptions' => $this->statusOptions(),
            'methodOptions' => $this->methodOptions(),
        ]);
    }

    private function assetMetrics(FixedAsset $asset): array
    {
        $depreciableBase = max((float) $asset->acquisition_cost - (float) $asset->salvage_value, 0);
        $monthlyDepreciation = $asset->useful_life_months > 0
            ? round($depreciableBase / $asset->useful_life_months, 2)
            : 0.0;

        $elapsedMonths = 0;
        if ($asset->status !== 'draft' && $asset->depreciation_start_date) {
            $start = $asset->depreciation_start_date->copy()->startOfMonth();
            $today = now()->startOfMonth();
            if ($start->lessThanOrEqualTo($today)) {
                $elapsedMonths = min($asset->useful_life_months, $start->diffInMonths($today) + 1);
            }
        }

        $accumulatedDepreciation = min($depreciableBase, round($monthlyDepreciation * $elapsedMonths, 2));
        $netBookValue = max((float) $asset->acquisition_cost - $accumulatedDepreciation, (float) $asset->salvage_value);

        return [
            'depreciable_base' => $depreciableBase,
            'monthly_depreciation' => $monthlyDepreciation,
            'elapsed_months' => $elapsedMonths,
            'accumulated_depreciation' => $accumulatedDepreciation,
            'net_book_value' => $netBookValue,
        ];
    }

    private function depreciationSchedule(FixedAsset $asset): Collection
    {
        $metrics = $this->assetMetrics($asset);
        $schedule = collect();
        $currentValue = (float) $asset->acquisition_cost;
        $base = (float) $metrics['depreciable_base'];
        $monthly = (float) $metrics['monthly_depreciation'];
        $start = $asset->depreciation_start_date->copy()->startOfMonth();
        $accumulated = 0.0;

        for ($month = 1; $month <= $asset->useful_life_months; $month++) {
            $periodDate = $start->copy()->addMonths($month - 1);
            $remainingBase = max($base - $accumulated, 0);
            $depreciation = $month === $asset->useful_life_months ? $remainingBase : min($monthly, $remainingBase);
            $openingValue = $currentValue;
            $accumulated = round($accumulated + $depreciation, 2);
            $currentValue = round(max((float) $asset->acquisition_cost - $accumulated, (float) $asset->salvage_value), 2);

            $schedule->push([
                'period' => $periodDate,
                'opening_value' => $openingValue,
                'depreciation' => $depreciation,
                'closing_value' => $currentValue,
                'is_posted_month' => $month <= $metrics['elapsed_months'],
            ]);
        }

        return $schedule;
    }

    private function statusOptions(): array
    {
        return [
            'draft' => 'Brouillon',
            'active' => 'En service',
            'disposed' => 'Sortie du patrimoine',
        ];
    }

    private function methodOptions(): array
    {
        return [
            'linear' => 'Lineaire',
        ];
    }
}
