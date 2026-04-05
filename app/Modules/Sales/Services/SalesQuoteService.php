<?php

namespace App\Modules\Sales\Services;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Company\Services\DocumentNumberService;
use App\Modules\Core\Company\Services\PricingService;
use App\Modules\Partners\Models\Partner;
use App\Modules\Sales\Models\SalesQuote;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesQuoteService
{
    public function __construct(
        private readonly DocumentNumberService $documentNumberService,
        private readonly SalesInvoiceService $salesInvoiceService,
        private readonly SalesOrderService $salesOrderService,
        private readonly PricingService $pricingService,
    ) {
    }

    public function create(int $companyId, int $branchId, Partner $customer, array $payload, Collection $items, User $user): SalesQuote
    {
        return DB::transaction(function () use ($companyId, $branchId, $customer, $payload, $items, $user) {
            $quote = SalesQuote::query()->create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'customer_id' => $customer->id,
                'price_list_id' => $customer->price_list_id,
                'quote_number' => $this->documentNumberService->nextNumber(
                    companyId: $companyId,
                    documentType: 'sales_quote',
                    branchId: $branchId,
                    date: $payload['quote_date'],
                ),
                'quote_date' => $payload['quote_date'],
                'valid_until' => $payload['valid_until'] ?? null,
                'status' => 'draft',
                'subtotal' => $items->sum('line_total'),
                'total' => $items->sum('line_total'),
                'notes' => $payload['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            foreach ($items as $item) {
                $quote->items()->create([
                    'product_id' => $item['product_id'],
                    'description' => $item['description'],
                    'qty' => $item['qty'],
                    'unit_price' => $item['unit_price'],
                    'line_total' => $item['line_total'],
                ]);
            }

            return $quote->load(['customer', 'priceList', 'branch', 'items.product', 'creator']);
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
                    'items' => 'Une ligne de devis reference un produit introuvable.',
                ]);
            }

            $product->assertAvailableForSale('devis');
            $qty = (float) $item['qty'];
            $providedPrice = isset($item['unit_price']) && $item['unit_price'] !== ''
                ? (float) $item['unit_price']
                : null;
            $unitPrice = $this->resolvedSaleUnitPrice($product, $priceRules->get($product->id), $qty, $providedPrice, $user, 'devis');

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

    public function updateStatus(SalesQuote $quote, string $status): SalesQuote
    {
        return DB::transaction(function () use ($quote, $status) {
            $lockedQuote = SalesQuote::query()->whereKey($quote->id)->lockForUpdate()->firstOrFail();
            $currentStatus = $lockedQuote->status;

            if ($currentStatus === 'converted') {
                throw ValidationException::withMessages([
                    'quote' => 'Ce devis a deja ete converti en document commercial.',
                ]);
            }

            if ($currentStatus === 'cancelled') {
                throw ValidationException::withMessages([
                    'quote' => 'Ce devis est annule et ne peut plus changer de statut.',
                ]);
            }

            if ($status === 'sent' && ! in_array($currentStatus, ['draft', 'sent'], true)) {
                throw ValidationException::withMessages([
                    'quote' => 'Seuls les devis brouillon peuvent etre envoyes.',
                ]);
            }

            if ($status === 'accepted' && ! in_array($currentStatus, ['draft', 'sent', 'accepted'], true)) {
                throw ValidationException::withMessages([
                    'quote' => 'Ce devis ne peut pas etre accepte depuis son statut actuel.',
                ]);
            }

            if ($status === 'cancelled' && ! in_array($currentStatus, ['draft', 'sent', 'accepted'], true)) {
                throw ValidationException::withMessages([
                    'quote' => 'Ce devis ne peut pas etre annule depuis son statut actuel.',
                ]);
            }

            $lockedQuote->update([
                'status' => $status,
                'sent_at' => $status === 'sent' ? now() : $lockedQuote->sent_at,
                'accepted_at' => $status === 'accepted' ? now() : $lockedQuote->accepted_at,
            ]);

            return $lockedQuote->fresh(['customer', 'priceList', 'branch', 'items.product', 'creator', 'convertedInvoice', 'convertedOrder']);
        });
    }

    public function convertToOrder(SalesQuote $quote, array $payload, User $user): array
    {
        return DB::transaction(function () use ($quote, $payload, $user) {
            $lockedQuote = SalesQuote::query()
                ->with(['customer', 'items.product'])
                ->whereKey($quote->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedQuote->isConvertible()) {
                throw ValidationException::withMessages([
                    'quote' => 'Seuls les devis acceptes peuvent etre convertis en commande.',
                ]);
            }

            $order = $this->salesOrderService->createConfirmedFromQuote(
                $lockedQuote,
                [
                    'order_date' => $payload['order_date'],
                    'requested_delivery_date' => $payload['requested_delivery_date'] ?? null,
                    'notes' => $payload['notes'] ?? null,
                ],
                $user,
            );

            $lockedQuote->update([
                'status' => 'converted',
                'converted_at' => now(),
                'converted_sales_order_id' => $order->id,
            ]);

            return [
                'quote' => $lockedQuote->fresh(['customer', 'priceList', 'branch', 'items.product', 'creator', 'convertedInvoice', 'convertedOrder']),
                'order' => $order,
            ];
        });
    }

    public function convertToInvoice(SalesQuote $quote, array $payload, User $user)
    {
        return DB::transaction(function () use ($quote, $payload, $user) {
            $lockedQuote = SalesQuote::query()
                ->with(['customer', 'items.product'])
                ->whereKey($quote->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedQuote->isConvertible()) {
                throw ValidationException::withMessages([
                    'quote' => 'Seuls les devis acceptes peuvent etre convertis en facture.',
                ]);
            }

            $items = $lockedQuote->items->map(function ($item) {
                return [
                    'product' => $item->product,
                    'product_id' => $item->product_id,
                    'description' => $item->description,
                    'qty' => (float) $item->qty,
                    'unit_price' => (float) $item->unit_price,
                    'line_total' => (float) $item->line_total,
                ];
            });

            $this->salesInvoiceService->assertCreatable($lockedQuote->company_id, $lockedQuote->branch_id, $items);

            $invoice = $this->salesInvoiceService->createPending(
                companyId: $lockedQuote->company_id,
                branchId: $lockedQuote->branch_id,
                customer: $lockedQuote->customer,
                payload: [
                    'invoice_date' => $payload['invoice_date'],
                    'due_date' => $payload['due_date'] ?? null,
                    'notes' => trim(($payload['notes'] ?? '').' Conversion du devis '.$lockedQuote->quote_number),
                ],
                items: $items,
                user: $user,
            );

            $lockedQuote->update([
                'status' => 'converted',
                'converted_at' => now(),
                'converted_sales_invoice_id' => $invoice->id,
            ]);

            return [
                'quote' => $lockedQuote->fresh(['customer', 'priceList', 'branch', 'items.product', 'creator', 'convertedInvoice', 'convertedOrder']),
                'invoice' => $invoice,
            ];
        });
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
                'items' => 'Tu n as pas l autorisation de modifier les prix de vente sur un '.$context.'.',
            ]);
        }

        return $providedPrice;
    }
}

