<?php

namespace App\Modules\Purchases\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Purchases\Services\ReplenishmentService;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReplenishmentController extends Controller
{
    public function __construct(
        private readonly ReplenishmentService $replenishmentService,
        private readonly ActivityLogger $activityLogger,
    ) {
    }

    public function index(Request $request, CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();
        abort_if(! $companyId || ! $branchId, 403);

        $warehouses = Warehouse::query()
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $selectedWarehouse = $warehouses->firstWhere('id', $request->integer('warehouse_id')) ?: $warehouses->first();
        $suggestions = $selectedWarehouse
            ? $this->replenishmentService->suggestionsForWarehouse($companyId, $selectedWarehouse)
            : collect();

        return view('replenishments.index', [
            'warehouses' => $warehouses,
            'selectedWarehouse' => $selectedWarehouse,
            'suggestions' => $suggestions,
            'stats' => [
                'count' => $suggestions->count(),
                'quantity' => (float) $suggestions->sum('suggested_qty'),
                'estimated_total' => (float) $suggestions->sum('estimated_total'),
                'urgent_count' => $suggestions->where('priority', 'urgent')->count(),
            ],
        ]);
    }

    public function generate(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();
        abort_if(! $companyId || ! $branchId, 403);

        $data = $request->validate([
            'warehouse_id' => [
                'required',
                Rule::exists('warehouses', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('branch_id', $branchId)),
            ],
            'selected' => ['required', 'array', 'min:1'],
            'selected.*' => [
                'required',
                Rule::exists('products', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('auto_replenish', true)),
            ],
        ]);

        $warehouse = Warehouse::query()->where('company_id', $companyId)->findOrFail($data['warehouse_id']);
        $availableSuggestions = $this->replenishmentService->suggestionsForWarehouse($companyId, $warehouse)
            ->keyBy(fn (array $suggestion) => $suggestion['product']->id);

        $selectedSuggestions = collect($data['selected'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->map(fn (int $productId) => $availableSuggestions->get($productId))
            ->filter()
            ->values();

        if ($selectedSuggestions->isEmpty()) {
            return redirect()
                ->route('replenishments.index', ['warehouse_id' => $warehouse->id])
                ->with('error', 'Les suggestions selectionnees ne sont plus a reapprovisionner. Recharge la page et reessaie.');
        }

        $purchaseRequest = $this->replenishmentService->createPurchaseRequestFromSuggestions(
            companyId: $companyId,
            branchId: $branchId,
            warehouse: $warehouse,
            suggestions: $selectedSuggestions,
            user: $request->user(),
        );

        $this->activityLogger->log('replenishments.generate', 'Generation demande d achat depuis le reappro automatique', $purchaseRequest, [
            'request_number' => $purchaseRequest->request_number,
            'warehouse_id' => $warehouse->id,
            'product_ids' => $selectedSuggestions->map(fn (array $suggestion) => $suggestion['product']->id)->all(),
        ]);

        return redirect()
            ->route('purchase-requests.show', $purchaseRequest)
            ->with('success', 'Demande d achat generee depuis les suggestions de reappro.');
    }
}
