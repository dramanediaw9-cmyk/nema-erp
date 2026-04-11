<?php

namespace App\Modules\Core\Ops\Services;

use App\Modules\Core\Approvals\Services\ApprovalSettingsService;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Core\Integrations\Models\ApiToken;
use App\Modules\Core\Integrations\Models\IntegrationConnection;
use App\Modules\Core\Integrations\Models\IntegrationEvent;
use App\Modules\Core\Integrations\Services\IntegrationSecretGovernanceService;
use App\Modules\Core\Integrations\Services\IntegrationOutboxService;
use App\Modules\Core\Ops\Models\SystemHealthSnapshot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

class SystemHealthService
{
    public function __construct(
        private readonly ApprovalSettingsService $approvalSettingsService,
        private readonly IntegrationOutboxService $integrationOutboxService,
        private readonly IntegrationSecretGovernanceService $integrationSecretGovernanceService,
        private readonly BackupService $backupService,
        private readonly ApplicationMonitoringService $applicationMonitoringService,
    ) {}

    public function report(?Company $company = null): array
    {
        $monitoring = $this->applicationMonitoringService->summary();

        $checks = [
            $this->appDebugCheck(),
            $this->appUrlCheck(),
            $this->databaseCheck(),
            $this->storageCheck(),
            $this->publicStorageLinkCheck(),
            $this->queueCheck(),
            $this->migrationCheck(),
            $this->backupCheck(),
            $this->applicationLogsCheck($monitoring['logs']),
            $this->failedJobsCheck($monitoring['failed_jobs']),
            $this->outboxCheck($company),
            $this->outboundNotificationCheck($company),
            $this->apiTokenCheck($company),
            $this->integrationConnectionsCheck($company),
            $this->integrationConnectionSecretsCheck($company),
        ];

        $warningCount = collect($checks)->where('status', 'warning')->count();
        $failureCount = collect($checks)->where('status', 'fail')->count();
        $overallStatus = $failureCount > 0 ? 'fail' : ($warningCount > 0 ? 'warning' : 'ok');

        return [
            'captured_at' => now(),
            'scope' => $company ? 'company' : 'platform',
            'tenant_id' => $company?->tenant_id,
            'company_id' => $company?->id,
            'company_name' => $company?->name,
            'overall_status' => $overallStatus,
            'warning_count' => $warningCount,
            'failure_count' => $failureCount,
            'checks' => $checks,
            'meta' => [
                'app_env' => config('app.env'),
                'queue_connection' => config('queue.default'),
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'monitoring_status' => $monitoring['status'],
            ],
        ];
    }

    public function capture(?Company $company = null): SystemHealthSnapshot
    {
        $report = $this->report($company);

        return SystemHealthSnapshot::query()->create([
            'tenant_id' => $report['tenant_id'],
            'company_id' => $report['company_id'],
            'scope' => $report['scope'],
            'overall_status' => $report['overall_status'],
            'warning_count' => $report['warning_count'],
            'failure_count' => $report['failure_count'],
            'checks' => $report['checks'],
            'meta' => $report['meta'],
            'captured_at' => $report['captured_at'],
        ]);
    }

    public function outboxSummary(?Company $company = null): array
    {
        $query = IntegrationEvent::query();

        if ($company) {
            $query->where('company_id', $company->id);
        }

        return [
            'pending' => (clone $query)->where('status', 'pending')->count(),
            'published' => (clone $query)->where('status', 'published')->count(),
            'failed' => (clone $query)->where('status', 'failed')->count(),
            'oldest_pending_at' => (clone $query)->where('status', 'pending')->min('created_at'),
            'last_failed_at' => (clone $query)->where('status', 'failed')->max('updated_at'),
            'last_published_at' => (clone $query)->where('status', 'published')->max('published_at'),
        ];
    }

