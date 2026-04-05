<?php

namespace App\Modules\Purchases\Services;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductSupplier;
use App\Modules\Core\Company\Services\DocumentNumberService;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Partners\Models\Partner;
use App\Modules\Purchases\Models\PurchaseOrder;
use App\Modules\Purchases\Models\PurchaseRequest;
use App\Modules\Purchases\Models\PurchaseRequestItem;
use App\Modules\Sales\Models\SalesOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseRequestService
{
    public function __construct(
        private readonly DocumentNumberService $documentNumberService,
        private readonly PurchaseOrderService $purchaseOrderService,
        private readonly SupplierPerformanceService $supplierPerformanceService,
    ) {
    }

    public function normalizeItems(int $companyId, array $items): Collection
    {
        $filteredItems = collect($items)
            ->filter(fn (array $item) => filled($item['product_id'] ?? null))
            ->values();

        $productIds = $filteredItems->pluck('product_id')->map(fn ($id) => (int) $id)->all();
        $products = Product::query()
            ->with(['supplierInfos.supplier'])
            ->where('company_id', $companyId)
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        return $filteredItems->map(function (array $item) use ($products) {
            /** @var Product|null $product */
            $product = $products->get((int) $item['product_id']);

            if (! $product) {
                throw ValidationException::withMessages([
                    'items' => 'Un produit de la demande d achat est introuvable.',
                ]);
            }

            $product->assertAvailableForPurchase('demande d achat');
            $supplierInfo = $product->supplierInfoFor();
            $qty = (float) $item['qty'];
            $estimatedUnitCost = isset($item['estimated_unit_cost']) && $item['estimated_unit_cost'] !== ''
                ? (float) $item['estimated_unit_cost']
                : (float) ($supplierInfo?->unit_cost ?: $product->purchase_price);

            return [
                'product' => $product,
                'product_id' => $product->id,
                'origin_sales_order_item_id' => isset($item['origin_sales_order_item_id']) && $item['origin_sales_order_item_id']
                    ? (int) $item['origin_sales_order_item_id']
                    : null,
                'description' => $item['description'] ?: ($supplierInfo?->supplier_product_name ?: ($product->purchase_description ?: $product->name)),
                'qty' => $qty,
                'estimated_unit_cost' => $estimatedUnitCost,
                'line_total' => round($qty * $estimatedUnitCost, 2),
            ];
        });
    }

    public function create(int $companyId, int $branchId, Warehouse $warehouse, array $payload, Collection $items, User $user): PurchaseRequest
    {
        return DB::transaction(function () use ($companyId, $branchId, $warehouse, $payload, $items, $user) {
            $items->each(fn (array $item) => $item['product']->assertAvailableForPurchase('demande d achat'));
            $request = PurchaseRequest::query()->create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'warehouse_id' => $warehouse->id,
                'origin_sales_order_id' => $payload['origin_sales_order_id'] ?? null,
                'request_number' => $this->documentNumberService->nextNumber(
                    companyId: $companyId,
                    documentType: 'purchase_request',
                    branchId: $branchId,
                    date: $payload['request_date'],
                ),
                'request_date' => $payload['request_date'],
                'needed_by_date' => $payload['needed_by_date'] ?? null,
                'priority' => $payload['priority'] ?? 'normal',
                'status' => 'pending_approval',
                'subtotal' => round((float) $items->sum('line_total'), 2),
                'total' => round((float) $items->sum('line_total'), 2),
                'notes' => $payload['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            foreach ($items as $item) {
                $request->items()->create([
                    'product_id' => $item['product_id'],
                    'origin_sales_order_item_id' => $item['origin_sales_order_item_id'] ?? null,
                    'description' => $item['description'],
                    'qty' => $item['qty'],
                    'estimated_unit_cost' => $item['estimated_unit_cost'],
                    'line_total' => $item['line_total'],
                ]);
            }

            return $request->load($this->purchaseRequestRelations());
        });
    }

    public function findOpenGeneratedRequestForOrder(SalesOrder $order): ?PurchaseRequest
    {
        return PurchaseRequest::query()
            ->where('company_id', $order->company_id)
            ->where('origin_sales_order_id', $order->id)
            ->whereIn('status', ['pending_approval', 'approved'])
            ->latest('id')
            ->first();
    }

    public function approve(PurchaseRequest $purchaseRequest, User $user): PurchaseRequest
    {
        return DB::transaction(function () use ($purchaseRequest, $user) {
            $request = PurchaseRequest::query()->whereKey($purchaseRequest->id)->lockForUpdate()->firstOrFail();

            if ($request->status !== 'pending_approval') {
                throw ValidationException::withMessages([
                    'request' => 'Cette demande d achat ne peut pas etre approuvee depuis son statut actuel.',
                ]);
            }

            $request->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $user->id,
            ]);

            return $request->fresh($this->purchaseRequestRelations());
        });
    }

    public function reject(PurchaseRequest $purchaseRequest, User $user): PurchaseRequest
    {
        return DB::transaction(function () use ($purchaseRequest, $user) {
            $request = PurchaseRequest::query()->whereKey($purchaseRequest->id)->lockForUpdate()->firstOrFail();

            if ($request->status !== 'pending_approval') {
                throw ValidationException::withMessages([
                    'request' => 'Cette demande d achat ne peut pas etre rejetee depuis son statut actuel.',
                ]);
            }

            $request->update([
                'status' => 'rejected',
                'rejected_at' => now(),
                'rejected_by' => $user->id,
            ]);

            return $request->fresh($this->purchaseRequestRelations());
        });
    }

    public function convertToOrder(PurchaseRequest $purchaseRequest, Partner $supplier, User $user): array
    {
        return DB::transaction(function () use ($purchaseRequest, $supplier, $user) {
            $request = PurchaseRequest::query()
                ->with(['warehouse', 'items.product.supplierInfos.supplier'])
                ->whereKey($purchaseRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureConvertible($request);

            $normalizedItems = $this->purchaseOrderService->normalizeItems(
                $request->company_id,
                $request->items->map(fn (PurchaseRequestItem $item) => [
                    'product_id' => $item->product_id,
                    'description' => $item->description,
                    'qty' => (float) $item->qty,
                    'unit_cost' => (float) $item->estimated_unit_cost,
                ])->all(),
                $supplier,
            );

            $order = $this->purchaseOrderService->create(
                companyId: $request->company_id,
                branchId: $request->branch_id,
                warehouse: $request->warehouse,
                supplier: $supplier,
                payload: [
                    'order_date' => now()->toDateString(),
                    'expected_receipt_date' => optional($request->needed_by_date)->toDateString(),
                    'notes' => 'Issue de la demande '.$request->request_number.($request->notes ? ' / '.$request->notes : ''),
                    'source_purchase_request_id' => $request->id,
                ],
                items: $normalizedItems,
                user: $user,
            );

            $request->update([
                'status' => 'converted',
                'converted_at' => now(),
                'converted_purchase_order_id' => $order->id,
            ]);

            return [
                'request' => $request->fresh($this->purchaseRequestRelations()),
                'order' => $order,
            ];
        });
    }

    public function supplierRecommendationPlan(PurchaseRequest $purchaseRequest): array
    {
        $purchaseRequest->loadMissing(['items.product.supplierInfos.supplier', 'generatedPurchaseOrders.supplier']);

        $itemPlans = $purchaseRequest->items
            ->map(fn (PurchaseRequestItem $item) => $this->supplierRecommendationForItem($item))
            ->values();

        $recommendedOrders = $itemPlans
            ->filter(fn (array $plan) => ! empty($plan['recommended_supplier_id']))
            ->groupBy('recommended_supplier_id')
            ->map(function (Collection $plans) {
                $first = $plans->first();
                /** @var Partner $supplier */
                $supplier = $first['recommended_supplier'];

                return [
                    'supplier_id' => $supplier->id,
                    'supplier' => $supplier,
                    'supplier_name' => $supplier->name,
                    'supplier_score' => $plans->first()['recommended_supplier_score'] ?? null,
                    'supplier_score_label' => $plans->first()['recommended_supplier_score_label'] ?? null,
                    'items' => $plans->values(),
                    'items_count' => $plans->count(),
                    'estimated_total' => round((float) $plans->sum('recommended_line_total'), 2),
                    'max_lead_time_days' => $plans->max('recommended_lead_time_days'),
                    'preferred_lines_count' => $plans->where('is_preferred', true)->count(),
                    'product_names' => $plans->map(fn (array $plan) => $plan['product_name'])->values()->all(),
                ];
            })
            ->sortBy(fn (array $group) => $group['supplier_name'])
            ->values();

        $missingItems = $itemPlans
            ->filter(fn (array $plan) => empty($plan['recommended_supplier_id']))
            ->values();

        $singleSupplierId = $recommendedOrders->count() === 1 && $missingItems->isEmpty()
            ? (int) $recommendedOrders->first()['supplier_id']
            : null;

        return [
            'items' => $itemPlans,
            'item_map' => $itemPlans->keyBy('item_id'),
            'recommended_orders' => $recommendedOrders,
            'recommended_orders_count' => $recommendedOrders->count(),
            'single_supplier_id' => $singleSupplierId,
            'missing_items' => $missingItems,
            'missing_items_count' => $missingItems->count(),
            'estimated_total' => round((float) $recommendedOrders->sum('estimated_total'), 2),
            'can_auto_convert' => $purchaseRequest->status === 'approved'
                && ! $this->hasGeneratedOrders($purchaseRequest)
                && $missingItems->isEmpty()
                && $recommendedOrders->isNotEmpty(),
        ];
    }

    public function autoConvertToRecommendedOrders(PurchaseRequest $purchaseRequest, User $user): array
    {
        return DB::transaction(function () use ($purchaseRequest, $user) {
            $request = PurchaseRequest::query()
                ->with(['warehouse', 'items.product.supplierInfos.supplier'])
                ->whereKey($purchaseRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureConvertible($request);

            $plan = $this->supplierRecommendationPlan($request);

            if (! $plan['can_auto_convert']) {
                throw ValidationException::withMessages([
                    'request' => 'Impossible de generer automatiquement les commandes : complete la configuration fournisseurs des lignes de cette demande.',
                ]);
            }

            $orders = collect();

            foreach ($plan['recommended_orders'] as $group) {
                /** @var Partner|null $supplier */
                $supplier = $group['supplier'] ?? null;

                if (! $supplier) {
                    throw ValidationException::withMessages([
                        'request' => 'Une recommandation fournisseur est incomplete sur cette demande.',
                    ]);
                }

                $normalizedItems = $this->purchaseOrderService->normalizeItems(
                    $request->company_id,
                    collect($group['items'])->map(fn (array $itemPlan) => [
                        'product_id' => $itemPlan['product_id'],
                        'description' => $itemPlan['description'],
                        'qty' => (float) $itemPlan['qty'],
                        'unit_cost' => (float) $itemPlan['recommended_unit_cost'],
                    ])->all(),
                    $supplier,
                );

                $order = $this->purchaseOrderService->create(
                    companyId: $request->company_id,
                    branchId: $request->branch_id,
                    warehouse: $request->warehouse,
                    supplier: $supplier,
                    payload: [
                        'order_date' => now()->toDateString(),
                        'expected_receipt_date' => optional($request->needed_by_date)->toDateString(),
                        'notes' => 'Issue de la demande '.$request->request_number.' / Allocation fournisseur recommandee'.($request->notes ? ' / '.$request->notes : ''),
                        'source_purchase_request_id' => $request->id,
                    ],
                    items: $normalizedItems,
                    user: $user,
                );

                $orders->push($order);
            }

            $request->update([
                'status' => 'converted',
                'converted_at' => now(),
                'converted_purchase_order_id' => $orders->first()?->id,
            ]);

            return [
                'request' => $request->fresh($this->purchaseRequestRelations()),
                'orders' => $orders,
            ];
        });
    }

    private function supplierRecommendationForItem(PurchaseRequestItem $item): array
    {
        $product = $item->product;
        $rankedInfos = $this->rankedSupplierInfos($item);
        /** @var ProductSupplier|null $recommendedInfo */
        $recommendedInfo = $rankedInfos->first();

        $recommendedSupplier = $recommendedInfo?->supplier;
        $recommendedUnitCost = $recommendedInfo?->unit_cost !== null
            ? (float) $recommendedInfo->unit_cost
            : (float) ($item->estimated_unit_cost ?: ($product?->purchase_price ?: 0));
        $leadTimeDays = $recommendedInfo?->lead_time_days;
        if ($leadTimeDays === null && $product) {
            $leadTimeDays = $product->purchase_lead_time_days;
        }

        $supplierScore = $recommendedSupplier
            ? $this->supplierPerformanceService->summaryForSupplier($product->company_id, $recommendedSupplier->id)
            : null;

        return [
            'item_id' => $item->id,
            'product' => $product,
            'product_id' => $item->product_id,
            'product_name' => $product?->display_name ?: $item->description,
            'description' => $item->description,
            'qty' => (float) $item->qty,
            'estimated_unit_cost' => (float) $item->estimated_unit_cost,
            'recommended_supplier' => $recommendedSupplier,
            'recommended_supplier_id' => $recommendedSupplier?->id,
            'recommended_supplier_name' => $recommendedSupplier?->name,
            'recommended_unit_cost' => $recommendedUnitCost,
            'recommended_line_total' => round((float) $item->qty * $recommendedUnitCost, 2),
            'recommended_lead_time_days' => $leadTimeDays !== null ? (int) $leadTimeDays : null,
            'recommended_supplier_code' => $recommendedInfo?->supplier_product_code,
            'recommended_supplier_product_name' => $recommendedInfo?->supplier_product_name,
            'recommended_supplier_score' => $supplierScore['score'] ?? null,
            'recommended_supplier_score_label' => $supplierScore['score_label'] ?? null,
            'is_preferred' => (bool) ($recommendedInfo?->is_preferred),
            'alternatives_count' => max($rankedInfos->count() - 1, 0),
        ];
    }

    private function rankedSupplierInfos(PurchaseRequestItem $item): Collection
    {
        $product = $item->product;
        if (! $product) {
            return collect();
        }

        if (! $product->relationLoaded('supplierInfos')) {
            $product->load('supplierInfos.supplier');
        }

        $scoreMap = $this->supplierPerformanceService->scoreMap(
            companyId: $product->company_id,
            supplierIds: $product->supplierInfos->pluck('supplier_id')->all(),
        );

        return $product->supplierInfos
            ->filter(fn (ProductSupplier $info) => $info->supplier?->is_active)
            ->sort(function (ProductSupplier $left, ProductSupplier $right) use ($scoreMap): int {
                $leftPreferred = $left->is_preferred ? 0 : 1;
                $rightPreferred = $right->is_preferred ? 0 : 1;
                if ($leftPreferred !== $rightPreferred) {
                    return $leftPreferred <=> $rightPreferred;
                }

                $leftScore = (float) ($scoreMap[$left->supplier_id]['score'] ?? 0);
                $rightScore = (float) ($scoreMap[$right->supplier_id]['score'] ?? 0);
                if (abs($leftScore - $rightScore) > 0.0001) {
                    return $leftScore < $rightScore ? 1 : -1;
                }

                $leftHasCost = $left->unit_cost === null ? 1 : 0;
                $rightHasCost = $right->unit_cost === null ? 1 : 0;
                if ($leftHasCost !== $rightHasCost) {
                    return $leftHasCost <=> $rightHasCost;
                }

                $costCompare = (float) ($left->unit_cost ?? 999999999) <=> (float) ($right->unit_cost ?? 999999999);
                if ($costCompare !== 0) {
                    return $costCompare;
                }

                $leadCompare = (int) ($left->lead_time_days ?? 999999) <=> (int) ($right->lead_time_days ?? 999999);
                if ($leadCompare !== 0) {
                    return $leadCompare;
                }

                return strcmp((string) ($left->supplier?->name ?? ''), (string) ($right->supplier?->name ?? ''));
            })
            ->values();
    }

    private function ensureConvertible(PurchaseRequest $request): void
    {
        if ($request->status !== 'approved') {
            throw ValidationException::withMessages([
                'request' => 'Seules les demandes approuvees peuvent etre converties en commande fournisseur.',
            ]);
        }

        if ($request->converted_purchase_order_id || PurchaseOrder::query()->where('source_purchase_request_id', $request->id)->exists()) {
            throw ValidationException::withMessages([
                'request' => 'Cette demande est deja liee a une ou plusieurs commandes fournisseurs.',
            ]);
        }
    }

    private function hasGeneratedOrders(PurchaseRequest $request): bool
    {
        if ($request->relationLoaded('generatedPurchaseOrders')) {
            return $request->generatedPurchaseOrders->isNotEmpty() || (bool) $request->converted_purchase_order_id;
        }

        return (bool) $request->converted_purchase_order_id
            || PurchaseOrder::query()->where('source_purchase_request_id', $request->id)->exists();
    }

    private function purchaseRequestRelations(): array
    {
        return [
            'warehouse',
            'branch',
            'creator',
            'approver',
            'rejector',
            'originSalesOrder.customer',
            'items.product.supplierInfos.supplier',
            'items.originSalesOrderItem.product',
            'convertedPurchaseOrder',
            'generatedPurchaseOrders.supplier',
        ];
    }
}




