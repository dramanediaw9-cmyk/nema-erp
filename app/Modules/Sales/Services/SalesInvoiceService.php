<?php

namespace App\Modules\Sales\Services;

use App\Models\User;
use App\Modules\Accounting\Services\AccountingService;
use App\Modules\Accounting\Services\PeriodLockService;
use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Company\Services\DocumentNumberService;
use App\Modules\Core\Company\Services\PricingService;
use App\Modules\Core\Integrations\Services\IntegrationOutboxService;
use App\Modules\Partners\Models\Partner;
use App\Modules\Sales\Models\SalesInvoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesInvoiceService
{
    public function __construct(
        private readonly DocumentNumberService $documentNumberService,
        private readonly \App\Modules\Inventory\Services\StockService $stockService,
        private readonly AccountingService $accountingService,
        private readonly PeriodLockService $periodLockService,
        private readonly PricingService $pricingService,
        private readonly IntegrationOutboxService $integrationOutboxService,
    ) {
    }

    public function createValidated(int $companyId, int $branchId, Partner $customer, array $payload, Collection $items, User $user): SalesInvoice
    {
        return $this->persistInvoice($companyId, $branchId, $customer, $payload, $items, $user);
    }

    public function createPending(int $companyId, int $branchId, Partner $customer, array $payload, Collection $items, User $user): SalesInvoice
    {
        return $this->persistInvoice($companyId, $branchId, $customer, $payload, $items, $user, null, 'pending_approval', false);
    }

    public function createHistorical(int $companyId, int $branchId, Partner $customer, string $invoiceNumber, array $payload, Collection $items, User $user): SalesInvoice
    {
        return $this->persistInvoice($companyId, $branchId, $customer, $payload, $items, $user, $invoiceNumber);
    }

    public function approve(SalesInvoice $invoice, User $user): SalesInvoice
    {
        return DB::transaction(function () use ($invoice, $user) {
            $invoice = SalesInvoice::query()
                ->with(['customer.paymentTerm', 'customer.priceList', 'branch', 'warehouse', 'items.product'])
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($invoice->status !== 'pending_approval') {
                throw ValidationException::withMessages([
                    'sale' => 'Cette facture client n est pas en attente d approbation.',
                ]);
            }

            $this->periodLockService->assertDateOpen($invoice->company_id, $invoice->invoice_date?->toDateString() ?? now()->toDateString(), 'invoice_date');

            $stockPosted = (bool) $invoice->stock_posted;

            if (! $stockPosted) {
                foreach ($invoice->items as $item) {
                    $product = $item->product;
                    $product?->assertAvailableForSale('approbation facture client');

                    if ($product && $product->type === 'stockable') {
                        $this->stockService->recordSale(
                            product: $product,
                            companyId: $invoice->company_id,
                            branchId: $invoice->branch_id,
                            quantity: (float) $item->qty,
                            unitCost: (float) $product->purchase_price,
                            referenceType: SalesInvoice::class,
                            referenceId: $invoice->id,
                            movementDate: $invoice->invoice_date?->toDateString(),
                            user: $user,
                            warehouseId: $invoice->warehouse_id,
                        );
                    }
                }

                $stockPosted = true;
            }

            $approvedAt = now();

            $invoice->update([
                'status' => 'validated',
                'validated_at' => $approvedAt,
                'approved_at' => $approvedAt,
                'approved_by' => $user->id,
                'stock_posted' => $stockPosted,
            ]);

            $invoice = $invoice->fresh(['customer', 'branch', 'warehouse', 'items.product', 'originDeliveryNote']);
            $this->accountingService->recordSalesInvoice($invoice, $user);
            $this->integrationOutboxService->record($invoice, 'sales.invoice.approved', [
                'invoice_number' => $invoice->invoice_number,
                'status' => $invoice->status,
                'total' => (float) $invoice->total,
            ]);

            return $invoice->load(['approver', 'creator', 'warehouse', 'originDeliveryNote', 'paymentAllocations.payment.cashAccount']);
        });
    }

    public function cancel(SalesInvoice $invoice, User $user, ?string $reason = null): SalesInvoice
    {
        return DB::transaction(function () use ($invoice, $user, $reason) {
            $invoice = SalesInvoice::query()
                ->with(['approvalSteps', 'paymentAllocations'])
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($invoice->status === 'cancelled') {
                throw ValidationException::withMessages([
                    'sale' => 'Cette facture client est deja annulee.',
                ]);
            }

            if ($invoice->status === 'validated') {
                throw ValidationException::withMessages([
                    'sale' => 'Une facture client approuvee ne peut pas etre annulee directement. Utilise un avoir client.',
                ]);
            }

            if ((float) $invoice->amount_paid > 0 || $invoice->paymentAllocations->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'sale' => 'Cette facture en attente a deja des encaissements et ne peut pas etre annulee directement.',
                ]);
            }

            $invoice->approvalSteps()->where('status', 'pending')->delete();

            $cancelledAt = now();

            $invoice->update([
                'status' => 'cancelled',
                'validated_at' => null,
                'approved_at' => null,
                'approved_by' => null,
                'cancelled_at' => $cancelledAt,
                'cancelled_by' => $user->id,
                'notes' => $reason ? trim(trim((string) $invoice->notes).' '.trim($reason)) : $invoice->notes,
            ]);

            $invoice = $invoice->fresh(['customer', 'branch', 'warehouse', 'items.product', 'creator', 'approver', 'cancelledBy', 'approvalSteps.approver']);

            $this->integrationOutboxService->record($invoice, 'sales.invoice.cancelled', [
                'invoice_number' => $invoice->invoice_number,
                'status' => $invoice->status,
                'cancelled_at' => $cancelledAt->toIso8601String(),
                'cancelled_by' => $user->id,
            ]);

            return $invoice;
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
            ->with(['saleTaxRule'])
            ->get()
            ->keyBy('id');
        $priceRules = $this->pricingService->rulesForPriceList($companyId, $customer?->price_list_id, $productIds);

        return $filteredItems->map(function (array $item) use ($products, $priceRules, $user) {
            /** @var Product|null $product */
            $product = $products->get((int) $item['product_id']);

            if (! $product) {
                throw ValidationException::withMessages([
                    'items' => 'Un article du ticket est introuvable ou n appartient pas a l entreprise courante.',
                ]);
            }

            $product->assertAvailableForSale('facture client');
            $qty = (float) $item['qty'];
            $providedPrice = isset($item['unit_price']) && $item['unit_price'] !== ''
                ? (float) $item['unit_price']
                : null;
            $unitPrice = $this->resolvedSaleUnitPrice($product, $priceRules->get($product->id), $qty, $providedPrice, $user, 'facture client');
            $lineSubtotal = round($qty * $unitPrice, 2);
            $lineDiscount = $this->resolveLineDiscounts($lineSubtotal, $item);

            return [
                'product' => $product,
                'product_id' => $product->id,
                'description' => $item['description'] ?: $product->name,
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'line_subtotal' => $lineSubtotal,
                'discount_type' => $lineDiscount['type'],
                'discount_value' => $lineDiscount['value'],
                'discount_total' => $lineDiscount['total'],
                'line_total' => $lineDiscount['net_total'],
            ];
        });
    }

    public function assertCreatable(int $companyId, int $branchId, Collection $items, ?int $warehouseId = null, ?int $excludeOrderId = null): void
    {
        $items->each(fn (array $item) => $item['product']->assertAvailableForSale('vente'));
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
                context: 'vente',
                excludeOrderId: $excludeOrderId,
            );
        }
    }

    private function persistInvoice(
        int $companyId,
        int $branchId,
        Partner $customer,
        array $payload,
        Collection $items,
        User $user,
        ?string $invoiceNumber = null,
        string $status = 'validated',
        bool $postSideEffects = true,
    ): SalesInvoice {
        $this->periodLockService->assertDateOpen($companyId, $payload['invoice_date'], 'invoice_date');

        return DB::transaction(function () use ($companyId, $branchId, $customer, $payload, $items, $user, $invoiceNumber, $status, $postSideEffects) {
            $items->each(fn (array $item) => $item['product']->assertAvailableForSale('facture client'));
            $resolvedWarehouseId = isset($payload['warehouse_id']) && $payload['warehouse_id']
                ? (int) $payload['warehouse_id']
                : $this->stockService->defaultWarehouseId($companyId, $branchId);

            $prepared = $this->prepareInvoiceItems($companyId, $customer, $items, $payload);
            $resolvedInvoiceNumber = $invoiceNumber ?: $this->documentNumberService->nextNumber(
                companyId: $companyId,
                documentType: 'sales_invoice',
                branchId: $branchId,
                date: $payload['invoice_date'],
            );

            $validatedAt = $status === 'validated'
                ? Carbon::parse($payload['validated_at'] ?? $payload['invoice_date'])
                : null;
            $approvedAt = $status === 'validated'
                ? Carbon::parse($payload['approved_at'] ?? $payload['validated_at'] ?? $payload['invoice_date'])
                : null;
            $stockPosted = (bool) ($payload['stock_posted'] ?? false);
            $resolvedDueDate = $payload['due_date'] ?? $this->resolveDueDate($payload['invoice_date'], $customer->paymentTerm);

            $invoice = SalesInvoice::query()->create([
                'tenant_id' => $customer->tenant_id,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'warehouse_id' => $resolvedWarehouseId,
                'customer_id' => $customer->id,
                'payment_term_id' => $customer->payment_term_id,
                'price_list_id' => $customer->price_list_id,
                'origin_delivery_note_id' => $payload['origin_delivery_note_id'] ?? null,
                'sale_channel' => $payload['sale_channel'] ?? 'standard',
                'pos_session_id' => $payload['pos_session_id'] ?? null,
                'pos_sync_key' => filled($payload['pos_sync_key'] ?? null) ? trim((string) $payload['pos_sync_key']) : null,
                'invoice_number' => $resolvedInvoiceNumber,
                'invoice_date' => $payload['invoice_date'],
                'due_date' => $resolvedDueDate,
                'status' => $status,
                'payment_status' => 'unpaid',
                'subtotal' => $prepared['subtotal'],
                'discount_type' => $prepared['global_discount_type'],
                'discount_value' => $prepared['global_discount_value'],
                'discount_total' => $prepared['discount_total'],
                'net_total' => $prepared['net_total'],
                'tax_total' => $prepared['tax_total'],
                'total' => $prepared['total'],
                'amount_paid' => 0,
                'balance_due' => $prepared['total'],
                'stock_posted' => $stockPosted,
                'notes' => $payload['notes'] ?? null,
                'validated_at' => $validatedAt,
                'approved_at' => $approvedAt,
                'approved_by' => $status === 'validated' ? $user->id : null,
                'cancelled_at' => null,
                'cancelled_by' => null,
                'created_by' => $user->id,
            ]);

            $stockMovementCreated = false;

            foreach ($prepared['items'] as $item) {
                $product = $item['product'];

                $invoice->items()->create([
                    'product_id' => $product->id,
                    'description' => $item['description'],
                    'qty' => $item['qty'],
                    'unit_price' => $item['unit_price'],
                    'line_subtotal' => $item['line_subtotal'],
                    'discount_type' => $item['discount_type'],
                    'discount_value' => $item['discount_value'],
                    'discount_total' => $item['discount_total'],
                    'tax_rule_id' => $item['tax_rule_id'],
                    'tax_rate' => $item['tax_rate'],
                    'tax_amount' => $item['tax_amount'],
                    'line_total' => $item['stored_line_total'],
                ]);

                if ($postSideEffects && ! $stockPosted && $product->type === 'stockable') {
                    $this->stockService->recordSale(
                        product: $product,
                        companyId: $companyId,
                        branchId: $branchId,
                        quantity: (float) $item['qty'],
                        unitCost: (float) $product->purchase_price,
                        referenceType: SalesInvoice::class,
                        referenceId: $invoice->id,
                        movementDate: $payload['invoice_date'],
                        user: $user,
                        warehouseId: $resolvedWarehouseId,
                    );
                    $stockMovementCreated = true;
                }
            }

            if ($stockMovementCreated) {
                $invoice->update(['stock_posted' => true]);
            }

            $invoice = $invoice->load(['customer', 'branch', 'warehouse', 'items.product', 'approver', 'originDeliveryNote']);

            if ($postSideEffects) {
                $this->accountingService->recordSalesInvoice($invoice, $user);
            }

            $this->integrationOutboxService->record($invoice, $status === 'validated' ? 'sales.invoice.validated' : 'sales.invoice.created', [
                'invoice_number' => $invoice->invoice_number,
                'status' => $invoice->status,
                'total' => (float) $invoice->total,
                'tax_total' => (float) $invoice->tax_total,
                'customer_id' => $invoice->customer_id,
            ]);

            return $invoice;
        });
    }

    private function prepareInvoiceItems(int $companyId, Partner $customer, Collection $items, array $payload): array
    {
        $customer->loadMissing('paymentTerm', 'priceList');
        $globalDiscount = $this->resolveDiscounts(
            round((float) $items->sum(fn (array $item) => (float) ($item['line_total'] ?? 0)), 2),
            $payload
        );

        $afterLineDiscountTotal = round((float) $items->sum(fn (array $item) => (float) ($item['line_total'] ?? 0)), 2);
        $remainingHeaderDiscount = $globalDiscount['total'];
        $count = $items->count();
        $index = 0;

        $preparedItems = $items->map(function (array $item) use ($afterLineDiscountTotal, $globalDiscount, &$remainingHeaderDiscount, $count, &$index) {
            $product = $item['product'];
            $index++;

            $unitPrice = (float) $item['unit_price'];
            $lineSubtotal = round((float) $item['qty'] * $unitPrice, 2);
            $lineDiscount = $this->resolveLineDiscounts($lineSubtotal, $item);
            $lineNetBeforeHeader = (float) $lineDiscount['net_total'];

            $headerDiscountShare = $this->resolveHeaderDiscountShare(
                lineNetBeforeHeader: $lineNetBeforeHeader,
                grossBase: $afterLineDiscountTotal,
                globalDiscountTotal: $globalDiscount['total'],
                isLastLine: $index === $count,
                remainingDiscount: $remainingHeaderDiscount,
            );
            $remainingHeaderDiscount = round($remainingHeaderDiscount - $headerDiscountShare, 2);

            $lineNetTotal = round($lineNetBeforeHeader - $headerDiscountShare, 2);
            $taxRule = $product->saleTaxRule;
            $taxRate = $taxRule?->is_active ? (float) $taxRule->rate : 0.0;
            $taxAmount = round($lineNetBeforeHeader * ($taxRate / 100), 2);
            $storedLineTotal = round($lineNetBeforeHeader + $taxAmount, 2);

            return [
                'product' => $product,
                'product_id' => $product->id,
                'description' => $item['description'],
                'qty' => $item['qty'],
                'unit_price' => $unitPrice,
                'line_subtotal' => $lineSubtotal,
                'discount_type' => $lineDiscount['type'],
                'discount_value' => $lineDiscount['value'],
                'discount_total' => $lineDiscount['total'],
                'header_discount_share' => $headerDiscountShare,
                'line_net_total' => $lineNetTotal,
                'tax_rule_id' => $taxRule?->id,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'stored_line_total' => $storedLineTotal,
                'line_total' => round($storedLineTotal - $headerDiscountShare, 2),
            ];
        })->values();

        return [
            'items' => $preparedItems,
            'subtotal' => round((float) $preparedItems->sum('line_subtotal'), 2),
            'discount_total' => round((float) $preparedItems->sum(fn (array $item) => (float) $item['discount_total'] + (float) $item['header_discount_share']), 2),
            'net_total' => round((float) $preparedItems->sum('line_net_total'), 2),
            'tax_total' => round((float) $preparedItems->sum('tax_amount'), 2),
            'total' => round((float) $preparedItems->sum('line_total'), 2),
            'global_discount_type' => $globalDiscount['type'],
            'global_discount_value' => $globalDiscount['value'],
        ];
    }

    private function resolvePriceFromList(Collection|array|null $priceRows, float $qty, float $fallbackPrice): float
    {
        if (! $priceRows instanceof Collection) {
            return round($fallbackPrice, 2);
        }

        $matched = $priceRows->first(fn ($row) => (float) $row->min_qty <= $qty);

        return round((float) ($matched->price ?? $fallbackPrice), 2);
    }

    private function resolveHeaderDiscountShare(float $lineNetBeforeHeader, float $grossBase, float $globalDiscountTotal, bool $isLastLine, float $remainingDiscount): float
    {
        if ($globalDiscountTotal <= 0 || $grossBase <= 0) {
            return 0.0;
        }

        if ($isLastLine) {
            return round($remainingDiscount, 2);
        }

        return round(($lineNetBeforeHeader / $grossBase) * $globalDiscountTotal, 2);
    }

    private function resolveDueDate(string $invoiceDate, $paymentTerm): ?string
    {
        $days = (int) ($paymentTerm?->days ?? 0);

        return Carbon::parse($invoiceDate)->addDays($days)->toDateString();
    }

    private function resolveLineDiscounts(float $lineSubtotal, array $payload): array
    {
        $type = $payload['discount_type'] ?? 'none';
        if (! in_array($type, ['none', 'fixed', 'percent'], true)) {
            $type = 'none';
        }

        $value = round((float) ($payload['discount_value'] ?? 0), 2);
        if ($value < 0) {
            throw ValidationException::withMessages([
                'items' => 'La remise par ligne doit etre positive ou nulle.',
            ]);
        }

        if ($type === 'percent' && $value > 100) {
            throw ValidationException::withMessages([
                'items' => 'La remise en pourcentage par ligne ne peut pas depasser 100%.',
            ]);
        }

        $discountTotal = match ($type) {
            'fixed' => min($lineSubtotal, $value),
            'percent' => round($lineSubtotal * ($value / 100), 2),
            default => 0.0,
        };

        $netTotal = round($lineSubtotal - $discountTotal, 2);

        if ($lineSubtotal > 0 && $netTotal <= 0) {
            throw ValidationException::withMessages([
                'items' => 'La remise d une ligne ne peut pas annuler totalement l article.',
            ]);
        }

        return [
            'type' => $discountTotal > 0 ? $type : 'none',
            'value' => $discountTotal > 0 ? $value : 0.0,
            'total' => $discountTotal > 0 ? $discountTotal : 0.0,
            'net_total' => $netTotal > 0 ? $netTotal : $lineSubtotal,
        ];
    }

    private function resolveDiscounts(float $baseAmount, array $payload): array
    {
        $type = $payload['discount_type'] ?? 'none';
        if (! in_array($type, ['none', 'fixed', 'percent'], true)) {
            $type = 'none';
        }

        $value = round((float) ($payload['discount_value'] ?? 0), 2);
        if ($value < 0) {
            throw ValidationException::withMessages([
                'discount_value' => 'La remise doit etre positive ou nulle.',
            ]);
        }

        if ($type === 'percent' && $value > 100) {
            throw ValidationException::withMessages([
                'discount_value' => 'La remise en pourcentage ne peut pas depasser 100%.',
            ]);
        }

        $discountTotal = match ($type) {
            'fixed' => min($baseAmount, $value),
            'percent' => round($baseAmount * ($value / 100), 2),
            default => 0.0,
        };

        $netTotal = round($baseAmount - $discountTotal, 2);

        if ($baseAmount > 0 && $netTotal <= 0) {
            throw ValidationException::withMessages([
                'discount_value' => 'La remise ne peut pas annuler totalement le ticket. Garde un total strictement positif.',
            ]);
        }

        return [
            'type' => $discountTotal > 0 ? $type : 'none',
            'value' => $discountTotal > 0 ? $value : 0.0,
            'total' => $discountTotal > 0 ? $discountTotal : 0.0,
            'net_total' => $netTotal > 0 ? $netTotal : $baseAmount,
        ];
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

        return $providedPrice;
    }
}