    private function appDebugCheck(): array
    {
        $debug = (bool) config('app.debug');

        return [
            'key' => 'app_debug',
            'label' => 'Mode debug',
            'status' => $debug ? 'warning' : 'ok',
            'message' => $debug ? 'APP_DEBUG est actif. A desactiver en production.' : 'APP_DEBUG est desactive.',
        ];
    }

    private function appUrlCheck(): array
    {
        $url = (string) config('app.url');
        $valid = filled($url) && str_starts_with($url, 'http');

        return [
            'key' => 'app_url',
            'label' => 'URL applicative',
            'status' => $valid ? 'ok' : 'warning',
            'message' => $valid ? 'APP_URL est renseignee.' : 'APP_URL est absente ou invalide.',
            'meta' => ['url' => $url],
        ];
    }

    private function databaseCheck(): array
    {
        try {
            $result = DB::select('select 1 as ok');

            return [
                'key' => 'database',
                'label' => 'Base de donnees',
                'status' => ! empty($result) ? 'ok' : 'warning',
                'message' => ! empty($result) ? 'Connexion base de donnees valide.' : 'Connexion base sans resultat attendu.',
                'meta' => ['connection' => config('database.default')],
            ];
        } catch (Throwable $exception) {
            return [
                'key' => 'database',
                'label' => 'Base de donnees',
                'status' => 'fail',
                'message' => 'Connexion base de donnees en echec.',
                'meta' => ['error' => $exception->getMessage()],
            ];
        }
    }

    private function storageCheck(): array
    {
        $paths = [
            storage_path(),
            storage_path('framework'),
            storage_path('logs'),
        ];

        $invalid = collect($paths)->first(fn (string $path) => ! File::exists($path) || ! is_writable($path));

        return [
            'key' => 'storage',
            'label' => 'Stockage local',
            'status' => $invalid ? 'fail' : 'ok',
            'message' => $invalid ? 'Un repertoire de stockage n est pas accessible en ecriture.' : 'Les repertoires de stockage sont accessibles.',
            'meta' => ['checked_paths' => $paths, 'invalid_path' => $invalid],
        ];
    }

    private function publicStorageLinkCheck(): array
    {
        $publicStorage = public_path('storage');
        $exists = file_exists($publicStorage);

        return [
            'key' => 'public_storage_link',
            'label' => 'Lien public storage',
            'status' => $exists ? 'ok' : 'warning',
            'message' => $exists ? 'Le lien public vers storage est en place.' : 'Le lien public storage manque. Lance artisan storage:link.',
        ];
    }

    private function queueCheck(): array
    {
        $connection = (string) config('queue.default');

        return [
            'key' => 'queue',
            'label' => 'File de taches',
            'status' => $connection === 'sync' ? 'warning' : 'ok',
            'message' => $connection === 'sync' ? 'La queue utilise sync. Passe sur database ou redis en production.' : 'La queue utilise '.$connection.'.',
            'meta' => ['connection' => $connection],
        ];
    }

    private function migrationCheck(): array
    {
        try {
            $migrator = app('migrator');
            $files = $migrator->getMigrationFiles(database_path('migrations'));
            $ran = $migrator->repositoryExists() ? $migrator->getRepository()->getRan() : [];
            $pending = count(array_diff(array_keys($files), $ran));

            return [
                'key' => 'migrations',
                'label' => 'Migrations',
                'status' => $pending > 0 ? 'warning' : 'ok',
                'message' => $pending > 0 ? $pending.' migration(s) en attente.' : 'Aucune migration en attente.',
                'meta' => ['pending' => $pending],
            ];
        } catch (Throwable $exception) {
            return [
                'key' => 'migrations',
                'label' => 'Migrations',
                'status' => 'warning',
                'message' => 'Impossible de verifier les migrations.',
                'meta' => ['error' => $exception->getMessage()],
            ];
        }
    }

