<?php

namespace App\Modules\Expenses\Services;

use App\Models\User;
use App\Modules\Accounting\Services\AccountingService;
use App\Modules\Accounting\Services\PeriodLockService;
use App\Modules\Core\Company\Services\DocumentNumberService;
use App\Modules\Core\Integrations\Services\IntegrationOutboxService;
use App\Modules\Expenses\Models\Expense;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpenseService
{
    public function __construct(
        private readonly DocumentNumberService $documentNumberService,
        private readonly AccountingService $accountingService,
        private readonly PeriodLockService $periodLockService,
        private readonly IntegrationOutboxService $integrationOutboxService,
    ) {
    }

    public function createValidated(int $companyId, int $branchId, array $payload, User $user): Expense
    {
        return $this->persistExpense($companyId, $branchId, $payload, $user);
    }

    public function createPending(int $companyId, int $branchId, array $payload, User $user): Expense
    {
        return $this->persistExpense($companyId, $branchId, $payload, $user, 'pending_approval', false);
    }

    public function approve(Expense $expense, User $user): Expense
    {
        return DB::transaction(function () use ($expense, $user) {
            $expense = Expense::query()
                ->with(['category', 'cashAccount', 'supplier', 'branch', 'company'])
                ->whereKey($expense->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($expense->status !== 'pending_approval') {
                throw ValidationException::withMessages([
                    'expense' => 'Cette depense n est pas en attente d approbation.',
                ]);
            }

            $this->periodLockService->assertDateOpen($expense->company_id, $expense->expense_date?->toDateString() ?? now()->toDateString(), 'expense_date');

            $approvedAt = now();

            $expense->update([
                'status' => 'validated',
                'approved_at' => $approvedAt,
                'approved_by' => $user->id,
            ]);

            $expense = $expense->fresh(['category', 'cashAccount', 'supplier', 'branch', 'company']);
            $this->accountingService->recordExpense($expense, $user);
            $this->integrationOutboxService->record($expense, 'expenses.expense.approved', [
                'expense_number' => $expense->expense_number,
                'status' => $expense->status,
                'total' => (float) $expense->total,
            ]);

            return $expense->load(['approver', 'creator']);
        });
    }

    public function reject(Expense $expense, User $user, ?string $reason = null): Expense
    {
        return DB::transaction(function () use ($expense, $user, $reason) {
            $expense = Expense::query()
                ->with(['category', 'cashAccount', 'supplier', 'branch', 'company'])
                ->whereKey($expense->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($expense->status !== 'pending_approval') {
                throw ValidationException::withMessages([
                    'expense' => 'Cette depense n est pas en attente d approbation.',
                ]);
            }

            $rejectedAt = now();

            $expense->update([
                'status' => 'rejected',
                'approved_at' => null,
                'approved_by' => null,
                'rejected_at' => $rejectedAt,
                'rejected_by' => $user->id,
                'rejection_reason' => $reason,
            ]);

            $expense = $expense->fresh(['category', 'cashAccount', 'supplier', 'branch', 'company', 'creator', 'approver', 'rejector', 'approvalSteps.approver', 'approvalSteps.rejectedBy']);

            $this->integrationOutboxService->record($expense, 'expenses.expense.rejected', [
                'expense_number' => $expense->expense_number,
                'status' => $expense->status,
                'rejected_at' => $rejectedAt->toIso8601String(),
                'rejected_by' => $user->id,
                'rejection_reason' => $reason,
            ]);

            return $expense;
        });
    }

    private function persistExpense(
        int $companyId,
        int $branchId,
        array $payload,
        User $user,
        string $status = 'validated',
        bool $postAccounting = true,
    ): Expense {
        $this->periodLockService->assertDateOpen($companyId, $payload['expense_date'], 'expense_date');

        return DB::transaction(function () use ($companyId, $branchId, $payload, $user, $status, $postAccounting) {
            $expenseNumber = $this->documentNumberService->nextNumber(
                companyId: $companyId,
                documentType: 'expense',
                branchId: $branchId,
                date: $payload['expense_date'],
            );
            $isPaid = filled($payload['cash_account_id'] ?? null);
            $approvedAt = $status === 'validated'
                ? Carbon::parse($payload['approved_at'] ?? $payload['expense_date'])
                : null;

            $expense = Expense::query()->create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'expense_category_id' => $payload['expense_category_id'],
                'supplier_id' => $payload['supplier_id'] ?? null,
                'cash_account_id' => $payload['cash_account_id'] ?? null,
                'expense_number' => $expenseNumber,
                'expense_date' => $payload['expense_date'],
                'description' => $payload['description'],
                'total' => $payload['total'],
                'status' => $status,
                'payment_status' => $isPaid ? 'paid' : 'unpaid',
                'payment_date' => $isPaid ? ($payload['payment_date'] ?? $payload['expense_date']) : null,
                'payment_method' => $isPaid ? ($payload['payment_method'] ?? 'cash') : null,
                'payment_reference' => $payload['payment_reference'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'approved_at' => $approvedAt,
                'approved_by' => $status === 'validated' ? $user->id : null,
                'created_by' => $user->id,
            ]);

            $expense = $expense->load(['category', 'cashAccount', 'supplier', 'approver']);

            if ($postAccounting) {
                $this->accountingService->recordExpense($expense, $user);
            }

            $this->integrationOutboxService->record($expense, $status === 'validated' ? 'expenses.expense.validated' : 'expenses.expense.created', [
                'expense_number' => $expense->expense_number,
                'status' => $expense->status,
                'total' => (float) $expense->total,
                'supplier_id' => $expense->supplier_id,
            ]);

            return $expense;
        });
    }
}
