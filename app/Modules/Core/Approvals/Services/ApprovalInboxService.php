<?php

namespace App\Modules\Core\Approvals\Services;

use App\Models\User;
use App\Modules\Core\Approvals\Models\ApprovalStep;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Sales\Models\SalesInvoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ApprovalInboxService
{
    public function __construct(private readonly ApprovalFlowService $approvalFlowService)
    {
    }

    public function pendingForUser(User $user, int $companyId, ?string $module = null): Collection
    {
        $modules = collect(['sales', 'purchases', 'expenses'])
            ->when($module, fn (Collection $items) => $items->filter(fn (string $item) => $item === $module))
            ->values();

        $items = collect();

        if ($modules->contains('sales')) {
            $items = $items->merge(
                SalesInvoice::query()
                    ->with(['branch', 'customer', 'creator', 'approvalSteps.approver', 'approvalSteps.assignedApprover', 'approvalSteps.delegatedBy'])
                    ->where('company_id', $companyId)
                    ->where('status', 'pending_approval')
                    ->latest('created_at')
                    ->get()
                    ->map(fn (SalesInvoice $invoice) => $this->mapDocument($invoice, 'sales', 'Vente client', $invoice->invoice_number, $invoice->customer?->name, $invoice->invoice_date?->format('d/m/Y')))
            );
        }

        if ($modules->contains('purchases')) {
            $items = $items->merge(
                PurchaseBill::query()
                    ->with(['branch', 'supplier', 'creator', 'approvalSteps.approver', 'approvalSteps.assignedApprover', 'approvalSteps.delegatedBy'])
                    ->where('company_id', $companyId)
                    ->where('status', 'pending_approval')
                    ->latest('created_at')
                    ->get()
                    ->map(fn (PurchaseBill $bill) => $this->mapDocument($bill, 'purchases', 'Achat fournisseur', $bill->bill_number, $bill->supplier?->name, $bill->bill_date?->format('d/m/Y')))
            );
        }

        if ($modules->contains('expenses')) {
            $items = $items->merge(
                Expense::query()
                    ->with(['branch', 'supplier', 'creator', 'approvalSteps.approver', 'approvalSteps.assignedApprover', 'approvalSteps.delegatedBy'])
                    ->where('company_id', $companyId)
                    ->where('status', 'pending_approval')
                    ->latest('created_at')
                    ->get()
                    ->map(fn (Expense $expense) => $this->mapDocument($expense, 'expenses', 'Depense', $expense->expense_number, $expense->description, $expense->expense_date?->format('d/m/Y')))
            );
        }

        return $items
            ->filter(function (array $item) use ($user) {
                return $item['pending_step'] instanceof ApprovalStep
                    && $this->approvalFlowService->canUserApproveStep($user, $item['module'], $item['pending_step']);
            })
            ->sortByDesc(fn (array $item) => ($item['submitted_at']?->timestamp ?? 0) * 1000000 + (int) round($item['amount'] * 100))
            ->values();
    }

    public function summaryForUser(User $user, int $companyId): array
    {
        $steps = ApprovalStep::query()
            ->with(['assignedApprover'])
            ->where('company_id', $companyId)
            ->whereIn('module', ['sales', 'purchases', 'expenses'])
            ->where('status', 'pending')
            ->orderBy('step_order')
            ->get()
            ->groupBy(fn (ApprovalStep $step) => $step->approvable_type.'#'.$step->approvable_id)
            ->map(fn (Collection $steps) => $steps->sortBy('step_order')->first())
            ->filter(fn (?ApprovalStep $step) => $step instanceof ApprovalStep)
            ->filter(fn (ApprovalStep $step) => $this->approvalFlowService->canUserApproveStep($user, $step->module, $step))
            ->values();

        return [
            'count' => $steps->count(),
            'by_module' => [
                'sales' => $steps->where('module', 'sales')->count(),
                'purchases' => $steps->where('module', 'purchases')->count(),
                'expenses' => $steps->where('module', 'expenses')->count(),
            ],
            'overdue_count' => $steps->filter(fn (ApprovalStep $step) => $step->isOverdue())->count(),
        ];
    }

    public function cachedSummaryForUser(User $user, int $companyId, int $ttlSeconds = 15): array
    {
        return Cache::remember(
            sprintf('approvals:summary:%d:%d', $companyId, $user->id),
            now()->addSeconds(max($ttlSeconds, 5)),
            fn (): array => $this->summaryForUser($user, $companyId),
        );
    }

    private function mapDocument(Model $document, string $module, string $moduleLabel, string $number, ?string $counterpart, ?string $documentDate): array
    {
        $pendingStep = $this->approvalFlowService->currentPendingStep($document);

        return [
            'module' => $module,
            'module_label' => $moduleLabel,
            'number' => $number,
            'counterpart' => $counterpart ?: 'Non renseigne',
            'document_date' => $documentDate ?: 'Non renseignee',
            'amount' => (float) ($document->total ?? 0),
            'branch_name' => $document->branch?->name ?? 'Agence non definie',
            'creator_name' => $document->creator?->name ?? 'Systeme',
            'status' => $document->status,
            'pending_step' => $pendingStep,
            'submitted_at' => $document->created_at,
            'due_at' => $pendingStep?->due_at,
            'is_overdue' => $pendingStep?->isOverdue() ?? false,
            'assigned_approver_name' => $pendingStep?->assignedApprover?->name,
            'detail_url' => $this->detailUrl($module, $document),
            'approve_url' => $this->approveUrl($module, $document),
            'reject_url' => $this->rejectUrl($module, $document),
            'delegate_url' => $pendingStep ? route('approvals.steps.delegate', $pendingStep) : null,
            'delegate_candidates' => $pendingStep
                ? $this->approvalFlowService->candidateApprovers($document->company_id, $module, $pendingStep)
                : collect(),
        ];
    }

    private function detailUrl(string $module, Model $document): string
    {
        return match ($module) {
            'sales' => route('sales.show', $document),
            'purchases' => route('purchases.show', $document),
            default => route('expenses.show', $document),
        };
    }

    private function approveUrl(string $module, Model $document): string
    {
        return match ($module) {
            'sales' => route('sales.approve', $document),
            'purchases' => route('purchases.approve', $document),
            default => route('expenses.approve', $document),
        };
    }

    private function rejectUrl(string $module, Model $document): string
    {
        return match ($module) {
            'sales' => route('sales.reject', $document),
            'purchases' => route('purchases.reject', $document),
            default => route('expenses.reject', $document),
        };
    }
}
