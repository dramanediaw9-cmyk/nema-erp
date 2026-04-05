<?php

namespace App\Modules\Treasury\Services;

use App\Models\User;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Treasury\Models\CashAccount;
use App\Modules\Treasury\Models\Payment;
use Illuminate\Validation\ValidationException;

class PaymentControlService
{
    private const DEFAULT_LIMIT = 250000.0;

    private const ROLE_LIMITS = [
        'platform_admin' => null,
        'company_admin' => null,
        'director' => 5000000.0,
        'operations_officer' => 500000.0,
        'cashier' => 150000.0,
    ];

    public function listingBranchScope(User $user, ?int $workspaceBranchId = null, ?int $preferredBranchId = null): ?int
    {
        return $user->resolvedBranchScope($preferredBranchId ?: $workspaceBranchId, $workspaceBranchId);
    }

    public function canAccessBranch(User $user, ?int $branchId, ?int $workspaceBranchId = null): bool
    {
        if (! $branchId) {
            return true;
        }

        if ($user->canAccessAllBranches()) {
            return true;
        }

        $allowedBranchId = (int) ($user->branch_id ?: $workspaceBranchId ?: 0);

        return $allowedBranchId === 0 || $allowedBranchId === (int) $branchId;
    }

    public function assertCanViewPayment(User $user, Payment $payment, ?int $workspaceBranchId = null): void
    {
        if ($payment->company_id !== $user->company_id || ! $this->canAccessBranch($user, $payment->branch_id, $workspaceBranchId)) {
            abort(403);
        }
    }

    public function resolveCreateScopeBranch(User $user, ?int $workspaceBranchId = null, ?SalesInvoice $invoice = null, ?PurchaseBill $bill = null): ?int
    {
        $preferredBranchId = (int) ($invoice?->branch_id ?: $bill?->branch_id ?: 0);

        if ($preferredBranchId > 0 && ! $this->canAccessBranch($user, $preferredBranchId, $workspaceBranchId)) {
            abort(403);
        }

        return $this->listingBranchScope($user, $workspaceBranchId, $preferredBranchId ?: null);
    }

    public function authorizePayment(
        User $user,
        CashAccount $cashAccount,
        ?SalesInvoice $invoice,
        ?PurchaseBill $bill,
        float $amount,
        string $paymentType,
        ?int $workspaceBranchId = null,
        ?int $requestedBranchId = null,
    ): int {
        $documentBranchId = (int) ($invoice?->branch_id ?: $bill?->branch_id ?: 0);
        $cashAccountBranchId = (int) ($cashAccount->branch_id ?: 0);
        $fallbackBranchId = (int) ($workspaceBranchId ?: $user->branch_id ?: 0);

        $effectiveBranchId = $this->resolveEffectiveBranchId(
            $requestedBranchId,
            $documentBranchId ?: null,
            $cashAccountBranchId ?: null,
            $fallbackBranchId ?: null,
        );

        $this->assertBranchAccessible($user, $effectiveBranchId, $workspaceBranchId, 'branch_id');
        $this->assertWithinLimit($user, $amount, $paymentType);

        return $effectiveBranchId;
    }

    public function validationLimit(User $user, string $paymentType = 'customer_receipt'): ?float
    {
        $user->loadMissing('roles');

        $limits = [];

        foreach ($user->roles as $role) {
            if (! array_key_exists($role->slug, self::ROLE_LIMITS)) {
                continue;
            }

            $limit = self::ROLE_LIMITS[$role->slug];

            if ($limit === null) {
                return null;
            }

            $limits[] = (float) $limit;
        }

        if ($limits !== []) {
            return max($limits);
        }

        return $user->hasPermission('payments.validate') ? self::DEFAULT_LIMIT : 0.0;
    }

    public function validationLimitLabel(User $user, string $paymentType = 'customer_receipt'): string
    {
        $limit = $this->validationLimit($user, $paymentType);

        if ($limit === null) {
            return 'Illimite';
        }

        return number_format($limit, 0, ',', ' ').' XOF';
    }

    private function resolveEffectiveBranchId(
        ?int $requestedBranchId,
        ?int $documentBranchId,
        ?int $cashAccountBranchId,
        ?int $fallbackBranchId = null,
    ): int {
        if ($requestedBranchId && $documentBranchId && $requestedBranchId !== $documentBranchId) {
            throw ValidationException::withMessages([
                'branch_id' => 'Le document selectionne appartient a une autre agence que celle demandee.',
            ]);
        }

        if ($requestedBranchId && $cashAccountBranchId && $requestedBranchId !== $cashAccountBranchId) {
            throw ValidationException::withMessages([
                'cash_account_id' => 'Le compte de tresorerie selectionne appartient a une autre agence que celle demandee.',
            ]);
        }

        if ($documentBranchId && $cashAccountBranchId && $documentBranchId !== $cashAccountBranchId) {
            throw ValidationException::withMessages([
                'cash_account_id' => 'Le compte de tresorerie doit appartenir a la meme agence que le document selectionne.',
            ]);
        }

        $effectiveBranchId = $requestedBranchId ?: $documentBranchId ?: $cashAccountBranchId ?: $fallbackBranchId;

        if (! $effectiveBranchId) {
            throw ValidationException::withMessages([
                'branch_id' => 'Impossible de determiner l agence de validation pour ce paiement.',
            ]);
        }

        return (int) $effectiveBranchId;
    }

    private function assertBranchAccessible(User $user, int $branchId, ?int $workspaceBranchId = null, string $field = 'branch_id'): void
    {
        if ($this->canAccessBranch($user, $branchId, $workspaceBranchId)) {
            return;
        }

        $allowedBranchId = (int) ($user->branch_id ?: $workspaceBranchId ?: 0);
        $allowedBranchName = $allowedBranchId > 0
            ? (Branch::query()->whereKey($allowedBranchId)->value('name') ?: 'votre agence autorisee')
            : 'votre perimetre autorise';

        throw ValidationException::withMessages([
            $field => 'Votre profil ne peut valider des paiements que sur '.$allowedBranchName.'.',
        ]);
    }

    private function assertWithinLimit(User $user, float $amount, string $paymentType): void
    {
        $limit = $this->validationLimit($user, $paymentType);

        if ($limit === null) {
            return;
        }

        if ($amount <= $limit) {
            return;
        }

        throw ValidationException::withMessages([
            'amount' => 'Le montant depasse le plafond de validation de votre profil ('.$this->validationLimitLabel($user, $paymentType).').',
        ]);
    }
}

