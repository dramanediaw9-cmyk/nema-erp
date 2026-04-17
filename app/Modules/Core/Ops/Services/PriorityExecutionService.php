<?php

namespace App\Modules\Core\Ops\Services;

use App\Modules\Core\Company\Models\Company;
use App\Modules\Core\Platform\Services\CorePulseService;

class PriorityExecutionService
{
    public function __construct(
        private readonly OpsAlertingService $opsAlertingService,
        private readonly ApplicationMonitoringService $applicationMonitoringService,
        private readonly BackupService $backupService,
        private readonly CorePulseService $corePulseService,
    ) {
    }

    public function execute(Company $company, bool $apply = false): array
    {
        $items = [
            $this->alertingExecution($apply),
            $this->backupExecution($apply),
            $this->pulseExecution($company, $apply),
            $this->queueExecution(),
            $this->releaseExecution(),
        ];

        $status = collect($items)->contains(fn (array $item): bool => $item['status'] === 'fail')
            ? 'fail'
            : (collect($items)->contains(fn (array $item): bool => $item['status'] === 'warning') ? 'warning' : 'ok');

        return [
            'status' => $status,
            'company_id' => $company->id,
            'company_name' => $company->name,
            'applied' => $apply,
            'items' => $items,
            'checked_at' => now()->toDateTimeString(),
        ];
    }

    private function alertingExecution(bool $apply): array
    {
        $webhook = trim((string) config('services.ops_alerting.webhook_url', ''));
        $signature = trim((string) config('ops.alert_signature_secret', ''));

        if ($webhook === '') {
            return [
                'key' => 'alerting_external',
                'status' => 'warning',
                'message' => 'Webhook alerting non configure.',
                'details' => ['configured' => false],
            ];
        }

        $details = [
            'configured' => true,
            'signed' => $signature !== '',
        ];

        if ($apply) {
            $summary = $this->applicationMonitoringService->summary();
            $dispatch = $this->opsAlertingService->dispatchMonitoringAlert($summary, true);
            $details['dispatch'] = $dispatch;

            return [
                'key' => 'alerting_external',
                'status' => ($dispatch['status'] ?? 'warning') === 'fail' ? 'fail' : 'ok',
                'message' => $dispatch['message'] ?? 'Execution alerting terminee.',
                'details' => $details,
            ];
        }

        return [
            'key' => 'alerting_external',
            'status' => 'ok',
            'message' => 'Alerting configure et pret a execution.',
            'details' => $details,
        ];
    }

    private function backupExecution(bool $apply): array
    {
        $disk = trim((string) config('ops.backup_offsite_disk', ''));
        $prefix = trim((string) config('ops.backup_offsite_prefix', 'nema-erp/backups'), '/');

        if ($disk === '') {
            return [
                'key' => 'backup_offsite',
                'status' => 'warning',
                'message' => 'Disque offsite non configure.',
                'details' => ['disk' => $disk, 'prefix' => $prefix],
            ];
        }

        $details = ['disk' => $disk, 'prefix' => $prefix];

        if ($apply) {
            $sync = $this->backupService->syncLatestToOffsite();
            $verify = $this->backupService->verifyLatestOffsite();
            $details['sync'] = $sync;
            $details['verify'] = $verify;

            $status = collect([$sync['status'] ?? 'warning', $verify['status'] ?? 'warning'])->contains('fail')
                ? 'fail'
                : (collect([$sync['status'] ?? 'warning', $verify['status'] ?? 'warning'])->contains('warning') ? 'warning' : 'ok');

            return [
                'key' => 'backup_offsite',
                'status' => $status,
                'message' => 'Execution backup offsite terminee.',
                'details' => $details,
            ];
        }

        return [
            'key' => 'backup_offsite',
            'status' => 'ok',
            'message' => 'Configuration backup offsite prete.',
            'details' => $details,
        ];
    }

    private function pulseExecution(Company $company, bool $apply): array
    {
        $pulse = $this->corePulseService->summary($company, $apply);

        return [
            'key' => 'core_pulse',
            'status' => ($pulse['status'] ?? 'progressing') === 'fragile' ? 'warning' : 'ok',
            'message' => 'Pulse calcule: '.strtoupper((string) ($pulse['status'] ?? 'progressing')).' (score '.((int) ($pulse['score'] ?? 0)).').',
            'details' => [
                'score' => $pulse['score'] ?? 0,
                'status' => $pulse['status'] ?? 'progressing',
                'sla' => $pulse['sla'] ?? [],
            ],
        ];
    }

    private function queueExecution(): array
    {
        $queue = (string) config('queue.default', 'database');
        $isRedis = $queue === 'redis';
        $horizonInstalled = class_exists(\Laravel\Horizon\HorizonServiceProvider::class);

        return [
            'key' => 'queue_runtime',
            'status' => $isRedis ? 'ok' : 'warning',
            'message' => $isRedis
                ? 'Queue configuree sur Redis. Horizon '.($horizonInstalled ? 'disponible' : 'non installe').'.'
                : 'Queue non Redis: bascule recommandee pour execution pilote/prod.',
            'details' => [
                'queue_connection' => $queue,
                'horizon_installed' => $horizonInstalled,
            ],
        ];
    }

    private function releaseExecution(): array
    {
        $hooks = [
            'staging_deploy' => trim((string) env('LARAVEL_CLOUD_DEPLOY_HOOK_STAGING', '')),
            'production_deploy' => trim((string) env('LARAVEL_CLOUD_DEPLOY_HOOK_PRODUCTION', '')),
        ];

        $configured = collect($hooks)->filter(fn (string $value): bool => $value !== '')->count();

        return [
            'key' => 'release_guardrails',
            'status' => $configured >= 2 ? 'ok' : 'warning',
            'message' => $configured >= 2
                ? 'Hooks de promotion staging/prod renseignes.'
                : 'Hooks staging/prod incomplets: promotion automatisee partielle.',
            'details' => [
                'configured_hooks' => $configured,
                'expected_hooks' => 2,
            ],
        ];
    }
}
