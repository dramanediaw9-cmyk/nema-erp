<?php

namespace App\Modules\Purchases\Services;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Company\Services\DocumentNumberService;
use App\Modules\Core\Company\Services\PricingService;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Partners\Models\Partner;
use App\Modules\Purchases\Models\PurchaseOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrderService
{
    public function __construct(
        private readonly DocumentNumberService $documentNumberService,
        private readonly PricingService $pricingService,
    ) {
    }

    public function normalizeItems(int $companyId, array $items, ?Partner $supplier = null): Collection
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
        $priceRules = $this->pricingService->rulesForPriceList($companyId, $supplier?->price_list_id, $productIds);

        return $filteredItems->map(function (array $item) use ($products, $priceRules, $supplier) {
            /** @var Product|null $product */
            $product = $products->get((int) $item['product_id']);

            if (! $product) {
                throw ValidationException::withMessages([
                    'items' => 'Une ligne de commande fournisseur reference un produit introuvable.',
                ]);
            }

            $product->assertAvailableForPurchase('commande fournisseur');
            $supplierInfo = $product->supplierInfoFor($supplier);
            $qty = (float) $item['qty'];
            $providedCost = isset($item['unit_cost']) && $item['unit_cost'] !== ''
                ? (float) $item['unit_cost']
                : null;
            $catalogCost = (float) $product->purchase_price;
            $autoCost = $supplierInfo?->unit_cost !== null
                ? (float) $supplierInfo->unit_cost
                : $this->pricingService->resolveGroupedPrice($priceRules->get($product->id), $qty, $catalogCost);
            $isAutomaticCost = $providedCost === null
                || abs($providedCost - $catalogCost) <= 0.0001
                || abs($providedCost - $autoCost) <= 0.0001;
            $unitCost = $isAutomaticCost ? $autoCost : $providedCost;

            return [
                'product' => $product,
                'product_id' => $product->id,
                'description' => $item['description'] ?: ($supplierInfo?->supplier_product_name ?: ($product->purchase_description ?: $product->name)),
                'qty' => $qty,
                'unit_cost' => $unitCost,
                'line_total' => round($qty * $unitCost, 2),
            ];
        });
    }

    public function create(int $companyId, int $branchId, Warehouse $warehouse, Partner $supplier, array $payload, Collection $items, User $user): PurchaseOrder
    {
        return DB::transaction(function () use ($companyId, $branchId, $warehouse, $supplier, $payload, $items, $user) {
            $items->each(fn (array $item) => $item['product']->assertAvailableForPurchase('commande fournisseur'));
            $order = PurchaseOrder::query()->create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'warehouse_id' => $warehouse->id,
                'source_purchase_request_id' => $payload['source_purchase_request_id'] ?? null,
                'supplier_id' => $supplier->id,
                'price_list_id' => $supplier->price_list_id,
                'order_number' => $this->documentNumberService->nextNumber(
                    companyId: $companyId,
                    documentType: 'purchase_order',
                    branchId: $branchId,
                    date: $payload['order_date'],
                ),
                'order_date' => $payload['order_date'],
                'expected_receipt_date' => $payload['expected_receipt_date'] ?? null,
                'status' => 'draft',
                'subtotal' => $items->sum('line_total'),
                'total' => $items->sum('line_total'),
                'notes' => $payload['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            foreach ($items as $item) {
                $order->items()->create([
                    'product_id' => $item['product_id'],
                    'description' => $item['description'],
                    'qty' => $item['qty'],
                    'received_qty' => 0,
                    'unit_cost' => $item['unit_cost'],
                    'line_total' => $item['line_total'],
                ]);
            }

            return $order->load(['supplier', 'priceList', 'warehouse', 'branch', 'sourcePurchaseRequest', 'items.product', 'creator']);
        });
    }

    public function updateStatus(PurchaseOrder $order, string $status): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $status) {
            $lockedOrder = PurchaseOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $currentStatus = $lockedOrder->status;

            if ($currentStatus === 'cancelled') {
                throw ValidationException::withMessages(['order' => 'Cette commande fournisseur est annulee.']);
            }

            if ($status === 'confirmed' && $currentStatus !== 'draft') {
                throw ValidationException::withMessages(['order' => 'Seules les commandes brouillon peuvent etre confirmees.']);
            }

            if ($status === 'confirmed') {
                $lockedOrder->loadMissing('items.product');
                $lockedOrder->items->each(fn ($item) => $item->product?->assertAvailableForPurchase('confirmation commande fournisseur'));
            }

            if ($status === 'cancelled' && ! in_array($currentStatus, ['draft', 'confirmed'], true)) {
                throw ValidationException::withMessages(['order' => 'Cette commande ne peut pas etre annulee depuis son statut actuel.']);
            }

            $lockedOrder->update([
                'status' => $status,
                'confirmed_at' => $status === 'confirmed' ? now() : $lockedOrder->confirmed_at,
                'cancelled_at' => $status === 'cancelled' ? now() : $lockedOrder->cancelled_at,
            ]);

            return $lockedOrder->fresh(['supplier', 'priceList', 'warehouse', 'branch', 'sourcePurchaseRequest', 'items.product', 'creator', 'goodsReceipts']);
        });
    }
}
