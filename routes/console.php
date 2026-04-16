<?php

use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Automation\Services\AutomationEngineService;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Core\Integrations\Services\IntegrationOutboxService;
use App\Modules\Core\Notifications\Services\NotificationService;
use App\Modules\Core\Notifications\Services\OutboundNotificationService;
use App\Modules\Core\Ops\Services\ApplicationMonitoringService;
use App\Modules\Core\Ops\Services\BackupService;
use App\Modules\Core\Ops\Services\SystemHealthService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('nema:notifications:dispatch-outbound {--company=* : Limite le traitement a une ou plusieurs societes} {--limit=50 : Nombre maximum de notifications a traiter par societe}', function (OutboundNotificationService $outboundNotificationService) {
    $companyIds = collect($this->option('company'))
        ->filter(fn (mixed $value): bool => filled($value))
        ->map(fn (mixed $value): int => (int) $value)
        ->filter(fn (int $value): bool => $value > 0)
        ->values();
    $limit = max((int) $this->option('limit'), 1);

    $companies = $companyIds->isNotEmpty()
        ? Company::query()->whereIn('id', $companyIds)->orderBy('name')->get()
        : Company::query()->orderBy('name')->get();

    if ($companies->isEmpty()) {
        $this->info('Aucune societe a traiter.');

        return 0;
    }

    foreach ($companies as $company) {
        $summary = $outboundNotificationService->processQueued($company->id, $limit);

        $this->line(sprintf(
            '%s : %d traitee(s), %d envoyee(s), %d echec(s)',
            $company->name,
            (int) $summary['processed'],
            (int) $summary['sent'],
            (int) $summary['failed'],
        ));
    }

    return 0;
})->purpose('Traite la file des notifications sortantes');

Artisan::command('nema:notifications:sync-internal {--company=* : Limite la synchronisation a une ou plusieurs societes}', function (NotificationService $notificationService) {
    $companyIds = collect($this->option('company'))
        ->filter(fn (mixed $value): bool => filled($value))
        ->map(fn (mixed $value): int => (int) $value)
        ->filter(fn (int $value): bool => $value > 0)
        ->values();

    $companies = $companyIds->isNotEmpty()
        ? Company::query()->whereIn('id', $companyIds)->orderBy('name')->get()
        : Company::query()->orderBy('name')->get();

    if ($companies->isEmpty()) {
        $this->info('Aucune societe a traiter.');

        return 0;
    }

    foreach ($companies as $company) {
        $notificationService->syncCompanyAlerts($company->id);

        $branchIds = Branch::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->pluck('id');

        foreach ($branchIds as $branchId) {
            $notificationService->syncCompanyAlerts($company->id, (int) $branchId);
        }

        $this->line(sprintf(
            '%s : alertes internes synchronisees (%d agence(s) active(s)).',
            $company->name,
            $branchIds->count(),
        ));
    }

    return 0;
})->purpose('Synchronise les alertes internes metier et techniques');

Artisan::command('nema:integrations:dispatch-outbox {--company=* : Limite le traitement a une ou plusieurs societes} {--limit=50 : Nombre maximum d evenements a publier par societe}', function (IntegrationOutboxService $integrationOutboxService) {
    $companyIds = collect($this->option('company'))
        ->filter(fn (mixed $value): bool => filled($value))
        ->map(fn (mixed $value): int => (int) $value)
        ->filter(fn (int $value): bool => $value > 0)
        ->values();
    $limit = max((int) $this->option('limit'), 1);

    $companies = $companyIds->isNotEmpty()
        ? Company::query()->whereIn('id', $companyIds)->orderBy('name')->get()
        : Company::query()->orderBy('name')->get();

    if ($companies->isEmpty()) {
        $summary = $integrationOutboxService->processPending(null, $limit);
        $this->line(sprintf(
            'Global : %d traite(s), %d publie(s), %d echec(s), %d ignore(s)',
            (int) $summary['processed'],
            (int) $summary['published'],
            (int) $summary['failed'],
            (int) $summary['skipped'],
        ));

        return 0;
    }

    foreach ($companies as $company) {
        $summary = $integrationOutboxService->processPending($company->id, $limit);

        $this->line(sprintf(
            '%s : %d traite(s), %d publie(s), %d echec(s), %d ignore(s)',
            $company->name,
            (int) $summary['processed'],
            (int) $summary['published'],
            (int) $summary['failed'],
            (int) $summary['skipped'],
        ));
    }

    return 0;
})->purpose('Publie les evenements outbox vers les webhooks externes');

