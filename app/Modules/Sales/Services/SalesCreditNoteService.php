<?php

namespace App\Modules\Sales\Services;

use App\Models\User;
use App\Modules\Accounting\Services\AccountingService;
use App\Modules\Accounting\Services\PeriodLockService;
use App\Modules\Core\Company\Services\DocumentNumberService;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Sales\Models\SalesCreditNote;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesInvoiceItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesCreditNoteService
{
    public function __construct(
        private readonly DocumentNumberService $documentNumberService,
        private readonly StockService $stockService,
        private readonly AccountingService $accountingService,
        private readonly PeriodLockService $periodLockService,
    ) {}

    public function create(SalesInvoice $invoice, array $payload, array $rows, User $user): SalesCreditNote
    {
        return DB::transaction(function () use ($invoice, $payload, $rows, $user) {
            $invoice = SalesInvoice::query()->with(['items.product', 'creditNotes.items'])->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($invoice->status !== 'validated') {
                throw ValidationException::withMessages(['invoice' => 'Seules les factures clients approuvees peuvent recevoir un avoir.']);
            }

            $this->periodLockService->assertDateOpen($invoice->company_id, $payload['credit_note_date'], 'credit_note_date');
            $items = $this->normalizeItems($invoice, $rows);

            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Renseigne au moins une quantite a avoirer.']);
            }

            $total = round((float) $items->sum('line_total'), 2);
            $alreadyCredited = (float) $invoice->creditNotes->sum('total');
            $creditableTotal = round((float) $invoice->total - $alreadyCredited, 2);
            if ($total > $creditableTotal) {
                throw ValidationException::withMessages(['items' => 'Le total de l avoir depasse le montant encore avoirable de la facture client.']);
            }

            $creditNote = SalesCreditNote::query()->create([
                'company_id' => $invoice->company_id,
                'branch_id' => $invoice->branch_id,
                'warehouse_id' => $invoice->warehouse_id,
                'sales_invoice_id' => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'credit_note_number' => $this->documentNumberService->nextNumber(companyId: $invoice->company_id, documentType: 'sales_credit_note', branchId: $invoice->branch_id, date: $payload['credit_note_date']),
                'credit_note_date' => $payload['credit_note_date'],
                'status' => 'validated',
                'restock_items' => (bool) ($payload['restock_items'] ?? false),
                'subtotal' => $total,
                'total' => $total,
                'notes' => $payload['notes'] ?? null,
                'validated_at' => now(),
                'created_by' => $user->id,
            ]);

            foreach ($items as $item) {
                $creditNote->items()->create([
                    'sales_invoice_item_id' => $item['sales_invoice_item_id'],
                    'product_id' => $item['product_id'],
                    'description' => $item['description'],
                    'qty' => $item['qty'],
                    'unit_price' => $item['unit_price'],
                    'line_total' => $item['line_total'],
                ]);

                if ($creditNote->restock_items && $item['product'] && $item['product']->type === 'stockable') {
                    $this->stockService->recordAdjustment(
                        product: $item['product'],
                        companyId: $invoice->company_id,
                        branchId: $invoice->branch_id,
                        direction: 'in',
                        quantity: (float) $item['qty'],
                        unitCost: (float) $item['product']->purchase_price,
                        reason: 'Avoir client '.$creditNote->credit_note_number,
                        notes: $creditNote->notes,
                        user: $user,
                        movementDate: $creditNote->credit_note_date,
                        warehouseId: $invoice->warehouse_id,
                        referenceType: SalesCreditNote::class,
                        referenceId: $creditNote->id,
                    );
                }
            }

            $newBalance = round((float) $invoice->balance_due - $total, 2);
            $invoice->update([
                'balance_due' => $newBalance,
                'payment_status' => $newBalance <= 0 ? 'paid' : ((float) $invoice->amount_paid > 0 ? 'partial' : 'unpaid'),
            ]);

            $creditNote = $creditNote->load(['invoice', 'customer', 'branch', 'warehouse', 'creator', 'items.product', 'items.salesInvoiceItem']);
            $this->accountingService->recordSalesCreditNote($creditNote, $invoice->fresh(), $user);

            return $creditNote;
        });
    }

    public function creditableLines(SalesInvoice $invoice): Collection
    {
        $invoice->loadMissing(['items.product', 'creditNotes.items']);
        $creditedByItem = $invoice->creditNotes
            ->flatMap(fn (SalesCreditNote $creditNote) => $creditNote->items)
            ->groupBy('sales_invoice_item_id')
            ->map(fn (Collection $group) => round((float) $group->sum('qty'), 3));

        return $invoice->items->map(function (SalesInvoiceItem $item) use ($creditedByItem) {
            $creditedQty = (float) ($creditedByItem[$item->id] ?? 0);
            $remainingQty = max(0, round((float) $item->qty - $creditedQty, 3));
            $unitPrice = (float) $item->qty > 0 ? round((float) $item->line_total / (float) $item->qty, 2) : (float) $item->unit_price;

            return [
                'invoice_item_id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name,
                'description' => $item->description,
                'product_type' => $item->product?->type,
                'original_qty' => round((float) $item->qty, 3),
                'credited_qty' => $creditedQty,
                'remaining_qty' => $remainingQty,
                'unit_price' => $unitPrice,
            ];
        })->filter(fn (array $line) => $line['remaining_qty'] > 0)->values();
    }

    private function normalizeItems(SalesInvoice $invoice, array $rows): Collection
    {
        $creditable = $this->creditableLines($invoice)->keyBy('invoice_item_id');

        return collect($rows)
            ->map(fn ($row) => is_array($row) ? $row : [])
            ->filter(fn (array $row) => filled($row['sales_invoice_item_id'] ?? null) && (float) ($row['qty'] ?? 0) > 0)
            ->values()
            ->map(function (array $row) use ($creditable, $invoice) {
                $invoiceItemId = (int) $row['sales_invoice_item_id'];
                $line = $creditable->get($invoiceItemId);

                if (! $line) {
                    throw ValidationException::withMessages(['items' => 'Une ligne de facture n est pas eligible a cet avoir.']);
                }

                $qty = round((float) $row['qty'], 3);
                if ($qty > (float) $line['remaining_qty']) {
                    throw ValidationException::withMessages(['items' => 'La quantite de l avoir depasse la quantite encore disponible sur une ligne.']);
                }

                $unitPrice = round((float) $line['unit_price'], 2);

                return [
                    'sales_invoice_item_id' => $invoiceItemId,
                    'product_id' => $line['product_id'],
                    'product' => $invoice->items->firstWhere('id', $invoiceItemId)?->product,
                    'description' => $line['description'],
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'line_total' => round($qty * $unitPrice, 2),
                ];
            });
    }
}
