<?php

namespace App\Modules\Core\Approvals\Services;

use App\Models\User;
use App\Modules\Core\Approvals\Models\ApprovalStep;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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

        return match ($step->rule) {
            'director_only' => $user->hasRole('director'),
            default => $user->hasPermission($module.'.approve'),
        };
    }

    public function candidateApprovers(int $companyId, string $module, ApprovalStep $step): Collection
    {
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
            ],
        ];

        if ($amount > $step2Threshold) {
            $definitions[] = [
                'step_order' => 2,
                'code' => 'director_review',
                'label' => $amount > $criticalThreshold ? 'Validation direction obligatoire' : 'Validation direction',
                'rule' => 'director_only',
            ];
        }

        return $definitions;
    }
}