Artisan::command('nema:automation:run {--company=* : Limite le traitement a une ou plusieurs societes}', function (AutomationEngineService $automationEngineService) {
    $companyIds = collect($this->option('company'))
        ->filter(fn (mixed $value): bool => filled($value))
        ->map(fn (mixed $value): int => (int) $value)
        ->filter(fn (int $value): bool => $value > 0)
        ->values();

    $companies = $companyIds->isNotEmpty()
        ? Company::query()->whereIn('id', $companyIds)->orderBy('name')->get()
        : Company::query()->orderBy('name')->get();

    if ($companies->isEmpty()) {
        $this->info('Aucune societe a traiter.');

        return 0;
    }

    foreach ($companies as $company) {
        $summary = $automationEngineService->runActiveRulesForCompany($company->id);

        $this->line(sprintf(
            '%s : %d regle(s), %d signal(s), %d cooldown.',
            $company->name,
            (int) $summary['rules'],
            (int) $summary['matched'],
            (int) $summary['cooldown'],
        ));
    }

    return 0;
})->purpose('Execute les regles d automatisation du noyau');

Artisan::command('nema:ops:health-check {--store : Enregistre un snapshot en base} {--json : Retourne le rapport en JSON} {--company=* : Limite le check a une ou plusieurs societes}', function (SystemHealthService $systemHealthService) {
    $companyIds = collect($this->option('company'))
        ->filter(fn (mixed $value): bool => filled($value))
        ->map(fn (mixed $value): int => (int) $value)
        ->filter(fn (int $value): bool => $value > 0)
        ->values();

    $companies = $companyIds->isNotEmpty()
        ? Company::query()->whereIn('id', $companyIds)->orderBy('name')->get()
        : Company::query()->orderBy('name')->get();

    $reports = $companies->isNotEmpty()
        ? $companies->map(function (Company $company) use ($systemHealthService): array {
            return $this->option('store')
                ? $systemHealthService->capture($company)->only([
                    'tenant_id',
                    'company_id',
                    'scope',
                    'overall_status',
                    'warning_count',
                    'failure_count',
                    'captured_at',
                ])
                : $systemHealthService->report($company);
        })->all()
        : [
            $this->option('store')
                ? $systemHealthService->capture()->only([
                    'tenant_id',
                    'company_id',
                    'scope',
                    'overall_status',
                    'warning_count',
                    'failure_count',
                    'captured_at',
                ])
                : $systemHealthService->report(),
        ];

    if ($this->option('json')) {
        $this->line(json_encode($reports, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return 0;
    }

    foreach ($reports as $report) {
        $label = $report['company_name']
            ?? Company::query()->whereKey($report['company_id'] ?? null)->value('name')
            ?? 'Plateforme';

        $this->line(sprintf(
            '%s | statut=%s | alertes=%d | echecs=%d',
            $label,
            strtoupper((string) $report['overall_status']),
            (int) ($report['warning_count'] ?? 0),
            (int) ($report['failure_count'] ?? 0)
        ));
    }

    return 0;
})->purpose('Verifie la sante systeme et peut enregistrer des snapshots');

Artisan::command('nema:ops:backup-run {--keep=7 : Nombre de sauvegardes locales a conserver}', function (BackupService $backupService) {
    $keep = max((int) $this->option('keep'), 1);
    $manifest = $backupService->create($keep);

    $this->line(sprintf(
        'Sauvegarde locale creee : %s | tables=%d | lignes=%d | assets=%d | purge=%d',
        $manifest['created_at'],
        (int) ($manifest['tables_count'] ?? 0),
        (int) ($manifest['total_rows'] ?? 0),
        (int) ($manifest['assets_count'] ?? 0),
        (int) ($manifest['pruned_count'] ?? 0),
    ));

    return 0;
})->purpose('Genere une sauvegarde locale exploitable de l application');

Artisan::command('nema:ops:backup-verify {--path= : Chemin complet vers un dossier ou un manifest de sauvegarde}', function (BackupService $backupService) {
    $verification = $backupService->verify($this->option('path') ?: null);

    $this->line(sprintf(
        'Sauvegarde | statut=%s | tables=%d/%d | lignes=%d | assets=%d/%d',
        strtoupper((string) $verification['status']),
        (int) ($verification['tables_checked'] ?? 0),
        (int) ($verification['tables_expected'] ?? 0),
        (int) ($verification['verified_rows'] ?? 0),
        (int) ($verification['assets_checked'] ?? 0),
        (int) ($verification['assets_expected'] ?? 0),
    ));

    if (! empty($verification['directory'])) {
        $this->line('Source : '.$verification['directory']);
    }

    $this->line($verification['message']);

    foreach ($verification['warnings'] ?? [] as $warning) {
        $this->warn($warning);
    }

    foreach ($verification['errors'] ?? [] as $error) {
        $this->error($error);
    }

    return $verification['status'] === 'fail' ? 1 : 0;
})->purpose('Verifie qu une sauvegarde locale est lisible et coherente');

Artisan::command('nema:ops:monitor-app {--tail=400 : Nombre de lignes de log a inspecter} {--json : Retourne le rapport en JSON}', function (ApplicationMonitoringService $applicationMonitoringService) {
    $tail = max((int) $this->option('tail'), 1);
    $summary = $applicationMonitoringService->summary($tail);

    if ($this->option('json')) {
        $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $summary['status'] === 'fail' ? 1 : 0;
    }

    $this->line(sprintf(
        'Monitoring | statut=%s | logs=%s (%d signal(s)) | failed_jobs=%s (%d)',
        strtoupper((string) $summary['status']),
        strtoupper((string) ($summary['logs']['status'] ?? 'ok')),
        (int) ($summary['logs']['signals_count'] ?? 0),
        strtoupper((string) ($summary['failed_jobs']['status'] ?? 'ok')),
        (int) ($summary['failed_jobs']['count'] ?? 0),
    ));

    $this->line($summary['logs']['message'] ?? '');
    if (! empty($summary['logs']['last_signal_excerpt'])) {
        $this->warn(($summary['logs']['last_signal_at'] ?? 'Signal recent').' | '.$summary['logs']['last_signal_excerpt']);
    }

    $this->line($summary['failed_jobs']['message'] ?? '');
    foreach ($summary['failed_jobs']['recent_jobs'] ?? [] as $job) {
        $this->line(sprintf(
            '#%d | %s | %s | %s',
            (int) $job['id'],
            $job['queue'] ?: 'default',
            $job['failed_at'] ?: 'n/a',
            $job['exception'] ?: 'n/a',
        ));
    }

    return $summary['status'] === 'fail' ? 1 : 0;
})->purpose('Surveille les logs applicatifs et les jobs en echec');

Artisan::command('nema:ops:outbox-retry-failed {--company=* : Limite la reprise a une ou plusieurs societes} {--limit=50 : Nombre maximum d evenements a reprogrammer par societe}', function (SystemHealthService $systemHealthService) {
    $companyIds = collect($this->option('company'))
        ->filter(fn (mixed $value): bool => filled($value))
        ->map(fn (mixed $value): int => (int) $value)
        ->filter(fn (int $value): bool => $value > 0)
        ->values();
    $limit = max((int) $this->option('limit'), 1);

    $companies = $companyIds->isNotEmpty()
        ? Company::query()->whereIn('id', $companyIds)->orderBy('name')->get()
        : Company::query()->orderBy('name')->get();

    if ($companies->isEmpty()) {
        $count = $systemHealthService->retryFailedOutbox(null, $limit);
        $this->info($count.' evenement(s) outbox reprogramme(s).');

        return 0;
    }

    foreach ($companies as $company) {
        $count = $systemHealthService->retryFailedOutbox($company, $limit);
        $this->line($company->name.' : '.$count.' evenement(s) outbox reprogramme(s).');
    }

    return 0;
})->purpose('Reprogramme les evenements outbox en echec');

Artisan::command('nema:ops:outbox-prune {--days=30 : Age minimal en jours des evenements publies a purger}', function (SystemHealthService $systemHealthService) {
    $days = max((int) $this->option('days'), 1);
    $deleted = $systemHealthService->prunePublishedOutbox($days);

    $this->info($deleted.' evenement(s) outbox publies supprimes.');

    return 0;
})->purpose('Nettoie les evenements outbox publies trop anciens');

Schedule::command('nema:notifications:dispatch-outbound --limit=50')->everyMinute();
Schedule::command('nema:notifications:sync-internal')->everyFifteenMinutes();
Schedule::command('nema:automation:run')->everyThirtyMinutes();
Schedule::command('nema:integrations:dispatch-outbox --limit=50')->everyMinute();
Schedule::command('nema:ops:health-check --store')->hourly();
Schedule::command('nema:ops:monitor-app')->hourlyAt(20);
Schedule::command('nema:ops:outbox-prune --days=30')->dailyAt('02:15');
Schedule::command('nema:ops:backup-run --keep=7')->dailyAt('02:45');
Schedule::command('nema:ops:backup-verify')->dailyAt('03:00');
