<?php

namespace App\Modules\Core\Approvals\Services;

use App\Models\User;
use App\Modules\Core\Approvals\Models\ApprovalStep;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Sales\Models\SalesInvoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

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
                    ->with(['branch', 'customer', 'creator', 'approvalSteps.approver'])
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
                    ->with(['branch', 'supplier', 'creator', 'approvalSteps.approver'])
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
                    ->with(['branch', 'supplier', 'creator', 'approvalSteps.approver'])
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
        $query = $this->currentPendingStepsQuery($companyId);

        if (! $user->hasRole('platform_admin')) {
            $moduleApproverModules = collect(['sales', 'purchases', 'expenses'])
                ->filter(fn (string $module) => $user->hasPermission($module.'.approve'))
                ->values();
            $canApproveDirectorSteps = $user->hasRole('director');

            if ($moduleApproverModules->isEmpty() && ! $canApproveDirectorSteps) {
                return [
                    'count' => 0,
                    'by_module' => [
                        'sales' => 0,
                        'purchases' => 0,
                        'expenses' => 0,
                    ],
                ];
            }

            $query->where(function (QueryBuilder $builder) use ($moduleApproverModules, $canApproveDirectorSteps): void {
                $hasModuleScope = $moduleApproverModules->isNotEmpty();

                if ($hasModuleScope) {
                    $builder->where(function (QueryBuilder $moduleBuilder) use ($moduleApproverModules): void {
                        $moduleBuilder
                            ->where('approval_steps.rule', '!=', 'director_only')
                            ->whereIn('approval_steps.module', $moduleApproverModules->all());
                    });
                }

                if ($canApproveDirectorSteps) {
                    if ($hasModuleScope) {
                        $builder->orWhere('approval_steps.rule', 'director_only');
                    } else {
                        $builder->where('approval_steps.rule', 'director_only');
                    }
                }
            });
        }

        return [
            'count' => (clone $query)->count(),
            'by_module' => [
                'sales' => (clone $query)->where('approval_steps.module', 'sales')->count(),
                'purchases' => (clone $query)->where('approval_steps.module', 'purchases')->count(),
                'expenses' => (clone $query)->where('approval_steps.module', 'expenses')->count(),
            ],
        ];
    }

    private function currentPendingStepsQuery(int $companyId): QueryBuilder
    {
        $currentSteps = ApprovalStep::query()
            ->selectRaw('company_id, module, approvable_type, approvable_id, MIN(step_order) as min_step_order')
            ->where('company_id', $companyId)
            ->whereIn('module', ['sales', 'purchases', 'expenses'])
            ->where('status', 'pending')
            ->groupBy('company_id', 'module', 'approvable_type', 'approvable_id');

        return ApprovalStep::query()
            ->toBase()
            ->from('approval_steps')
            ->joinSub($currentSteps->toBase(), 'current_steps', function ($join): void {
                $join->on('approval_steps.company_id', '=', 'current_steps.company_id')
                    ->on('approval_steps.module', '=', 'current_steps.module')
                    ->on('approval_steps.approvable_type', '=', 'current_steps.approvable_type')
                    ->on('approval_steps.approvable_id', '=', 'current_steps.approvable_id')
                    ->on('approval_steps.step_order', '=', 'current_steps.min_step_order');
            })
            ->where('approval_steps.status', 'pending');
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
            'detail_url' => $this->detailUrl($module, $document),
            'approve_url' => $this->approveUrl($module, $document),
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
}
