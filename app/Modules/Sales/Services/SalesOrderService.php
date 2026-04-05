<?php

namespace App\Modules\Sales\Services;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Company\Services\DocumentNumberService;
use App\Modules\Core\Company\Services\PricingService;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Partners\Models\Partner;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Models\SalesQuote;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesOrderService
{
    public function __construct(
        private readonly SalesInvoiceService $salesInvoiceService,
        private readonly DocumentNumberService $documentNumberService,
        private readonly PricingService $pricingService,
        private readonly StockService $stockService,
    ) {
    }

    public function create(int $companyId, int $branchId, Partner $customer, array $payload, Collection $items, User $user): SalesOrder
    {
        return DB::transaction(function () use ($companyId, $branchId, $customer, $payload, $items, $user) {
            $items->each(fn (array $item) => $item['product']->assertAvailableForSale('commande client'));
            $resolvedWarehouseId = $this->resolvedWarehouseId($companyId, $branchId, $payload['warehouse_id'] ?? null);

            $order = SalesOrder::query()->create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'warehouse_id' => $resolvedWarehouseId,
                'customer_id' => $customer->id,
                'price_list_id' => $customer->price_list_id,
                'order_number' => $this->documentNumberService->nextNumber(
                    companyId: $companyId,
                    documentType: 'sales_order',
                    branchId: $branchId,
                    date: $payload['order_date'],
                ),
                'order_date' => $payload['order_date'],
                'requested_delivery_date' => $payload['requested_delivery_date'] ?? null,
                'customer_reference' => $payload['customer_reference'] ?? null,
                'source_document' => $payload['source_document'] ?? null,
                'salesperson_name' => $this->salesperson($payload, $user),
                'commitment_date' => $payload['commitment_date'] ?? null,
                'status' => 'draft',
                'subtotal' => $items->sum('line_total'),
                'total' => $items->sum('line_total'),
                'notes' => $payload['notes'] ?? null,
                'delivery_instruction' => $payload['delivery_instruction'] ?? null,
                'created_by' => $user->id,
            ]);

            foreach ($items as $item) {
                $order->items()->create([
                    'product_id' => $item['product_id'],
                    'description' => $item['description'],
                    'qty' => $item['qty'],
                    'unit_price' => $item['unit_price'],
                    'line_total' => $item['line_total'],
                ]);
            }

            return $order->load(['customer', 'priceList', 'branch', 'warehouse', 'items.product', 'creator']);
        });
    }

    public function normalizeItems(int $companyId, array $items, ?Partner $customer = null, ?User $user = null): Collection
    {
        $filteredItems = collect($items)
            ->filter(fn (array $item) => filled($item['product_id'] ?? null))
            ->values();

        $productIds = $filteredItems->pluck('product_id')->map(fn ($id) => (int) $id)->all();
        $products = Product::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');
        $priceRules = $this->pricingService->rulesForPriceList($companyId, $customer?->price_list_id, $productIds);

        return $filteredItems->map(function (array $item) use ($products, $priceRules, $user) {
            /** @var Product|null $product */
            $product = $products->get((int) $item['product_id']);

            if (! $product) {
                throw ValidationException::withMessages([
                    'items' => 'Une ligne de commande reference un produit introuvable.',
                ]);
            }

            $product->assertAvailableForSale('commande client');
            $qty = (float) $item['qty'];
            $providedPrice = isset($item['unit_price']) && $item['unit_price'] !== ''
                ? (float) $item['unit_price']
                : null;
            $unitPrice = $this->resolvedSaleUnitPrice($product, $priceRules->get($product->id), $qty, $providedPrice, $user, 'commande client');

            return [
                'product' => $product,
                'product_id' => $product->id,
                'description' => $item['description'] ?: $product->name,
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'line_total' => round($qty * $unitPrice, 2),
            ];
        });
    }

    public function createConfirmedFromQuote(SalesQuote $quote, array $payload, User $user): SalesOrder
    {
        return DB::transaction(function () use ($quote, $payload, $user) {
            $quote->loadMissing('items.product');
            $quote->items->each(fn ($item) => $item->product?->assertAvailableForSale('commande client'));
            $resolvedWarehouseId = $this->resolvedWarehouseId($quote->company_id, $quote->branch_id, $payload['warehouse_id'] ?? null);
            $normalizedItems = $quote->items->map(fn ($item) => [
                'product' => $item->product,
                'product_id' => $item->product_id,
                'description' => $item->description,
                'qty' => (float) $item->qty,
                'unit_price' => (float) $item->unit_price,
                'line_total' => (float) $item->line_total,
            ]);
            $this->assertReservableItems($normalizedItems, $quote->company_id, $quote->branch_id, $resolvedWarehouseId, 'confirmation commande client');

            $order = SalesOrder::query()->create([
                'company_id' => $quote->company_id,
                'branch_id' => $quote->branch_id,
                'warehouse_id' => $resolvedWarehouseId,
                'customer_id' => $quote->customer_id,
                'price_list_id' => $quote->price_list_id,
                'order_number' => $this->documentNumberService->nextNumber(
                    companyId: $quote->company_id,
                    documentType: 'sales_order',
                    branchId: $quote->branch_id,
                    date: $payload['order_date'],
                ),
                'order_date' => $payload['order_date'],
                'requested_delivery_date' => $payload['requested_delivery_date'] ?? null,
                'customer_reference' => $payload['customer_reference'] ?? null,
                'source_document' => $payload['source_document'] ?? $quote->quote_number,
                'salesperson_name' => $this->salesperson($payload, $user),
                'commitment_date' => $payload['commitment_date'] ?? null,
                'status' => 'confirmed',
                'subtotal' => $quote->items->sum(fn ($item) => (float) $item->line_total),
                'total' => $quote->items->sum(fn ($item) => (float) $item->line_total),
                'notes' => trim(($payload['notes'] ?? '').' Conversion du devis '.$quote->quote_number),
                'delivery_instruction' => $payload['delivery_instruction'] ?? null,
                'confirmed_at' => now(),
                'origin_sales_quote_id' => $quote->id,
                'created_by' => $user->id,
            ]);

            foreach ($quote->items as $item) {
                $order->items()->create([
                    'product_id' => $item->product_id,
                    'description' => $item->description,
                    'qty' => $item->qty,
                    'unit_price' => $item->unit_price,
                    'line_total' => $item->line_total,
                ]);
            }

            return $order->load(['customer', 'priceList', 'branch', 'warehouse', 'items.product', 'creator', 'originQuote']);
        });
    }

    public function updateStatus(SalesOrder $order, string $status): SalesOrder
    {
        return DB::transaction(function () use ($order, $status) {
            $lockedOrder = SalesOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $currentStatus = $lockedOrder->status;

            if ($currentStatus === 'converted') {
                throw ValidationException::withMessages([
                    'order' => 'Cette commande a deja ete convertie en facture.',
                ]);
            }

            if ($currentStatus === 'cancelled') {
                throw ValidationException::withMessages([
                    'order' => 'Cette commande est annulee et ne peut plus changer de statut.',
                ]);
            }

            if ($status === 'confirmed' && $currentStatus !== 'draft') {
                throw ValidationException::withMessages([
                    'order' => 'Seules les commandes brouillon peuvent etre confirmees.',
                ]);
            }

            if ($status === 'confirmed') {
                $lockedOrder->loadMissing('items.product');
                $lockedOrder->items->each(fn ($item) => $item->product?->assertAvailableForSale('confirmation commande client'));
                $normalizedItems = $lockedOrder->items->map(fn ($item) => [
                    'product' => $item->product,
                    'product_id' => $item->product_id,
                    'qty' => (float) $item->qty,
                ]);
                $this->assertReservableItems(
                    items: $normalizedItems,
                    companyId: $lockedOrder->company_id,
                    branchId: $lockedOrder->branch_id,
                    warehouseId: $lockedOrder->warehouse_id,
                    context: 'confirmation commande client',
                    excludeOrderId: $lockedOrder->id,
                );
            }

            if ($status === 'cancelled' && ! in_array($currentStatus, ['draft', 'confirmed'], true)) {
                throw ValidationException::withMessages([
                    'order' => 'Cette commande ne peut pas etre annulee depuis son statut actuel.',
                ]);
            }

            $lockedOrder->update([
                'status' => $status,
                'confirmed_at' => $status === 'confirmed' ? now() : $lockedOrder->confirmed_at,
                'cancelled_at' => $status === 'cancelled' ? now() : $lockedOrder->cancelled_at,
            ]);

            return $lockedOrder->fresh(['customer', 'priceList', 'branch', 'warehouse', 'items.product', 'creator', 'convertedInvoice', 'deliveryNotes', 'originQuote']);
        });
    }

    public function convertToInvoice(SalesOrder $order, array $payload, User $user): array
    {
        return DB::transaction(function () use ($order, $payload, $user) {
            $lockedOrder = SalesOrder::query()
                ->with(['customer', 'items.product', 'deliveryNotes'])
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedOrder->isConvertible()) {
                throw ValidationException::withMessages([
                    'order' => 'Seules les commandes confirmees peuvent etre converties en facture.',
                ]);
            }

            if ($lockedOrder->deliveryNotes->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'order' => 'Cette commande a deja un bon de livraison. Utilise la conversion depuis le bon de livraison.',
                ]);
            }

            $warehouseId = $this->resolvedWarehouseId($lockedOrder->company_id, $lockedOrder->branch_id, $lockedOrder->warehouse_id);
            $items = $lockedOrder->items->map(function ($item) {
                return [
                    'product' => $item->product,
                    'product_id' => $item->product_id,
                    'description' => $item->description,
                    'qty' => (float) $item->qty,
                    'unit_price' => (float) $item->unit_price,
                    'line_total' => (float) $item->line_total,
                ];
            });

            $this->salesInvoiceService->assertCreatable($lockedOrder->company_id, $lockedOrder->branch_id, $items, $warehouseId, $lockedOrder->id);

            $invoice = $this->salesInvoiceService->createPending(
                companyId: $lockedOrder->company_id,
                branchId: $lockedOrder->branch_id,
                customer: $lockedOrder->customer,
                payload: [
                    'invoice_date' => $payload['invoice_date'],
                    'due_date' => $payload['due_date'] ?? null,
                    'warehouse_id' => $warehouseId,
                    'notes' => trim(($payload['notes'] ?? '').' Conversion de la commande '.$lockedOrder->order_number),
                ],
                items: $items,
                user: $user,
            );

            $lockedOrder->update([
                'warehouse_id' => $warehouseId,
                'status' => 'converted',
                'converted_at' => now(),
                'converted_sales_invoice_id' => $invoice->id,
            ]);

            return [
                'order' => $lockedOrder->fresh(['customer', 'priceList', 'branch', 'warehouse', 'items.product', 'creator', 'convertedInvoice', 'deliveryNotes', 'originQuote']),
                'invoice' => $invoice,
            ];
        });
    }

    private function assertReservableItems(Collection $items, int $companyId, int $branchId, ?int $warehouseId = null, string $context = 'commande client', ?int $excludeOrderId = null): void
    {
        $groupedItems = $items
            ->filter(fn (array $item) => ($item['product']->type ?? 'stockable') === 'stockable')
            ->groupBy('product_id');

        foreach ($groupedItems as $productItems) {
            /** @var Product $product */
            $product = $productItems->first()['product'];
            $requestedQuantity = (float) $productItems->sum('qty');

            $this->stockService->assertReservableQuantity(
                product: $product,
                companyId: $companyId,
                branchId: $branchId,
                quantity: $requestedQuantity,
                warehouseId: $warehouseId,
                context: $context,
                excludeOrderId: $excludeOrderId,
            );
        }
    }

    private function resolvedWarehouseId(int $companyId, int $branchId, mixed $warehouseId = null): int
    {
        $resolved = (int) ($warehouseId ?: 0);

        return $resolved > 0
            ? $resolved
            : $this->stockService->defaultWarehouseId($companyId, $branchId);
    }

    private function salesperson(array $payload, User $user): string
    {
        $name = trim((string) ($payload['salesperson_name'] ?? ''));

        return $name !== '' ? $name : $user->name;
    }

    private function resolvedSaleUnitPrice(Product $product, Collection|array|null $priceRows, float $qty, ?float $providedPrice, ?User $user, string $context): float
    {
        $catalogPrice = (float) $product->sale_price;
        $resolvedPrice = $this->pricingService->resolveGroupedPrice($priceRows, $qty, $catalogPrice);

        if ($providedPrice === null) {
            return $resolvedPrice;
        }

        $providedPrice = round($providedPrice, 2);
        if (abs($providedPrice - $resolvedPrice) <= 0.0001 || abs($providedPrice - $catalogPrice) <= 0.0001) {
            return $resolvedPrice;
        }

        if (! $user?->hasPermission('sales.price_override')) {
            throw ValidationException::withMessages([
                'items' => 'Tu n as pas l autorisation de modifier les prix de vente sur une '.$context.'.',
            ]);
        }

        return $providedPrice;
    }
}

