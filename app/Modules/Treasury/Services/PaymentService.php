<?php

namespace App\Modules\Treasury\Services;

use App\Models\User;
use App\Modules\Accounting\Services\AccountingService;
use App\Modules\Accounting\Services\PeriodLockService;
use App\Modules\Core\Company\Services\DocumentNumberService;
use App\Modules\Core\Integrations\Services\IntegrationOutboxService;
use App\Modules\Pos\Models\PosReturn;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Treasury\Models\CashAccount;
use App\Modules\Treasury\Models\Payment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(
        private readonly DocumentNumberService $documentNumberService,
        private readonly AccountingService $accountingService,
        private readonly PeriodLockService $periodLockService,
        private readonly IntegrationOutboxService $integrationOutboxService,
    ) {}

    public function recordCustomerReceipt(int $companyId, int $branchId, SalesInvoice $invoice, CashAccount $cashAccount, array $payload, User $user): Payment
    {
        return DB::transaction(function () use ($companyId, $branchId, $invoice, $cashAccount, $payload, $user) {
            $invoice = SalesInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($invoice->status !== 'validated') {
                throw ValidationException::withMessages([
                    'invoice_id' => 'Cette facture client doit etre approuvee avant enregistrement d un paiement.',
                ]);
            }

            $payment = $this->recordPaymentAgainstDocument(
                companyId: $companyId,
                branchId: $branchId,
                document: $invoice,
                cashAccount: $cashAccount,
                partnerId: $invoice->customer_id,
                paymentType: 'customer_receipt',
                direction: 'in',
                documentKey: 'invoice_id',
                payload: $payload,
                user: $user,
            );

            $this->accountingService->recordCustomerReceipt($payment, $invoice->fresh(), $cashAccount, $user);
            $this->integrationOutboxService->record($payment, 'treasury.customer_receipt.recorded', [
                'payment_number' => $payment->payment_number,
                'invoice_id' => $invoice->id,
                'amount' => (float) $payment->amount,
            ]);

            return $payment;
        });
    }

    public function recordSupplierPayment(int $companyId, int $branchId, PurchaseBill $bill, CashAccount $cashAccount, array $payload, User $user): Payment
    {
        return DB::transaction(function () use ($companyId, $branchId, $bill, $cashAccount, $payload, $user) {
            $bill = PurchaseBill::query()->whereKey($bill->id)->lockForUpdate()->firstOrFail();

            if ($bill->status !== 'validated') {
                throw ValidationException::withMessages([
                    'purchase_bill_id' => 'Cette facture fournisseur doit etre approuvee avant enregistrement d un reglement.',
                ]);
            }

            $payment = $this->recordPaymentAgainstDocument(
                companyId: $companyId,
                branchId: $branchId,
                document: $bill,
                cashAccount: $cashAccount,
                partnerId: $bill->supplier_id,
                paymentType: 'supplier_payment',
                direction: 'out',
                documentKey: 'purchase_bill_id',
                payload: $payload,
                user: $user,
            );

            $this->accountingService->recordSupplierPayment($payment, $bill->fresh(), $cashAccount, $user);
            $this->integrationOutboxService->record($payment, 'treasury.supplier_payment.recorded', [
                'payment_number' => $payment->payment_number,
                'purchase_bill_id' => $bill->id,
                'amount' => (float) $payment->amount,
            ]);

            return $payment;
        });
    }

    public function recordCustomerRefund(int $companyId, int $branchId, SalesInvoice $invoice, CashAccount $cashAccount, array $payload, User $user): Payment
    {
        return DB::transaction(function () use ($companyId, $branchId, $invoice, $cashAccount, $payload, $user) {
            $invoice = SalesInvoice::query()->with('creditNotes')->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($invoice->status !== 'validated') {
                throw ValidationException::withMessages([
                    'invoice_id' => 'Cette facture client doit etre approuvee avant remboursement.',
                ]);
            }

            $this->periodLockService->assertDateOpen($companyId, $payload['payment_date'], 'payment_date');

            $amount = round((float) $payload['amount'], 2);
            $refundableAmount = $this->refundableCustomerAmount($invoice);

            if ($refundableAmount <= 0) {
                throw ValidationException::withMessages([
                    'invoice_id' => 'Cette facture client ne presente aucun trop-percu a rembourser.',
                ]);
            }

            if ($amount <= 0 || $amount > $refundableAmount) {
                throw ValidationException::withMessages([
                    'amount' => 'Le montant rembourse doit etre strictement positif et ne pas depasser le trop-percu client.',
                ]);
            }

            $payment = Payment::query()->create([
                'tenant_id' => $invoice->tenant_id,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'cash_account_id' => $cashAccount->id,
                'pos_session_id' => null,
                'partner_id' => $invoice->customer_id,
                'payment_number' => $this->documentNumberService->nextNumber(
                    companyId: $companyId,
                    documentType: 'payment',
                    branchId: $branchId,
                    date: $payload['payment_date'],
                ),
                'direction' => 'out',
                'payment_type' => 'customer_refund',
                'payment_date' => $payload['payment_date'],
                'amount' => $amount,
                'method' => $payload['method'],
                'reference' => $payload['reference'] ?? null,
                'notes' => $payload['notes'] ?? 'Remboursement client',
                'created_by' => $user->id,
            ]);

            $payment->allocations()->create([
                'allocatable_type' => SalesInvoice::class,
                'allocatable_id' => $invoice->id,
                'allocated_amount' => $amount,
            ]);

            $paid = round((float) $invoice->amount_paid - $amount, 2);
            $documentNetTotal = $this->documentNetTotal($invoice);
            $balance = round($documentNetTotal - $paid, 2);

            $invoice->update([
                'amount_paid' => $paid,
                'balance_due' => $balance,
                'payment_status' => $balance <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid'),
            ]);

            $this->accountingService->recordCustomerRefund($payment, $invoice->fresh(), $cashAccount, $user);
            $this->integrationOutboxService->record($payment, 'treasury.customer_refund.recorded', [
                'payment_number' => $payment->payment_number,
                'invoice_id' => $invoice->id,
                'amount' => (float) $payment->amount,
            ]);

            return $payment->load(['cashAccount', 'partner', 'allocations.allocatable']);
        });
    }

    public function recordPosRefund(int $companyId, int $branchId, SalesInvoice $invoice, CashAccount $cashAccount, PosReturn $return, array $payload, User $user): Payment
    {
        return DB::transaction(function () use ($companyId, $branchId, $invoice, $cashAccount, $return, $payload, $user) {
            $invoice = SalesInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($invoice->status !== 'validated' || $invoice->sale_channel !== 'pos') {
                throw ValidationException::withMessages([
                    'sale' => 'Le remboursement POS ne peut etre applique que sur un ticket valide du point de vente.',
                ]);
            }

            $this->periodLockService->assertDateOpen($companyId, $payload['payment_date'], 'payment_date');

            $amount = round((float) $payload['amount'], 2);
            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Le montant rembourse doit etre strictement positif.',
                ]);
            }

            $payment = Payment::query()->create([
                'tenant_id' => $invoice->tenant_id,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'cash_account_id' => $cashAccount->id,
                'pos_session_id' => $payload['pos_session_id'] ?? null,
                'partner_id' => $invoice->customer_id,
                'payment_number' => $this->documentNumberService->nextNumber(
                    companyId: $companyId,
                    documentType: 'payment',
                    branchId: $branchId,
                    date: $payload['payment_date'],
                ),
                'direction' => 'out',
                'payment_type' => 'pos_refund',
                'payment_date' => $payload['payment_date'],
                'amount' => $amount,
                'method' => $payload['method'],
                'reference' => $payload['reference'] ?? $return->return_number,
                'notes' => $payload['notes'] ?? 'Remboursement ticket POS',
                'created_by' => $user->id,
            ]);

            $payment->allocations()->create([
                'allocatable_type' => SalesInvoice::class,
                'allocatable_id' => $invoice->id,
                'allocated_amount' => $amount,
            ]);

            $return->update(['payment_id' => $payment->id]);
            $this->accountingService->recordPosRefund($payment, $invoice->fresh(), $cashAccount, $return->fresh(), $user);
            $this->integrationOutboxService->record($payment, 'treasury.pos_refund.recorded', [
                'payment_number' => $payment->payment_number,
                'return_id' => $return->id,
                'amount' => (float) $payment->amount,
            ]);

            return $payment->load(['cashAccount', 'partner', 'allocations.allocatable', 'posSession']);
        });
    }

    public function recordInternalTransfer(
        int $companyId,
        int $sourceBranchId,
        int $destinationBranchId,
        CashAccount $sourceCashAccount,
        CashAccount $destinationCashAccount,
        array $payload,
        User $user,
    ): Payment {
        return DB::transaction(function () use ($companyId, $sourceBranchId, $destinationBranchId, $sourceCashAccount, $destinationCashAccount, $payload, $user) {
            if ($sourceCashAccount->is($destinationCashAccount)) {
                throw ValidationException::withMessages([
                    'destination_cash_account_id' => 'Le compte source et le compte destination doivent etre differents.',
                ]);
            }

            $this->periodLockService->assertDateOpen($companyId, $payload['payment_date'], 'payment_date');

            $amount = round((float) $payload['amount'], 2);
            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Le montant du versement doit etre strictement positif.',
                ]);
            }

            $reference = $payload['reference'] ?? null;
            $notes = trim((string) ($payload['notes'] ?? ''));

            $outgoingPayment = Payment::query()->create([
                'tenant_id' => $sourceCashAccount->tenant_id,
                'company_id' => $companyId,
                'branch_id' => $sourceBranchId,
                'cash_account_id' => $sourceCashAccount->id,
                'pos_session_id' => null,
                'partner_id' => null,
                'payment_number' => $this->documentNumberService->nextNumber(
                    companyId: $companyId,
                    documentType: 'payment',
                    branchId: $sourceBranchId,
                    date: $payload['payment_date'],
                ),
                'direction' => 'out',
                'payment_type' => 'internal_transfer',
                'payment_date' => $payload['payment_date'],
                'amount' => $amount,
                'method' => $payload['method'],
                'reference' => $reference,
                'notes' => $this->internalTransferNotes('out', $sourceCashAccount, $destinationCashAccount, $notes),
                'created_by' => $user->id,
            ]);

            $incomingPayment = Payment::query()->create([
                'tenant_id' => $destinationCashAccount->tenant_id,
                'company_id' => $companyId,
                'branch_id' => $destinationBranchId,
                'cash_account_id' => $destinationCashAccount->id,
                'pos_session_id' => null,
                'partner_id' => null,
                'payment_number' => $this->documentNumberService->nextNumber(
                    companyId: $companyId,
                    documentType: 'payment',
                    branchId: $destinationBranchId,
                    date: $payload['payment_date'],
                ),
                'direction' => 'in',
                'payment_type' => 'internal_transfer',
                'payment_date' => $payload['payment_date'],
                'amount' => $amount,
                'method' => $payload['method'],
                'reference' => $reference,
                'notes' => $this->internalTransferNotes('in', $sourceCashAccount, $destinationCashAccount, $notes),
                'created_by' => $user->id,
            ]);

            $outgoingPayment->allocations()->create([
                'allocatable_type' => Payment::class,
                'allocatable_id' => $incomingPayment->id,
                'allocated_amount' => $amount,
            ]);

            $incomingPayment->allocations()->create([
                'allocatable_type' => Payment::class,
                'allocatable_id' => $outgoingPayment->id,
                'allocated_amount' => $amount,
            ]);

            $this->accountingService->recordInternalTransfer(
                $outgoingPayment,
                $incomingPayment,
                $sourceCashAccount,
                $destinationCashAccount,
                $user,
            );

            $this->integrationOutboxService->record($outgoingPayment, 'treasury.internal_transfer.recorded', [
                'payment_number' => $outgoingPayment->payment_number,
                'counterpart_payment_number' => $incomingPayment->payment_number,
                'source_cash_account_id' => $sourceCashAccount->id,
                'destination_cash_account_id' => $destinationCashAccount->id,
                'amount' => (float) $outgoingPayment->amount,
            ]);

            return $outgoingPayment->load(['cashAccount', 'allocations.allocatable']);
        });
    }

    private function recordPaymentAgainstDocument(
        int $companyId,
        int $branchId,
        Model $document,
        CashAccount $cashAccount,
        int $partnerId,
        string $paymentType,
        string $direction,
        string $documentKey,
        array $payload,
        User $user,
    ): Payment {
        $this->periodLockService->assertDateOpen($companyId, $payload['payment_date'], 'payment_date');

        $amount = (float) $payload['amount'];
        $balanceDue = (float) $document->balance_due;

        if ($balanceDue <= 0) {
            throw ValidationException::withMessages([
                $documentKey => 'Ce document est deja totalement regle.',
            ]);
        }

        if ($amount > $balanceDue) {
            throw ValidationException::withMessages([
                'amount' => 'Le montant depasse le solde restant du document.',
            ]);
        }

        $payment = Payment::query()->create([
            'tenant_id' => $document->tenant_id,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'cash_account_id' => $cashAccount->id,
            'pos_session_id' => $payload['pos_session_id'] ?? null,
            'partner_id' => $partnerId,
            'payment_number' => $this->documentNumberService->nextNumber(
                companyId: $companyId,
                documentType: 'payment',
                branchId: $branchId,
                date: $payload['payment_date'],
            ),
            'direction' => $direction,
            'payment_type' => $paymentType,
            'payment_date' => $payload['payment_date'],
            'amount' => $amount,
            'method' => $payload['method'],
            'reference' => $payload['reference'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'created_by' => $user->id,
        ]);

        $payment->allocations()->create([
            'allocatable_type' => $document::class,
            'allocatable_id' => $document->getKey(),
            'allocated_amount' => $amount,
        ]);

        $paid = round((float) $document->amount_paid + $amount, 2);
        $documentNetTotal = $this->documentNetTotal($document);
        $balance = round($documentNetTotal - $paid, 2);

        $document->update([
            'amount_paid' => $paid,
            'balance_due' => $balance,
            'payment_status' => $balance <= 0 ? 'paid' : 'partial',
        ]);

        return $payment->load(['cashAccount', 'partner', 'allocations.allocatable']);
    }

    private function documentNetTotal(Model $document): float
    {
        if ($document instanceof SalesInvoice) {
            $credited = (float) $document->creditNotes()->sum('total');

            return round((float) $document->total - $credited, 2);
        }

        return round((float) $document->total, 2);
    }

    private function refundableCustomerAmount(SalesInvoice $invoice): float
    {
        return max(0, round((float) $invoice->amount_paid - $this->documentNetTotal($invoice), 2));
    }

    private function internalTransferNotes(
        string $direction,
        CashAccount $sourceCashAccount,
        CashAccount $destinationCashAccount,
        string $notes,
    ): string {
        $default = $direction === 'out'
            ? 'Versement interne vers '.$destinationCashAccount->name
            : 'Reception de versement depuis '.$sourceCashAccount->name;

        return $notes !== '' ? $default.' · '.$notes : $default;
    }
}
