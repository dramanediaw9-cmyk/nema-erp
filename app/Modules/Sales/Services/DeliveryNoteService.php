<?php

namespace App\Modules\Sales\Services;

use App\Models\User;
use App\Modules\Core\Company\Services\DocumentNumberService;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Sales\Models\DeliveryNote;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Models\SalesOrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryNoteService
{
    public function __construct(
        private readonly DocumentNumberService $documentNumberService,
        private readonly SalesInvoiceService $salesInvoiceService,
        private readonly StockService $stockService,
    ) {
    }

    public function createFromOrder(SalesOrder $order, array $payload, User $user): DeliveryNote
    {
        return DB::transaction(function () use ($order, $payload, $user) {
            $lockedOrder = SalesOrder::query()
                ->with(['customer', 'items.product', 'deliveryNotes'])
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($lockedOrder->status, ['confirmed', 'partial_delivered'], true)) {
                throw ValidationException::withMessages([
                    'order_id' => 'Seules les commandes confirmees ou partiellement livrees peuvent generer un bon de livraison.',
                ]);
            }

            if ($lockedOrder->converted_sales_invoice_id) {
                throw ValidationException::withMessages([
                    'order_id' => 'Cette commande a deja ete convertie en facture.',
                ]);
            }

            $providedItems = collect($payload['items'] ?? [])
                ->map(fn ($item) => is_array($item) ? $item : [])
                ->filter(fn (array $item) => filled($item['sales_order_item_id'] ?? null) || filled($item['qty'] ?? null))
                ->values();

            $requestedItems = $providedItems
                ->filter(fn (array $item) => filled($item['sales_order_item_id'] ?? null) && (float) ($item['qty'] ?? 0) > 0)
                ->values();

            if ($requestedItems->isEmpty() && $providedItems->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Renseigne au moins une quantite a livrer.',
                ]);
            }

            if ($requestedItems->isEmpty()) {
                $requestedItems = $lockedOrder->items
                    ->filter(fn (SalesOrderItem $item) => $item->remainingQty() > 0)
                    ->map(fn (SalesOrderItem $item) => [
                        'sales_order_item_id' => $item->id,
                        'qty' => $item->remainingQty(),
                    ])
                    ->values();
            }

            if ($requestedItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Cette commande est deja totalement livree.',
                ]);
            }

            $warehouseId = isset($payload['warehouse_id']) && $payload['warehouse_id']
                ? (int) $payload['warehouse_id']
                : $this->stockService->defaultWarehouseId($lockedOrder->company_id, $lockedOrder->branch_id);

            $resolvedItems = $requestedItems->map(function (array $item) use ($lockedOrder) {
                /** @var SalesOrderItem|null $orderItem */
                $orderItem = $lockedOrder->items->firstWhere('id', (int) $item['sales_order_item_id']);
                $qty = (float) $item['qty'];

                if (! $orderItem) {
                    throw ValidationException::withMessages([
                        'items' => 'Une ligne de commande client est introuvable.',
                    ]);
                }

                if ($qty > $orderItem->remainingQty()) {
                    throw ValidationException::withMessages([
                        'items' => 'La quantite livree depasse le reliquat de la commande client.',
                    ]);
                }

                return [
                    'order_item' => $orderItem,
                    'product' => $orderItem->product,
                    'product_id' => $orderItem->product_id,
                    'description' => $orderItem->description,
                    'qty' => $qty,
                    'unit_price' => (float) $orderItem->unit_price,
                    'line_total' => round($qty * (float) $orderItem->unit_price, 2),
                ];
            });

            $this->salesInvoiceService->assertCreatable($lockedOrder->company_id, $lockedOrder->branch_id, $resolvedItems, $warehouseId);

            $deliveryNote = DeliveryNote::query()->create([
                'company_id' => $lockedOrder->company_id,
                'branch_id' => $lockedOrder->branch_id,
                'warehouse_id' => $warehouseId,
                'sales_order_id' => $lockedOrder->id,
                'customer_id' => $lockedOrder->customer_id,
                'delivery_number' => $this->documentNumberService->nextNumber(
                    companyId: $lockedOrder->company_id,
                    documentType: 'delivery_note',
                    branchId: $lockedOrder->branch_id,
                    date: $payload['delivery_date'],
                ),
                'delivery_date' => $payload['delivery_date'],
                'status' => 'issued',
                'subtotal' => $resolvedItems->sum('line_total'),
                'total' => $resolvedItems->sum('line_total'),
                'notes' => $payload['notes'] ?? null,
                'issued_at' => now(),
                'created_by' => $user->id,
            ]);

            foreach ($resolvedItems as $item) {
                /** @var SalesOrderItem $orderItem */
                $orderItem = $item['order_item'];
                $product = $item['product'];

                $deliveryNote->items()->create([
                    'sales_order_item_id' => $orderItem->id,
                    'product_id' => $item['product_id'],
                    'description' => $item['description'],
                    'qty' => $item['qty'],
                    'unit_price' => $item['unit_price'],
                    'line_total' => $item['line_total'],
                ]);

                $orderItem->increment('delivered_qty', $item['qty']);

                if ($product && $product->type === 'stockable') {
                    $this->stockService->recordDelivery(
                        product: $product,
                        companyId: $lockedOrder->company_id,
                        branchId: $lockedOrder->branch_id,
                        quantity: (float) $item['qty'],
                        unitCost: (float) $product->purchase_price,
                        referenceType: DeliveryNote::class,
                        referenceId: $deliveryNote->id,
                        movementDate: $payload['delivery_date'],
                        user: $user,
                        warehouseId: $warehouseId,
                    );
                }
            }

            $lockedOrder->refresh();
            $remaining = $lockedOrder->items->sum(fn (SalesOrderItem $item) => $item->remainingQty());
            $lockedOrder->update([
                'status' => $remaining > 0 ? 'partial_delivered' : 'delivered',
            ]);

            return $deliveryNote->load(['customer', 'branch', 'warehouse', 'salesOrder', 'items.product', 'items.orderItem', 'creator']);
        });
    }

    public function convertToInvoice(DeliveryNote $deliveryNote, array $payload, User $user): array
    {
        return DB::transaction(function () use ($deliveryNote, $payload, $user) {
            $lockedDelivery = DeliveryNote::query()
                ->with(['customer', 'salesOrder.items', 'items.product'])
                ->whereKey($deliveryNote->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedDelivery->isConvertible()) {
                throw ValidationException::withMessages([
                    'delivery_note' => 'Seuls les bons de livraison emis peuvent etre convertis en facture.',
                ]);
            }

            $items = $lockedDelivery->items->map(function ($item) {
                return [
                    'product' => $item->product,
                    'product_id' => $item->product_id,
                    'description' => $item->description,
                    'qty' => (float) $item->qty,
                    'unit_price' => (float) $item->unit_price,
                    'line_total' => (float) $item->line_total,
                ];
            });

            $invoice = $this->salesInvoiceService->createPending(
                companyId: $lockedDelivery->company_id,
                branchId: $lockedDelivery->branch_id,
                customer: $lockedDelivery->customer,
                payload: [
                    'invoice_date' => $payload['invoice_date'],
                    'due_date' => $payload['due_date'] ?? null,
                    'warehouse_id' => $lockedDelivery->warehouse_id,
                    'notes' => trim(($payload['notes'] ?? '').' Conversion du bon de livraison '.$lockedDelivery->delivery_number),
                    'origin_delivery_note_id' => $lockedDelivery->id,
                    'stock_posted' => true,
                ],
                items: $items,
                user: $user,
            );

            $lockedDelivery->update([
                'status' => 'invoiced',
                'converted_at' => now(),
                'converted_sales_invoice_id' => $invoice->id,
            ]);

            if ($lockedDelivery->salesOrder) {
                $order = $lockedDelivery->salesOrder->fresh(['items']);
                $remaining = $order->items->sum(fn (SalesOrderItem $item) => $item->remainingQty());

                $order->update([
                    'status' => $remaining > 0 ? 'partial_delivered' : 'delivered',
                ]);
            }

            return [
                'delivery_note' => $lockedDelivery->fresh(['customer', 'branch', 'warehouse', 'salesOrder', 'items.product', 'items.orderItem', 'creator', 'convertedInvoice']),
                'invoice' => $invoice,
            ];
        });
    }
}


