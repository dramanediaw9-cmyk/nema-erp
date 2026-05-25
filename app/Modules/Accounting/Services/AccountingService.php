<?php

namespace App\Modules\Accounting\Services;

use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Core\Company\Models\DocumentSequence;
use App\Modules\Core\Company\Services\DocumentNumberService;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Pos\Models\PosReturn;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Purchases\Models\PurchaseCreditNote;
use App\Modules\Sales\Models\SalesCreditNote;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Treasury\Models\CashAccount;
use App\Modules\Treasury\Models\Payment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AccountingService
{
    public function __construct(
        private readonly DocumentNumberService $documentNumberService,
        private readonly ChartOfAccountsService $chartOfAccountsService,
        private readonly PeriodLockService $periodLockService,
    ) {}

    public function recordSalesInvoice(SalesInvoice $invoice, User $user): JournalEntry
    {
        $lines = [
            [
                'account_code' => '411000',
                'partner_id' => $invoice->customer_id,
                'label' => 'Creance client '.$invoice->invoice_number,
                'debit' => (float) $invoice->total,
                'credit' => 0,
            ],
            [
                'account_code' => '707000',
                'partner_id' => $invoice->customer_id,
                'label' => 'Vente '.$invoice->invoice_number,
                'debit' => 0,
                'credit' => (float) $this->netAmount($invoice),
            ],
        ];

        if ((float) $invoice->tax_total > 0.009) {
            $lines[] = [
                'account_code' => '443100',
                'partner_id' => $invoice->customer_id,
                'label' => 'TVA collectee '.$invoice->invoice_number,
                'debit' => 0,
                'credit' => (float) $invoice->tax_total,
            ];
        }

        return $this->postEntry(
            companyId: $invoice->company_id,
            tenantId: $invoice->tenant_id,
            branchId: $invoice->branch_id,
            journalCode: 'VEN',
            entryDate: $invoice->invoice_date?->format('Y-m-d') ?? now()->toDateString(),
            source: $invoice,
            reference: $invoice->invoice_number,
            description: 'Facture de vente '.$invoice->invoice_number,
            createdBy: $user->id,
            lines: $lines,
        );
    }

    public function recordPurchaseBill(PurchaseBill $bill, User $user): JournalEntry
    {
        $lines = [
            [
                'account_code' => '601000',
                'partner_id' => $bill->supplier_id,
                'label' => 'Achat '.$bill->bill_number,
                'debit' => (float) $this->netAmount($bill),
                'credit' => 0,
            ],
        ];

        if ((float) $bill->tax_total > 0.009) {
            $lines[] = [
                'account_code' => '445100',
                'partner_id' => $bill->supplier_id,
                'label' => 'TVA deductible '.$bill->bill_number,
                'debit' => (float) $bill->tax_total,
                'credit' => 0,
            ];
        }

        $lines[] = [
            'account_code' => '401000',
            'partner_id' => $bill->supplier_id,
            'label' => 'Dette fournisseur '.$bill->bill_number,
            'debit' => 0,
            'credit' => (float) $bill->total,
        ];

        return $this->postEntry(
            companyId: $bill->company_id,
            tenantId: $bill->tenant_id,
            branchId: $bill->branch_id,
            journalCode: 'ACH',
            entryDate: $bill->bill_date?->format('Y-m-d') ?? now()->toDateString(),
            source: $bill,
            reference: $bill->bill_number,
            description: 'Facture fournisseur '.$bill->bill_number,
            createdBy: $user->id,
            lines: $lines,
        );
    }

    public function recordSalesCreditNote(SalesCreditNote $creditNote, SalesInvoice $invoice, User $user): JournalEntry
    {
        return $this->postEntry(
            companyId: $creditNote->company_id,
            tenantId: $creditNote->tenant_id,
            branchId: $creditNote->branch_id,
            journalCode: 'AVO',
            entryDate: $creditNote->credit_note_date?->format('Y-m-d') ?? now()->toDateString(),
            source: $creditNote,
            reference: $creditNote->credit_note_number,
            description: 'Avoir client '.$creditNote->credit_note_number,
            createdBy: $user->id,
            lines: [
                [
                    'account_code' => '707000',
                    'partner_id' => $creditNote->customer_id,
                    'label' => 'Avoir '.$creditNote->credit_note_number,
                    'debit' => (float) $creditNote->total,
                    'credit' => 0,
                ],
                [
                    'account_code' => '411000',
                    'partner_id' => $invoice->customer_id,
                    'label' => 'Reduction creance '.$invoice->invoice_number,
                    'debit' => 0,
                    'credit' => (float) $creditNote->total,
                ],
            ],
        );
    }

    public function recordPurchaseCreditNote(PurchaseCreditNote $creditNote, PurchaseBill $bill, User $user): JournalEntry
    {
        $lines = [
            [
                'account_code' => '401000',
                'partner_id' => $creditNote->supplier_id,
                'label' => 'Reduction dette '.$bill->bill_number,
                'debit' => (float) $creditNote->total,
                'credit' => 0,
            ],
            [
                'account_code' => '601000',
                'partner_id' => $creditNote->supplier_id,
                'label' => 'Avoir fournisseur '.$creditNote->credit_note_number,
                'debit' => 0,
                'credit' => (float) $creditNote->net_total,
            ],
        ];

        if ((float) $creditNote->tax_total > 0.009) {
            $lines[] = [
                'account_code' => '445100',
                'partner_id' => $creditNote->supplier_id,
                'label' => 'TVA deductible ajustee '.$creditNote->credit_note_number,
                'debit' => 0,
                'credit' => (float) $creditNote->tax_total,
            ];
        }

        return $this->postEntry(
            companyId: $creditNote->company_id,
            tenantId: $creditNote->tenant_id,
            branchId: $creditNote->branch_id,
            journalCode: 'AVF',
            entryDate: $creditNote->credit_note_date?->format('Y-m-d') ?? now()->toDateString(),
            source: $creditNote,
            reference: $creditNote->credit_note_number,
            description: 'Avoir fournisseur '.$creditNote->credit_note_number,
            createdBy: $user->id,
            lines: $lines,
        );
    }

    public function recordCustomerReceipt(Payment $payment, SalesInvoice $invoice, CashAccount $cashAccount, User $user): JournalEntry
    {
        $treasuryCode = $this->treasuryAccountCode($cashAccount);

        return $this->postEntry(
            companyId: $payment->company_id,
            tenantId: $payment->tenant_id,
            branchId: $payment->branch_id,
            journalCode: 'TRE',
            entryDate: $payment->payment_date?->format('Y-m-d') ?? now()->toDateString(),
            source: $payment,
            reference: $payment->payment_number,
            description: 'Encaissement client '.$payment->payment_number,
            createdBy: $user->id,
            lines: [
                [
                    'account_code' => $treasuryCode,
                    'partner_id' => $payment->partner_id,
                    'label' => 'Encaissement '.$payment->payment_number,
                    'debit' => (float) $payment->amount,
                    'credit' => 0,
                ],
                [
                    'account_code' => '411000',
                    'partner_id' => $invoice->customer_id,
                    'label' => 'Reglement client '.$invoice->invoice_number,
                    'debit' => 0,
                    'credit' => (float) $payment->amount,
                ],
            ],
        );
    }

    public function recordCustomerRefund(Payment $payment, SalesInvoice $invoice, CashAccount $cashAccount, User $user): JournalEntry
    {
        $treasuryCode = $this->treasuryAccountCode($cashAccount);

        return $this->postEntry(
            companyId: $payment->company_id,
            tenantId: $payment->tenant_id,
            branchId: $payment->branch_id,
            journalCode: 'TRE',
            entryDate: $payment->payment_date?->format('Y-m-d') ?? now()->toDateString(),
            source: $payment,
            reference: $payment->payment_number,
            description: 'Remboursement client '.$payment->payment_number,
            createdBy: $user->id,
            lines: [
                [
                    'account_code' => '411000',
                    'partner_id' => $invoice->customer_id,
                    'label' => 'Remboursement trop-percu '.$invoice->invoice_number,
                    'debit' => (float) $payment->amount,
                    'credit' => 0,
                ],
                [
                    'account_code' => $treasuryCode,
                    'partner_id' => $payment->partner_id,
                    'label' => 'Sortie tresorerie '.$payment->payment_number,
                    'debit' => 0,
                    'credit' => (float) $payment->amount,
                ],
            ],
        );
    }

    public function recordSupplierPayment(Payment $payment, PurchaseBill $bill, CashAccount $cashAccount, User $user): JournalEntry
    {
        $treasuryCode = $this->treasuryAccountCode($cashAccount);

        return $this->postEntry(
            companyId: $payment->company_id,
            tenantId: $payment->tenant_id,
            branchId: $payment->branch_id,
            journalCode: 'TRE',
            entryDate: $payment->payment_date?->format('Y-m-d') ?? now()->toDateString(),
            source: $payment,
            reference: $payment->payment_number,
            description: 'Reglement fournisseur '.$payment->payment_number,
            createdBy: $user->id,
            lines: [
                [
                    'account_code' => '401000',
                    'partner_id' => $bill->supplier_id,
                    'label' => 'Reglement fournisseur '.$bill->bill_number,
                    'debit' => (float) $payment->amount,
                    'credit' => 0,
                ],
                [
                    'account_code' => $treasuryCode,
                    'partner_id' => $payment->partner_id,
                    'label' => 'Sortie tresorerie '.$payment->payment_number,
                    'debit' => 0,
                    'credit' => (float) $payment->amount,
                ],
            ],
        );
    }

    public function recordPosRefund(Payment $payment, SalesInvoice $invoice, CashAccount $cashAccount, PosReturn $return, User $user): JournalEntry
    {
        $treasuryCode = $this->treasuryAccountCode($cashAccount);

        return $this->postEntry(
            companyId: $payment->company_id,
            tenantId: $payment->tenant_id,
            branchId: $payment->branch_id,
            journalCode: 'TRE',
            entryDate: $payment->payment_date?->format('Y-m-d') ?? now()->toDateString(),
            source: $payment,
            reference: $payment->payment_number,
            description: 'Remboursement POS '.$return->return_number,
            createdBy: $user->id,
            lines: [
                [
                    'account_code' => '707000',
                    'partner_id' => $invoice->customer_id,
                    'label' => 'Retour ticket '.$invoice->invoice_number,
                    'debit' => (float) $payment->amount,
                    'credit' => 0,
                ],
                [
                    'account_code' => $treasuryCode,
                    'partner_id' => $invoice->customer_id,
                    'label' => 'Remboursement '.$payment->payment_number,
                    'debit' => 0,
                    'credit' => (float) $payment->amount,
                ],
            ],
        );
    }

    public function recordInternalTransfer(
        Payment $outgoingPayment,
        Payment $incomingPayment,
        CashAccount $sourceCashAccount,
        CashAccount $destinationCashAccount,
        User $user,
    ): JournalEntry {
        $sourceTreasuryCode = $this->treasuryAccountCode($sourceCashAccount);
        $destinationTreasuryCode = $this->treasuryAccountCode($destinationCashAccount);

        return $this->postEntry(
            companyId: $outgoingPayment->company_id,
            tenantId: $outgoingPayment->tenant_id,
            branchId: $outgoingPayment->branch_id,
            journalCode: 'TRE',
            entryDate: $outgoingPayment->payment_date?->format('Y-m-d') ?? now()->toDateString(),
            source: $outgoingPayment,
            reference: $outgoingPayment->payment_number,
            description: 'Versement interne '.$outgoingPayment->payment_number,
            createdBy: $user->id,
            lines: [
                [
                    'account_code' => $destinationTreasuryCode,
                    'partner_id' => null,
                    'label' => 'Reception '.$incomingPayment->payment_number,
                    'debit' => (float) $outgoingPayment->amount,
                    'credit' => 0,
                ],
                [
                    'account_code' => $sourceTreasuryCode,
                    'partner_id' => null,
                    'label' => 'Versement '.$outgoingPayment->payment_number,
                    'debit' => 0,
                    'credit' => (float) $outgoingPayment->amount,
                ],
            ],
        );
    }

    public function recordExpense(Expense $expense, User $user): JournalEntry
    {
        $expenseAccountCode = $expense->category?->default_account_code ?: '606300';
        $counterpartCode = $expense->payment_status === 'paid' && $expense->cashAccount
            ? $this->treasuryAccountCode($expense->cashAccount)
            : ($expense->supplier_id ? '401000' : '471000');

        return $this->postEntry(
            companyId: $expense->company_id,
            tenantId: $expense->tenant_id,
            branchId: $expense->branch_id,
            journalCode: 'OD',
            entryDate: $expense->expense_date?->format('Y-m-d') ?? now()->toDateString(),
            source: $expense,
            reference: $expense->expense_number,
            description: 'Depense '.$expense->expense_number,
            createdBy: $user->id,
            lines: [
                [
                    'account_code' => $expenseAccountCode,
                    'partner_id' => $expense->supplier_id,
                    'label' => $expense->description,
                    'debit' => (float) $expense->total,
                    'credit' => 0,
                ],
                [
                    'account_code' => $counterpartCode,
                    'partner_id' => $expense->supplier_id,
                    'label' => 'Contrepartie '.$expense->expense_number,
                    'debit' => 0,
                    'credit' => (float) $expense->total,
                ],
            ],
        );
    }

    public function reverseJournalEntry(JournalEntry $entry, User $user, string $entryDate, string $reason): JournalEntry
    {
        $entry->loadMissing(['lines.account', 'reversalEntry']);

        if (! $entry->posted_at) {
            throw ValidationException::withMessages([
                'journal_entry' => 'Seules les ecritures comptables postees peuvent etre contrepassees.',
            ]);
        }

        if ($entry->is_reversal) {
            throw ValidationException::withMessages([
                'journal_entry' => 'Une contrepassation ne peut pas etre annulee depuis cet ecran.',
            ]);
        }

        if ($entry->reversalEntry) {
            throw ValidationException::withMessages([
                'journal_entry' => 'Cette ecriture comptable a deja ete contrepassee.',
            ]);
        }

        if ($entry->entry_date && $entry->entry_date->gt($entryDate)) {
            throw ValidationException::withMessages([
                'reversal_date' => 'La date de contrepassation doit etre posterieure ou egale a la date de l ecriture.',
            ]);
        }

        $reversalLines = $entry->lines->map(function ($line): array {
            $accountCode = $line->account?->code;

            if (! $accountCode) {
                throw new RuntimeException('Compte comptable introuvable pour la ligne a contrepasser.');
            }

            return [
                'account_code' => $accountCode,
                'partner_id' => $line->partner_id,
                'label' => $line->label ? 'Contrepassation '.$line->label : 'Contrepassation '.$line->id,
                'debit' => (float) $line->credit,
                'credit' => (float) $line->debit,
            ];
        })->all();

        return $this->postEntry(
            companyId: $entry->company_id,
            tenantId: $entry->tenant_id,
            branchId: $entry->branch_id,
            journalCode: $entry->journal_code,
            entryDate: $entryDate,
            source: $entry,
            reference: $entry->journal_number,
            description: Str::limit('Contrepassation '.$entry->journal_number.' - '.$reason, 255, ''),
            createdBy: $user->id,
            lines: $reversalLines,
            isReversal: true,
            reversesJournalEntryId: $entry->id,
            reversalReason: $reason,
        );
    }

    private function postEntry(
        int $companyId,
        ?int $tenantId,
        ?int $branchId,
        string $journalCode,
        string $entryDate,
        Model $source,
        ?string $reference,
        string $description,
        ?int $createdBy,
        array $lines,
        bool $isReversal = false,
        ?int $reversesJournalEntryId = null,
        ?string $reversalReason = null,
    ): JournalEntry {
        $this->ensureAccountingSetup($companyId);
        $this->periodLockService->assertDateOpen($companyId, $entryDate, 'entry_date');

        $existing = JournalEntry::query()
            ->with(['lines.account', 'lines.partner', 'branch'])
            ->where('company_id', $companyId)
            ->where('source_type', $source::class)
            ->where('source_id', $source->getKey())
            ->where('journal_code', $journalCode)
            ->first();

        if ($existing) {
            if ($isReversal) {
                throw ValidationException::withMessages([
                    'journal_entry' => 'Cette ecriture comptable a deja ete contrepassee.',
                ]);
            }

            return $existing;
        }

        $debitTotal = round(collect($lines)->sum(fn (array $line) => (float) ($line['debit'] ?? 0)), 2);
        $creditTotal = round(collect($lines)->sum(fn (array $line) => (float) ($line['credit'] ?? 0)), 2);

        if (abs($debitTotal - $creditTotal) > 0.009) {
            throw new RuntimeException('L ecriture comptable n est pas equilibree.');
        }

        $accountIds = Account::query()
            ->where('company_id', $companyId)
            ->whereIn('code', collect($lines)->pluck('account_code')->unique()->all())
            ->pluck('id', 'code');

        $journalNumber = $this->documentNumberService->nextNumber(
            companyId: $companyId,
            documentType: 'journal_entry',
            branchId: $branchId,
            date: $entryDate,
            journalCode: $journalCode,
        );

        $entry = JournalEntry::query()->create([
            'tenant_id' => $tenantId,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'journal_number' => $journalNumber,
            'journal_code' => $journalCode,
            'status' => 'posted',
            'entry_date' => $entryDate,
            'source_type' => $source::class,
            'source_id' => $source->getKey(),
            'reference' => $reference,
            'description' => $description,
            'posted_at' => now(),
            'is_reversal' => $isReversal,
            'reverses_journal_entry_id' => $reversesJournalEntryId,
            'reversal_reason' => $reversalReason,
            'total_debit' => $debitTotal,
            'total_credit' => $creditTotal,
            'created_by' => $createdBy,
        ]);

        foreach ($lines as $line) {
            $accountId = $accountIds->get($line['account_code']);

            if (! $accountId) {
                throw new RuntimeException('Compte comptable introuvable: '.Arr::get($line, 'account_code'));
            }

            $entry->lines()->create([
                'account_id' => $accountId,
                'partner_id' => $line['partner_id'] ?? null,
                'label' => $line['label'] ?? null,
                'debit' => round((float) ($line['debit'] ?? 0), 2),
                'credit' => round((float) ($line['credit'] ?? 0), 2),
            ]);
        }

        $hash = hash('sha256', json_encode([
            'journal_number' => $entry->journal_number,
            'reference' => $entry->reference,
            'is_reversal' => $entry->is_reversal,
            'reverses_journal_entry_id' => $entry->reverses_journal_entry_id,
            'reversal_reason' => $entry->reversal_reason,
            'lines' => $entry->lines()->orderBy('id')->get(['account_id', 'partner_id', 'debit', 'credit', 'label'])->toArray(),
        ], JSON_THROW_ON_ERROR));

        JournalEntry::withoutEvents(function () use ($entry, $hash): void {
            $entry->forceFill(['immutable_hash' => $hash])->save();
        });

        return $entry->load(['lines.account', 'lines.partner', 'branch', 'reversalOf']);
    }

    private function ensureAccountingSetup(int $companyId): void
    {
        $this->chartOfAccountsService->ensureDefaultAccounts($companyId);

        DocumentSequence::query()->firstOrCreate(
            ['company_id' => $companyId, 'document_type' => 'journal_entry'],
            ['prefix' => 'JRN-{JOURNAL}-{YEAR}-', 'next_number' => 1, 'padding' => 5]
        );
    }

    private function netAmount(Model $document): float
    {
        $net = (float) ($document->getAttribute('net_total') ?? 0);

        if ($net > 0) {
            return round($net, 2);
        }

        $tax = (float) ($document->getAttribute('tax_total') ?? 0);

        return round((float) $document->total - $tax, 2);
    }

    private function treasuryAccountCode(CashAccount $cashAccount): string
    {
        return match ($cashAccount->type) {
            'bank' => '521000',
            'mobile_money' => '531000',
            default => '571000',
        };
    }
}
