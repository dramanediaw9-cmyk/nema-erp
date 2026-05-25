<?php

namespace App\Modules\Core\Automation\Services;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Approvals\Models\ApprovalStep;
use App\Modules\Core\Automation\Models\AutomationExecution;
use App\Modules\Core\Automation\Models\AutomationRule;
use App\Modules\Core\Company\Services\SectorProfileService;
use App\Modules\Core\Integrations\Models\IntegrationConnection;
use App\Modules\Core\Integrations\Services\IntegrationSecretGovernanceService;
use App\Modules\Core\Notifications\Models\InternalNotification;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Inventory\Models\ProductLot;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Manufacturing\Models\ProductionOrder;
use App\Modules\Projects\Models\ProjectTask;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Services\OrderCoverageService;
use App\Modules\Treasury\Models\Payment;
use Illuminate\Support\Collection;

class AutomationEngineService
{
    public function __construct(
        private readonly IntegrationSecretGovernanceService $integrationSecretGovernanceService,
        private readonly SectorProfileService $sectorProfileService,
        private readonly OrderCoverageService $orderCoverageService,
    ) {}

    public function signalDefinitions(): array
    {
        return [
            'approvals.stale_steps' => [
                'label' => 'Approbations bloquees',
                'module_key' => 'approvals',
                'description' => 'Declenche quand des etapes d approbation restent ouvertes trop longtemps.',
                'default_threshold' => 1,
                'default_window_hours' => 48,
                'action_url' => route('approvals.index'),
            ],
            'collections.overdue_invoices' => [
                'label' => 'Creances clients en retard',
                'module_key' => 'collections',
                'description' => 'Repere les factures clients echees encore impayees ou partiellement reglees.',
                'default_threshold' => 3,
                'default_window_hours' => 24,
                'action_url' => route('sales.index', ['due_state' => 'overdue']),
            ],
            'treasury.unreconciled_mobile_money' => [
                'label' => 'Mobile money a rapprocher',
                'module_key' => 'treasury',
                'description' => 'Repere les flux Wave, Orange Money, Moov et wallets mobiles encore ouverts ou sans reference exploitable.',
                'default_threshold' => 2,
                'default_window_hours' => 24,
                'action_url' => route('payments.index', ['reconciliation_status' => 'unreconciled']),
            ],
            'treasury.pending_internal_transfer_deposits' => [
                'label' => 'Versements agence a confirmer',
                'module_key' => 'treasury',
                'description' => 'Repere les versements internes vers banque ou wallet central encore absents du rapprochement apres la fenetre attendue.',
                'default_threshold' => 1,
                'default_window_hours' => 48,
                'action_url' => route('payments.index', ['payment_type' => 'internal_transfer', 'reconciliation_status' => 'unreconciled']),
            ],
            'treasury.documented_internal_transfer_deposits' => [
                'label' => 'Versements documentes a rapprocher',
                'module_key' => 'treasury',
                'description' => 'Repere les versements agence deja appuyes par une reference ou un justificatif joint mais encore absents du rapprochement.',
                'default_threshold' => 1,
                'default_window_hours' => 24,
                'action_url' => route('payments.index', ['deposit_documented' => 1]),
            ],
            'inventory.tracked_products_saleable_zero' => [
                'label' => 'Produits traces sans stock vendable',
                'module_key' => 'inventory',
                'description' => 'Repere les references suivies par lot ou serie qui n ont plus aucun lot non expire disponible a la vente.',
                'default_threshold' => 1,
                'default_window_hours' => 24,
                'action_url' => route('stock.index', ['tracking_type' => 'tracked', 'saleability_state' => 'zero']),
            ],
            'inventory.food_store_short_dated_lots' => [
                'label' => 'Lots courts a ecouler',
                'module_key' => 'inventory',
                'description' => 'Repere, en mode alimentation et boutique, les lots encore disponibles qui expirent sous 7 jours.',
                'default_threshold' => 1,
                'default_window_hours' => 24,
                'action_url' => route('stock.lots', ['status' => 'expiring', 'availability' => 'available', 'expiry_window_days' => 7]),
            ],
            'inventory.food_store_saleable_stockouts' => [
                'label' => 'Ruptures rayon vendables',
                'module_key' => 'inventory',
                'description' => 'Repere, en mode alimentation et boutique, les references deja passees par stock mais sans plus aucun stock vendable au comptoir.',
                'default_threshold' => 1,
                'default_window_hours' => 24,
                'action_url' => route('stock.index', ['saleability_state' => 'zero']),
            ],
            'sales.wholesale_orders_at_risk' => [
                'label' => 'Commandes grossiste a risque',
                'module_key' => 'sales',
                'description' => 'Repere, en mode grossiste et distribution, les commandes confirmees dont certaines lignes restent non couvertes apres ATP et achats attendus.',
                'default_threshold' => 1,
                'default_window_hours' => 24,
                'action_url' => route('orders.index', ['coverage_state' => 'at_risk']),
            ],
            'sales.wholesale_overdue_commitments' => [
                'label' => 'Engagements grossiste en retard',
                'module_key' => 'sales',
                'description' => 'Repere, en mode grossiste et distribution, les commandes avec reliquat encore ouvert apres la date promise au client.',
                'default_threshold' => 1,
                'default_window_hours' => 24,
                'action_url' => route('orders.index', ['delivery_focus' => 'overdue']),
            ],
            'projects.overdue_tasks' => [
                'label' => 'Taches projet en retard',
                'module_key' => 'projects',
                'description' => 'Suit les projets qui glissent par accumulation de taches en retard.',
                'default_threshold' => 1,
                'default_window_hours' => 24,
                'action_url' => route('projects.index'),
            ],
            'manufacturing.late_orders' => [
                'label' => 'Ordres de production en retard',
                'module_key' => 'manufacturing',
                'description' => 'Repere les ordres de production qui depassent leur echeance.',
                'default_threshold' => 1,
                'default_window_hours' => 24,
                'action_url' => route('manufacturing.index'),
            ],
            'integrations.secrets_critical' => [
                'label' => 'Secrets connecteurs critiques',
                'module_key' => 'platform',
                'description' => 'Surveille les secrets expires, en retard de rotation ou sans hygiene suffisante.',
                'default_threshold' => 1,
                'default_window_hours' => null,
                'action_url' => route('platform.index'),
            ],
        ];
    }

