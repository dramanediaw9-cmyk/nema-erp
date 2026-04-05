<?php

namespace App\Modules\Budgets\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Budgets\Models\Budget;
use App\Modules\Budgets\Models\BudgetLine;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Treasury\Models\Payment;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BudgetController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger)
    {
    }

    public function index(CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $budgets = Budget::query()
            ->with(['branch'])
            ->withSum('lines as planned_total', 'amount')
            ->where('company_id', $companyId)
            ->orderByDesc('fiscal_year')
            ->orderByDesc('id')
            ->paginate(15);

        return view('budgets.index', [
            'budgets' => $budgets,
            'summary' => [
                'budget_count' => (int) Budget::query()->where('company_id', $companyId)->count(),
                'active_count' => (int) Budget::query()->where('company_id', $companyId)->where('status', 'active')->count(),
                'planned_total' => (float) BudgetLine::query()
                    ->whereHas('budget', fn (Builder $query) => $query->where('company_id', $companyId))
                    ->sum('amount'),
            ],
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function create(CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        return view('budgets.create', [
            'branches' => Branch::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'metricOptions' => $this->metricOptions(),
            'statusOptions' => $this->statusOptions(),
            'monthOptions' => $this->monthOptions(),
            'defaultRows' => old('lines', array_fill(0, 12, ['metric' => '', 'period_month' => '', 'amount' => '', 'notes' => ''])),
            'currentYear' => now()->year,
        ]);
    }

    public function store(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'fiscal_year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'status' => ['required', Rule::in(array_keys($this->statusOptions()))],
            'notes' => ['nullable', 'string'],
        ]);

        $lines = collect($request->input('lines', []))
            ->map(fn ($line) => is_array($line) ? $line : [])
            ->filter(fn (array $line) => filled($line['metric'] ?? null) || filled($line['period_month'] ?? null) || filled($line['amount'] ?? null))
            ->values();

        Validator::make(['lines' => $lines->all()], [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.metric' => ['required', Rule::in(array_keys($this->metricOptions()))],
            'lines.*.period_month' => ['required', 'integer', 'between:1,12'],
            'lines.*.amount' => ['required', 'numeric', 'min:0'],
            'lines.*.notes' => ['nullable', 'string', 'max:255'],
        ], [
            'lines.required' => 'Ajoute au moins une ligne budgetaire.',
            'lines.min' => 'Ajoute au moins une ligne budgetaire.',
        ])->validate();

        $duplicates = $lines
            ->map(fn (array $line) => ($line['metric'] ?? '').'-'.($line['period_month'] ?? ''))
            ->duplicates();

        if ($duplicates->isNotEmpty()) {
            return back()->withInput()->withErrors(['lines' => 'Chaque combinaison axe / mois doit etre unique dans le budget.']);
        }

        $budget = DB::transaction(function () use ($companyId, $payload, $lines, $request) {
            $budget = Budget::query()->create([
                'company_id' => $companyId,
                'branch_id' => $payload['branch_id'] ?? null,
                'name' => $payload['name'],
                'fiscal_year' => $payload['fiscal_year'],
                'status' => $payload['status'],
                'notes' => $payload['notes'] ?? null,
                'created_by' => $request->user()?->id,
                'updated_by' => $request->user()?->id,
            ]);

            foreach ($lines as $line) {
                $budget->lines()->create([
                    'metric' => $line['metric'],
                    'period_month' => (int) $line['period_month'],
                    'amount' => $line['amount'],
                    'notes' => $line['notes'] ?? null,
                ]);
            }

            return $budget;
        });

        $this->activityLogger->log('budgets.create', 'Creation budget de pilotage', $budget, [
            'fiscal_year' => $budget->fiscal_year,
            'status' => $budget->status,
            'line_count' => $lines->count(),
        ]);

        return redirect()->route('budgets.show', $budget)->with('success', 'Budget enregistre avec succes.');
    }

    public function show(Budget $budget, CurrentWorkspace $workspace): View
    {
        abort_if($workspace->companyId() !== $budget->company_id, 403);

        $budget->load(['branch', 'creator', 'updater', 'lines']);

        $lineDetails = $budget->lines
            ->map(function (BudgetLine $line) use ($budget) {
                $actualAmount = $this->actualAmount($budget, $line);
                $plannedAmount = (float) $line->amount;

                return [
                    'line' => $line,
                    'planned_amount' => $plannedAmount,
                    'actual_amount' => $actualAmount,
                    'variance' => $plannedAmount - $actualAmount,
                    'achievement_rate' => $plannedAmount > 0 ? round(($actualAmount / $plannedAmount) * 100, 1) : null,
                ];
            })
            ->sortBy(fn (array $detail) => sprintf('%02d-%s', $detail['line']->period_month, $detail['line']->metric))
            ->values();

        $summaryByMetric = $lineDetails
            ->groupBy(fn (array $detail) => $detail['line']->metric)
            ->map(function (Collection $group) {
                return [
                    'planned' => (float) $group->sum('planned_amount'),
                    'actual' => (float) $group->sum('actual_amount'),
                    'variance' => (float) $group->sum('variance'),
                ];
            });

        return view('budgets.show', [
            'budget' => $budget,
            'lineDetails' => $lineDetails,
            'summaryByMetric' => $summaryByMetric,
            'metricOptions' => $this->metricOptions(),
            'statusOptions' => $this->statusOptions(),
            'monthOptions' => $this->monthOptions(),
            'totals' => [
                'planned' => (float) $lineDetails->sum('planned_amount'),
                'actual' => (float) $lineDetails->sum('actual_amount'),
                'variance' => (float) $lineDetails->sum('variance'),
            ],
        ]);
    }

    private function actualAmount(Budget $budget, BudgetLine $line): float
    {
        $start = Carbon::create($budget->fiscal_year, $line->period_month, 1)->startOfMonth();
        $end = (clone $start)->endOfMonth();

        return match ($line->metric) {
            'sales' => (float) SalesInvoice::query()
                ->where('company_id', $budget->company_id)
                ->when($budget->branch_id, fn (Builder $query, int $branchId) => $query->where('branch_id', $branchId))
                ->where('status', 'validated')
                ->whereBetween('invoice_date', [$start->toDateString(), $end->toDateString()])
                ->sum('total'),
            'purchases' => (float) PurchaseBill::query()
                ->where('company_id', $budget->company_id)
                ->when($budget->branch_id, fn (Builder $query, int $branchId) => $query->where('branch_id', $branchId))
                ->where('status', 'validated')
                ->whereBetween('bill_date', [$start->toDateString(), $end->toDateString()])
                ->sum('total'),
            'expenses' => (float) Expense::query()
                ->where('company_id', $budget->company_id)
                ->when($budget->branch_id, fn (Builder $query, int $branchId) => $query->where('branch_id', $branchId))
                ->where('status', 'validated')
                ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
                ->sum('total'),
            'collections' => (float) Payment::query()
                ->where('company_id', $budget->company_id)
                ->when($budget->branch_id, fn (Builder $query, int $branchId) => $query->where('branch_id', $branchId))
                ->where('direction', 'in')
                ->where('payment_type', 'customer_receipt')
                ->whereBetween('payment_date', [$start->toDateString(), $end->toDateString()])
                ->sum('amount'),
            default => 0.0,
        };
    }

    private function metricOptions(): array
    {
        return [
            'sales' => 'Ventes',
            'purchases' => 'Achats',
            'expenses' => 'Depenses',
            'collections' => 'Encaissements clients',
        ];
    }

    private function statusOptions(): array
    {
        return [
            'draft' => 'Brouillon',
            'active' => 'Actif',
            'closed' => 'Cloture',
        ];
    }

    private function monthOptions(): array
    {
        return [
            1 => 'Janvier',
            2 => 'Fevrier',
            3 => 'Mars',
            4 => 'Avril',
            5 => 'Mai',
            6 => 'Juin',
            7 => 'Juillet',
            8 => 'Aout',
            9 => 'Septembre',
            10 => 'Octobre',
            11 => 'Novembre',
            12 => 'Decembre',
        ];
    }
}