    private function backupCheck(): array
    {
        $verification = $this->backupService->verify();

        if (blank($verification['created_at'] ?? null)) {
            return [
                'key' => 'backups',
                'label' => 'Sauvegardes locales',
                'status' => $verification['status'],
                'message' => $verification['message'],
                'meta' => $verification,
            ];
        }

        $createdAt = Carbon::parse($verification['created_at']);
        $ageHours = $createdAt->diffInHours(now());
        $status = $verification['status'];

        if ($status !== 'fail' && $ageHours > 48) {
            $status = 'warning';
        }

        $message = $verification['message'].' Derniere execution : '.$createdAt->format('d/m/Y H:i').'.';

        if ($status === 'warning' && $verification['status'] !== 'fail' && $ageHours > 48) {
            $message = 'Derniere sauvegarde trop ancienne ('.$createdAt->format('d/m/Y H:i').'). Integrite : '.mb_strtolower($verification['message']);
        }

        return [
            'key' => 'backups',
            'label' => 'Sauvegardes locales',
            'status' => $status,
            'message' => $message,
            'meta' => array_merge($verification, [
                'age_hours' => $ageHours,
            ]),
        ];
    }

    private function applicationLogsCheck(array $logs): array
    {
        return [
            'key' => 'application_logs',
            'label' => 'Logs applicatifs',
            'status' => $logs['status'],
            'message' => $logs['message'],
            'meta' => $logs,
        ];
    }

    private function failedJobsCheck(array $failedJobs): array
    {
        return [
            'key' => 'failed_jobs',
            'label' => 'Jobs en echec',
            'status' => $failedJobs['status'],
            'message' => $failedJobs['message'],
            'meta' => $failedJobs,
        ];
    }

    private function outboxCheck(?Company $company): array
    {
        $summary = $this->outboxSummary($company);
        $configuration = $company ? $this->integrationOutboxService->configurationForCompany($company->id) : null;

        if ($company && ($configuration['enabled'] ?? false) !== true) {
            $status = $summary['pending'] > 0 ? 'warning' : 'ok';

            return [
                'key' => 'outbox',
                'label' => 'Outbox integration',
                'status' => $status,
                'message' => $summary['pending'] > 0
                    ? 'Webhook integration desactive. Les evenements restent en attente.'
                    : 'Webhook integration desactive volontairement.',
                'meta' => array_merge($summary, ['webhook' => $configuration]),
            ];
        }

        if ($company && ($configuration['enabled'] ?? false) === true && blank($configuration['url'] ?? null)) {
            return [
                'key' => 'outbox',
                'label' => 'Outbox integration',
                'status' => 'fail',
                'message' => 'Webhook integration actif sans URL configuree.',
                'meta' => array_merge($summary, ['webhook' => $configuration]),
            ];
        }

        $status = $summary['failed'] > 0 ? 'fail' : ($summary['pending'] > 100 ? 'warning' : 'ok');

        return [
            'key' => 'outbox',
            'label' => 'Outbox integration',
            'status' => $status,
            'message' => $summary['failed'] > 0
                ? $summary['failed'].' evenement(s) outbox en echec.'
                : ($summary['pending'] > 100 ? 'Le volume outbox en attente est eleve.' : 'Outbox dans un etat normal.'),
            'meta' => array_merge($summary, $company ? ['webhook' => $configuration] : []),
        ];
    }

