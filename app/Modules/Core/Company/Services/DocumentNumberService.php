<?php

namespace App\Modules\Core\Company\Services;

use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\DocumentSequence;
use App\Modules\Expenses\Models\Expense;
use App\Modules\FixedAssets\Models\FixedAsset;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Pos\Models\PosSession;
use App\Modules\Pos\Models\PosReturn;
use App\Modules\Purchases\Models\GoodsReceipt;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Purchases\Models\PurchaseOrder;
use App\Modules\Purchases\Models\PurchaseRequest;
use App\Modules\Sales\Models\DeliveryNote;
use App\Modules\Sales\Models\SalesCreditNote;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Models\SalesQuote;
use App\Modules\Treasury\Models\Payment;
use App\Modules\Treasury\Models\TreasuryReconciliation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DocumentNumberService
{
    public function nextNumber(
        int $companyId,
        string $documentType,
        ?int $branchId = null,
        ?string $date = null,
        ?string $journalCode = null,
    ): string {
        return DB::transaction(function () use ($companyId, $documentType, $branchId, $date, $journalCode) {
            $sequence = DocumentSequence::query()
                ->where('company_id', $companyId)
                ->where('document_type', $documentType)
                ->lockForUpdate()
                ->firstOrFail();

            $currentNumber = (int) $sequence->next_number;
            $prefix = $this->resolvePrefix(
                prefix: $sequence->prefix,
                branchId: $branchId,
                date: $date,
                journalCode: $journalCode,
            );

            do {
                $number = Str::upper($prefix.str_pad((string) $currentNumber, (int) $sequence->padding, '0', STR_PAD_LEFT));
                $currentNumber++;
            } while ($this->numberAlreadyExists($companyId, $documentType, $number));

            $sequence->update([
                'next_number' => $currentNumber,
            ]);

            return $number;
        });
    }

    private function numberAlreadyExists(int $companyId, string $documentType, string $number): bool
    {
        $definition = $this->sequenceDefinition($documentType);

        if (! $definition) {
            return false;
        }

        /** @var class-string<Model> $model */
        $model = $definition['model'];
        $column = $definition['column'];

        return $model::query()
            ->where('company_id', $companyId)
            ->where($column, $number)
            ->exists();
    }

    private function sequenceDefinition(string $documentType): ?array
    {
        return [
            'sales_quote' => ['model' => SalesQuote::class, 'column' => 'quote_number'],
            'sales_order' => ['model' => SalesOrder::class, 'column' => 'order_number'],
            'delivery_note' => ['model' => DeliveryNote::class, 'column' => 'delivery_number'],
            'sales_invoice' => ['model' => SalesInvoice::class, 'column' => 'invoice_number'],
            'sales_credit_note' => ['model' => SalesCreditNote::class, 'column' => 'credit_note_number'],
            'purchase_bill' => ['model' => PurchaseBill::class, 'column' => 'bill_number'],
            'purchase_order' => ['model' => PurchaseOrder::class, 'column' => 'order_number'],
            'purchase_request' => ['model' => PurchaseRequest::class, 'column' => 'request_number'],
            'goods_receipt' => ['model' => GoodsReceipt::class, 'column' => 'receipt_number'],
            'stock_transfer' => ['model' => StockTransfer::class, 'column' => 'transfer_number'],
            'stock_count' => ['model' => StockCount::class, 'column' => 'count_number'],
            'pos_session' => ['model' => PosSession::class, 'column' => 'session_number'],
            'pos_return' => ['model' => PosReturn::class, 'column' => 'return_number'],
            'payment' => ['model' => Payment::class, 'column' => 'payment_number'],
            'treasury_reconciliation' => ['model' => TreasuryReconciliation::class, 'column' => 'reconciliation_number'],
            'expense' => ['model' => Expense::class, 'column' => 'expense_number'],
            'journal_entry' => ['model' => JournalEntry::class, 'column' => 'journal_number'],
            'fixed_asset' => ['model' => FixedAsset::class, 'column' => 'asset_number'],
        ][$documentType] ?? null;
    }

    private function resolvePrefix(string $prefix, ?int $branchId = null, ?string $date = null, ?string $journalCode = null): string
    {
        $branchCode = null;

        if ($branchId) {
            $branchCode = Branch::query()->whereKey($branchId)->value('code');
        }

        $resolvedDate = $date ? Carbon::parse($date) : now();

        return strtr($prefix, [
            '{BRANCH}' => Str::upper($branchCode ?: 'GEN'),
            '{YEAR}' => $resolvedDate->format('Y'),
            '{YY}' => $resolvedDate->format('y'),
            '{MONTH}' => $resolvedDate->format('m'),
            '{JOURNAL}' => Str::upper($journalCode ?: 'GEN'),
        ]);
    }
}


