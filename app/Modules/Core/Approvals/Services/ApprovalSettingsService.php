<?php

namespace App\Modules\Core\Approvals\Services;

use App\Models\User;
use App\Modules\Core\Company\Models\Setting;

class ApprovalSettingsService
{
    public function workflowForCompany(int $companyId): array
    {
        $setting = Setting::query()->firstOrCreate(
            ['company_id' => $companyId, 'key' => 'approval_workflows'],
            ['value' => $this->defaultWorkflow()]
        );

        return $this->merge($this->defaultWorkflow(), $setting->value ?? []);
    }

    public function notificationChannelsForCompany(int $companyId): array
    {
        $setting = Setting::query()->firstOrCreate(
            ['company_id' => $companyId, 'key' => 'approval_notification_channels'],
            ['value' => $this->defaultNotificationChannels()]
        );

        return $this->merge($this->defaultNotificationChannels(), $setting->value ?? []);
    }

    public function updateWorkflowForCompany(int $companyId, array $value): void
    {
        Setting::query()->updateOrCreate(
            ['company_id' => $companyId, 'key' => 'approval_workflows'],
            ['value' => $this->merge($this->defaultWorkflow(), $value)]
        );
    }

    public function updateNotificationChannelsForCompany(int $companyId, array $value): void
    {
        Setting::query()->updateOrCreate(
            ['company_id' => $companyId, 'key' => 'approval_notification_channels'],
            ['value' => $this->merge($this->defaultNotificationChannels(), $value)]
        );
    }

    public function resolveAssignment(int $companyId, string $module, int $stepOrder, ?int $branchId = null): array
    {
        $workflow = $this->workflowForCompany($companyId)[$module] ?? [];
        $assignmentKey = $stepOrder > 1 ? 'step2_assignee_id' : 'step1_assignee_id';

        $assigneeId = $branchId
            ? $this->normalizeAssigneeId(data_get($workflow, 'branch_assignments.'.$branchId.'.'.$assignmentKey))
            : null;
        $source = $assigneeId ? 'branch_assignment' : null;

        if (! $assigneeId) {
            $assigneeId = $this->normalizeAssigneeId($workflow[$assignmentKey] ?? null);
            $source = $assigneeId ? 'module_default' : null;
        }

        if (! $assigneeId) {
            return [
                'assignee_id' => null,
                'source' => null,
                'branch_id' => $branchId,
                'step_order' => $stepOrder,
            ];
        }

        $assignee = User::query()
            ->with(['roles.permissions'])
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->find($assigneeId);

        if (! $assignee || ! $this->canUserBeAssigned($assignee, $module, $stepOrder)) {
            return [
                'assignee_id' => null,
                'source' => null,
                'branch_id' => $branchId,
                'step_order' => $stepOrder,
            ];
        }

        return [
            'assignee_id' => $assignee->id,
            'source' => $source,
            'branch_id' => $branchId,
            'step_order' => $stepOrder,
        ];
    }

    public function canUserBeAssigned(User $user, string $module, int $stepOrder): bool
    {
        if ($user->hasRole('platform_admin')) {
            return true;
        }

        return match ($this->ruleForStepOrder($stepOrder)) {
            'director_only' => $user->hasRole('director'),
            default => $user->hasPermission($module.'.approve'),
        };
    }

    public function defaultWorkflow(): array
    {
        return [
            'sales' => [
                'step2_threshold' => 100000,
                'critical_threshold' => 500000,
                'step1_sla_hours' => 24,
                'step2_sla_hours' => 12,
                'step1_assignee_id' => null,
                'step2_assignee_id' => null,
                'branch_assignments' => [],
            ],
            'purchases' => [
                'step2_threshold' => 100000,
                'critical_threshold' => 500000,
                'step1_sla_hours' => 24,
                'step2_sla_hours' => 12,
                'step1_assignee_id' => null,
                'step2_assignee_id' => null,
                'branch_assignments' => [],
            ],
            'expenses' => [
                'step2_threshold' => 100000,
                'critical_threshold' => 500000,
                'step1_sla_hours' => 24,
                'step2_sla_hours' => 12,
                'step1_assignee_id' => null,
                'step2_assignee_id' => null,
                'branch_assignments' => [],
            ],
        ];
    }

    public function defaultNotificationChannels(): array
    {
        return [
            'email' => [
                'enabled' => false,
                'copy_to' => '',
            ],
            'whatsapp' => [
                'enabled' => false,
                'copy_to' => '',
            ],
        ];
    }

    private function merge(array $defaults, array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item) && isset($defaults[$key]) && is_array($defaults[$key])) {
                $defaults[$key] = $this->merge($defaults[$key], $item);
                continue;
            }

            $defaults[$key] = $item;
        }

        return $defaults;
    }

    private function normalizeAssigneeId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max((int) $value, 0) ?: null;
    }

    private function ruleForStepOrder(int $stepOrder): string
    {
        return $stepOrder > 1 ? 'director_only' : 'module_approver';
    }
}
