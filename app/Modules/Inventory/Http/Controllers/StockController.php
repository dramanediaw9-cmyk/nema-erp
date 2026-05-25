<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductCategory;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Purchases\Models\GoodsReceipt;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Sales\Models\DeliveryNote;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesOrder;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use App\Support\Exports\CsvExportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockController extends Controller
{
    public function __construct(
        private readonly StockService $stockService,
        private readonly ActivityLogger $activityLogger,
        private readonly CsvExportService $csvExportService,
    ) {}

    public function index(Request $request, CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();
        abort_if(! $companyId || ! $branchId, 403);

        $filters = $this->filters($request);
        $warehouses = $this->branchWarehouses($companyId, $branchId);
        $products = $this->stockQuery($companyId, $branchId, $filters)
            ->paginate(20)
            ->withQueryString();

        $products->setCollection(
            $this->decorateStockRows($products->getCollection(), $companyId, $branchId, $filters['warehouse_id'] ?? null)
        );

        return view('stock.index', [
            'products' => $products,
            'branch' => $workspace->branch(),
            'filters' => $filters,
            'categories' => ProductCategory::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'warehouses' => $warehouses,
            'selectedWarehouse' => $filters['warehouse_id']
                ? $warehouses->firstWhere('id', $filters['warehouse_id'])
                : null,
        ]);
    }

    public function show(Request $request, Product $product, CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();
        abort_if(! $companyId || ! $branchId, 403);
        abort_if($product->company_id !== $companyId || $product->type !== 'stockable', 403);

        $warehouses = $this->branchWarehouses($companyId, $branchId);
        $selectedWarehouseId = $request->integer('warehouse_id') ?: null;
        if ($selectedWarehouseId && ! $warehouses->contains('id', $selectedWarehouseId)) {
            abort(403);
        }

        $product->load(['category', 'company']);

        $recentMovements = StockMovement::query()
            ->with(['product', 'branch', 'warehouse', 'creator', 'reference'])
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('product_id', $product->id)
            ->when($selectedWarehouseId, fn (Builder $query, int $warehouseId) => $query->where('warehouse_id', $warehouseId))
            ->latest('movement_date')
            ->latest('id')
            ->limit(20)
            ->get();

        $currentStock = $this->stockService->availableQuantity($companyId, $branchId, $product->id, $selectedWarehouseId);
        $saleableStock = $this->stockService->saleableQuantity($product, $companyId, $branchId, $selectedWarehouseId);
        $reservedStock = $this->stockService->reservedQuantity($companyId, $branchId, $product->id, $selectedWarehouseId);
        $availableToPromise = $this->stockService->reservableQuantity($product, $companyId, $branchId, $selectedWarehouseId);

        $reservationOrders = SalesOrder::query()
            ->with(['customer', 'warehouse', 'items' => fn ($query) => $query->where('product_id', $product->id)->whereRaw('qty > delivered_qty')])
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->whereIn('status', ['confirmed', 'partial_delivered'])
            ->whereNull('converted_sales_invoice_id')
            ->when($selectedWarehouseId, fn (Builder $query, int $warehouseId) => $query->where('warehouse_id', $warehouseId))
            ->whereHas('items', fn (Builder $query) => $query->where('product_id', $product->id)->whereRaw('qty > delivered_qty'))
            ->orderBy('requested_delivery_date')
            ->orderBy('order_date')
            ->get()
            ->map(function (SalesOrder $order) use ($product) {
                $reservedQuantity = (float) $order->items
                    ->where('product_id', $product->id)
                    ->sum(fn ($item) => $item->remainingQty());

                return [
                    'order' => $order,
                    'reserved_qty' => $reservedQuantity,
                ];
            })
            ->filter(fn (array $row) => $row['reserved_qty'] > 0.0001)
            ->values();

        return view('stock.show', [
            'product' => $product,
            'branch' => $workspace->branch(),
            'warehouses' => $warehouses,
            'selectedWarehouse' => $selectedWarehouseId ? $warehouses->firstWhere('id', $selectedWarehouseId) : null,
            'currentStock' => $currentStock,
            'saleableStock' => $saleableStock,
            'reservedStock' => $reservedStock,
            'availableToPromise' => $availableToPromise,
            'totalIn' => (float) StockMovement::query()
                ->where('company_id', $companyId)
                ->where('branch_id', $branchId)
                ->where('product_id', $product->id)
                ->when($selectedWarehouseId, fn (Builder $query, int $warehouseId) => $query->where('warehouse_id', $warehouseId))
                ->sum('quantity_in'),
            'totalOut' => (float) StockMovement::query()
                ->where('company_id', $companyId)
                ->where('branch_id', $branchId)
                ->where('product_id', $product->id)
                ->when($selectedWarehouseId, fn (Builder $query, int $warehouseId) => $query->where('warehouse_id', $warehouseId))
                ->sum('quantity_out'),
            'recentMovements' => $recentMovements,
            'movementContexts' => $recentMovements
                ->mapWithKeys(fn (StockMovement $movement) => [$movement->id => $this->movementSourceContext($movement)])
                ->all(),
            'reservationOrders' => $reservationOrders,
            'relatedDocuments' => $this->relatedDocumentsForProduct($companyId, $branchId, $product->id, $selectedWarehouseId),
            'journalEntries' => $this->journalEntriesForProduct($companyId, $branchId, $product->id, $selectedWarehouseId),
        ]);
    }

    public function export(Request $request, CurrentWorkspace $workspace): StreamedResponse
    {
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();
        abort_if(! $companyId || ! $branchId, 403);

        $filters = $this->filters($request);
        $selectedWarehouse = $filters['warehouse_id']
            ? Warehouse::query()->where('company_id', $companyId)->where('branch_id', $branchId)->find($filters['warehouse_id'])
            : null;

        $products = $this->decorateStockRows(
            $this->stockQuery($companyId, $branchId, $filters)->get(),
            $companyId,
            $branchId,
            $filters['warehouse_id'] ?? null,
        );

        $rows = $products->map(function ($product) use ($selectedWarehouse) {
            $currentStock = (float) $product->current_stock;
            $valuation = $currentStock * (float) $product->purchase_price;

            return [
                $product->sku,
                $product->name,
                $product->category_name,
                $selectedWarehouse?->name ?? 'Tous les entrepots',
                $product->unit,
                number_format($currentStock, 3, '.', ''),
                number_format((float) $product->reserved_stock, 3, '.', ''),
                number_format((float) $product->available_to_promise, 3, '.', ''),
                number_format((float) $product->min_stock, 3, '.', ''),
                number_format($valuation, 2, '.', ''),
            ];
        });

        return $this->csvExportService->download('stock.csv', [
            'SKU', 'Produit', 'Categorie', 'Entrepot', 'Unite', 'Stock actuel', 'Reserve', 'Disponible apres reservation', 'Stock minimum', 'Valorisation',
        ], $rows);
    }

    public function movements(Request $request, CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();
        abort_if(! $companyId || ! $branchId, 403);

        $filters = $this->movementFilters($request);
        $warehouses = $this->branchWarehouses($companyId, $branchId);

        $movements = StockMovement::query()
            ->with(['product', 'branch', 'warehouse', 'creator', 'reference'])
            ->where('company_id', $companyId)
            ->when($filters['date_from'], fn (Builder $query, string $dateFrom) => $query->whereDate('movement_date', '>=', $dateFrom))
            ->when($filters['date_to'], fn (Builder $query, string $dateTo) => $query->whereDate('movement_date', '<=', $dateTo))
            ->when($filters['branch_id'], fn (Builder $query, int $filterBranchId) => $query->where('branch_id', $filterBranchId))
            ->when($filters['warehouse_id'], fn (Builder $query, int $warehouseId) => $query->where('warehouse_id', $warehouseId))
            ->when($filters['movement_type'], fn (Builder $query, string $movementType) => $query->where('movement_type', $movementType))
            ->when($filters['search'], function (Builder $query, string $search) {
                $like = '%'.$search.'%';

                $query->where(function (Builder $nested) use ($like) {
                    $nested->where('reason', 'like', $like)
                        ->orWhere('notes', 'like', $like)
                        ->orWhereHas('product', function (Builder $productQuery) use ($like) {
                            $productQuery->where('name', 'like', $like)
                                ->orWhere('sku', 'like', $like);
                        })
                        ->orWhereHas('branch', function (Builder $branchQuery) use ($like) {
                            $branchQuery->where('name', 'like', $like)
                                ->orWhere('code', 'like', $like);
                        })
                        ->orWhereHas('warehouse', function (Builder $warehouseQuery) use ($like) {
                            $warehouseQuery->where('name', 'like', $like)
                                ->orWhere('code', 'like', $like);
                        });
                });
            })
            ->latest('movement_date')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('stock.movements', [
            'movements' => $movements,
            'movementContexts' => $movements->getCollection()
                ->mapWithKeys(fn (StockMovement $movement) => [$movement->id => $this->movementSourceContext($movement)])
                ->all(),
            'filters' => $filters,
            'branches' => Branch::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'warehouses' => $warehouses,
            'movementTypes' => [
                'opening' => 'Stock initial',
                'purchase' => 'Achat / Reception',
                'sale' => 'Vente / Livraison',
                'adjustment_in' => 'Entree interne',
                'adjustment_out' => 'Sortie interne',
            ],
        ]);
    }

    public function createOpening(CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();
        abort_if(! $companyId || ! $branchId, 403);

        return view('stock.opening', [
            'products' => $this->stockableProducts($companyId),
            'warehouses' => $this->branchWarehouses($companyId, $branchId),
            'branch' => $workspace->branch(),
        ]);
    }

    public function storeOpening(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();
        abort_if(! $companyId || ! $branchId, 403);

        $data = $request->validate([
            'product_id' => [
                'required',
                Rule::exists('products', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('type', 'stockable')),
            ],
            'warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('branch_id', $branchId))],
            'movement_date' => ['required', 'date'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $product = Product::query()->findOrFail($data['product_id']);
        $movement = $this->stockService->recordOpening(
            product: $product,
            companyId: $companyId,
            branchId: $branchId,
            quantity: (float) $data['quantity'],
            unitCost: (float) ($data['unit_cost'] ?? $product->purchase_price),
            notes: $data['notes'] ?? null,
            user: $request->user(),
            movementDate: $data['movement_date'],
            warehouseId: $data['warehouse_id'] ?? null,
        );

        $this->activityLogger->log('stock.opening', 'Saisie stock initial', $movement, [
            'product_id' => $product->id,
            'warehouse_id' => $movement->warehouse_id,
            'quantity' => $data['quantity'],
            'movement_date' => $data['movement_date'],
        ]);

        return redirect()->route('stock.index')->with('success', 'Stock initial enregistre avec succes.');
    }

    public function createAdjustment(CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();
        abort_if(! $companyId || ! $branchId, 403);

        return view('stock.adjustments', [
            'products' => $this->stockableProducts($companyId),
            'warehouses' => $this->branchWarehouses($companyId, $branchId),
            'branch' => $workspace->branch(),
        ]);
    }

    public function storeAdjustment(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();
        abort_if(! $companyId || ! $branchId, 403);

        $data = $request->validate([
            'product_id' => [
                'required',
                Rule::exists('products', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('type', 'stockable')),
            ],
            'warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('branch_id', $branchId))],
            'movement_date' => ['required', 'date'],
            'direction' => ['required', Rule::in(['in', 'out'])],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $product = Product::query()->findOrFail($data['product_id']);

        $movement = DB::transaction(function () use ($data, $product, $companyId, $branchId, $request) {
            return $this->stockService->recordAdjustment(
                product: $product,
                companyId: $companyId,
                branchId: $branchId,
                direction: $data['direction'],
                quantity: (float) $data['quantity'],
                unitCost: (float) ($data['unit_cost'] ?? $product->purchase_price),
                reason: $data['reason'],
                notes: $data['notes'] ?? null,
                user: $request->user(),
                movementDate: $data['movement_date'],
                warehouseId: $data['warehouse_id'] ?? null,
            );
        });

        $this->activityLogger->log('stock.adjustment', 'Ajustement de stock', $movement, [
            'product_id' => $product->id,
            'warehouse_id' => $movement->warehouse_id,
            'direction' => $data['direction'],
            'quantity' => $data['quantity'],
            'movement_date' => $data['movement_date'],
        ]);

        return redirect()->route('stock.index')->with('success', 'Ajustement de stock enregistre avec succes.');
    }

    private function stockQuery(int $companyId, int $branchId, array $filters): Builder
    {
        $today = now()->toDateString();
        $balances = StockMovement::query()
            ->select('product_id')
            ->selectRaw('SUM(quantity_in - quantity_out) as current_stock')
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->when($filters['warehouse_id'], fn (Builder $query, int $warehouseId) => $query->where('warehouse_id', $warehouseId))
            ->groupBy('product_id');
        $trackedBalances = DB::table('product_lots')
            ->select('product_id')
            ->selectRaw('SUM(quantity_available) as tracked_stock')
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->when($filters['warehouse_id'], fn ($query, int $warehouseId) => $query->where('warehouse_id', $warehouseId))
            ->groupBy('product_id');
        $saleableBalances = DB::table('product_lots')
            ->select('product_id')
            ->selectRaw('SUM(quantity_available) as saleable_stock')
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->when($filters['warehouse_id'], fn ($query, int $warehouseId) => $query->where('warehouse_id', $warehouseId))
            ->where(function ($query) use ($today) {
                $query->whereNull('expires_at')
                    ->orWhereDate('expires_at', '>=', $today);
            })
            ->groupBy('product_id');
        $displayStockExpression = "CASE WHEN products.tracking_type IN ('lot', 'serial') THEN COALESCE(tracked_balances.tracked_stock, 0) ELSE COALESCE(balances.current_stock, 0) END";
        $saleableStockExpression = "CASE WHEN products.tracking_type IN ('lot', 'serial') THEN COALESCE(saleable_balances.saleable_stock, 0) ELSE COALESCE(balances.current_stock, 0) END";

        return Product::query()
            ->where('products.company_id', $companyId)
            ->where('products.type', 'stockable')
            ->leftJoinSub($balances, 'balances', fn ($join) => $join->on('products.id', '=', 'balances.product_id'))
            ->leftJoinSub($trackedBalances, 'tracked_balances', fn ($join) => $join->on('products.id', '=', 'tracked_balances.product_id'))
            ->leftJoinSub($saleableBalances, 'saleable_balances', fn ($join) => $join->on('products.id', '=', 'saleable_balances.product_id'))
            ->leftJoin('product_categories', 'products.category_id', '=', 'product_categories.id')
            ->select([
                'products.id',
                'products.sku',
                'products.name',
                'products.unit',
                'products.tracking_type',
                'products.min_stock',
                'products.purchase_price',
                'products.is_active',
                'product_categories.name as category_name',
            ])
            ->selectRaw($displayStockExpression.' as current_stock')
            ->selectRaw($saleableStockExpression.' as query_saleable_stock')
            ->when($filters['search'], function (Builder $query, string $search) {
                $like = '%'.$search.'%';

                $query->where(function (Builder $nested) use ($like) {
                    $nested->where('products.name', 'like', $like)
                        ->orWhere('products.sku', 'like', $like);
                });
            })
            ->when($filters['category_id'], fn (Builder $query, int $categoryId) => $query->where('products.category_id', $categoryId))
            ->when($filters['tracking_type'] === 'tracked', fn (Builder $query) => $query->whereIn('products.tracking_type', ['lot', 'serial']))
            ->when(in_array($filters['tracking_type'], ['none', 'lot', 'serial'], true), fn (Builder $query, string $trackingType) => $query->where('products.tracking_type', $trackingType))
            ->when($filters['stock_state'] === 'low', fn (Builder $query) => $query->whereRaw($displayStockExpression.' <= products.min_stock'))
            ->when($filters['stock_state'] === 'positive', fn (Builder $query) => $query->whereRaw($displayStockExpression.' > 0'))
            ->when($filters['stock_state'] === 'zero', fn (Builder $query) => $query->whereRaw($displayStockExpression.' = 0'))
            ->when($filters['saleability_state'] === 'low', fn (Builder $query) => $query->whereRaw($saleableStockExpression.' <= products.min_stock'))
            ->when($filters['saleability_state'] === 'critical', fn (Builder $query) => $query
                ->whereRaw($saleableStockExpression.' > 0')
                ->whereRaw($saleableStockExpression.' <= products.min_stock'))
            ->when($filters['saleability_state'] === 'zero', fn (Builder $query) => $query->whereRaw($saleableStockExpression.' = 0'))
            ->orderBy('products.name');
    }

    private function decorateStockRows(Collection $products, int $companyId, int $branchId, ?int $warehouseId = null): Collection
    {
        $productIds = $products->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($productIds->isEmpty()) {
            return $products;
        }

        $reservedByProduct = DB::table('sales_order_items')
            ->join('sales_orders', 'sales_order_items.sales_order_id', '=', 'sales_orders.id')
            ->where('sales_orders.company_id', $companyId)
            ->where('sales_orders.branch_id', $branchId)
            ->whereIn('sales_orders.status', ['confirmed', 'partial_delivered'])
            ->whereNull('sales_orders.converted_sales_invoice_id')
            ->whereIn('sales_order_items.product_id', $productIds->all())
            ->whereRaw('sales_order_items.qty > sales_order_items.delivered_qty')
            ->when($warehouseId, fn ($query, int $resolvedWarehouseId) => $query->where('sales_orders.warehouse_id', $resolvedWarehouseId))
            ->groupBy('sales_order_items.product_id')
            ->selectRaw('sales_order_items.product_id, COALESCE(SUM(sales_order_items.qty - sales_order_items.delivered_qty), 0) as reserved_qty')
            ->pluck('reserved_qty', 'sales_order_items.product_id');

        return $products->map(function (Product $product) use ($companyId, $branchId, $warehouseId, $reservedByProduct) {
            $reservedStock = (float) ($reservedByProduct[(string) $product->id] ?? 0);
            $saleableStock = array_key_exists('query_saleable_stock', $product->getAttributes())
                ? (float) $product->getAttribute('query_saleable_stock')
                : (in_array($product->tracking_type, ['lot', 'serial'], true)
                    ? $this->stockService->saleableQuantity($product, $companyId, $branchId, $warehouseId)
                    : (float) $product->current_stock);

            $product->reserved_stock = $reservedStock;
            $product->saleable_stock = $saleableStock;
            $product->available_to_promise = max(0, round($saleableStock - $reservedStock, 3));

            return $product;
        });
    }

    private function filters(Request $request): array
    {
        $stockState = $request->string('stock_state')->trim()->value() ?: null;
        if (! in_array($stockState, ['low', 'positive', 'zero'], true)) {
            $stockState = null;
        }

        $trackingType = $request->string('tracking_type')->trim()->value() ?: null;
        if (! in_array($trackingType, ['tracked', 'none', 'lot', 'serial'], true)) {
            $trackingType = null;
        }

        $saleabilityState = $request->string('saleability_state')->trim()->value() ?: null;
        if (! in_array($saleabilityState, ['low', 'critical', 'zero'], true)) {
            $saleabilityState = null;
        }

        return [
            'search' => $request->string('search')->trim()->value() ?: null,
            'category_id' => $request->integer('category_id') ?: null,
            'warehouse_id' => $request->integer('warehouse_id') ?: null,
            'stock_state' => $stockState,
            'tracking_type' => $trackingType,
            'saleability_state' => $saleabilityState,
        ];
    }

    private function movementFilters(Request $request): array
    {
        $movementType = $request->string('movement_type')->trim()->value() ?: null;
        if (! in_array($movementType, ['opening', 'purchase', 'sale', 'adjustment_in', 'adjustment_out'], true)) {
            $movementType = null;
        }

        return [
            'search' => $request->string('search')->trim()->value() ?: null,
            'date_from' => $request->string('date_from')->value() ?: null,
            'date_to' => $request->string('date_to')->value() ?: null,
            'branch_id' => $request->integer('branch_id') ?: null,
            'warehouse_id' => $request->integer('warehouse_id') ?: null,
            'movement_type' => $movementType,
        ];
    }

    private function stockableProducts(int $companyId)
    {
        return Product::query()
            ->where('company_id', $companyId)
            ->where('type', 'stockable')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    private function branchWarehouses(int $companyId, int $branchId)
    {
        return Warehouse::query()
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'is_default']);
    }

    private function movementSourceContext(StockMovement $movement): ?array
    {
        $reference = $movement->reference;

        return match (true) {
            $reference instanceof DeliveryNote => [
                'label' => 'Bon de livraison',
                'number' => $reference->delivery_number,
                'url' => route('delivery-notes.show', $reference),
            ],
            $reference instanceof SalesInvoice => [
                'label' => 'Facture client',
                'number' => $reference->invoice_number,
                'url' => route('sales.show', $reference),
            ],
            $reference instanceof PurchaseBill => [
                'label' => 'Facture fournisseur',
                'number' => $reference->bill_number,
                'url' => route('purchases.show', $reference),
            ],
            $reference instanceof GoodsReceipt => [
                'label' => 'Reception fournisseur',
                'number' => $reference->receipt_number,
                'url' => route('goods-receipts.show', $reference),
            ],
            $reference instanceof StockTransfer => [
                'label' => 'Transfert de stock',
                'number' => $reference->transfer_number,
                'url' => route('transfers.show', $reference),
            ],
            $reference instanceof StockCount => [
                'label' => 'Inventaire de stock',
                'number' => $reference->count_number,
                'url' => route('stock-counts.show', $reference),
            ],
            default => null,
        };
    }

    private function relatedDocumentsForProduct(int $companyId, int $branchId, int $productId, ?int $warehouseId = null)
    {
        $references = StockMovement::query()
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->when($warehouseId, fn (Builder $query, int $selectedWarehouseId) => $query->where('warehouse_id', $selectedWarehouseId))
            ->whereNotNull('reference_type')
            ->whereNotNull('reference_id')
            ->get(['reference_type', 'reference_id'])
            ->groupBy('reference_type')
            ->map(fn ($group) => $group->pluck('reference_id')->unique()->values());

        $deliveryDocuments = DeliveryNote::query()
            ->where('company_id', $companyId)
            ->when(($references[DeliveryNote::class] ?? collect())->isNotEmpty(), fn (Builder $query) => $query->whereIn('id', $references[DeliveryNote::class]), fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->get()
            ->map(fn (DeliveryNote $deliveryNote) => [
                'label' => 'Bon de livraison',
                'number' => $deliveryNote->delivery_number,
                'date' => $deliveryNote->delivery_date,
                'status' => $deliveryNote->status,
                'url' => route('delivery-notes.show', $deliveryNote),
            ]);

        $salesDocuments = SalesInvoice::query()
            ->where('company_id', $companyId)
            ->when(($references[SalesInvoice::class] ?? collect())->isNotEmpty(), fn (Builder $query) => $query->whereIn('id', $references[SalesInvoice::class]), fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->get()
            ->map(fn (SalesInvoice $invoice) => [
                'label' => 'Facture client',
                'number' => $invoice->invoice_number,
                'date' => $invoice->invoice_date,
                'status' => $invoice->status,
                'url' => route('sales.show', $invoice),
            ]);

        $purchaseDocuments = PurchaseBill::query()
            ->where('company_id', $companyId)
            ->when(($references[PurchaseBill::class] ?? collect())->isNotEmpty(), fn (Builder $query) => $query->whereIn('id', $references[PurchaseBill::class]), fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->get()
            ->map(fn (PurchaseBill $bill) => [
                'label' => 'Facture fournisseur',
                'number' => $bill->bill_number,
                'date' => $bill->bill_date,
                'status' => $bill->status,
                'url' => route('purchases.show', $bill),
            ]);

        $receiptDocuments = GoodsReceipt::query()
            ->where('company_id', $companyId)
            ->when(($references[GoodsReceipt::class] ?? collect())->isNotEmpty(), fn (Builder $query) => $query->whereIn('id', $references[GoodsReceipt::class]), fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->get()
            ->map(fn (GoodsReceipt $receipt) => [
                'label' => 'Reception fournisseur',
                'number' => $receipt->receipt_number,
                'date' => $receipt->receipt_date,
                'status' => $receipt->status,
                'url' => route('goods-receipts.show', $receipt),
            ]);

        $transferDocuments = StockTransfer::query()
            ->where('company_id', $companyId)
            ->when(($references[StockTransfer::class] ?? collect())->isNotEmpty(), fn (Builder $query) => $query->whereIn('id', $references[StockTransfer::class]), fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->get()
            ->map(fn (StockTransfer $transfer) => [
                'label' => 'Transfert',
                'number' => $transfer->transfer_number,
                'date' => $transfer->transfer_date,
                'status' => $transfer->status,
                'url' => route('transfers.show', $transfer),
            ]);

        $countDocuments = StockCount::query()
            ->where('company_id', $companyId)
            ->when(($references[StockCount::class] ?? collect())->isNotEmpty(), fn (Builder $query) => $query->whereIn('id', $references[StockCount::class]), fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->get()
            ->map(fn (StockCount $count) => [
                'label' => 'Inventaire',
                'number' => $count->count_number,
                'date' => $count->count_date,
                'status' => $count->status,
                'url' => route('stock-counts.show', $count),
            ]);

        return $deliveryDocuments
            ->concat($salesDocuments)
            ->concat($purchaseDocuments)
            ->concat($receiptDocuments)
            ->concat($transferDocuments)
            ->concat($countDocuments)
            ->sortByDesc(fn (array $document) => optional($document['date'])?->timestamp ?? 0)
            ->values();
    }

    private function journalEntriesForProduct(int $companyId, int $branchId, int $productId, ?int $warehouseId = null)
    {
        $salesIds = StockMovement::query()
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->when($warehouseId, fn (Builder $query, int $selectedWarehouseId) => $query->where('warehouse_id', $selectedWarehouseId))
            ->where('reference_type', SalesInvoice::class)
            ->pluck('reference_id')
            ->filter()
            ->unique()
            ->values();

        $purchaseIds = StockMovement::query()
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->when($warehouseId, fn (Builder $query, int $selectedWarehouseId) => $query->where('warehouse_id', $selectedWarehouseId))
            ->where('reference_type', PurchaseBill::class)
            ->pluck('reference_id')
            ->filter()
            ->unique()
            ->values();

        if ($salesIds->isEmpty() && $purchaseIds->isEmpty()) {
            return collect();
        }

        return JournalEntry::query()
            ->with(['creator'])
            ->where('company_id', $companyId)
            ->where(function (Builder $query) use ($salesIds, $purchaseIds) {
                if ($salesIds->isNotEmpty()) {
                    $query->orWhere(function (Builder $salesQuery) use ($salesIds) {
                        $salesQuery->where('source_type', SalesInvoice::class)
                            ->whereIn('source_id', $salesIds);
                    });
                }

                if ($purchaseIds->isNotEmpty()) {
                    $query->orWhere(function (Builder $purchaseQuery) use ($purchaseIds) {
                        $purchaseQuery->where('source_type', PurchaseBill::class)
                            ->whereIn('source_id', $purchaseIds);
                    });
                }
            })
            ->latest('entry_date')
            ->latest('id')
            ->get();
    }
}
