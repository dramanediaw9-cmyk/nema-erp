<?php

namespace App\Modules\Treasury\Services;

use App\Models\User;
use App\Modules\Core\Company\Services\DocumentNumberService;
use App\Modules\Treasury\Models\CashAccount;
use App\Modules\Treasury\Models\Payment;
use App\Modules\Treasury\Models\TreasuryReconciliation;
use App\Modules\Treasury\Models\TreasuryReconciliationPayment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TreasuryReconciliationService
{
    public function __construct(private readonly DocumentNumberService $documentNumberService)
    {
    }

    public function candidatePaymentsQuery(int $companyId, CashAccount $cashAccount, string $statementDate): Builder
    {
        return Payment::query()
            ->with(['partner', 'allocations.allocatable'])
            ->where('company_id', $companyId)
            ->where('cash_account_id', $cashAccount->id)
            ->whereDate('payment_date', '<=', $statementDate)
            ->whereDoesntHave('reconciliationItem');
    }

    public function bookBalance(int $companyId, CashAccount $cashAccount, string $statementDate): float
    {
        $movements = Payment::query()
            ->where('company_id', $companyId)
            ->where('cash_account_id', $cashAccount->id)
            ->whereDate('payment_date', '<=', $statementDate)
            ->get(['direction', 'amount']);

        return round((float) $cashAccount->opening_balance + $movements->sum(function (Payment $payment): float {
            return $payment->direction === 'in'
                ? (float) $payment->amount
                : -1 * (float) $payment->amount;
        }), 2);
    }

    public function create(int $companyId, ?int $branchId, CashAccount $cashAccount, array $payload, User $user): TreasuryReconciliation
    {
        return DB::transaction(function () use ($companyId, $branchId, $cashAccount, $payload, $user) {
            $paymentIds = collect($payload['payment_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->values();

            if ($paymentIds->isEmpty()) {
                throw ValidationException::withMessages([
                    'payment_ids' => 'Selectionnez au moins un mouvement a rapprocher.',
                ]);
            }

            $payments = $this->candidatePaymentsQuery($companyId, $cashAccount, $payload['statement_date'])
                ->whereIn('id', $paymentIds)
                ->lockForUpdate()
                ->get();

            if ($payments->count() !== $paymentIds->count()) {
                throw ValidationException::withMessages([
                    'payment_ids' => 'Un ou plusieurs mouvements selectionnes sont deja rapproches ou hors filtre.',
                ]);
            }

            $bookBalance = $this->bookBalance($companyId, $cashAccount, $payload['statement_date']);
            $matchedTotal = $this->signedAmount($payments);
            $statementBalance = round((float) $payload['statement_balance'], 2);
            $difference = round($statementBalance - $bookBalance, 2);
            $effectiveBranchId = $cashAccount->branch_id ?: $branchId;

            $reconciliation = TreasuryReconciliation::query()->create([
                'company_id' => $companyId,
                'branch_id' => $effectiveBranchId,
                'cash_account_id' => $cashAccount->id,
                'reconciliation_number' => $this->documentNumberService->nextNumber(
                    companyId: $companyId,
                    documentType: 'treasury_reconciliation',
                    branchId: $effectiveBranchId,
                    date: $payload['statement_date'],
                ),
                'statement_date' => $payload['statement_date'],
                'statement_reference' => $payload['statement_reference'] ?? null,
                'statement_balance' => $statementBalance,
                'matched_total' => $matchedTotal,
                'book_balance' => $bookBalance,
                'difference' => $difference,
                'payments_count' => $payments->count(),
                'status' => abs($difference) < 0.01 ? 'balanced' : 'with_gap',
                'notes' => $payload['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            $payments->each(function (Payment $payment) use ($reconciliation): void {
                TreasuryReconciliationPayment::query()->create([
                    'treasury_reconciliation_id' => $reconciliation->id,
                    'payment_id' => $payment->id,
                ]);
            });

            return $reconciliation->load(['cashAccount', 'items.payment.partner']);
        });
    }

    public function signedAmount(Collection $payments): float
    {
        return round($payments->sum(function (Payment $payment): float {
            return $payment->direction === 'in'
                ? (float) $payment->amount
                : -1 * (float) $payment->amount;
        }), 2);
    }
}
