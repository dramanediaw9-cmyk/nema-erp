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

        $products = Product::query()
            ->where('company_id', $companyId)
            ->where('type', 'stockable')
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function (Product $product) use ($companyId, $branchId, $selectedWarehouse) {
                $expectedQty = $selectedWarehouse
                    ? $this->stockService->availableQuantity($companyId, $branchId, $product->id, $selectedWarehouse->id)
                    : 0;

                return [
                    'id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'unit' => $product->unit,
                    'expected_qty' => round($expectedQty, 3),
                ];
            });

        return view('stock-counts.create', [
            'warehouses' => $warehouses,
            'selectedWarehouse' => $selectedWarehouse,
            'products' => $products,
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
}
