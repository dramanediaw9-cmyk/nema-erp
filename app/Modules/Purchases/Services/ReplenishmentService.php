<?php

namespace App\Modules\Purchases\Services;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Purchases\Models\PurchaseOrderItem;
use App\Modules\Purchases\Models\PurchaseRequest;
use App\Modules\Purchases\Models\PurchaseRequestItem;
use Illuminate\Support\Collection;

class ReplenishmentService
{
    public function __construct(private readonly PurchaseRequestService $purchaseRequestService)
    {
    }

    public function suggestionsForWarehouse(int $companyId, Warehouse $warehouse): Collection
    {
        $products = Product::query()
            ->with(['parent', 'supplierInfos.supplier'])
            ->where('company_id', $companyId)
            ->where('type', 'stockable')
            ->purchasable()
            ->where('auto_replenish', true)
            ->orderBy('name')
            ->get();

        if ($products->isEmpty()) {
            return collect();
        }

        $currentStocks = StockMovement::query()
            ->where('company_id', $companyId)
            ->where('warehouse_id', $warehouse->id)
            ->selectRaw('product_id, COALESCE(SUM(quantity_in - quantity_out), 0) as current_stock')
            ->groupBy('product_id')
            ->pluck('current_stock', 'product_id');

        $incomingQuantities = PurchaseOrderItem::query()
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->where('purchase_orders.company_id', $companyId)
            ->where('purchase_orders.warehouse_id', $warehouse->id)
            ->whereIn('purchase_orders.status', ['confirmed', 'partial_received'])
            ->selectRaw('purchase_order_items.product_id, SUM(CASE WHEN purchase_order_items.qty > purchase_order_items.received_qty THEN purchase_order_items.qty - purchase_order_items.received_qty ELSE 0 END) as incoming_qty')
            ->groupBy('purchase_order_items.product_id')
            ->pluck('incoming_qty', 'purchase_order_items.product_id');

        $openRequestQuantities = PurchaseRequestItem::query()
            ->join('purchase_requests', 'purchase_requests.id', '=', 'purchase_request_items.purchase_request_id')
            ->where('purchase_requests.company_id', $companyId)
            ->where('purchase_requests.warehouse_id', $warehouse->id)
            ->whereNull('purchase_requests.converted_purchase_order_id')
            ->whereIn('purchase_requests.status', ['pending_approval', 'approved'])
            ->selectRaw('purchase_request_items.product_id, SUM(purchase_request_items.qty) as open_request_qty')
            ->groupBy('purchase_request_items.product_id')
            ->pluck('open_request_qty', 'purchase_request_items.product_id');

        return $products
            ->map(function (Product $product) use ($currentStocks, $incomingQuantities, $openRequestQuantities) {
                $supplierInfo = $product->supplierInfoFor();
                $minStock = (float) $product->min_stock;
                $targetStock = max((float) ($product->reorder_max_qty ?: 0), $minStock);
                $currentStock = (float) ($currentStocks[$product->id] ?? 0);
                $incomingQty = (float) ($incomingQuantities[$product->id] ?? 0);
                $openRequestQty = (float) ($openRequestQuantities[$product->id] ?? 0);
                $projectedStock = $currentStock + $incomingQty + $openRequestQty;

                if ($targetStock <= 0 || $projectedStock > $minStock) {
                    return null;
                }

                $rawSuggestedQty = max(0, $targetStock - $projectedStock);
                $multipleQty = (float) ($product->reorder_multiple_qty ?: 0);
                $supplierMinimumQty = (float) ($supplierInfo?->min_qty ?: 0);
                $suggestedQty = max($this->roundUpToMultiple($rawSuggestedQty, $multipleQty), $supplierMinimumQty > 0 ? $supplierMinimumQty : 0);

                if ($suggestedQty <= 0.0001) {
                    return null;
                }

                $estimatedUnitCost = (float) ($supplierInfo?->unit_cost ?: $product->purchase_price);
                $leadTimeDays = (int) ($supplierInfo?->lead_time_days ?? $product->purchase_lead_time_days ?? 0);

                return [
                    'product' => $product,
                    'supplier_info' => $supplierInfo,
                    'supplier_name' => $supplierInfo?->supplier?->name,
                    'supplier_product_code' => $supplierInfo?->supplier_product_code,
                    'supplier_product_name' => $supplierInfo?->supplier_product_name,
                    'current_stock' => $currentStock,
                    'incoming_qty' => $incomingQty,
                    'open_request_qty' => $openRequestQty,
                    'projected_stock' => $projectedStock,
                    'min_stock' => $minStock,
                    'target_stock' => $targetStock,
                    'multiple_qty' => $multipleQty,
                    'supplier_min_qty' => $supplierMinimumQty,
                    'suggested_qty' => $suggestedQty,
                    'estimated_unit_cost' => $estimatedUnitCost,
                    'estimated_total' => round($suggestedQty * $estimatedUnitCost, 2),
                    'purchase_lead_time_days' => $leadTimeDays,
                    'priority' => $this->priorityFor($minStock, $currentStock, $projectedStock),
                ];
            })
            ->filter()
            ->sortBy([
                fn (array $item) => match ($item['priority']) {
                    'urgent' => 0,
                    'high' => 1,
                    default => 2,
                },
                fn (array $item) => $item['product']->display_name,
            ])
            ->values();
    }

    public function createPurchaseRequestFromSuggestions(
        int $companyId,
        int $branchId,
        Warehouse $warehouse,
        Collection $suggestions,
        User $user,
    ): PurchaseRequest {
        $payload = [
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays((int) $suggestions->max('purchase_lead_time_days'))->toDateString(),
            'priority' => $this->highestPriority($suggestions),
            'notes' => 'Reappro automatique genere depuis les regles min/max du depot '.$warehouse->name.'.',
        ];

        $items = $suggestions->map(function (array $suggestion) {
            /** @var Product $product */
            $product = $suggestion['product'];
            $qty = (float) $suggestion['suggested_qty'];
            $unitCost = (float) $suggestion['estimated_unit_cost'];

            return [
                'product' => $product,
                'product_id' => $product->id,
                'description' => $suggestion['supplier_product_name'] ?: ($product->purchase_description ?: $product->display_name),
                'qty' => $qty,
                'estimated_unit_cost' => $unitCost,
                'line_total' => round($qty * $unitCost, 2),
            ];
        })->values();

        return $this->purchaseRequestService->create(
            companyId: $companyId,
            branchId: $branchId,
            warehouse: $warehouse,
            payload: $payload,
            items: $items,
            user: $user,
        );
    }

    private function roundUpToMultiple(float $quantity, float $multiple): float
    {
        if ($quantity <= 0) {
            return 0;
        }

        if ($multiple <= 0) {
            return round($quantity, 3);
        }

        return round(ceil($quantity / $multiple) * $multiple, 3);
    }

    private function priorityFor(float $minStock, float $currentStock, float $projectedStock): string
    {
        if ($currentStock <= 0 || $projectedStock <= 0) {
            return 'urgent';
        }

        if ($minStock > 0 && $projectedStock <= ($minStock / 2)) {
            return 'high';
        }

        return 'normal';
    }

    private function highestPriority(Collection $suggestions): string
    {
        if ($suggestions->contains(fn (array $item) => $item['priority'] === 'urgent')) {
            return 'urgent';
        }

        if ($suggestions->contains(fn (array $item) => $item['priority'] === 'high')) {
            return 'high';
        }

        return 'normal';
    }
}