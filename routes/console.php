<?php

use App\Modules\Core\Company\Models\Company;
use App\Modules\Core\Ops\Services\SystemHealthService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

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
        $this->line($company->name.' : '.$count.' evenement(s) reprogramme(s).');
    }

    return 0;
})->purpose('Reprogramme les evenements outbox en echec');

Artisan::command('nema:ops:outbox-prune {--days=30 : Age minimal en jours des evenements publies a purger}', function (SystemHealthService $systemHealthService) {
    $days = max((int) $this->option('days'), 1);
    $deleted = $systemHealthService->prunePublishedOutbox($days);

    $this->info($deleted.' evenement(s) outbox publies supprimes.');

    return 0;
})->purpose('Nettoie les evenements outbox publies trop anciens');

Schedule::command('nema:ops:health-check --store')->hourly();
Schedule::command('nema:ops:outbox-prune --days=30')->dailyAt('02:15');