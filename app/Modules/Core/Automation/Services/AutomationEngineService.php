<?php

namespace App\Modules\Core\Automation\Services;

use App\Models\User;
use App\Modules\Core\Approvals\Models\ApprovalStep;
use App\Modules\Core\Automation\Models\AutomationExecution;
use App\Modules\Core\Automation\Models\AutomationRule;
use App\Modules\Core\Integrations\Models\IntegrationConnection;
use App\Modules\Core\Integrations\Services\IntegrationSecretGovernanceService;
use App\Modules\Core\Notifications\Models\InternalNotification;
use App\Modules\Manufacturing\Models\ProductionOrder;
use App\Modules\Projects\Models\ProjectTask;
use App\Modules\Sales\Models\SalesInvoice;
use Illuminate\Support\Collection;

class AutomationEngineService
{
    public function __construct(
        private readonly IntegrationSecretGovernanceService $integrationSecretGovernanceService,
    ) {
    }

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
        $number = AutomationRule::query()->where('company_id', $companyId)->count() + 1;

        return 'AUTO-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }
}
