<?php

namespace App\Modules\Expenses\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Core\Approvals\Services\ApprovalFlowService;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Notifications\Services\OutboundNotificationService;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Expenses\Models\ExpenseCategory;
use App\Modules\Expenses\Services\ExpenseService;
use App\Modules\Partners\Models\Partner;
use App\Modules\Treasury\Models\CashAccount;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use App\Support\Exports\CsvExportService;
use App\Support\PaymentMethodCatalog;
use App\Support\Pdf\PdfDocumentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseController extends Controller
{
    public function __construct(
        private readonly ExpenseService $expenseService,
        private readonly ApprovalFlowService $approvalFlowService,
        private readonly OutboundNotificationService $outboundNotificationService,
        private readonly ActivityLogger $activityLogger,
        private readonly CsvExportService $csvExportService,
        private readonly PdfDocumentService $pdfDocumentService,
    ) {
    }

    public function index(Request $request, CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $filters = $this->filters($request);

        return view('expenses.index', [
            'expenses' => $this->filteredQuery($companyId, $filters)
                ->latest('expense_date')
                ->latest('id')
                ->paginate(15)
                ->withQueryString(),
            'filters' => $filters,
            'branches' => Branch::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'categories' => ExpenseCategory::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'summary' => $this->summary($companyId),
            'today' => now()->startOfDay(),
        ]);
    }

    public function export(Request $request, CurrentWorkspace $workspace): StreamedResponse
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $filters = $this->filters($request);
        $today = now()->startOfDay();

        $rows = $this->filteredQuery($companyId, $filters)
            ->orderBy('expense_date')
            ->orderBy('id')
            ->get()
            ->map(fn (Expense $expense) => [
                $expense->expense_number,
                $expense->expense_date?->format('d/m/Y'),
                $expense->description,
                $expense->category?->name,
                $expense->supplier?->name,
                $expense->branch?->name,
                $expense->status === 'validated' ? 'Approuvee' : ($expense->status === 'rejected' ? 'Rejetee' : 'En attente'),
                number_format((float) $expense->total, 2, '.', ''),
                $expense->payment_status,
                $this->followUpLabel($expense, $today),
            ]);

        return $this->csvExportService->download('depenses.csv', [
            'Numero', 'Date', 'Description', 'Categorie', 'Fournisseur', 'Agence', 'Workflow', 'Total', 'Statut paiement', 'Suivi',
        ], $rows);
    }

    public function create(CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId || ! $workspace->branchId(), 403);

        return view('expenses.create', [
            'categories' => ExpenseCategory::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(),
            'suppliers' => Partner::query()->suppliers()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(),
            'cashAccounts' => CashAccount::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(),
            'branch' => $workspace->branch(),
        ]);
    }

    public function store(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();
        abort_if(! $companyId || ! $branchId, 403);

        $data = $request->validate([
            'expense_category_id' => ['required', Rule::exists('expense_categories', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'supplier_id' => ['nullable', Rule::exists('partners', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'cash_account_id' => ['nullable', Rule::exists('cash_accounts', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'expense_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'total' => ['required', 'numeric', 'gt:0'],
            'payment_date' => ['nullable', 'date'],
            'payment_method' => ['nullable', Rule::in(PaymentMethodCatalog::values())],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $expense = $this->expenseService->createPending($companyId, $branchId, $data, $request->user());
        $result = $this->approvalFlowService->autoAdvance(
            $expense,
            'expenses',
            $request->user(),
            fn (Expense $pendingExpense, $user) => $this->expenseService->approve($pendingExpense, $user),
        );

        /** @var Expense $expense */
        $expense = $result['document'];
        $this->outboundNotificationService->dispatchApprovalRequest($expense, 'expenses', $result['next_step']);

        $this->activityLogger->log($result['is_fully_approved'] ? 'expenses.create' : 'expenses.submit', $result['is_fully_approved'] ? 'Creation depense' : 'Soumission depense pour approbation', $expense, [
            'expense_number' => $expense->expense_number,
            'total' => $expense->total,
            'status' => $expense->status,
            'approved_steps' => $result['approved_steps']->pluck('step_order')->all(),
        ]);

        $message = $result['is_fully_approved']
            ? 'Depense enregistree et approuvee avec succes.'
            : ($result['approved_steps']->isNotEmpty() && $result['next_step']
                ? 'Depense enregistree. Etape suivante requise : '.$result['next_step']->label.'.'
                : 'Depense soumise pour approbation.');

        return redirect()->route('expenses.show', $expense)->with('success', $message);
    }

    public function approve(Expense $expense, CurrentWorkspace $workspace, Request $request): RedirectResponse
    {
        abort_if($workspace->companyId() !== $expense->company_id, 403);

        $result = $this->approvalFlowService->approve(
            $expense,
            'expenses',
            $request->user(),
            fn (Expense $pendingExpense, $user) => $this->expenseService->approve($pendingExpense, $user),
        );

        /** @var Expense $expense */
        $expense = $result['document'];
        foreach ($result['approved_steps'] as $approvedStep) {
            $this->outboundNotificationService->cancelQueuedForApprovalStep($expense, (int) $approvedStep->step_order, 'Etape deja approuvee, notification obsolete.');
        }
        $this->outboundNotificationService->dispatchApprovalRequest($expense, 'expenses', $result['next_step']);

        $this->activityLogger->log('expenses.approve', 'Approbation depense', $expense, [
            'expense_number' => $expense->expense_number,
            'approved_by' => $request->user()->id,
            'approved_steps' => $result['approved_steps']->pluck('step_order')->all(),
            'is_fully_approved' => $result['is_fully_approved'],
        ]);

        $message = $result['is_fully_approved']
            ? 'Depense completement approuvee avec succes.'
            : 'Etape d approbation validee. Prochaine etape : '.($result['next_step']?->label ?? 'Aucune').'.';

        return redirect()->route('expenses.show', $expense)->with('success', $message);
    }

    public function reject(Expense $expense, CurrentWorkspace $workspace, Request $request): RedirectResponse
    {
        abort_if($workspace->companyId() !== $expense->company_id, 403);

        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $result = $this->approvalFlowService->reject(
            $expense,
            'expenses',
            $request->user(),
            fn (Expense $pendingExpense, $user, ?string $reason) => $this->expenseService->reject($pendingExpense, $user, $reason),
            $data['rejection_reason'],
        );

        /** @var Expense $expense */
        $expense = $result['document'];
        $this->outboundNotificationService->cancelQueuedForResource($expense, 'Workflow rejete avant validation finale.');

        $this->activityLogger->log('expenses.reject', 'Rejet depense', $expense, [
            'expense_number' => $expense->expense_number,
            'rejected_by' => $request->user()->id,
            'rejected_step_order' => $result['rejected_step']->step_order,
            'rejection_reason' => $data['rejection_reason'],
        ]);

        return redirect()->route('expenses.show', $expense)->with('success', 'Depense rejetee avec motif.');
    }

    public function show(Expense $expense, CurrentWorkspace $workspace): View
    {
        abort_if($workspace->companyId() !== $expense->company_id, 403);

        return view('expenses.show', [
            'expense' => $expense->load(['category', 'supplier', 'cashAccount', 'branch', 'company', 'creator', 'approver', 'rejector', 'approvalSteps.approver', 'approvalSteps.rejectedBy', 'approvalSteps.assignedApprover', 'approvalSteps.delegatedBy', 'internalComments.creator', 'attachments.creator']),
            'journalEntries' => JournalEntry::query()
                ->with(['creator'])
                ->where('company_id', $expense->company_id)
                ->where('source_type', Expense::class)
                ->where('source_id', $expense->id)
                ->orderBy('entry_date')
                ->get(),
        ]);
    }

    public function print(Expense $expense, CurrentWorkspace $workspace): \Symfony\Component\HttpFoundation\Response
    {
        abort_if($workspace->companyId() !== $expense->company_id, 403);

        return $this->pdfDocumentService->inline('expenses.print', [
            'expense' => $expense->load(['category', 'supplier', 'cashAccount', 'branch', 'company', 'creator', 'approver', 'rejector', 'approvalSteps.approver', 'approvalSteps.rejectedBy', 'approvalSteps.assignedApprover', 'approvalSteps.delegatedBy', 'internalComments.creator', 'attachments.creator']),
        ], 'depense-'.$expense->expense_number.'.pdf');
    }

    private function filteredQuery(int $companyId, array $filters): Builder
    {
        $day7 = now()->subDays(7)->toDateString();
        $day30 = now()->subDays(30)->toDateString();

        return Expense::query()
            ->with(['category', 'supplier', 'cashAccount', 'branch', 'approver', 'approvalSteps'])
            ->where('company_id', $companyId)
            ->when($filters['date_from'], fn (Builder $query, string $dateFrom) => $query->whereDate('expense_date', '>=', $dateFrom))
            ->when($filters['date_to'], fn (Builder $query, string $dateTo) => $query->whereDate('expense_date', '<=', $dateTo))
            ->when($filters['branch_id'], fn (Builder $query, int $branchId) => $query->where('branch_id', $branchId))
            ->when($filters['category_id'], fn (Builder $query, int $categoryId) => $query->where('expense_category_id', $categoryId))
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['payment_status'], fn (Builder $query, string $paymentStatus) => $query->where('payment_status', $paymentStatus))
            ->when($filters['aging_state'] === 'pending', fn (Builder $query) => $query->where('status', 'pending_approval'))
            ->when($filters['aging_state'] === 'unpaid', fn (Builder $query) => $query->where('status', 'validated')->where('payment_status', 'unpaid'))
            ->when($filters['aging_state'] === 'age_8_30', fn (Builder $query) => $query->where('status', 'validated')->where('payment_status', 'unpaid')->whereDate('expense_date', '<=', $day7)->whereDate('expense_date', '>=', $day30))
            ->when($filters['aging_state'] === 'age_31_plus', fn (Builder $query) => $query->where('status', 'validated')->where('payment_status', 'unpaid')->whereDate('expense_date', '<', $day30))
            ->when($filters['search'], function (Builder $query, string $search) {
                $like = '%'.$search.'%';

                $query->where(function (Builder $nested) use ($like) {
                    $nested->where('expense_number', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhere('payment_reference', 'like', $like)
                        ->orWhere('notes', 'like', $like)
                        ->orWhereHas('supplier', function (Builder $supplierQuery) use ($like) {
                            $supplierQuery->where('name', 'like', $like)
                                ->orWhere('code', 'like', $like);
                        })
                        ->orWhereHas('category', fn (Builder $categoryQuery) => $categoryQuery->where('name', 'like', $like))
                        ->orWhereHas('branch', function (Builder $branchQuery) use ($like) {
                            $branchQuery->where('name', 'like', $like)
                                ->orWhere('code', 'like', $like);
                        });
                });
            });
    }

    private function filters(Request $request): array
    {
        $status = $request->string('status')->trim()->value() ?: null;
        if (! in_array($status, ['validated', 'pending_approval', 'rejected'], true)) {
            $status = null;
        }

        $paymentStatus = $request->string('payment_status')->trim()->value() ?: null;
        if (! in_array($paymentStatus, ['unpaid', 'paid'], true)) {
            $paymentStatus = null;
        }

        $agingState = $request->string('aging_state')->trim()->value() ?: null;
        if (! in_array($agingState, ['pending', 'unpaid', 'age_8_30', 'age_31_plus'], true)) {
            $agingState = null;
        }

        return [
            'search' => $request->string('search')->trim()->value() ?: null,
            'date_from' => $request->string('date_from')->value() ?: null,
            'date_to' => $request->string('date_to')->value() ?: null,
            'branch_id' => $request->integer('branch_id') ?: null,
            'category_id' => $request->integer('category_id') ?: null,
            'status' => $status,
            'payment_status' => $paymentStatus,
            'aging_state' => $agingState,
        ];
    }

    private function summary(int $companyId): array
    {
        $day7 = now()->subDays(7)->toDateString();
        $day30 = now()->subDays(30)->toDateString();

        return [
            'unpaid_count' => (int) Expense::query()
                ->where('company_id', $companyId)
                ->where('status', 'validated')
                ->where('payment_status', 'unpaid')
                ->count(),
            'unpaid_total' => (float) Expense::query()
                ->where('company_id', $companyId)
                ->where('status', 'validated')
                ->where('payment_status', 'unpaid')
                ->sum('total'),
            'pending_approval_count' => (int) Expense::query()
                ->where('company_id', $companyId)
                ->where('status', 'pending_approval')
                ->count(),
            'aging_8_30_total' => (float) Expense::query()
                ->where('company_id', $companyId)
                ->where('status', 'validated')
                ->where('payment_status', 'unpaid')
                ->whereDate('expense_date', '<=', $day7)
                ->whereDate('expense_date', '>=', $day30)
                ->sum('total'),
            'aging_31_plus_total' => (float) Expense::query()
                ->where('company_id', $companyId)
                ->where('status', 'validated')
                ->where('payment_status', 'unpaid')
                ->whereDate('expense_date', '<', $day30)
                ->sum('total'),
        ];
    }

    private function followUpLabel(Expense $expense, $today): string
    {
        if ($expense->status === 'rejected') {
            return 'Rejetee';
        }

        if ($expense->status !== 'validated') {
            return 'Workflow';
        }

        if ($expense->payment_status === 'paid') {
            return 'Payee';
        }

        $ageInDays = $expense->expense_date?->diffInDays($today) ?? 0;

        if ($ageInDays > 30) {
            return 'A regler';
        }

        if ($ageInDays > 7) {
            return 'A planifier';
        }

        return 'Recent';
    }
}


