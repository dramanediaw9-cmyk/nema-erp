<?php

namespace App\Modules\Core\Approvals\Services;

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

    public function defaultWorkflow(): array
    {
        return [
            'sales' => [
                'step2_threshold' => 100000,
                'critical_threshold' => 500000,
            ],
            'purchases' => [
                'step2_threshold' => 100000,
                'critical_threshold' => 500000,
            ],
            'expenses' => [
                'step2_threshold' => 100000,
                'critical_threshold' => 500000,
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
}
