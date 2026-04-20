<?php

namespace App\Modules\Core\Approvals\Services;

use App\Models\User;
use App\Modules\Core\Approvals\Models\ApprovalStep;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ApprovalFlowService
{
    public function __construct(private readonly ApprovalSettingsService $approvalSettingsService)
    {
    }

    public function initialize(Model $document, string $module, float $amount): Collection
    {
        if ($document->approvalSteps()->exists()) {
            return $document->approvalSteps()->orderBy('step_order')->get();
        }

        foreach ($this->definitions($document->company_id, $module, $amount) as $definition) {
            $document->approvalSteps()->create([
                'company_id' => $document->company_id,
                'module' => $module,
                'step_order' => $definition['step_order'],
                'code' => $definition['code'],
                'label' => $definition['label'],
                'rule' => $definition['rule'],
                'status' => 'pending',
                'due_at' => $definition['due_at'],
            ]);
        }

        return $document->approvalSteps()->orderBy('step_order')->get();
    }

    public function autoAdvance(Model $document, string $module, User $user, callable $onFinalApproval): array
    {
        return $this->advance($document, $module, $user, $onFinalApproval, false);
    }

    public function approve(Model $document, string $module, User $user, callable $onFinalApproval): array
    {
        return $this->advance($document, $module, $user, $onFinalApproval, true);
    }

    public function currentPendingStep(Model $document): ?ApprovalStep
    {
        return $document->approvalSteps()
            ->where('status', 'pending')
            ->orderBy('step_order')
            ->first();
    }

    public function canUserApproveStep(User $user, string $module, ApprovalStep $step): bool
    {
        if ($user->hasRole('platform_admin')) {
            return true;
        }

        if ($step->assigned_to) {
            return (int) $step->assigned_to === (int) $user->id;
        }

        return match ($step->rule) {
            'director_only' => $user->hasRole('director'),
            default => $user->hasPermission($module.'.approve'),
        };
    }

    public function candidateApprovers(int $companyId, string $module, ApprovalStep $step): Collection
    {
        if ($step->assigned_to) {
            $assigned = User::query()
                ->with(['roles.permissions'])
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->find($step->assigned_to);

            if ($assigned) {
                return collect([$assigned]);
            }
        }

        $users = User::query()
            ->with(['roles.permissions'])
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->get();

        $businessApprovers = $users
            ->reject(fn (User $user) => $user->hasRole('platform_admin'))
            ->filter(fn (User $user) => $this->canUserApproveStep($user, $module, $step))
            ->values();

        if ($businessApprovers->isNotEmpty()) {
            return $businessApprovers;
        }

        return $users
            ->filter(fn (User $user) => $this->canUserApproveStep($user, $module, $step))
            ->values();
    }

    public function delegate(ApprovalStep $step, User $actor, User $delegateTo, ?string $note = null): ApprovalStep
    {
        return DB::transaction(function () use ($step, $actor, $delegateTo, $note) {
            $step = ApprovalStep::query()
                ->with(['approvable'])
                ->whereKey($step->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($step->status !== 'pending') {
                throw ValidationException::withMessages([
                    'approval_step' => 'Seules les etapes encore en attente peuvent etre deleguees.',
                ]);
            }

            $document = $step->approvable;
            if (! ($document instanceof Model)) {
                throw ValidationException::withMessages([
                    'approval_step' => 'Impossible de retrouver le document lie a cette etape.',
                ]);
            }

            $currentPendingStep = $this->currentPendingStep($document);
            if (! $currentPendingStep || $currentPendingStep->id !== $step->id) {
                throw ValidationException::withMessages([
                    'approval_step' => 'Seule l etape courante du workflow peut etre deleguee.',
                ]);
            }

            if (! $this->canUserApproveStep($actor, $step->module, $step)) {
                throw ValidationException::withMessages([
                    'approval_step' => 'Vous ne pouvez pas deleguer une etape que vous ne pouvez pas traiter.',
                ]);
            }

            if ((int) $delegateTo->company_id !== (int) $step->company_id || ! $delegateTo->is_active) {
                throw ValidationException::withMessages([
                    'delegate_to' => 'Le delegataire choisi n est pas disponible dans cette societe.',
                ]);
            }

            if ((int) $delegateTo->id === (int) $actor->id) {
                throw ValidationException::withMessages([
                    'delegate_to' => 'Choisissez un autre valideur pour la delegation.',
                ]);
            }

            if (! $this->baseApprovalPermission($delegateTo, $step->module, $step)) {
                throw ValidationException::withMessages([
                    'delegate_to' => 'Le delegataire choisi ne peut pas valider cette etape.',
                ]);
            }

            $meta = $step->meta ?? [];
            $meta['delegation'] = array_filter([
                'note' => Str::limit(trim((string) $note), 500, ''),
                'delegated_from' => $actor->id,
                'delegated_to' => $delegateTo->id,
                'delegated_at' => now()->toIso8601String(),
            ], fn (mixed $value) => filled($value));

            $step->forceFill([
                'assigned_to' => $delegateTo->id,
                'delegated_by' => $actor->id,
                'delegated_at' => now(),
                'meta' => $meta,
            ])->save();

            return $step->fresh(['approvable', 'assignedApprover', 'delegatedBy', 'approver']);
        });
    }

    public function escalateOverdueSteps(?int $companyId = null, ?string $module = null, int $limit = 50): Collection
    {
        $steps = $this->currentPendingStepsQuery($companyId, $module)
            ->with(['approvable', 'assignedApprover'])
            ->orderByRaw('COALESCE(due_at, created_at)')
            ->limit(max($limit, 1))
            ->get()
            ->filter(fn (ApprovalStep $step) => $this->isDueForEscalation($step))
            ->values();

        $escalated = collect();

        foreach ($steps as $step) {
            $escalationApprover = $this->preferredEscalationApprover($step);

            if (! $escalationApprover) {
                continue;
            }

            $escalated->push($this->applyEscalation($step, $escalationApprover));
        }

        return $escalated;
    }

    public function dueAtForStep(ApprovalStep $step): ?Carbon
    {
        if ($step->due_at) {
            return $step->due_at->copy();
        }

        $config = $this->approvalSettingsService->workflowForCompany($step->company_id)[$step->module] ?? [];
        $hours = $step->step_order > 1
            ? (int) ($config['step2_sla_hours'] ?? 12)
            : (int) ($config['step1_sla_hours'] ?? 24);

        if ($hours <= 0 || ! $step->created_at) {
            return null;
        }

        return $step->created_at->copy()->addHours($hours);
    }

    private function advance(Model $document, string $module, User $user, callable $onFinalApproval, bool $strict): array
    {
        return DB::transaction(function () use ($document, $module, $user, $onFinalApproval, $strict) {
            $document = $document->newQuery()->whereKey($document->getKey())->lockForUpdate()->firstOrFail();
            $this->initialize($document, $module, (float) $document->total);

            $approvedSteps = collect();
            $pendingStep = $this->currentPendingStep($document);

            while ($pendingStep && $this->canUserApproveStep($user, $module, $pendingStep)) {
                $pendingStep->update([
                    'status' => 'approved',
                    'assigned_to' => null,
                    'approved_at' => now(),
                    'approved_by' => $user->id,
                ]);

                $approvedSteps->push($pendingStep->fresh('approver'));
                $pendingStep = $this->currentPendingStep($document);
            }

            if ($strict && $approvedSteps->isEmpty()) {
                throw ValidationException::withMessages([
                    'approval' => 'Vous ne pouvez pas valider cette etape d approbation.',
                ]);
            }

            $isFullyApproved = ! $this->currentPendingStep($document);

            if ($isFullyApproved && $document->status !== 'validated') {
                $document = $onFinalApproval($document, $user);
            } else {
                $document = $document->fresh();
            }

            return [
                'document' => $document->loadMissing('approvalSteps.approver'),
                'approved_steps' => $approvedSteps,
                'is_fully_approved' => $isFullyApproved,
                'next_step' => $this->currentPendingStep($document),
            ];
        });
    }

    private function definitions(int $companyId, string $module, float $amount): array
    {
        $config = $this->approvalSettingsService->workflowForCompany($companyId)[$module] ?? [
            'step2_threshold' => 100000,
            'critical_threshold' => 500000,
        ];

        $step2Threshold = (float) ($config['step2_threshold'] ?? 100000);
        $criticalThreshold = (float) ($config['critical_threshold'] ?? 500000);

        $definitions = [
            [
                'step_order' => 1,
                'code' => 'operational_review',
                'label' => 'Validation operationnelle',
                'rule' => 'module_approver',
                'due_at' => $this->dueAtForHours((int) ($config['step1_sla_hours'] ?? 24)),
            ],
        ];

        if ($amount > $step2Threshold) {
            $definitions[] = [
                'step_order' => 2,
                'code' => 'director_review',
                'label' => $amount > $criticalThreshold ? 'Validation direction obligatoire' : 'Validation direction',
                'rule' => 'director_only',
                'due_at' => $this->dueAtForHours((int) ($config['step2_sla_hours'] ?? 12)),
            ];
        }

        return $definitions;
    }

    private function currentPendingStepsQuery(?int $companyId = null, ?string $module = null)
    {
        return ApprovalStep::query()
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->when($module, fn ($query) => $query->where('module', $module))
            ->where('status', 'pending')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('approval_steps as lower_steps')
                    ->whereColumn('lower_steps.approvable_type', 'approval_steps.approvable_type')
                    ->whereColumn('lower_steps.approvable_id', 'approval_steps.approvable_id')
                    ->whereColumn('lower_steps.company_id', 'approval_steps.company_id')
                    ->where('lower_steps.status', 'pending')
                    ->whereColumn('lower_steps.step_order', '<', 'approval_steps.step_order');
            });
    }

    private function isDueForEscalation(ApprovalStep $step): bool
    {
        if ($step->status !== 'pending' || $step->escalated_at) {
            return false;
        }

        $dueAt = $this->dueAtForStep($step);

        if (! $dueAt || $dueAt->isFuture()) {
            return false;
        }

        return true;
    }

    private function preferredEscalationApprover(ApprovalStep $step): ?User
    {
        $documentBranchId = (int) ($step->approvable?->getAttribute('branch_id') ?? 0);
        $users = User::query()
            ->with(['roles.permissions'])
            ->where('company_id', $step->company_id)
            ->where('is_active', true)
            ->orderByRaw($documentBranchId > 0 ? 'CASE WHEN branch_id = '.$documentBranchId.' THEN 0 ELSE 1 END' : '0')
            ->orderBy('id')
            ->get();

        $director = $users->first(fn (User $user) => $user->hasRole('director'));
        if ($director) {
            return $director;
        }

        $companyAdmin = $users->first(fn (User $user) => $user->hasRole('company_admin'));
        if ($companyAdmin) {
            return $companyAdmin;
        }

        return $users->first(fn (User $user) => $user->hasRole('platform_admin'));
    }

    private function applyEscalation(ApprovalStep $step, User $escalationApprover): ApprovalStep
    {
        return DB::transaction(function () use ($step, $escalationApprover) {
            $step = ApprovalStep::query()
                ->with(['approvable'])
                ->whereKey($step->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $this->isDueForEscalation($step)) {
                return $step->fresh(['approvable', 'assignedApprover', 'delegatedBy', 'approver']);
            }

            $meta = $step->meta ?? [];
            $meta['escalation'] = [
                'assigned_to' => $escalationApprover->id,
                'previous_assigned_to' => $step->assigned_to,
                'escalated_at' => now()->toIso8601String(),
                'due_at' => $this->dueAtForStep($step)?->toIso8601String(),
            ];

            $step->forceFill([
                'assigned_to' => $escalationApprover->id,
                'escalated_at' => now(),
                'meta' => $meta,
            ])->save();

            if (! $step->due_at) {
                $step->forceFill([
                    'due_at' => $this->dueAtForStep($step),
                ])->save();
            }

            return $step->fresh(['approvable', 'assignedApprover', 'delegatedBy', 'approver']);
        });
    }

    private function baseApprovalPermission(User $user, string $module, ApprovalStep $step): bool
    {
        if ($user->hasRole('platform_admin')) {
            return true;
        }

        return match ($step->rule) {
            'director_only' => $user->hasRole('director'),
            default => $user->hasPermission($module.'.approve'),
        };
    }

    private function dueAtForHours(int $hours): ?Carbon
    {
        return $hours > 0 ? now()->addHours($hours) : null;
    }
}