    private function outboundNotificationCheck(?Company $company): array
    {
        if (! $company) {
            return [
                'key' => 'outbound_notifications',
                'label' => 'Notifications sortantes',
                'status' => 'ok',
                'message' => 'Controle des canaux sortants disponible au niveau societe.',
            ];
        }

        $channels = $this->approvalSettingsService->notificationChannelsForCompany($company->id);
        $warnings = [];

        if (($channels['email']['enabled'] ?? false) === true) {
            $mailer = (string) config('mail.default');

            if (in_array($mailer, ['log', 'array'], true)) {
                $warnings[] = 'Email actif mais MAIL_MAILER='.$mailer.'.';
            }
        }

        if (($channels['whatsapp']['enabled'] ?? false) === true && blank(config('services.whatsapp.webhook_url'))) {
            $warnings[] = 'WhatsApp actif sans WHATSAPP_WEBHOOK_URL.';
        }

        if (($channels['email']['enabled'] ?? false) === false && ($channels['whatsapp']['enabled'] ?? false) === false) {
            return [
                'key' => 'outbound_notifications',
                'label' => 'Notifications sortantes',
                'status' => 'ok',
                'message' => 'Aucun canal externe active pour les approbations.',
                'meta' => $channels,
            ];
        }

        return [
            'key' => 'outbound_notifications',
            'label' => 'Notifications sortantes',
            'status' => empty($warnings) ? 'ok' : 'warning',
            'message' => empty($warnings)
                ? 'Canaux externes prets a l envoi.'
                : implode(' ', $warnings),
            'meta' => [
                'email_enabled' => (bool) ($channels['email']['enabled'] ?? false),
                'whatsapp_enabled' => (bool) ($channels['whatsapp']['enabled'] ?? false),
                'mailer' => config('mail.default'),
                'whatsapp_webhook_configured' => filled(config('services.whatsapp.webhook_url')),
            ],
        ];
    }

