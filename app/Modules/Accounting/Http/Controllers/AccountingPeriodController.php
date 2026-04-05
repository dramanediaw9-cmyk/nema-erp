<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Services\PeriodChecklistService;
use App\Modules\Accounting\Services\PeriodLockService;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccountingPeriodController extends Controller
{
    public function __construct(
        private readonly PeriodLockService $periodLockService,
        private readonly PeriodChecklistService $periodChecklistService,
        private readonly ActivityLogger $activityLogger,
    ) {
    }

    public function index(CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $periods = AccountingPeriod::query()
            ->with('closer')
            ->where('company_id', $companyId)
            ->orderByDesc('start_date')
            ->paginate(18);

        $snapshots = $periods->getCollection()->mapWithKeys(function (AccountingPeriod $period) {
            return [$period->id => $this->periodChecklistService->summaryForPeriod($period)];
        });

        return view('accounting.periods.index', [
            'periods' => $periods,
            'currentPeriodSummary' => $this->periodChecklistService->currentPeriodSummary($companyId),
            'periodSnapshots' => $snapshots,
        ]);
    }

    public function store(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $overlap = AccountingPeriod::query()
            ->where('company_id', $companyId)
            ->whereDate('start_date', '<=', $data['end_date'])
            ->whereDate('end_date', '>=', $data['start_date'])
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'start_date' => 'Une periode existe deja sur cet intervalle de dates.',
            ]);
        }

        $period = AccountingPeriod::query()->create([
            'company_id' => $companyId,
            'name' => $data['name'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'status' => 'open',
        ]);

        $this->activityLogger->log('accounting_periods.create', 'Creation periode comptable', $period, [
            'name' => $period->name,
            'start_date' => $period->start_date?->toDateString(),
            'end_date' => $period->end_date?->toDateString(),
        ]);

        return redirect()->route('accounting.periods.index')->with('success', 'Periode comptable creee avec succes.');
    }

    public function close(AccountingPeriod $period, CurrentWorkspace $workspace, Request $request): RedirectResponse
    {
        abort_if($workspace->companyId() !== $period->company_id, 403);

        $summary = $this->periodChecklistService->summaryForPeriod($period);

        if (! $summary['can_close']) {
            return redirect()->route('accounting.periods.index')->with('error', sprintf(
                'Cloture impossible : %d achat(s) et %d depense(s) restent en attente d approbation sur cette periode.',
                $summary['pending_purchases_count'],
                $summary['pending_expenses_count'],
            ));
        }

        $period = $this->periodLockService->close($period, $request->user());

        $this->activityLogger->log('accounting_periods.close', 'Cloture periode comptable', $period, [
            'name' => $period->name,
            'start_date' => $period->start_date?->toDateString(),
            'end_date' => $period->end_date?->toDateString(),
        ]);

        return redirect()->route('accounting.periods.index')->with('success', 'Periode cloturee avec succes.');
    }

    public function reopen(AccountingPeriod $period, CurrentWorkspace $workspace): RedirectResponse
    {
        abort_if($workspace->companyId() !== $period->company_id, 403);

        $period = $this->periodLockService->reopen($period);

        $this->activityLogger->log('accounting_periods.reopen', 'Reouverture periode comptable', $period, [
            'name' => $period->name,
            'start_date' => $period->start_date?->toDateString(),
            'end_date' => $period->end_date?->toDateString(),
        ]);

        return redirect()->route('accounting.periods.index')->with('success', 'Periode reouverte avec succes.');
    }
}
