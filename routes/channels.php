<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('pos.preparation.{companyId}.{branchId}', function (User $user, int $companyId, int $branchId): bool {
    if ((int) $user->company_id !== $companyId) {
        return false;
    }

    if (! $user->hasPermission('pos.view')) {
        return false;
    }

    return $user->canAccessAllBranches() || (int) ($user->branch_id ?? 0) === $branchId;
});
