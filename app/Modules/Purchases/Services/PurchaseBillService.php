<?php

namespace App\Modules\Purchases\Services;

use App\Models\User;
use App\Modules\Accounting\Services\AccountingService;
use App\Modules\Accounting\Services\PeriodLockService;
use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Company\Services\DocumentNumberService;
use App\Modules\Core\Company\Services\PricingService;
use App\Modules\Core\Integrations\Services\IntegrationOutboxService;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Partners\Models\Partner;
use App\Modules\Purchases\Models\GoodsReceipt;
use App\Modules\Purchases\Models\GoodsReceiptItem;
use App\Modules\Purchases\Models\PurchaseBill;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseBillService
{
    public function __construct(
        private readonly DocumentNumberService $documentNumberService,
        private readonly StockService $stockService,
        private readonly AccountingService $accountingService,
        private readonly PeriodLockService $periodLockService,
        private readonly PricingService $pricingService,
        private readonly IntegrationOutboxService $integrationOutboxService,
    ) {
    }

    public function createValidated(int $companyId, int $branchId, Partner $supplier, array $payload, Collection $items, User $user): PurchaseBill
    {
        return $this->persistBill($companyId, $branchId, $supplier, $payload, $items, $user);
    }

    public function createPending(int $companyId, int $branchId, Partner $supplier, array $payload, Collection $items, User $user): PurchaseBill
    {
        return $this->persistBill($companyId, $branchId, $supplier, $payload, $items, $user, null, 'pending_approval', false);
    }

    public function createPendingFromReceipt(GoodsReceipt $receipt, array $payload, Collection $items, User $user): PurchaseBill
    {
        return DB::transaction(function () use ($receipt, $payload, $items, $user) {
            $lockedReceipt = GoodsReceipt::query()
                ->with(['supplier.paymentTerm', 'warehouse', 'purchaseOrder', 'purchaseBill', 'items.product.purchaseTaxRule'])
                ->whereKey($receipt->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedReceipt->purchaseBill) {
                throw ValidationException::withMessages([
                    'goods_receipt_id' => 'Cette reception fournisseur a deja genere une facture fournisseur.',
                ]);
            }

            return $this->persistBill(
                companyId: $lockedReceipt->company_id,
                branchId: $lockedReceipt->branch_id,
                supplier: $lockedReceipt->supplier,
                payload: array_merge($payload, [
                    'warehouse_id' => $lockedReceipt->warehouse_id,
                    'purchase_order_id' => $lockedReceipt->purchase_order_id,
                    'goods_receipt_id' => $lockedReceipt->id,
                    'notes' => $payload['notes'] ?? $lockedReceipt->notes,
                ]),
                items: $items,
                user: $user,
                billNumber: null,
                status: 'pending_approval',
                postSideEffects: false,
            );
        });
    }

    public function createHistorical(int $companyId, int $branchId, Partner $supplier, string $billNumber, array $payload, Collection $items, User $user): PurchaseBill
    {
        return $this->persistBill($companyId, $branchId, $supplier, $payload, $items, $user, $billNumber);
    }

    public function approve(PurchaseBill $bill, User $user): PurchaseBill
    {
        return DB::transaction(function () use ($bill, $user) {
            $bill = PurchaseBill::query()
                ->with(['supplier.paymentTerm', 'branch', 'warehouse', 'goodsReceipt', 'purchaseOrder', 'items.product'])
                ->whereKey($bill->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($bill->status !== 'pending_approval') {
                throw ValidationException::withMessages([
                    'purchase' => 'Cette facture fournisseur n est pas en attente d approbation.',
                ]);
            }

            $this->periodLockService->assertDateOpen($bill->company_id, $bill->bill_date?->toDateString() ?? now()->toDateString(), 'bill_date');

            if ($this->shouldPostStockSideEffects($bill)) {
                foreach ($bill->items as $item) {
                    $product = $item->product;
                    $product?->assertAvailableForPurchase('approbation facture fournisseur');

                    if ($product && $product->type === 'stockable') {
                        $this->stockService->recordPurchase(
                            product: $product,
                            companyId: $bill->company_id,
                            branchId: $bill->branch_id,
                            quantity: (float) $item->qty,
                            unitCost: (float) $item->unit_cost,
                            referenceType: PurchaseBill::class,
                            referenceId: $bill->id,
                            movementDate: $bill->bill_date?->toDateString(),
                            user: $user,
                            warehouseId: $bill->warehouse_id,
                        );
                    }
                }
            }

            $approvedAt = now();

            $bill->update([
                'status' => 'validated',
                'validated_at' => $approvedAt,
                'approved_at' => $approvedAt,
                'approved_by' => $user->id,
            ]);

            $bill = $bill->fresh(['supplier', 'branch', 'warehouse', 'goodsReceipt', 'purchaseOrder', 'items.product']);
            $this->accountingService->recordPurchaseBill($bill, $user);
            $this->integrationOutboxService->record($bill, 'purchases.bill.approved', [
                'bill_number' => $bill->bill_number,
                'status' => $bill->status,
                'total' => (float) $bill->total,
                'purchase_order_id' => $bill->purchase_order_id,
                'goods_receipt_id' => $bill->goods_receipt_id,
            ]);

            return $bill->load(['approver', 'creator', 'warehouse', 'goodsReceipt.purchaseOrder', 'purchaseOrder', 'paymentAllocations.payment.cashAccount']);
        });
    }

    public function reject(PurchaseBill $bill, User $user, ?string $reason = null): PurchaseBill
    {
        return DB::transaction(function () use ($bill, $user, $reason) {
            $bill = PurchaseBill::query()
                ->with(['supplier', 'branch', 'warehouse', 'goodsReceipt', 'purchaseOrder', 'items.product'])
                ->whereKey($bill->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($bill->status !== 'pending_approval') {
                throw ValidationException::withMessages([
                    'purchase' => 'Cette facture fournisseur n est pas en attente d approbation.',
                ]);
            }

            $rejectedAt = now();

            $bill->update([
                'status' => 'rejected',
                'validated_at' => null,
                'approved_at' => null,
                'approved_by' => null,
                'rejected_at' => $rejectedAt,
                'rejected_by' => $user->id,
                'rejection_reason' => $reason,
            ]);

            $bill = $bill->fresh(['supplier', 'branch', 'warehouse', 'goodsReceipt', 'purchaseOrder', 'items.product', 'creator', 'approver', 'rejector', 'approvalSteps.approver', 'approvalSteps.rejectedBy']);

            $this->integrationOutboxService->record($bill, 'purchases.bill.rejected', [
                'bill_number' => $bill->bill_number,
                'status' => $bill->status,
                'rejected_at' => $rejectedAt->toIso8601String(),
                'rejected_by' => $user->id,
                'rejection_reason' => $reason,
            ]);

            return $bill;
        });
    }

    public function normalizeItems(int $companyId, array $items, ?Partner $supplier = null): Collection
    {
        $filteredItems = collect($items)
            ->filter(fn (array $item) => filled($item['product_id'] ?? null))
            ->values();

        $productIds = $filteredItems->pluck('product_id')->map(fn ($id) => (int) $id)->all();
        $products = Product::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $productIds)
            ->with(['purchaseTaxRule', 'supplierInfos.supplier'])
            ->get()
            ->keyBy('id');
        $priceRules = $this->pricingService->rulesForPriceList($companyId, $supplier?->price_list_id, $productIds);

        return $filteredItems->map(function (array $item) use ($products, $priceRules, $supplier) {
            /** @var Product $product */
            $product = $products->get((int) $item['product_id']);
            $product->assertAvailableForPurchase('facture fournisseur');
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

    public function normalizeReceiptItems(GoodsReceipt $receipt, array $items): Collection
    {
        $receipt->loadMissing(['items.product.purchaseTaxRule']);

        $filteredItems = collect($items)
            ->filter(fn (array $item) => filled($item['goods_receipt_item_id'] ?? null))
            ->values();

        $receiptItems = $receipt->items->keyBy('id');

        return $filteredItems->map(function (array $item) use ($receiptItems) {
            /** @var GoodsReceiptItem|null $receiptItem */
            $receiptItem = $receiptItems->get((int) $item['goods_receipt_item_id']);

            if (! $receiptItem) {
                throw ValidationException::withMessages([
                    'items' => 'Une ligne de reception fournisseur est introuvable.',
                ]);
            }

            $submittedQty = (float) ($item['qty'] ?? 0);
            $expectedQty = (float) $receiptItem->qty;

            if (abs($submittedQty - $expectedQty) > 0.0009) {
                throw ValidationException::withMessages([
                    'items' => 'Les quantites facturees doivent correspondre a la reception fournisseur source.',
                ]);
            }

            if (filled($item['product_id'] ?? null) && (int) $item['product_id'] !== (int) $receiptItem->product_id) {
                throw ValidationException::withMessages([
                    'items' => 'Le produit facture doit correspondre a la ligne de reception source.',
                ]);
            }

            $product = $receiptItem->product;
            $unitCost = isset($item['unit_cost']) && $item['unit_cost'] !== ''
                ? (float) $item['unit_cost']
                : (float) $receiptItem->unit_cost;

            return [
                'product' => $product,
                'product_id' => $product->id,
                'description' => $item['description'] ?: $receiptItem->description,
                'qty' => $expectedQty,
                'unit_cost' => $unitCost,
                'line_total' => round($expectedQty * $unitCost, 2),
            ];
        });
    }

    private function persistBill(
        int $companyId,
        int $branchId,
        Partner $supplier,
        array $payload,
        Collection $items,
        User $user,
        ?string $billNumber = null,
        string $status = 'validated',
        bool $postSideEffects = true,
    ): PurchaseBill {
        $this->periodLockService->assertDateOpen($companyId, $payload['bill_date'], 'bill_date');

        return DB::transaction(function () use ($companyId, $branchId, $supplier, $payload, $items, $user, $billNumber, $status, $postSideEffects) {
            if (empty($payload['goods_receipt_id'])) {
                $items->each(fn (array $item) => $item['product']->assertAvailableForPurchase('facture fournisseur'));
            }
            if (! empty($payload['goods_receipt_id']) && PurchaseBill::query()->where('company_id', $companyId)->where('goods_receipt_id', $payload['goods_receipt_id'])->exists()) {
                throw ValidationException::withMessages([
                    'goods_receipt_id' => 'Cette reception fournisseur a deja genere une facture fournisseur.',
                ]);
            }

            $resolvedWarehouseId = isset($payload['warehouse_id']) && $payload['warehouse_id']
                ? (int) $payload['warehouse_id']
                : $this->stockService->defaultWarehouseId($companyId, $branchId);

            $prepared = $this->prepareBillItems($companyId, $items);
            $resolvedBillNumber = $billNumber ?: $this->documentNumberService->nextNumber(
                companyId: $companyId,
                documentType: 'purchase_bill',
                branchId: $branchId,
                date: $payload['bill_date'],
            );
            $validatedAt = $status === 'validated'
                ? Carbon::parse($payload['validated_at'] ?? $payload['bill_date'])
                : null;
            $approvedAt = $status === 'validated'
                ? Carbon::parse($payload['approved_at'] ?? $payload['validated_at'] ?? $payload['bill_date'])
                : null;
            $resolvedDueDate = $payload['due_date'] ?? $this->resolveDueDate($payload['bill_date'], $supplier->paymentTerm);

            $bill = PurchaseBill::query()->create([
                'tenant_id' => $supplier->tenant_id,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'warehouse_id' => $resolvedWarehouseId,
                'supplier_id' => $supplier->id,
                'payment_term_id' => $supplier->payment_term_id,
                'price_list_id' => $supplier->price_list_id,
                'purchase_order_id' => $payload['purchase_order_id'] ?? null,
                'goods_receipt_id' => $payload['goods_receipt_id'] ?? null,
                'bill_number' => $resolvedBillNumber,
                'bill_date' => $payload['bill_date'],
                'due_date' => $resolvedDueDate,
                'status' => $status,
                'payment_status' => 'unpaid',
                'subtotal' => $prepared['subtotal'],
                'net_total' => $prepared['net_total'],
                'tax_total' => $prepared['tax_total'],
                'total' => $prepared['total'],
                'amount_paid' => 0,
                'balance_due' => $prepared['total'],
                'notes' => $payload['notes'] ?? null,
                'validated_at' => $validatedAt,
                'approved_at' => $approvedAt,
                'approved_by' => $status === 'validated' ? $user->id : null,
                'created_by' => $user->id,
            ]);

            foreach ($prepared['items'] as $item) {
                $product = $item['product'];

                $bill->items()->create([
                    'product_id' => $product->id,
                    'description' => $item['description'],
                    'qty' => $item['qty'],
                    'unit_cost' => $item['unit_cost'],
                    'tax_rule_id' => $item['tax_rule_id'],
                    'tax_rate' => $item['tax_rate'],
                    'tax_amount' => $item['tax_amount'],
                    'line_total' => $item['line_total'],
                ]);

                if ($postSideEffects && $this->shouldPostStockSideEffects($bill) && $product->type === 'stockable') {
                    $this->stockService->recordPurchase(
                        product: $product,
                        companyId: $companyId,
                        branchId: $branchId,
                        quantity: (float) $item['qty'],
                        unitCost: (float) $item['unit_cost'],
                        referenceType: PurchaseBill::class,
                        referenceId: $bill->id,
                        movementDate: $payload['bill_date'],
                        user: $user,
                        warehouseId: $resolvedWarehouseId,
                    );
                }
            }

            $bill = $bill->load(['supplier', 'branch', 'warehouse', 'goodsReceipt.purchaseOrder', 'purchaseOrder', 'items.product', 'approver']);

            if ($postSideEffects) {
                $this->accountingService->recordPurchaseBill($bill, $user);
            }

            $this->integrationOutboxService->record($bill, $status === 'validated' ? 'purchases.bill.validated' : 'purchases.bill.created', [
                'bill_number' => $bill->bill_number,
                'status' => $bill->status,
                'total' => (float) $bill->total,
                'tax_total' => (float) $bill->tax_total,
                'supplier_id' => $bill->supplier_id,
                'purchase_order_id' => $bill->purchase_order_id,
                'goods_receipt_id' => $bill->goods_receipt_id,
            ]);

            return $bill;
        });
    }

    private function prepareBillItems(int $companyId, Collection $items): array
    {
        $preparedItems = $items->map(function (array $item) {
            $product = $item['product'];
            $netTotal = round((float) $item['line_total'], 2);
            $taxRule = $product->purchaseTaxRule;
            $taxRate = $taxRule?->is_active ? (float) $taxRule->rate : 0.0;
            $taxAmount = round($netTotal * ($taxRate / 100), 2);

            return [
                'product' => $product,
                'description' => $item['description'],
                'qty' => $item['qty'],
                'unit_cost' => $item['unit_cost'],
                'tax_rule_id' => $taxRule?->id,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'line_net_total' => $netTotal,
                'line_total' => round($netTotal + $taxAmount, 2),
            ];
        })->values();

        return [
            'items' => $preparedItems,
            'subtotal' => round((float) $preparedItems->sum('line_net_total'), 2),
            'net_total' => round((float) $preparedItems->sum('line_net_total'), 2),
            'tax_total' => round((float) $preparedItems->sum('tax_amount'), 2),
            'total' => round((float) $preparedItems->sum('line_total'), 2),
        ];
    }

    private function resolveDueDate(string $billDate, $paymentTerm): ?string
    {
        $days = (int) ($paymentTerm?->days ?? 0);

        return Carbon::parse($billDate)->addDays($days)->toDateString();
    }

    private function shouldPostStockSideEffects(PurchaseBill $bill): bool
    {
        return ! $bill->goods_receipt_id;
    }
}









