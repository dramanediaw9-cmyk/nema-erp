<?php

namespace App\Modules\Accounting\Services;

use App\Models\User;
use App\Modules\Accounting\Models\AccountingPeriod;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class PeriodLockService
{
    public function assertDateOpen(int $companyId, CarbonInterface|string $date, string $field = 'date'): void
    {
        $resolvedDate = $date instanceof CarbonInterface ? $date->toDateString() : Carbon::parse($date)->toDateString();

        $period = AccountingPeriod::query()
            ->where('company_id', $companyId)
            ->where('status', 'closed')
            ->whereDate('start_date', '<=', $resolvedDate)
            ->whereDate('end_date', '>=', $resolvedDate)
            ->first();

        if (! $period) {
            return;
        }

        throw ValidationException::withMessages([
            $field => 'La date selectionnee appartient a la periode cloturee "'.$period->name.'".',
        ]);
    }

    public function close(AccountingPeriod $period, User $user): AccountingPeriod
    {
        if ($period->status === 'closed') {
            return $period;
        }

        $period->update([
            'status' => 'closed',
            'closed_at' => now(),
            'closed_by' => $user->id,
        ]);

        return $period->fresh(['closer']);
    }

    public function reopen(AccountingPeriod $period): AccountingPeriod
    {
        if ($period->status === 'open') {
            return $period;
        }

        $period->update([
            'status' => 'open',
            'closed_at' => null,
            'closed_by' => null,
        ]);

        return $period->fresh(['closer']);
    }
}
