<?php

namespace App\Modules\Payroll\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Payroll\Models\PayrollRun;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PayrollController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger)
    {
    }

    public function index(CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        return view('payroll.index', [
            'branches' => Branch::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'runs' => PayrollRun::query()
                ->with('branch')
                ->where('company_id', $companyId)
                ->orderByDesc('period_end')
                ->orderByDesc('id')
                ->get(),
            'summary' => [
                'runs' => (int) PayrollRun::query()->where('company_id', $companyId)->count(),
                'draft_runs' => (int) PayrollRun::query()->where('company_id', $companyId)->where('status', 'draft')->count(),
                'scheduled_net' => (float) PayrollRun::query()->where('company_id', $companyId)->sum('net_amount'),
                'people_planned' => (int) PayrollRun::query()->where('company_id', $companyId)->sum('headcount'),
            ],
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function store(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $payload = $request->validate([
            'run_number' => ['nullable', 'string', 'max:30', Rule::unique('payroll_runs', 'run_number')->where(fn ($query) => $query->where('company_id', $companyId))],
            'label' => ['required', 'string', 'max:255'],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'scheduled_pay_date' => ['nullable', 'date', 'after_or_equal:period_end'],
            'headcount' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'gross_amount' => ['nullable', 'numeric', 'min:0'],
            'net_amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(array_keys($this->statusOptions()))],
            'notes' => ['nullable', 'string'],
        ]);

        $run = PayrollRun::query()->create([
            'company_id' => $companyId,
            'branch_id' => $payload['branch_id'] ?? null,
            'run_number' => ($payload['run_number'] ?? null) ?: $this->generateRunNumber($companyId),
            'label' => $payload['label'],
            'period_start' => $payload['period_start'],
            'period_end' => $payload['period_end'],
            'scheduled_pay_date' => $payload['scheduled_pay_date'] ?? null,
            'headcount' => (int) ($payload['headcount'] ?? 0),
            'gross_amount' => $payload['gross_amount'] ?? 0,
            'net_amount' => $payload['net_amount'] ?? 0,
            'status' => $payload['status'],
            'notes' => $payload['notes'] ?? null,
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        $this->activityLogger->log('payroll.runs.create', 'Creation execution paie', $run, [
            'run_number' => $run->run_number,
            'status' => $run->status,
        ]);

        return redirect()->route('payroll.index')->with('success', 'Execution de paie enregistree avec succes.');
    }

    private function statusOptions(): array
    {
        return [
            'draft' => 'Brouillon',
            'review' => 'En revue',
            'ready' => 'Pret a payer',
            'paid' => 'Paye',
        ];
    }

    private function generateRunNumber(int $companyId): string
    {
        $sequence = PayrollRun::query()->where('company_id', $companyId)->count() + 1;

        return 'PAY-'.now()->format('Y').'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
