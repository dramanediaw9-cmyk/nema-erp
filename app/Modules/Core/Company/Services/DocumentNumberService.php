<?php

namespace App\Modules\Core\Company\Services;

use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Automation\Models\AutomationRule;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\DocumentSequence;
use App\Modules\Core\Company\Models\PaymentTerm;
use App\Modules\Core\Company\Models\PriceList;
use App\Modules\Core\Company\Models\TaxRule;
use App\Modules\Core\Integrations\Models\IntegrationConnection;
use App\Modules\Expenses\Models\Expense;
use App\Modules\FixedAssets\Models\FixedAsset;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Partners\Models\Partner;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Models\PayrollSlip;
use App\Modules\Pos\Models\PosReturn;
use App\Modules\Pos\Models\PosSession;
use App\Modules\Projects\Models\Project;
use App\Modules\Purchases\Models\GoodsReceipt;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Purchases\Models\PurchaseCreditNote;
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
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
            $sequence = $this->lockSequence($companyId, $documentType);

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

    private function lockSequence(int $companyId, string $documentType): DocumentSequence
    {
        $sequence = DocumentSequence::query()
            ->where('company_id', $companyId)
            ->where('document_type', $documentType)
            ->lockForUpdate()
            ->first();

        if ($sequence) {
            return $sequence;
        }

        $definition = $this->sequenceDefinition($documentType);

        if (! $definition) {
            throw new \InvalidArgumentException(sprintf('Sequence [%s] inconnue.', $documentType));
        }

        try {
            DocumentSequence::query()->create([
                'company_id' => $companyId,
                'document_type' => $documentType,
                'prefix' => $definition['prefix'],
                'next_number' => 1,
                'padding' => $definition['padding'],
            ]);
        } catch (QueryException $exception) {
            $alreadyExists = DocumentSequence::query()
                ->where('company_id', $companyId)
                ->where('document_type', $documentType)
                ->exists();

            if (! $alreadyExists) {
                throw $exception;
            }
        }

        return DocumentSequence::query()
            ->where('company_id', $companyId)
            ->where('document_type', $documentType)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function numberAlreadyExists(int $companyId, string $documentType, string $number): bool
    {
        $definition = $this->sequenceDefinition($documentType);

        if (! $definition) {
            return false;
        }

        $column = $definition['column'];

        if (isset($definition['model'])) {
            /** @var class-string<Model> $model */
            $model = $definition['model'];

            $query = $model::query()->where($column, $number);

            if (! $this->columnHasGlobalUniqueConstraint($definition, $column)) {
                $query->where('company_id', $companyId);
            }

            return $query->exists();
        }

        $query = DB::table($definition['table'])->where($column, $number);

        if (! $this->columnHasGlobalUniqueConstraint($definition, $column)) {
            $query->where('company_id', $companyId);
        }

        return $query->exists();
    }

    private function columnHasGlobalUniqueConstraint(array $definition, string $column): bool
    {
        $table = $definition['table'] ?? null;

        if (! $table && isset($definition['model'])) {
            /** @var class-string<Model> $model */
            $model = $definition['model'];
            $table = (new $model())->getTable();
        }

        if (! $table) {
            return false;
        }

        foreach (Schema::getIndexes($table) as $index) {
            if (($index['unique'] ?? false) && ($index['columns'] ?? []) === [$column]) {
                return true;
            }
        }

        return false;
    }

    private function sequenceDefinition(string $documentType): ?array
    {
        return [
            'sales_quote' => ['model' => SalesQuote::class, 'column' => 'quote_number', 'prefix' => 'DEV-{BRANCH}-{YEAR}-', 'padding' => 5],
            'sales_order' => ['model' => SalesOrder::class, 'column' => 'order_number', 'prefix' => 'CMD-{BRANCH}-{YEAR}-', 'padding' => 5],
            'delivery_note' => ['model' => DeliveryNote::class, 'column' => 'delivery_number', 'prefix' => 'BL-{BRANCH}-{YEAR}-', 'padding' => 5],
            'sales_invoice' => ['model' => SalesInvoice::class, 'column' => 'invoice_number', 'prefix' => 'FAC-{BRANCH}-{YEAR}-', 'padding' => 5],
            'sales_credit_note' => ['model' => SalesCreditNote::class, 'column' => 'credit_note_number', 'prefix' => 'AVO-{BRANCH}-{YEAR}-', 'padding' => 5],
            'purchase_bill' => ['model' => PurchaseBill::class, 'column' => 'bill_number', 'prefix' => 'ACH-{BRANCH}-{YEAR}-', 'padding' => 5],
            'purchase_credit_note' => ['model' => PurchaseCreditNote::class, 'column' => 'credit_note_number', 'prefix' => 'AVF-{BRANCH}-{YEAR}-', 'padding' => 5],
            'purchase_order' => ['model' => PurchaseOrder::class, 'column' => 'order_number', 'prefix' => 'BCF-{BRANCH}-{YEAR}-', 'padding' => 5],
            'purchase_request' => ['model' => PurchaseRequest::class, 'column' => 'request_number', 'prefix' => 'DA-{BRANCH}-{YEAR}-', 'padding' => 5],
            'goods_receipt' => ['model' => GoodsReceipt::class, 'column' => 'receipt_number', 'prefix' => 'BRF-{BRANCH}-{YEAR}-', 'padding' => 5],
            'stock_transfer' => ['model' => StockTransfer::class, 'column' => 'transfer_number', 'prefix' => 'TRF-{BRANCH}-{YEAR}-', 'padding' => 5],
            'stock_count' => ['model' => StockCount::class, 'column' => 'count_number', 'prefix' => 'INV-{BRANCH}-{YEAR}-', 'padding' => 5],
            'pos_session' => ['model' => PosSession::class, 'column' => 'session_number', 'prefix' => 'POS-{BRANCH}-{YEAR}-', 'padding' => 5],
            'pos_return' => ['model' => PosReturn::class, 'column' => 'return_number', 'prefix' => 'RET-{BRANCH}-{YEAR}-', 'padding' => 5],
            'payment' => ['model' => Payment::class, 'column' => 'payment_number', 'prefix' => 'ENC-{BRANCH}-{YEAR}-', 'padding' => 5],
            'treasury_reconciliation' => ['model' => TreasuryReconciliation::class, 'column' => 'reconciliation_number', 'prefix' => 'RAP-{BRANCH}-{YEAR}-', 'padding' => 5],
            'expense' => ['model' => Expense::class, 'column' => 'expense_number', 'prefix' => 'DEP-{BRANCH}-{YEAR}-', 'padding' => 5],
            'journal_entry' => ['model' => JournalEntry::class, 'column' => 'journal_number', 'prefix' => 'JRN-{JOURNAL}-{YEAR}-', 'padding' => 5],
            'fixed_asset' => ['model' => FixedAsset::class, 'column' => 'asset_number', 'prefix' => 'IMO-{BRANCH}-{YEAR}-', 'padding' => 5],
            'product_sku' => ['model' => Product::class, 'column' => 'sku', 'prefix' => 'PRD-', 'padding' => 4],
            'partner_customer_code' => ['model' => Partner::class, 'column' => 'code', 'prefix' => 'C', 'padding' => 4],
            'partner_supplier_code' => ['model' => Partner::class, 'column' => 'code', 'prefix' => 'F', 'padding' => 4],
            'partner_generic_code' => ['model' => Partner::class, 'column' => 'code', 'prefix' => 'P', 'padding' => 4],
            'payment_term_code' => ['model' => PaymentTerm::class, 'column' => 'code', 'prefix' => 'TERM-', 'padding' => 3],
            'price_list_code' => ['model' => PriceList::class, 'column' => 'code', 'prefix' => 'TARIF-', 'padding' => 3],
            'tax_rule_code' => ['model' => TaxRule::class, 'column' => 'code', 'prefix' => 'TAX-', 'padding' => 3],
            'automation_rule_code' => ['model' => AutomationRule::class, 'column' => 'code', 'prefix' => 'AUTO-', 'padding' => 4],
            'commerce_channel_code' => ['table' => 'commerce_channels', 'column' => 'code', 'prefix' => 'CH-', 'padding' => 4],
            'integration_connection_code' => ['model' => IntegrationConnection::class, 'column' => 'code', 'prefix' => 'INT-', 'padding' => 4],
            'hr_department_code' => ['table' => 'hr_departments', 'column' => 'code', 'prefix' => 'DEP-', 'padding' => 4],
            'hr_employee_number' => ['table' => 'hr_employees', 'column' => 'employee_number', 'prefix' => 'EMP-{YEAR}-', 'padding' => 5],
            'hr_leave_number' => ['table' => 'hr_leave_requests', 'column' => 'leave_number', 'prefix' => 'CONGE-{YEAR}-', 'padding' => 4],
            'manufacturing_bom_code' => ['table' => 'manufacturing_boms', 'column' => 'code', 'prefix' => 'BOM-{YEAR}-', 'padding' => 4],
            'payroll_run_number' => ['model' => PayrollRun::class, 'column' => 'run_number', 'prefix' => 'PAY-{YEAR}-', 'padding' => 4],
            'payroll_slip_number' => ['model' => PayrollSlip::class, 'column' => 'slip_number', 'prefix' => 'BUL-{YEAR}-', 'padding' => 5],
            'pos_combo_choice_code' => ['table' => 'pos_combo_choices', 'column' => 'code', 'prefix' => 'CBO-', 'padding' => 4],
            'pos_gift_card_code' => ['table' => 'pos_stored_value_cards', 'column' => 'code', 'prefix' => 'GFT-', 'padding' => 4],
            'pos_loyalty_program_code' => ['table' => 'pos_loyalty_programs', 'column' => 'code', 'prefix' => 'LOY-', 'padding' => 4],
            'pos_menu_category_code' => ['table' => 'pos_menu_categories', 'column' => 'code', 'prefix' => 'CAT-', 'padding' => 4],
            'pos_note_template_code' => ['table' => 'pos_note_templates', 'column' => 'code', 'prefix' => 'NOTE-', 'padding' => 4],
            'pos_preparation_display_code' => ['table' => 'pos_preparation_displays', 'column' => 'code', 'prefix' => 'DSP-', 'padding' => 4],
            'pos_preparation_printer_code' => ['table' => 'pos_preparation_printers', 'column' => 'code', 'prefix' => 'PRN-', 'padding' => 4],
            'pos_product_tag_code' => ['table' => 'pos_product_tags', 'column' => 'code', 'prefix' => 'TAG-', 'padding' => 4],
            'pos_profile_code' => ['table' => 'pos_profiles', 'column' => 'code', 'prefix' => 'POS-', 'padding' => 4],
            'pos_wallet_card_code' => ['table' => 'pos_stored_value_cards', 'column' => 'code', 'prefix' => 'WLT-', 'padding' => 4],
            'production_order_number' => ['table' => 'production_orders', 'column' => 'order_number', 'prefix' => 'OF-{YEAR}-', 'padding' => 4],
            'project_code' => ['model' => Project::class, 'column' => 'code', 'prefix' => 'PRJ-{YEAR}-', 'padding' => 4],
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
