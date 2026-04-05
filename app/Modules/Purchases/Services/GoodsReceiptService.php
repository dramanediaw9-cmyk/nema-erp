<?php

namespace App\Modules\Purchases\Services;

use App\Models\User;
use App\Modules\Core\Company\Services\DocumentNumberService;
use App\Modules\Inventory\Models\ProductLot;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Purchases\Models\GoodsReceipt;
use App\Modules\Purchases\Models\GoodsReceiptItem;
use App\Modules\Purchases\Models\PurchaseOrder;
use App\Modules\Purchases\Models\PurchaseOrderItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GoodsReceiptService
{
    public function __construct(
        private readonly DocumentNumberService $documentNumberService,
        private readonly StockService $stockService,
    ) {
    }

    public function createFromOrder(PurchaseOrder $order, array $payload, User $user): GoodsReceipt
    {
        return DB::transaction(function () use ($order, $payload, $user) {
            $lockedOrder = PurchaseOrder::query()
                ->with(['supplier', 'warehouse', 'items.product'])
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($lockedOrder->status, ['confirmed', 'partial_received'], true)) {
                throw ValidationException::withMessages([
                    'order_id' => 'Seules les commandes confirmees ou partiellement recues peuvent generer une reception.',
                ]);
            }

            $requestedItems = collect($payload['items'] ?? [])
                ->map(fn ($item) => is_array($item) ? $item : [])
                ->filter(fn (array $item) => filled($item['purchase_order_item_id'] ?? null) && (float) ($item['qty'] ?? 0) > 0)
                ->values();

            if ($requestedItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Renseigne au moins une ligne a recevoir.',
                ]);
            }

            $subtotal = 0;
            $resolvedItems = $requestedItems->map(function (array $item) use ($lockedOrder, $payload, &$subtotal) {
                /** @var PurchaseOrderItem|null $orderItem */
                $orderItem = $lockedOrder->items->firstWhere('id', (int) $item['purchase_order_item_id']);
                $qty = (float) $item['qty'];

                if (! $orderItem) {
                    throw ValidationException::withMessages(['items' => 'Une ligne de commande fournisseur est introuvable.']);
                }

                if ($qty > $orderItem->remainingQty()) {
                    throw ValidationException::withMessages(['items' => 'La quantite recue depasse le reliquat de la commande fournisseur.']);
                }

                $lineTotal = round($qty * (float) $orderItem->unit_cost, 2);
                $subtotal += $lineTotal;
                $tracking = $this->resolveTrackingPayload(
                    product: $orderItem->product,
                    item: $item,
                    qty: $qty,
                    receiptDate: $payload['receipt_date'],
                    companyId: $lockedOrder->company_id,
                );

                return [
                    'order_item' => $orderItem,
                    'product' => $orderItem->product,
                    'qty' => $qty,
                    'unit_cost' => (float) $orderItem->unit_cost,
                    'line_total' => $lineTotal,
                    'tracking' => $tracking,
                ];
            });

            $receipt = GoodsReceipt::query()->create([
                'company_id' => $lockedOrder->company_id,
                'branch_id' => $lockedOrder->branch_id,
                'warehouse_id' => $lockedOrder->warehouse_id,
                'purchase_order_id' => $lockedOrder->id,
                'supplier_id' => $lockedOrder->supplier_id,
                'receipt_number' => $this->documentNumberService->nextNumber(
                    companyId: $lockedOrder->company_id,
                    documentType: 'goods_receipt',
                    branchId: $lockedOrder->branch_id,
                    date: $payload['receipt_date'],
                ),
                'receipt_date' => $payload['receipt_date'],
                'status' => 'received',
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'notes' => $payload['notes'] ?? null,
                'received_at' => now(),
                'created_by' => $user->id,
            ]);

            foreach ($resolvedItems as $resolvedItem) {
                /** @var PurchaseOrderItem $orderItem */
                $orderItem = $resolvedItem['order_item'];
                $product = $resolvedItem['product'];
                $tracking = $resolvedItem['tracking'];

                $receiptItem = $receipt->items()->create([
                    'purchase_order_item_id' => $orderItem->id,
                    'product_id' => $product->id,
                    'description' => $orderItem->description,
                    'qty' => $resolvedItem['qty'],
                    'unit_cost' => $resolvedItem['unit_cost'],
                    'line_total' => $resolvedItem['line_total'],
                    'lot_number' => $tracking['lot_number'],
                    'expires_at' => $tracking['expires_at'],
                    'serial_numbers' => $tracking['serial_numbers'] ?: null,
                ]);

                $orderItem->increment('received_qty', $resolvedItem['qty']);

                if ($product && $product->type === 'stockable') {
                    $this->recordTrackedReceiptStock(
                        product: $product,
                        receipt: $receipt,
                        receiptItem: $receiptItem,
                        quantity: (float) $resolvedItem['qty'],
                        unitCost: (float) $resolvedItem['unit_cost'],
                        tracking: $tracking,
                        user: $user,
                    );
                }
            }

            $lockedOrder->refresh();
            $remaining = $lockedOrder->items->sum(fn (PurchaseOrderItem $item) => $item->remainingQty());
            $lockedOrder->update([
                'status' => $remaining > 0 ? 'partial_received' : 'received',
                'received_at' => $remaining > 0 ? null : now(),
            ]);

            return $receipt->load(['supplier', 'warehouse', 'branch', 'purchaseOrder', 'items.product', 'items.productLots', 'creator']);
        });
    }

    private function resolveTrackingPayload($product, array $item, float $qty, string $receiptDate, int $companyId): array
    {
        $trackingType = $product && $product->type === 'stockable'
            ? ($product->tracking_type ?: 'none')
            : 'none';
        $lotNumber = filled($item['lot_number'] ?? null) ? trim((string) $item['lot_number']) : null;
        $expiresAt = filled($item['expires_at'] ?? null) ? Carbon::parse($item['expires_at'])->toDateString() : null;

        if ($expiresAt && Carbon::parse($expiresAt)->lt(Carbon::parse($receiptDate))) {
            throw ValidationException::withMessages([
                'items' => 'La date de peremption doit etre posterieure ou egale a la date de reception.',
            ]);
        }

        if ($trackingType === 'lot') {
            if (! $lotNumber) {
                throw ValidationException::withMessages([
                    'items' => 'Les produits suivis par lot exigent un numero de lot a la reception.',
                ]);
            }

            return [
                'tracking_type' => 'lot',
                'lot_number' => $lotNumber,
                'expires_at' => $expiresAt,
                'serial_numbers' => [],
            ];
        }

        if ($trackingType === 'serial') {
            if (abs($qty - round($qty)) > 0.0001) {
                throw ValidationException::withMessages([
                    'items' => 'Un produit suivi par numero de serie doit etre receptionne en quantite entiere.',
                ]);
            }

            $rawSerials = collect(preg_split('/[\r\n,;]+/', (string) ($item['serial_numbers_text'] ?? '')))
                ->map(fn ($value) => trim((string) $value))
                ->filter()
                ->values();

            if ($rawSerials->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Renseigne les numeros de serie pour les produits suivis individuellement.',
                ]);
            }

            if ($rawSerials->count() !== $rawSerials->unique()->count()) {
                throw ValidationException::withMessages([
                    'items' => 'Chaque numero de serie doit etre unique dans la reception.',
                ]);
            }

            if ($rawSerials->count() !== (int) round($qty)) {
                throw ValidationException::withMessages([
                    'items' => 'Le nombre de numeros de serie doit correspondre exactement a la quantite recue.',
                ]);
            }

            $existingSerial = ProductLot::query()
                ->where('company_id', $companyId)
                ->where('product_id', $product->id)
                ->whereIn('serial_number', $rawSerials->all())
                ->first();

            if ($existingSerial) {
                throw ValidationException::withMessages([
                    'items' => 'Au moins un numero de serie existe deja pour ce produit.',
                ]);
            }

            return [
                'tracking_type' => 'serial',
                'lot_number' => null,
                'expires_at' => $expiresAt,
                'serial_numbers' => $rawSerials->all(),
            ];
        }

        return [
            'tracking_type' => 'none',
            'lot_number' => null,
            'expires_at' => null,
            'serial_numbers' => [],
        ];
    }

    private function recordTrackedReceiptStock($product, GoodsReceipt $receipt, GoodsReceiptItem $receiptItem, float $quantity, float $unitCost, array $tracking, User $user): void
    {
        if (($tracking['tracking_type'] ?? 'none') === 'serial') {
            foreach ($tracking['serial_numbers'] as $serialNumber) {
                $productLot = ProductLot::query()->create([
                    'company_id' => $receipt->company_id,
                    'branch_id' => $receipt->branch_id,
                    'warehouse_id' => $receipt->warehouse_id,
                    'product_id' => $product->id,
                    'goods_receipt_id' => $receipt->id,
                    'goods_receipt_item_id' => $receiptItem->id,
                    'tracking_type' => 'serial',
                    'serial_number' => $serialNumber,
                    'expires_at' => $tracking['expires_at'],
                    'received_at' => $receipt->receipt_date,
                    'unit_cost' => $unitCost,
                    'quantity_received' => 1,
                    'quantity_available' => 1,
                    'notes' => $receipt->notes,
                ]);

                $this->stockService->recordPurchase(
                    product: $product,
                    companyId: $receipt->company_id,
                    branchId: $receipt->branch_id,
                    quantity: 1,
                    unitCost: $unitCost,
                    referenceType: GoodsReceipt::class,
                    referenceId: $receipt->id,
                    movementDate: $receipt->receipt_date,
                    user: $user,
                    warehouseId: $receipt->warehouse_id,
                    reason: 'Reception fournisseur',
                    notes: 'Serie '.$serialNumber,
                    productLotId: $productLot->id,
                );
            }

            return;
        }

        $productLotId = null;
        $notes = null;

        if (($tracking['tracking_type'] ?? 'none') === 'lot') {
            $productLot = ProductLot::query()->create([
                'company_id' => $receipt->company_id,
                'branch_id' => $receipt->branch_id,
                'warehouse_id' => $receipt->warehouse_id,
                'product_id' => $product->id,
                'goods_receipt_id' => $receipt->id,
                'goods_receipt_item_id' => $receiptItem->id,
                'tracking_type' => 'lot',
                'lot_number' => $tracking['lot_number'],
                'expires_at' => $tracking['expires_at'],
                'received_at' => $receipt->receipt_date,
                'unit_cost' => $unitCost,
                'quantity_received' => $quantity,
                'quantity_available' => $quantity,
                'notes' => $receipt->notes,
            ]);

            $productLotId = $productLot->id;
            $notes = 'Lot '.$tracking['lot_number'];
            if ($tracking['expires_at']) {
                $notes .= ' · Exp '.$tracking['expires_at'];
            }
        }

        $this->stockService->recordPurchase(
            product: $product,
            companyId: $receipt->company_id,
            branchId: $receipt->branch_id,
            quantity: $quantity,
            unitCost: $unitCost,
            referenceType: GoodsReceipt::class,
            referenceId: $receipt->id,
            movementDate: $receipt->receipt_date,
            user: $user,
            warehouseId: $receipt->warehouse_id,
            reason: 'Reception fournisseur',
            notes: $notes,
            productLotId: $productLotId,
        );
    }
}
