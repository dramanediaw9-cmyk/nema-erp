<?php

namespace App\Modules\Purchases\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Purchases\Services\ReplenishmentService;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use App\Support\Exports\CsvExportService;
use Illuminate\Support\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReplenishmentController extends Controller
{
    public function __construct(
        private readonly ReplenishmentService $replenishmentService,
        private readonly ActivityLogger $activityLogger,
        private readonly CsvExportService $csvExportService,
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
            'unconfiguredCriticalProducts' => $selectedWarehouse
                ? $this->replenishmentService->criticalProductsMissingRules($companyId, $selectedWarehouse)
                : collect(),
            'stats' => [
                'count' => $suggestions->count(),
                'quantity' => (float) $suggestions->sum('suggested_qty'),
                'estimated_total' => (float) $suggestions->sum('estimated_total'),
                'urgent_count' => $suggestions->where('priority', 'urgent')->count(),
            ],
        ]);
    }

    public function print(Request $request, CurrentWorkspace $workspace): View
    {
        [$selectedWarehouse, $suggestions, $stats] = $this->suggestionContext($request, $workspace);

        return view('replenishments.print', [
            'company' => $workspace->company(),
            'branch' => $workspace->branch(),
            'selectedWarehouse' => $selectedWarehouse,
            'suggestions' => $suggestions,
            'stats' => $stats,
        ]);
    }

    public function export(Request $request, CurrentWorkspace $workspace): StreamedResponse
    {
        [$selectedWarehouse, $suggestions] = $this->suggestionContext($request, $workspace);

        $rows = $suggestions->map(function (array $suggestion) {
            $product = $suggestion['product'];

            return [
                $product->display_name,
                $product->sku,
                $product->barcode,
                $suggestion['supplier_name'] ?: '',
                $suggestion['supplier_product_code'] ?: '',
                $suggestion['priority'] === 'urgent' ? 'Urgent' : ($suggestion['priority'] === 'high' ? 'Haute' : 'Normale'),
                number_format((float) $suggestion['current_stock'], 3, '.', ''),
                number_format((float) $suggestion['incoming_qty'], 3, '.', ''),
                number_format((float) $suggestion['open_request_qty'], 3, '.', ''),
                number_format((float) $suggestion['projected_stock'], 3, '.', ''),
                number_format((float) $suggestion['min_stock'], 3, '.', ''),
                number_format((float) $suggestion['target_stock'], 3, '.', ''),
                number_format((float) $suggestion['suggested_qty'], 3, '.', ''),
                number_format((float) $suggestion['estimated_unit_cost'], 2, '.', ''),
                number_format((float) $suggestion['estimated_total'], 2, '.', ''),
                (string) $suggestion['purchase_lead_time_days'],
            ];
        });

        $warehouseSlug = str($selectedWarehouse?->name ?: 'depot')->slug()->toString();

        return $this->csvExportService->download('reappro-'.$warehouseSlug.'-'.now()->format('Ymd').'.csv', [
            'Produit',
            'Reference',
            'Code-barres',
            'Fournisseur',
            'Reference fournisseur',
            'Priorite',
            'Stock reel',
            'Commandes en cours',
            'Demandes ouvertes',
            'Stock projete',
            'Minimum',
            'Cible',
            'Quantite proposee',
            'Cout unitaire estime',
            'Valeur estimee',
            'Delai achat jours',
        ], $rows);
    }

    public function activateProducts(Request $request, CurrentWorkspace $workspace): RedirectResponse
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
                Rule::exists('products', 'id')->where(fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->where('type', 'stockable')
                    ->where('purchase_ok', true)
                    ->where('purchase_blocked', false)),
            ],
        ]);

        $products = Product::query()
            ->where('company_id', $companyId)
            ->whereIn('id', collect($data['selected'])->map(fn ($id) => (int) $id)->unique()->all())
            ->get();

        foreach ($products as $product) {
            $minStock = (float) $product->min_stock;
            $targetStock = (float) ($product->reorder_max_qty ?: 0);

            $product->forceFill([
                'auto_replenish' => true,
                'reorder_max_qty' => $targetStock > $minStock
                    ? $targetStock
                    : max($minStock * 2, $minStock + 1),
            ])->save();
        }

        $this->activityLogger->log('replenishments.activate_products', 'Activation reappro automatique en lot', null, [
            'warehouse_id' => (int) $data['warehouse_id'],
            'product_ids' => $products->pluck('id')->all(),
        ]);

        return redirect()
            ->route('replenishments.index', ['warehouse_id' => $data['warehouse_id']])
            ->with('success', $products->count().' produit(s) active(s) pour le reappro automatique.');
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

    private function suggestionContext(Request $request, CurrentWorkspace $workspace): array
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

        return [
            $selectedWarehouse,
            $suggestions,
            $this->statsFor($suggestions),
        ];
    }

    private function statsFor(Collection $suggestions): array
    {
        return [
            'count' => $suggestions->count(),
            'quantity' => (float) $suggestions->sum('suggested_qty'),
            'estimated_total' => (float) $suggestions->sum('estimated_total'),
            'urgent_count' => $suggestions->where('priority', 'urgent')->count(),
        ];
    }
}
