<?php

namespace App\Modules\Purchases\Services;

use App\Models\User;
use App\Modules\Accounting\Services\AccountingService;
use App\Modules\Accounting\Services\PeriodLockService;
use App\Modules\Core\Company\Services\DocumentNumberService;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Purchases\Models\PurchaseBillItem;
use App\Modules\Purchases\Models\PurchaseCreditNote;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseCreditNoteService
{
    public function __construct(
        private readonly DocumentNumberService $documentNumberService,
        private readonly StockService $stockService,
        private readonly AccountingService $accountingService,
        private readonly PeriodLockService $periodLockService,
    ) {}

    public function create(PurchaseBill $bill, array $payload, array $rows, User $user): PurchaseCreditNote
    {
        return DB::transaction(function () use ($bill, $payload, $rows, $user) {
            $bill = PurchaseBill::query()
                ->with(['items.product.purchaseTaxRule', 'creditNotes.items'])
                ->whereKey($bill->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($bill->status !== 'validated') {
                throw ValidationException::withMessages(['purchase' => 'Seules les factures fournisseurs approuvees peuvent recevoir un avoir.']);
            }

            if ((float) $bill->balance_due <= 0) {
                throw ValidationException::withMessages(['purchase' => 'Cette facture fournisseur est deja soldee et ne peut pas recevoir cet avoir dans cette version.']);
            }

            $this->periodLockService->assertDateOpen($bill->company_id, $payload['credit_note_date'], 'credit_note_date');
            $items = $this->normalizeItems($bill, $rows);

            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Renseigne au moins une quantite a avoirer.']);
            }

            $total = round((float) $items->sum('line_total'), 2);
            if ($total > (float) $bill->balance_due) {
                throw ValidationException::withMessages(['items' => 'Le total de l avoir depasse le solde restant de la facture fournisseur.']);
            }

            $creditNote = PurchaseCreditNote::query()->create([
                'tenant_id' => $bill->tenant_id,
                'company_id' => $bill->company_id,
                'branch_id' => $bill->branch_id,
                'warehouse_id' => $bill->warehouse_id,
                'purchase_bill_id' => $bill->id,
                'supplier_id' => $bill->supplier_id,
                'credit_note_number' => $this->documentNumberService->nextNumber(companyId: $bill->company_id, documentType: 'purchase_credit_note', branchId: $bill->branch_id, date: $payload['credit_note_date']),
                'credit_note_date' => $payload['credit_note_date'],
                'status' => 'validated',
                'destock_items' => (bool) ($payload['destock_items'] ?? false),
                'subtotal' => round((float) $items->sum('line_net_total'), 2),
                'net_total' => round((float) $items->sum('line_net_total'), 2),
                'tax_total' => round((float) $items->sum('tax_amount'), 2),
                'total' => $total,
                'notes' => $payload['notes'] ?? null,
                'validated_at' => now(),
                'created_by' => $user->id,
            ]);

            foreach ($items as $item) {
                $creditNote->items()->create([
                    'purchase_bill_item_id' => $item['purchase_bill_item_id'],
                    'product_id' => $item['product_id'],
                    'description' => $item['description'],
                    'qty' => $item['qty'],
                    'unit_cost' => $item['unit_cost'],
                    'tax_rule_id' => $item['tax_rule_id'],
                    'tax_rate' => $item['tax_rate'],
                    'tax_amount' => $item['tax_amount'],
                    'line_total' => $item['line_total'],
                ]);

                if ($creditNote->destock_items && $item['product'] && $item['product']->type === 'stockable') {
                    $this->stockService->recordAdjustment(
                        product: $item['product'],
                        companyId: $bill->company_id,
                        branchId: $bill->branch_id,
                        direction: 'out',
                        quantity: (float) $item['qty'],
                        unitCost: (float) $item['unit_cost'],
                        reason: 'Avoir fournisseur '.$creditNote->credit_note_number,
                        notes: $creditNote->notes,
                        user: $user,
                        movementDate: $creditNote->credit_note_date,
                        warehouseId: $bill->warehouse_id,
                        referenceType: PurchaseCreditNote::class,
                        referenceId: $creditNote->id,
                    );
                }
            }

            $newBalance = round((float) $bill->balance_due - $total, 2);
            $bill->update([
                'balance_due' => $newBalance,
                'payment_status' => $newBalance <= 0 ? 'paid' : ((float) $bill->amount_paid > 0 ? 'partial' : 'unpaid'),
            ]);

            $creditNote = $creditNote->load(['bill', 'supplier', 'branch', 'warehouse', 'creator', 'items.product', 'items.purchaseBillItem']);
            $this->accountingService->recordPurchaseCreditNote($creditNote, $bill->fresh(), $user);

            return $creditNote;
        });
    }

    public function creditableLines(PurchaseBill $bill): Collection
    {
        $bill->loadMissing(['items.product', 'creditNotes.items']);
        $creditedByItem = $bill->creditNotes
            ->flatMap(fn (PurchaseCreditNote $creditNote) => $creditNote->items)
            ->groupBy('purchase_bill_item_id')
            ->map(fn (Collection $group) => round((float) $group->sum('qty'), 3));

        return $bill->items->map(function (PurchaseBillItem $item) use ($creditedByItem) {
            $creditedQty = (float) ($creditedByItem[$item->id] ?? 0);
            $remainingQty = max(0, round((float) $item->qty - $creditedQty, 3));
            $unitTotal = (float) $item->qty > 0 ? round((float) $item->line_total / (float) $item->qty, 2) : (float) $item->unit_cost;

            return [
                'purchase_bill_item_id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name,
                'description' => $item->description,
                'product_type' => $item->product?->type,
                'original_qty' => round((float) $item->qty, 3),
                'credited_qty' => $creditedQty,
                'remaining_qty' => $remainingQty,
                'unit_cost' => (float) $item->unit_cost,
                'unit_total' => $unitTotal,
            ];
        })->filter(fn (array $line) => $line['remaining_qty'] > 0)->values();
    }

    private function normalizeItems(PurchaseBill $bill, array $rows): Collection
    {
        $creditable = $this->creditableLines($bill)->keyBy('purchase_bill_item_id');

        return collect($rows)
            ->map(fn ($row) => is_array($row) ? $row : [])
            ->filter(fn (array $row) => filled($row['purchase_bill_item_id'] ?? null) && (float) ($row['qty'] ?? 0) > 0)
            ->values()
            ->map(function (array $row) use ($creditable, $bill) {
                $billItemId = (int) $row['purchase_bill_item_id'];
                $line = $creditable->get($billItemId);

                if (! $line) {
                    throw ValidationException::withMessages(['items' => 'Une ligne de facture fournisseur n est pas eligible a cet avoir.']);
                }

                $qty = round((float) $row['qty'], 3);
                if ($qty > (float) $line['remaining_qty']) {
                    throw ValidationException::withMessages(['items' => 'La quantite de l avoir depasse la quantite encore disponible sur une ligne.']);
                }

                /** @var PurchaseBillItem $billItem */
                $billItem = $bill->items->firstWhere('id', $billItemId);
                $unitCost = round((float) $line['unit_cost'], 2);
                $lineNetTotal = round($qty * $unitCost, 2);
                $taxRate = (float) ($billItem->tax_rate ?? 0);
                $taxAmount = round($lineNetTotal * ($taxRate / 100), 2);

                return [
                    'purchase_bill_item_id' => $billItemId,
                    'product_id' => $line['product_id'],
                    'product' => $billItem->product,
                    'description' => $line['description'],
                    'qty' => $qty,
                    'unit_cost' => $unitCost,
                    'tax_rule_id' => $billItem->tax_rule_id,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                    'line_net_total' => $lineNetTotal,
                    'line_total' => round($lineNetTotal + $taxAmount, 2),
                ];
            });
    }
}
