<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\StockCountService;
use App\Modules\Inventory\Services\StockService;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StockCountController extends Controller
{
    public function __construct(
        private readonly StockCountService $stockCountService,
        private readonly StockService $stockService,
        private readonly ActivityLogger $activityLogger,
    ) {
    }

    public function index(CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        return view('stock-counts.index', [
            'counts' => StockCount::query()
                ->with(['warehouse', 'creator', 'poster'])
                ->where('company_id', $companyId)
                ->latest('count_date')
                ->latest('id')
                ->paginate(15),
        ]);
    }

    public function create(Request $request, CurrentWorkspace $workspace): View
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

        $selectedWarehouse = $request->integer('warehouse_id')
            ? $warehouses->firstWhere('id', $request->integer('warehouse_id'))
            : $warehouses->first();

        $search = $request->string('q')->trim()->value();
        $productQuery = Product::query()
            ->where('company_id', $companyId)
            ->where('type', 'stockable')
            ->where('is_active', true)
            ->when($search, function ($query, string $term): void {
                $like = '%'.$term.'%';
                $query->where(fn ($nested) => $nested
                    ->where('name', 'like', $like)
                    ->orWhere('sku', 'like', $like)
                    ->orWhere('barcode', 'like', $like));
            });
        $catalogTotal = Product::query()
            ->where('company_id', $companyId)
            ->where('type', 'stockable')
            ->where('is_active', true)
            ->count();
        $matchTotal = (clone $productQuery)->count();
        $products = $this->countRows(
            (clone $productQuery)->orderBy('name')->limit(200)->get(['id', 'sku', 'name', 'unit']),
            $companyId,
            $branchId,
            $selectedWarehouse?->id,
        );

        return view('stock-counts.create', [
            'warehouses' => $warehouses,
            'selectedWarehouse' => $selectedWarehouse,
            'products' => $products,
            'productSearch' => $search,
            'catalogTotal' => $catalogTotal,
            'matchTotal' => $matchTotal,
            'isLimited' => $matchTotal > $products->count(),
            'branch' => $workspace->branch(),
        ]);
    }

    public function quick(Request $request, CurrentWorkspace $workspace): View
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

        return view('stock-counts.quick', [
            'warehouses' => $warehouses,
            'selectedWarehouse' => $request->integer('warehouse_id')
                ? $warehouses->firstWhere('id', $request->integer('warehouse_id'))
                : $warehouses->first(),
            'products' => $this->countRows(
                app(\App\Modules\Catalog\Services\ProductOptionService::class)
                    ->initial($companyId, 'stockable', [old('product_id')], 40),
                $companyId,
                $branchId,
                ($request->integer('warehouse_id') ? $warehouses->firstWhere('id', $request->integer('warehouse_id')) : $warehouses->first())?->id,
                true,
            ),
            'branch' => $workspace->branch(),
        ]);
    }

    public function store(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();
        abort_if(! $companyId || ! $branchId, 403);

        $data = $request->validate([
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('branch_id', $branchId))],
            'count_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $rows = collect($request->input('items', []))
            ->map(fn ($item) => is_array($item) ? $item : [])
            ->values()
            ->all();

        Validator::make(['items' => $rows], [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', Rule::exists('products', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('type', 'stockable'))],
            'items.*.counted_qty' => ['nullable', 'numeric', 'min:0'],
        ])->validate();

        $warehouse = Warehouse::query()->where('company_id', $companyId)->findOrFail($data['warehouse_id']);
        $items = $this->stockCountService->prepareItems($companyId, $branchId, $warehouse->id, $rows);

        if ($items->isEmpty()) {
            return back()->withErrors(['items' => 'Renseigne au moins une quantite comptee pour enregistrer un inventaire.'])->withInput();
        }

        $stockCount = $this->stockCountService->create(
            companyId: $companyId,
            branchId: $branchId,
            warehouse: $warehouse,
            payload: $data,
            items: $items,
            user: $request->user(),
        );

        $this->activityLogger->log('stock_counts.create', 'Creation inventaire de stock', $stockCount, [
            'count_number' => $stockCount->count_number,
        ]);

        return redirect()->route('stock-counts.show', $stockCount)->with('success', 'Inventaire de stock cree avec succes.');
    }

    private function countRows(Collection $products, int $companyId, int $branchId, ?int $warehouseId, bool $withScanData = false): Collection
    {
        $productIds = $products->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $balances = $warehouseId
            ? DB::table('stock_movements')
                ->where('company_id', $companyId)
                ->where('branch_id', $branchId)
                ->where('warehouse_id', $warehouseId)
                ->whereIn('product_id', $productIds)
                ->groupBy('product_id')
                ->selectRaw('product_id, COALESCE(SUM(quantity_in - quantity_out), 0) as balance')
                ->pluck('balance', 'product_id')
            : collect();

        return $products->map(fn (Product $product): array => array_filter([
            'id' => $product->id,
            'sku' => $product->sku,
            'barcode' => $withScanData ? $product->barcode : null,
            'name' => $product->name,
            'unit' => $product->unit,
            'purchase_price' => $withScanData ? (float) $product->purchase_price : null,
            'expected_qty' => round((float) ($balances[$product->id] ?? 0), 3),
        ], fn (mixed $value, string $key): bool => $withScanData || ! in_array($key, ['barcode', 'purchase_price'], true), ARRAY_FILTER_USE_BOTH));
    }

    public function storeQuick(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();
        abort_if(! $companyId || ! $branchId, 403);

        $data = $request->validate([
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('branch_id', $branchId))],
            'product_id' => ['required', Rule::exists('products', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('type', 'stockable'))],
            'counted_qty' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $warehouse = Warehouse::query()->where('company_id', $companyId)->findOrFail($data['warehouse_id']);
        $items = $this->stockCountService->prepareItems($companyId, $branchId, $warehouse->id, [[
            'product_id' => $data['product_id'],
            'counted_qty' => $data['counted_qty'],
        ]]);

        $stockCount = $this->stockCountService->create(
            companyId: $companyId,
            branchId: $branchId,
            warehouse: $warehouse,
            payload: [
                'count_date' => now()->toDateString(),
                'notes' => $data['notes'] ?: 'Inventaire rapide',
            ],
            items: $items,
            user: $request->user(),
        );

        $stockCount = $this->stockCountService->post($stockCount, $request->user());

        $this->activityLogger->log('stock_counts.quick', 'Inventaire rapide valide', $stockCount, [
            'count_number' => $stockCount->count_number,
            'product_id' => $data['product_id'],
            'warehouse_id' => $warehouse->id,
        ]);

        if ($request->boolean('continue')) {
            return redirect()
                ->route('stock-counts.quick', ['warehouse_id' => $warehouse->id])
                ->with('success', 'Ecart applique. Tu peux compter le produit suivant.');
        }

        return redirect()->route('stock-counts.show', $stockCount)->with('success', 'Inventaire rapide valide et ecart applique au stock.');
    }

    public function post(StockCount $stockCount, CurrentWorkspace $workspace, Request $request): RedirectResponse
    {
        abort_if($workspace->companyId() !== $stockCount->company_id, 403);

        $stockCount = $this->stockCountService->post($stockCount, $request->user());
        $this->activityLogger->log('stock_counts.post', 'Validation inventaire de stock', $stockCount, [
            'count_number' => $stockCount->count_number,
        ]);

        return redirect()->route('stock-counts.show', $stockCount)->with('success', 'Inventaire valide et ecarts appliques au stock.');
    }

    public function show(StockCount $stockCount, CurrentWorkspace $workspace): View
    {
        abort_if($workspace->companyId() !== $stockCount->company_id, 403);

        return view('stock-counts.show', [
            'stockCount' => $stockCount->load(['warehouse', 'branch', 'creator', 'poster', 'items.product']),
        ]);
    }

    public function print(StockCount $stockCount, CurrentWorkspace $workspace): View
    {
        abort_if($workspace->companyId() !== $stockCount->company_id, 403);

        return view('stock-counts.print', [
            'stockCount' => $stockCount->load(['company', 'warehouse', 'branch', 'creator', 'poster', 'items.product']),
        ]);
    }
}