    public function statusOptions(): array
    {
        return [
            'draft' => 'Brouillon',
            'active' => 'Active',
            'paused' => 'En pause',
        ];
    }

    public function severityOptions(): array
    {
        return [
            'info' => 'Info',
            'warning' => 'Attention',
            'danger' => 'Critique',
        ];
    }

    public function actionTypeOptions(): array
    {
        return [
            'internal_alert' => 'Alerte interne',
            'ops_watch' => 'Observation Ops',
        ];
    }

    public function summary(int $companyId): array
    {
        $rules = AutomationRule::query()
            ->with(['branch', 'owner', 'latestExecution.notification'])
            ->where('company_id', $companyId)
            ->orderBy('status')
            ->orderBy('name')
            ->get();

        $signals = collect($this->signalDefinitions())
            ->map(fn (array $definition, string $key): array => array_merge(
                $definition,
                [
                    'key' => $key,
                    'preview' => $this->evaluateSignal(
                        companyId: $companyId,
                        signalKey: $key,
                        thresholdValue: (int) ($definition['default_threshold'] ?? 1),
                        windowHours: $definition['default_window_hours'] ?? null,
                        branchId: null,
                    ),
                ]
            ))
            ->values()
            ->all();

        return [
            'summary' => [
                'rules' => $rules->count(),
                'active' => $rules->where('status', 'active')->count(),
                'paused' => $rules->where('status', 'paused')->count(),
                'matched_last_24h' => AutomationExecution::query()
                    ->where('company_id', $companyId)
                    ->where('matched', true)
                    ->where('executed_at', '>=', now()->subDay())
                    ->count(),
                'signals_on_watch' => collect($signals)->filter(fn (array $signal) => $signal['preview']['matched'])->count(),
            ],
            'rules' => $rules,
            'signals' => $signals,
            'recent_executions' => AutomationExecution::query()
                ->with(['rule', 'notification'])
                ->where('company_id', $companyId)
                ->latest('executed_at')
                ->limit(12)
                ->get(),
        ];
    }

    public function createRule(int $companyId, array $attributes, ?User $actor = null): AutomationRule
    {
        $signal = $this->signalDefinitions()[$attributes['signal_key']];

        return AutomationRule::query()->create([
            'company_id' => $companyId,
            'branch_id' => $attributes['branch_id'] ?? null,
            'owner_id' => $attributes['owner_id'] ?? null,
            'code' => ($attributes['code'] ?? null) ?: $this->generateCode($companyId),
            'name' => $attributes['name'],
            'module_key' => $signal['module_key'],
            'signal_key' => $attributes['signal_key'],
            'status' => $attributes['status'],
            'severity' => $attributes['severity'],
            'action_type' => $attributes['action_type'],
            'threshold_value' => (int) $attributes['threshold_value'],
            'window_hours' => $attributes['window_hours'] ?? $signal['default_window_hours'],
            'cooldown_minutes' => (int) ($attributes['cooldown_minutes'] ?? 240),
            'description' => $attributes['description'] ?? null,
            'notes' => $attributes['notes'] ?? null,
            'created_by' => $actor?->id,
            'updated_by' => $actor?->id,
        ]);
    }

    public function updateRule(AutomationRule $rule, array $attributes, ?User $actor = null): AutomationRule
    {
        $signal = $this->signalDefinitions()[$attributes['signal_key']];

        $rule->fill([
            'branch_id' => $attributes['branch_id'] ?? null,
            'owner_id' => $attributes['owner_id'] ?? null,
            'name' => $attributes['name'],
            'module_key' => $signal['module_key'],
            'signal_key' => $attributes['signal_key'],
            'status' => $attributes['status'],
            'severity' => $attributes['severity'],
            'action_type' => $attributes['action_type'],
            'threshold_value' => (int) $attributes['threshold_value'],
            'window_hours' => $attributes['window_hours'] ?? $signal['default_window_hours'],
            'cooldown_minutes' => (int) ($attributes['cooldown_minutes'] ?? 240),
            'description' => $attributes['description'] ?? null,
            'notes' => $attributes['notes'] ?? null,
            'updated_by' => $actor?->id,
        ]);
        $rule->save();

        return $rule->fresh(['branch', 'owner', 'latestExecution.notification']);
    }