    private function apiTokenCheck(?Company $company): array
    {
        $query = ApiToken::query();

        if ($company) {
            $query->where('company_id', $company->id);
        }

        $active = (clone $query)
            ->where(function ($builder): void {
                $builder->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->count();
        $expired = (clone $query)->whereNotNull('expires_at')->where('expires_at', '<=', now())->count();
        $expiringSoon = (clone $query)
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', now()->addDays(7))
            ->count();
        $stale = (clone $query)
            ->where(function ($builder): void {
                $builder->whereNull('last_used_at')->orWhere('last_used_at', '<', now()->subDays(30));
            })
            ->count();

        $status = $expired > 0 ? 'warning' : (($expiringSoon > 0 || $stale > 0) ? 'warning' : 'ok');
        $message = $expired > 0
            ? $expired.' jeton(s) API expires a nettoyer.'
            : ($expiringSoon > 0
                ? $expiringSoon.' jeton(s) API expirent dans moins de 7 jours.'
                : ($stale > 0 ? $stale.' jeton(s) API sont inactifs ou jamais utilises.' : $active.' jeton(s) API actif(s).'));

        return [
            'key' => 'api_tokens',
            'label' => 'Jetons API',
            'status' => $status,
            'message' => $message,
            'meta' => [
                'active' => $active,
                'expired' => $expired,
                'expiring_soon' => $expiringSoon,
                'stale' => $stale,
            ],
        ];
    }

    private function integrationConnectionsCheck(?Company $company): array
    {
        $query = IntegrationConnection::query();

        if ($company) {
            $query->where('company_id', $company->id);
        }

        $active = (clone $query)->where('status', 'active')->count();
        $criticalActive = (clone $query)->where('status', 'active')->where('health_status', 'critical')->count();
        $watchActive = (clone $query)->where('status', 'active')->where('health_status', 'watch')->count();
        $overdueHealth = (clone $query)
            ->where('status', 'active')
            ->where(function ($builder): void {
                $builder->whereNull('last_health_at')->orWhere('last_health_at', '<', now()->subHours(48));
            })
            ->count();
        $staleSync = (clone $query)
            ->where('status', 'active')
            ->whereIn('sync_mode', ['outbound', 'bidirectional'])
            ->where(function ($builder): void {
                $builder->whereNull('last_sync_at')->orWhere('last_sync_at', '<', now()->subHours(72));
            })
            ->count();
        $criticalNames = (clone $query)
            ->where('status', 'active')
            ->where('health_status', 'critical')
            ->orderBy('partner_name')
            ->limit(5)
            ->pluck('name')
            ->all();

        $status = $criticalActive > 0 ? 'fail' : (($overdueHealth > 0 || $staleSync > 0) ? 'warning' : 'ok');
        $message = $criticalActive > 0
            ? 'Au moins un connecteur actif est critique: '.implode(', ', $criticalNames).'.'
            : (($overdueHealth > 0 || $staleSync > 0)
                ? 'Des connecteurs actifs demandent un controle recent ou une resynchronisation.'
                : $active.' connecteur(s) actif(s) sous controle.');

        return [
            'key' => 'integration_connections',
            'label' => 'Connecteurs partenaires',
            'status' => $status,
            'message' => $message,
            'meta' => [
                'active' => $active,
                'watch_active' => $watchActive,
                'critical_active' => $criticalActive,
                'overdue_health' => $overdueHealth,
                'stale_sync' => $staleSync,
                'critical_connections' => $criticalNames,
            ],
        ];
    }

    private function integrationConnectionSecretsCheck(?Company $company): array
    {
        $query = IntegrationConnection::query()->with('secretOwner');

        if ($company) {
            $query->where('company_id', $company->id);
        }

        $summary = $this->integrationSecretGovernanceService->summary($query->get());
        $critical = (int) ($summary['critical'] ?? 0);
        $watch = (int) ($summary['watch'] ?? 0);
        $overdue = (int) ($summary['rotation_overdue'] ?? 0);
        $expired = (int) ($summary['expired'] ?? 0);

        $status = $critical > 0
            ? 'fail'
            : (($watch > 0 || $overdue > 0 || $expired > 0) ? 'warning' : 'ok');

        $topAlerts = collect($summary['items'] ?? [])
            ->filter(fn (array $item): bool => ! empty($item['alerts']))
            ->take(3)
            ->map(fn (array $item): string => $item['name'].': '.$item['message'])
            ->values()
            ->all();

        $message = $critical > 0
            ? 'Au moins un secret de connecteur demande une rotation ou a expire.'
            : (($watch > 0 || $overdue > 0 || $expired > 0)
                ? 'Des secrets de connecteurs approchent leur echeance ou manquent de traçabilite.'
                : 'Les secrets des connecteurs sont sous controle.');

        return [
            'key' => 'integration_connection_secrets',
            'label' => 'Secrets des connecteurs',
            'status' => $status,
            'message' => $message,
            'meta' => [
                'total' => $summary['total'] ?? 0,
                'healthy' => $summary['healthy'] ?? 0,
                'watch' => $watch,
                'critical' => $critical,
                'rotation_due_soon' => $summary['rotation_due_soon'] ?? 0,
                'rotation_overdue' => $overdue,
                'expiring_soon' => $summary['expiring_soon'] ?? 0,
                'expired' => $expired,
                'top_alerts' => $topAlerts,
            ],
        ];
    }

    public function retryOutboxEvent(IntegrationEvent $event): IntegrationEvent
    {
        $event->forceFill([
            'status' => 'pending',
            'available_at' => now(),
            'published_at' => null,
            'last_error' => null,
        ])->save();

        return $event->fresh();
    }

    public function retryFailedOutbox(?Company $company = null, int $limit = 50): int
    {
        $query = IntegrationEvent::query()->where('status', 'failed');

        if ($company) {
            $query->where('company_id', $company->id);
        }

        $ids = $query
            ->orderBy('id')
            ->limit(max($limit, 1))
            ->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        return IntegrationEvent::query()
            ->whereIn('id', $ids)
            ->update([
                'status' => 'pending',
                'available_at' => now(),
                'published_at' => null,
                'last_error' => null,
                'updated_at' => now(),
            ]);
    }

    public function prunePublishedOutbox(int $days = 30): int
    {
        return IntegrationEvent::query()
            ->where('status', 'published')
            ->where('published_at', '<', now()->subDays($days))
            ->delete();
    }
}