    public function runRule(AutomationRule $rule): AutomationExecution
    {
        $evaluation = $this->evaluateSignal(
            companyId: $rule->company_id,
            signalKey: $rule->signal_key,
            thresholdValue: $rule->threshold_value,
            windowHours: $rule->window_hours,
            branchId: $rule->branch_id,
        );

        $notification = null;
        $status = 'clear';

        if ($evaluation['matched']) {
            $onCooldown = $rule->last_triggered_at
                && $rule->cooldown_minutes > 0
                && $rule->last_triggered_at->gt(now()->subMinutes($rule->cooldown_minutes));

            if ($rule->action_type === 'internal_alert') {
                $notification = $this->syncNotification($rule, $evaluation);
            }

            $status = $onCooldown ? 'cooldown' : 'matched';
        } elseif ($rule->action_type === 'internal_alert') {
            $this->resolveNotification($rule);
        }

        $rule->update([
            'last_evaluated_at' => now(),
            'last_triggered_at' => $evaluation['matched'] ? ($rule->last_triggered_at && $status === 'cooldown' ? $rule->last_triggered_at : now()) : $rule->last_triggered_at,
            'last_value' => $evaluation['value'],
        ]);

        return AutomationExecution::query()->create([
            'company_id' => $rule->company_id,
            'automation_rule_id' => $rule->id,
            'notification_id' => $notification?->id,
            'signal_key' => $rule->signal_key,
            'status' => $status,
            'matched' => $evaluation['matched'],
            'observed_value' => $evaluation['value'],
            'message' => $evaluation['message'],
            'payload' => $evaluation,
            'executed_at' => now(),
        ]);
    }

    public function runActiveRulesForCompany(int $companyId): array
    {
        $rules = AutomationRule::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $executions = $rules->map(fn (AutomationRule $rule) => $this->runRule($rule));

        return [
            'rules' => $rules->count(),
            'matched' => $executions->where('matched', true)->count(),
            'cooldown' => $executions->where('status', 'cooldown')->count(),
            'clear' => $executions->where('status', 'clear')->count(),
        ];
    }

    public function evaluateSignal(int $companyId, string $signalKey, int $thresholdValue, ?int $windowHours = null, ?int $branchId = null): array
    {
        $windowHours = $windowHours !== null ? max($windowHours, 1) : null;
        $definition = $this->signalDefinitions()[$signalKey];

        return match ($signalKey) {
            'approvals.stale_steps' => $this->evaluateStaleApprovals($companyId, $definition, $thresholdValue, $windowHours ?? 48, $branchId),
            'collections.overdue_invoices' => $this->evaluateOverdueInvoices($companyId, $definition, $thresholdValue, $windowHours ?? 24, $branchId),
            'treasury.unreconciled_mobile_money' => $this->evaluateUnreconciledMobileMoney($companyId, $definition, $thresholdValue, $windowHours ?? 24, $branchId),
            'treasury.pending_internal_transfer_deposits' => $this->evaluatePendingInternalTransferDeposits($companyId, $definition, $thresholdValue, $windowHours ?? 48, $branchId),
            'treasury.documented_internal_transfer_deposits' => $this->evaluateDocumentedInternalTransferDeposits($companyId, $definition, $thresholdValue, $windowHours ?? 24, $branchId),
            'inventory.tracked_products_saleable_zero' => $this->evaluateTrackedProductsWithoutSaleableStock($companyId, $definition, $thresholdValue, $windowHours ?? 24, $branchId),
            'inventory.food_store_short_dated_lots' => $this->evaluateFoodStoreShortDatedLots($companyId, $definition, $thresholdValue, $windowHours ?? 24, $branchId),
            'inventory.food_store_saleable_stockouts' => $this->evaluateFoodStoreSaleableStockouts($companyId, $definition, $thresholdValue, $windowHours ?? 24, $branchId),
            'sales.wholesale_orders_at_risk' => $this->evaluateWholesaleOrdersAtRisk($companyId, $definition, $thresholdValue, $windowHours ?? 24, $branchId),
            'sales.wholesale_overdue_commitments' => $this->evaluateWholesaleOverdueCommitments($companyId, $definition, $thresholdValue, $windowHours ?? 24, $branchId),
            'projects.overdue_tasks' => $this->evaluateOverdueTasks($companyId, $definition, $thresholdValue, $windowHours ?? 24, $branchId),
            'manufacturing.late_orders' => $this->evaluateLateOrders($companyId, $definition, $thresholdValue, $windowHours ?? 24, $branchId),
            'integrations.secrets_critical' => $this->evaluateCriticalSecrets($companyId, $definition, $thresholdValue, $branchId),
            default => throw new \InvalidArgumentException('Signal automation inconnu.'),
        };
    }

    private function evaluateStaleApprovals(int $companyId, array $definition, int $thresholdValue, int $windowHours, ?int $branchId = null): array
    {
        $thresholdDate = now()->subHours($windowHours);
        $query = ApprovalStep::query()
            ->where('company_id', $companyId)
            ->where('status', 'pending')
            ->where('created_at', '<=', $thresholdDate);

        if ($branchId) {
            $query->whereHasMorph(
                'approvable',
                [SalesInvoice::class, PurchaseBill::class, Expense::class],
                fn ($approvableQuery) => $approvableQuery->where('branch_id', $branchId)
            );
        }

        $count = (int) (clone $query)->count();
        $breakdown = (clone $query)
            ->select('module')
            ->selectRaw('COUNT(*) as aggregate_count')
            ->groupBy('module')
            ->orderByDesc('aggregate_count')
            ->get()
            ->map(fn (ApprovalStep $step) => ($step->module ?: 'workflow').': '.$step->aggregate_count)
            ->values()
            ->all();

        return $this->evaluationPayload(
            signalKey: 'approvals.stale_steps',
            definition: $definition,
            thresholdValue: $thresholdValue,
            value: $count,
            message: $count > 0
                ? $count.' etape(s) d approbation sont ouvertes depuis plus de '.$windowHours.' h.'
                : 'Aucune approbation stale detectee.',
            details: [
                'branch_id' => $branchId,
                'window_hours' => $windowHours,
                'breakdown' => $breakdown,
            ],
        );
    }

    private function evaluateOverdueInvoices(int $companyId, array $definition, int $thresholdValue, int $windowHours, ?int $branchId = null): array
    {
        $thresholdDate = now()->subHours($windowHours);
        $query = SalesInvoice::query()
            ->where('company_id', $companyId)
            ->when($branchId, fn ($builder, $branchId) => $builder->where('branch_id', $branchId))
            ->where('status', 'validated')
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $thresholdDate->toDateString());

        $count = (int) (clone $query)->count();
        $balance = (float) (clone $query)->sum('balance_due');

        return $this->evaluationPayload(
            signalKey: 'collections.overdue_invoices',
            definition: $definition,
            thresholdValue: $thresholdValue,
            value: $count,
            message: $count > 0
                ? $count.' facture(s) client sont en retard pour '.number_format($balance, 0, ',', ' ').' XOF.'
                : 'Aucune facture client overdue detectee.',
            details: [
                'branch_id' => $branchId,
                'window_hours' => $windowHours,
                'balance_due' => $balance,
            ],
        );
    }

    private function evaluateUnreconciledMobileMoney(int $companyId, array $definition, int $thresholdValue, int $windowHours, ?int $branchId = null): array
    {
        $thresholdDate = now()->subHours($windowHours);
        $query = Payment::query()
            ->where('company_id', $companyId)
            ->when($branchId, fn ($builder, $selectedBranchId) => $builder->where('branch_id', $selectedBranchId))
            ->whereIn('method', $this->mobileMoneyMethods())
            ->whereDate('payment_date', '>=', $thresholdDate->toDateString())
            ->whereDoesntHave('reconciliationItem');

        $payments = (clone $query)->get();
        $count = (int) $payments->count();
        $netAmount = round((float) $payments->sum(function (Payment $payment): float {
            return $payment->direction === 'out'
                ? -1 * (float) $payment->amount
                : (float) $payment->amount;
        }), 2);
        $missingReferenceCount = (int) $payments
            ->filter(fn (Payment $payment) => blank(trim((string) $payment->reference)))
            ->count();
        $providerBreakdown = $payments
            ->groupBy('method')
            ->map(function (Collection $items, string $method): string {
                $labels = [
                    'wave' => 'Wave',
                    'orange_money' => 'Orange Money',
                    'moov_money' => 'Moov Money',
                    'mobile_money' => 'Autre mobile money',
                ];

                return ($labels[$method] ?? $method).': '.$items->count();
            })
            ->sortByDesc(fn (string $line): int => (int) preg_replace('/^.*: /', '', $line))
            ->values()
            ->all();

        $message = $count > 0
            ? $count.' flux mobile money restent a rapprocher pour '.number_format($netAmount, 0, ',', ' ').' XOF.'
            : 'Flux mobile money rapproches sur la fenetre suivie.';

        if ($count > 0 && $missingReferenceCount > 0) {
            $message .= ' '.$missingReferenceCount.' sans reference.';
        }

        return $this->evaluationPayload(
            signalKey: 'treasury.unreconciled_mobile_money',
            definition: $definition,
            thresholdValue: $thresholdValue,
            value: $count,
            message: $message,
            details: [
                'branch_id' => $branchId,
                'window_hours' => $windowHours,
                'net_amount' => $netAmount,
                'missing_reference_count' => $missingReferenceCount,
                'provider_breakdown' => $providerBreakdown,
            ],
        );
    }

    private function evaluatePendingInternalTransferDeposits(int $companyId, array $definition, int $thresholdValue, int $windowHours, ?int $branchId = null): array
    {
        $thresholdDate = now()->subHours($windowHours);
        $query = Payment::query()
            ->withCount('attachments')
            ->where('company_id', $companyId)
            ->when($branchId, fn ($builder, $selectedBranchId) => $builder->where('branch_id', $selectedBranchId))
            ->where('payment_type', 'internal_transfer')
            ->where('direction', 'in')
            ->whereDate('payment_date', '<=', $thresholdDate->toDateString())
            ->whereHas('cashAccount', fn ($cashAccountQuery) => $cashAccountQuery->whereIn('type', $this->externalReconciliationAccountTypes()))
            ->whereDoesntHave('reconciliationItem');

        $payments = (clone $query)->get();
        $count = (int) $payments->count();
        $amount = round((float) $payments->sum('amount'), 2);
        $missingReferenceCount = (int) $payments
            ->filter(fn (Payment $payment) => $this->paymentNeedsDepositProofAttention($payment))
            ->count();
        $oldestPaymentDate = $payments
            ->sortBy(fn (Payment $payment) => $payment->payment_date?->timestamp ?? PHP_INT_MAX)
            ->first()?->payment_date;
        $accountBreakdown = $payments
            ->groupBy(fn (Payment $payment) => $payment->cashAccount?->name ?? 'Compte inconnu')
            ->map(fn (Collection $items, string $accountName): string => $accountName.': '.$items->count())
            ->values()
            ->all();

        $message = $count > 0
            ? $count.' versement(s) agence attendent encore confirmation pour '.number_format($amount, 0, ',', ' ').' XOF.'
            : 'Aucun versement agence en attente de confirmation au-dela de la fenetre suivie.';

        if ($count > 0 && $missingReferenceCount > 0) {
            $message .= ' '.$missingReferenceCount.' sans bordereau exploitable.';
        }

        if ($count > 0 && $oldestPaymentDate) {
            $message .= ' Plus ancien depot : '.$oldestPaymentDate->format('d/m/Y').'.';
        }

        return $this->evaluationPayload(
            signalKey: 'treasury.pending_internal_transfer_deposits',
            definition: $definition,
            thresholdValue: $thresholdValue,
            value: $count,
            message: $message,
            details: [
                'branch_id' => $branchId,
                'window_hours' => $windowHours,
                'amount' => $amount,
                'missing_reference_count' => $missingReferenceCount,
                'oldest_payment_date' => $oldestPaymentDate,
                'account_breakdown' => $accountBreakdown,
            ],
        );
    }

    private function evaluateDocumentedInternalTransferDeposits(int $companyId, array $definition, int $thresholdValue, int $windowHours, ?int $branchId = null): array
    {
        $thresholdDate = now()->subHours($windowHours);
        $query = Payment::query()
            ->withCount('attachments')
            ->where('company_id', $companyId)
            ->when($branchId, fn ($builder, $selectedBranchId) => $builder->where('branch_id', $selectedBranchId))
            ->where('payment_type', 'internal_transfer')
            ->where('direction', 'in')
            ->whereDate('payment_date', '<=', $thresholdDate->toDateString())
            ->whereHas('cashAccount', fn ($cashAccountQuery) => $cashAccountQuery->whereIn('type', $this->externalReconciliationAccountTypes()))
            ->whereDoesntHave('reconciliationItem');

        $payments = (clone $query)->get();
        $documentedPayments = $payments
            ->filter(fn (Payment $payment) => $this->paymentReadyForExternalReconciliation($payment))
            ->values();
        $count = (int) $documentedPayments->count();
        $amount = round((float) $documentedPayments->sum('amount'), 2);
        $referenceCount = (int) $documentedPayments
            ->filter(fn (Payment $payment) => filled(trim((string) $payment->reference)))
            ->count();
        $attachmentOnlyCount = (int) $documentedPayments
            ->filter(fn (Payment $payment) => blank(trim((string) $payment->reference)) && ((int) ($payment->attachments_count ?? 0) > 0))
            ->count();
        $oldestPaymentDate = $documentedPayments
            ->sortBy(fn (Payment $payment) => $payment->payment_date?->timestamp ?? PHP_INT_MAX)
            ->first()?->payment_date;
        $accountBreakdown = $documentedPayments
            ->groupBy(fn (Payment $payment) => $payment->cashAccount?->name ?? 'Compte inconnu')
            ->map(fn (Collection $items, string $accountName): string => $accountName.': '.$items->count())
            ->values()
            ->all();

        $message = $count > 0
            ? $count.' versement(s) documente(s) attendent encore rapprochement pour '.number_format($amount, 0, ',', ' ').' XOF.'
            : 'Aucun versement documente en attente de rapprochement au-dela de la fenetre suivie.';

        if ($count > 0 && $referenceCount > 0) {
            $message .= ' '.$referenceCount.' avec reference exploitable.';
        }

        if ($count > 0 && $attachmentOnlyCount > 0) {
            $message .= ' '.$attachmentOnlyCount.' via justificatif joint.';
        }

        if ($count > 0 && $oldestPaymentDate) {
            $message .= ' Plus ancien depot : '.$oldestPaymentDate->format('d/m/Y').'.';
        }

        return $this->evaluationPayload(
            signalKey: 'treasury.documented_internal_transfer_deposits',
            definition: $definition,
            thresholdValue: $thresholdValue,
            value: $count,
            message: $message,
            details: [
                'branch_id' => $branchId,
                'window_hours' => $windowHours,
                'amount' => $amount,
                'reference_count' => $referenceCount,
                'attachment_only_count' => $attachmentOnlyCount,
                'oldest_payment_date' => $oldestPaymentDate,
                'account_breakdown' => $accountBreakdown,
            ],
        );
    }

    private function evaluateTrackedProductsWithoutSaleableStock(int $companyId, array $definition, int $thresholdValue, int $windowHours, ?int $branchId = null): array
    {
        $products = $this->trackedProductsWithoutSaleableStock($companyId, $branchId);
        $count = (int) $products->count();
        $highlights = $products
            ->take(5)
            ->map(fn (Product $product) => $product->display_name ?? $product->name ?? $product->sku ?? 'Produit')
            ->values()
            ->all();

        $message = $count > 0
            ? $count.' produit(s) traces n ont plus aucun lot non expire disponible pour la vente.'
            : 'Tous les produits traces disposent encore d un stock vendable.';

        if ($count > 0 && $highlights !== []) {
            $message .= ' Exemples : '.implode(', ', $highlights).'.';
        }

        return $this->evaluationPayload(
            signalKey: 'inventory.tracked_products_saleable_zero',
            definition: $definition,
            thresholdValue: $thresholdValue,
            value: $count,
            message: $message,
            details: [
                'branch_id' => $branchId,
                'window_hours' => $windowHours,
                'highlights' => $highlights,
            ],
        );
    }

    private function evaluateFoodStoreShortDatedLots(int $companyId, array $definition, int $thresholdValue, int $windowHours, ?int $branchId = null): array
    {
        if (! $this->foodStoreProfileActive($companyId)) {
            return $this->evaluationPayload(
                signalKey: 'inventory.food_store_short_dated_lots',
                definition: $definition,
                thresholdValue: $thresholdValue,
                value: 0,
                message: 'Le profil alimentation et boutique n est pas actif sur cette societe.',
                details: [
                    'branch_id' => $branchId,
                    'window_hours' => $windowHours,
                ],
            );
        }

        $lots = $this->foodStoreShortDatedLots($companyId, $branchId);
        $count = (int) $lots->count();
        $productCount = $lots->pluck('product_id')->unique()->count();
        $quantity = round((float) $lots->sum(fn (ProductLot $lot) => (float) $lot->quantity_available), 3);
        $highlights = $lots
            ->take(5)
            ->map(fn (ProductLot $lot) => ($lot->product?->display_name ?? $lot->product?->name ?? 'Produit').' · '.$lot->displayCode())
            ->values()
            ->all();

        $message = $count > 0
            ? $count.' lot(s) sur '.$productCount.' produit(s) expirent sous 7 jours pour '.number_format($quantity, 3, ',', ' ').' unite(s) encore a ecouler.'
            : 'Aucun lot court detecte sur le perimetre alimentation et boutique.';

        if ($count > 0 && $highlights !== []) {
            $message .= ' Exemples : '.implode(', ', $highlights).'.';
        }

        return $this->evaluationPayload(
            signalKey: 'inventory.food_store_short_dated_lots',
            definition: $definition,
            thresholdValue: $thresholdValue,
            value: $count,
            message: $message,
            details: [
                'branch_id' => $branchId,
                'window_hours' => $windowHours,
                'product_count' => $productCount,
                'quantity' => $quantity,
                'highlights' => $highlights,
            ],
        );
    }

    private function evaluateFoodStoreSaleableStockouts(int $companyId, array $definition, int $thresholdValue, int $windowHours, ?int $branchId = null): array
    {
        if (! $this->foodStoreProfileActive($companyId)) {
            return $this->evaluationPayload(
                signalKey: 'inventory.food_store_saleable_stockouts',
                definition: $definition,
                thresholdValue: $thresholdValue,
                value: 0,
                message: 'Le profil alimentation et boutique n est pas actif sur cette societe.',
                details: [
                    'branch_id' => $branchId,
                    'window_hours' => $windowHours,
                ],
            );
        }

        $products = $this->foodStoreSaleableShelfProducts($companyId, $branchId)
            ->filter(fn (Product $product) => (float) ($product->saleable_stock ?? 0) <= 0.0001)
            ->values();
        $count = (int) $products->count();
        $highlights = $products
            ->take(5)
            ->map(fn (Product $product) => $product->display_name ?? $product->name ?? $product->sku ?? 'Produit')
            ->values()
            ->all();

        $message = $count > 0
            ? $count.' reference(s) ont deja tourne en stock mais n ont plus rien de vendable au comptoir.'
            : 'Aucune rupture vendable detectee sur le perimetre alimentation et boutique.';

        if ($count > 0 && $highlights !== []) {
            $message .= ' Exemples : '.implode(', ', $highlights).'.';
        }

        return $this->evaluationPayload(
            signalKey: 'inventory.food_store_saleable_stockouts',
            definition: $definition,
            thresholdValue: $thresholdValue,
            value: $count,
            message: $message,
            details: [
                'branch_id' => $branchId,
                'window_hours' => $windowHours,
                'highlights' => $highlights,
            ],
        );
    }

    private function evaluateWholesaleOrdersAtRisk(int $companyId, array $definition, int $thresholdValue, int $windowHours, ?int $branchId = null): array
    {
        if (($this->sectorProfileService->profileForCompany($companyId)['key'] ?? null) !== 'wholesale_distribution') {
            return $this->evaluationPayload(
                signalKey: 'sales.wholesale_orders_at_risk',
                definition: $definition,
                thresholdValue: $thresholdValue,
                value: 0,
                message: 'Le profil grossiste et distribution n est pas actif sur cette societe.',
                details: [
                    'branch_id' => $branchId,
                    'window_hours' => $windowHours,
                ],
            );
        }

        $summary = $this->orderCoverageService->wholesalePortfolioSummary($companyId, $branchId);
        $count = (int) ($summary['orders_at_risk_count'] ?? 0);
        $lineCount = (int) ($summary['order_lines_at_risk_count'] ?? 0);
        $shortageQty = (float) ($summary['at_risk_shortage_qty'] ?? 0);
        $highlights = array_values($summary['highlights'] ?? []);

        $message = $count > 0
            ? $count.' commande(s) grossiste restent a risque sur '.$lineCount.' ligne(s) pour '.number_format($shortageQty, 3, ',', ' ').' unite(s) encore non couvertes.'
            : 'Les commandes grossiste actives restent couvertes par le stock et les approvisionnements attendus.';

        if ($count > 0 && $highlights !== []) {
            $message .= ' Exemples : '.implode(', ', $highlights).'.';
        }

        return $this->evaluationPayload(
            signalKey: 'sales.wholesale_orders_at_risk',
            definition: $definition,
            thresholdValue: $thresholdValue,
            value: $count,
            message: $message,
            details: [
                'branch_id' => $branchId,
                'window_hours' => $windowHours,
                'line_count' => $lineCount,
                'shortage_qty' => $shortageQty,
                'highlights' => $highlights,
            ],
        );
    }

    private function evaluateWholesaleOverdueCommitments(int $companyId, array $definition, int $thresholdValue, int $windowHours, ?int $branchId = null): array
    {
        if (($this->sectorProfileService->profileForCompany($companyId)['key'] ?? null) !== 'wholesale_distribution') {
            return $this->evaluationPayload(
                signalKey: 'sales.wholesale_overdue_commitments',
                definition: $definition,
                thresholdValue: $thresholdValue,
                value: 0,
                message: 'Le profil grossiste et distribution n est pas actif sur cette societe.',
                details: [
                    'branch_id' => $branchId,
                    'window_hours' => $windowHours,
                ],
            );
        }

        $summary = $this->orderCoverageService->wholesalePortfolioSummary($companyId, $branchId);
        $count = (int) ($summary['overdue_backlog_orders_count'] ?? 0);
        $remainingQty = (float) ($summary['overdue_backlog_remaining_qty'] ?? 0);
        $highlights = array_values($summary['overdue_highlights'] ?? []);
        $oldestTargetDate = $summary['oldest_overdue_target_date'] ?? null;

        $message = $count > 0
            ? $count.' commande(s) grossiste gardent encore '.number_format($remainingQty, 3, ',', ' ').' unite(s) en reliquat apres la date promise.'
            : 'Les engagements grossiste actifs restent dans la promesse client.';

        if ($count > 0 && $oldestTargetDate) {
            $message .= ' Plus ancien engagement : '.$oldestTargetDate->format('d/m/Y').'.';
        }

        if ($count > 0 && $highlights !== []) {
            $message .= ' Exemples : '.implode(', ', $highlights).'.';
        }

        return $this->evaluationPayload(
            signalKey: 'sales.wholesale_overdue_commitments',
            definition: $definition,
            thresholdValue: $thresholdValue,
            value: $count,
            message: $message,
            details: [
                'branch_id' => $branchId,
                'window_hours' => $windowHours,
                'remaining_qty' => $remainingQty,
                'oldest_target_date' => $oldestTargetDate?->toDateString(),
                'highlights' => $highlights,
            ],
        );
    }

    private function evaluateOverdueTasks(int $companyId, array $definition, int $thresholdValue, int $windowHours, ?int $branchId = null): array
    {
        $thresholdDate = now()->subHours($windowHours);
        $query = ProjectTask::query()
            ->where('company_id', $companyId)
            ->when($branchId, fn ($builder, $branchId) => $builder->whereHas('project', fn ($projectQuery) => $projectQuery->where('branch_id', $branchId)))
            ->where('status', '!=', 'done')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $thresholdDate->toDateString());

        $count = (int) (clone $query)->count();

        return $this->evaluationPayload(
            signalKey: 'projects.overdue_tasks',
            definition: $definition,
            thresholdValue: $thresholdValue,
            value: $count,
            message: $count > 0
                ? $count.' tache(s) projet sont en retard et menacent l execution.'
                : 'Execution projets sous controle sur les echeances.',
            details: [
                'branch_id' => $branchId,
                'window_hours' => $windowHours,
            ],
        );
    }

    private function evaluateLateOrders(int $companyId, array $definition, int $thresholdValue, int $windowHours, ?int $branchId = null): array
    {
        $thresholdDate = now()->subHours($windowHours);
        $query = ProductionOrder::query()
            ->where('company_id', $companyId)
            ->when($branchId, fn ($builder, $branchId) => $builder->where('branch_id', $branchId))
            ->where('status', '!=', 'completed')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $thresholdDate->toDateString());

        $count = (int) (clone $query)->count();

        return $this->evaluationPayload(
            signalKey: 'manufacturing.late_orders',
            definition: $definition,
            thresholdValue: $thresholdValue,
            value: $count,
            message: $count > 0
                ? $count.' ordre(s) de production ont depasse leur echeance.'
                : 'Production sans ordre en retard critique.',
            details: [
                'branch_id' => $branchId,
                'window_hours' => $windowHours,
            ],
        );
    }

    private function evaluateCriticalSecrets(int $companyId, array $definition, int $thresholdValue, ?int $branchId = null): array
    {
        $connections = IntegrationConnection::query()
            ->with('secretOwner')
            ->where('company_id', $companyId)
            ->when($branchId, fn ($builder, $branchId) => $builder->where('branch_id', $branchId))
            ->where('status', 'active')
            ->get();

        $summary = $this->integrationSecretGovernanceService->summary($connections);
        $count = (int) ($summary['critical'] ?? 0);

        return $this->evaluationPayload(
            signalKey: 'integrations.secrets_critical',
            definition: $definition,
            thresholdValue: $thresholdValue,
            value: $count,
            message: $count > 0
                ? $count.' connecteur(s) ont une hygiene secret critique.'
                : 'Secrets connecteurs sous controle.',
            details: [
                'branch_id' => $branchId,
                'rotation_due_soon' => $summary['rotation_due_soon'] ?? 0,
                'rotation_overdue' => $summary['rotation_overdue'] ?? 0,
                'expiring_soon' => $summary['expiring_soon'] ?? 0,
                'expired' => $summary['expired'] ?? 0,
            ],
        );
    }

    private function mobileMoneyMethods(): array
    {
        return ['wave', 'orange_money', 'moov_money', 'mobile_money'];
    }

    private function externalReconciliationAccountTypes(): array
    {
        return ['bank', 'mobile_money'];
    }

    private function paymentNeedsDepositProofAttention(Payment $payment): bool
    {
        return blank(trim((string) $payment->reference))
            && ((int) ($payment->attachments_count ?? 0) === 0);
    }

    private function paymentReadyForExternalReconciliation(Payment $payment): bool
    {
        return filled(trim((string) $payment->reference))
            || ((int) ($payment->attachments_count ?? 0) > 0);
    }

    private function trackedProductsWithoutSaleableStock(int $companyId, ?int $branchId = null): Collection
    {
        $today = now()->toDateString();
        $saleableLotBalances = ProductLot::query()
            ->select('product_id')
            ->selectRaw('SUM(quantity_available) as saleable_qty')
            ->where('company_id', $companyId)
            ->when($branchId, fn ($query, $selectedBranchId) => $query->where('branch_id', $selectedBranchId))
            ->where('quantity_available', '>', 0.0001)
            ->where(function ($query) use ($today) {
                $query->whereNull('expires_at')
                    ->orWhereDate('expires_at', '>=', $today);
            })
            ->groupBy('product_id');

        return Product::query()
            ->where('products.company_id', $companyId)
            ->where('products.type', 'stockable')
            ->where('products.is_active', true)
            ->where('products.sale_ok', true)
            ->whereIn('products.tracking_type', ['lot', 'serial'])
            ->whereHas('lots', fn ($query) => $query
                ->where('company_id', $companyId)
                ->when($branchId, fn ($branchQuery, $selectedBranchId) => $branchQuery->where('branch_id', $selectedBranchId)))
            ->leftJoinSub($saleableLotBalances, 'saleable_balances', fn ($join) => $join->on('products.id', '=', 'saleable_balances.product_id'))
            ->select(['products.id', 'products.name', 'products.sku'])
            ->whereRaw('COALESCE(saleable_balances.saleable_qty, 0) <= 0.0001')
            ->orderBy('products.name')
            ->get();
    }

    private function foodStoreProfileActive(int $companyId): bool
    {
        return ($this->sectorProfileService->profileForCompany($companyId)['key'] ?? null) === 'food_store';
    }

    private function foodStoreShortDatedLots(int $companyId, ?int $branchId = null): Collection
    {
        $today = now()->toDateString();
        $horizon = now()->addDays(7)->toDateString();

        return ProductLot::query()
            ->with('product')
            ->where('company_id', $companyId)
            ->when($branchId, fn ($query, $selectedBranchId) => $query->where('branch_id', $selectedBranchId))
            ->where('quantity_available', '>', 0.0001)
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '>', $today)
            ->whereDate('expires_at', '<=', $horizon)
            ->whereHas('product', fn ($query) => $query
                ->where('type', 'stockable')
                ->where('is_active', true)
                ->where('sale_ok', true))
            ->orderBy('expires_at')
            ->orderBy('product_id')
            ->get();
    }

    private function foodStoreSaleableShelfProducts(int $companyId, ?int $branchId = null): Collection
    {
        $today = now()->toDateString();
        $balances = StockMovement::query()
            ->select('product_id')
            ->selectRaw('SUM(quantity_in - quantity_out) as current_stock')
            ->where('company_id', $companyId)
            ->when($branchId, fn ($query, $selectedBranchId) => $query->where('branch_id', $selectedBranchId))
            ->groupBy('product_id');
        $saleableLotBalances = ProductLot::query()
            ->select('product_id')
            ->selectRaw('SUM(quantity_available) as saleable_qty')
            ->where('company_id', $companyId)
            ->when($branchId, fn ($query, $selectedBranchId) => $query->where('branch_id', $selectedBranchId))
            ->where('quantity_available', '>', 0.0001)
            ->where(function ($query) use ($today) {
                $query->whereNull('expires_at')
                    ->orWhereDate('expires_at', '>=', $today);
            })
            ->groupBy('product_id');
        $saleableStockExpression = "CASE WHEN products.tracking_type IN ('lot', 'serial') THEN COALESCE(saleable_balances.saleable_qty, 0) ELSE COALESCE(balances.current_stock, 0) END";

        return Product::query()
            ->where('products.company_id', $companyId)
            ->where('products.type', 'stockable')
            ->where('products.is_active', true)
            ->where('products.sale_ok', true)
            ->where('products.min_stock', '>', 0)
            ->where(function ($query) use ($companyId, $branchId) {
                $query->whereHas('stockMovements', fn ($movementQuery) => $movementQuery
                    ->where('company_id', $companyId)
                    ->when($branchId, fn ($scopedQuery, $selectedBranchId) => $scopedQuery->where('branch_id', $selectedBranchId)))
                    ->orWhereHas('lots', fn ($lotQuery) => $lotQuery
                        ->where('company_id', $companyId)
                        ->when($branchId, fn ($scopedQuery, $selectedBranchId) => $scopedQuery->where('branch_id', $selectedBranchId)));
            })
            ->leftJoinSub($balances, 'balances', fn ($join) => $join->on('products.id', '=', 'balances.product_id'))
            ->leftJoinSub($saleableLotBalances, 'saleable_balances', fn ($join) => $join->on('products.id', '=', 'saleable_balances.product_id'))
            ->select(['products.id', 'products.name', 'products.sku', 'products.min_stock', 'products.tracking_type'])
            ->selectRaw($saleableStockExpression.' as saleable_stock')
            ->orderBy('products.name')
            ->get();
    }

    private function evaluationPayload(string $signalKey, array $definition, int $thresholdValue, int $value, string $message, array $details = []): array
    {
        return [
            'signal_key' => $signalKey,
            'label' => $definition['label'],
            'module_key' => $definition['module_key'],
            'action_url' => $definition['action_url'],
            'threshold_value' => $thresholdValue,
            'value' => $value,
            'matched' => $value >= $thresholdValue,
            'message' => $message,
            'details' => $details,
        ];
    }

    private function syncNotification(AutomationRule $rule, array $evaluation): InternalNotification
    {
        return InternalNotification::query()->updateOrCreate(
            [
                'company_id' => $rule->company_id,
                'code' => $this->notificationCode($rule),
            ],
            [
                'branch_id' => $rule->branch_id,
                'type' => 'automation',
                'level' => $rule->severity,
                'title' => 'Automatisation : '.$rule->name,
                'message' => $evaluation['message'],
                'action_url' => $evaluation['action_url'],
                'is_read' => false,
                'read_at' => null,
                'read_by' => null,
                'resolved_at' => null,
                'meta' => [
                    'rule_id' => $rule->id,
                    'rule_code' => $rule->code,
                    'signal_key' => $rule->signal_key,
                    'value' => $evaluation['value'],
                    'threshold_value' => $evaluation['threshold_value'],
                    'details' => $evaluation['details'],
                ],
            ]
        );
    }

    private function resolveNotification(AutomationRule $rule): void
    {
        InternalNotification::query()
            ->where('company_id', $rule->company_id)
            ->where('code', $this->notificationCode($rule))
            ->whereNull('resolved_at')
            ->update([
                'resolved_at' => now(),
            ]);
    }

    private function notificationCode(AutomationRule $rule): string
    {
        return 'automation-rule-'.$rule->id;
    }

    private function generateCode(int $companyId): string
    {
        return app(\App\Modules\Core\Company\Services\DocumentNumberService::class)
            ->nextNumber($companyId, 'automation_rule_code');
    }
}
