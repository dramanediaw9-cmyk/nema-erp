<?php

namespace App\Modules\Core\Platform\Services;

use App\Modules\Core\Automation\Models\AutomationExecution;
use App\Modules\Core\Automation\Models\AutomationRule;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Core\Integrations\Models\IntegrationConnection;
use App\Modules\Core\Integrations\Models\IntegrationEvent;
use App\Modules\Core\Ops\Services\ApplicationMonitoringService;
use App\Modules\Core\Ops\Services\BackupService;
use App\Modules\Core\Platform\Models\CorePulseSnapshot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CorePulseService
{
    public function __construct(
        private readonly ApplicationMonitoringService $applicationMonitoringService,
        private readonly BackupService $backupService,
        private readonly DeploymentReadinessService $deploymentReadinessService,
        private readonly DeploymentProfileService $deploymentProfileService,
    ) {
    }

    public function summary(Company $company, bool $store = false): array
    {
        $monitoring = $this->applicationMonitoringService->summary();
        $backupVerification = $this->backupService->verify();
        $profile = $this->deploymentProfileService->profileForCompany($company);
        $readiness = $this->deploymentReadinessService->summary($company, $profile);
        $slaTarget = max((int) config('ops.core_pulse_sla_score', 75), 1);
        $history = $this->history($company->id);

        $activeRules = AutomationRule::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->count();

        $matchedLastDay = AutomationExecution::query()
            ->where('company_id', $company->id)
            ->where('matched', true)
            ->where('executed_at', '>=', now()->subDay())
            ->count();

        $connections = IntegrationConnection::query()->where('company_id', $company->id)->count();
        $criticalConnections = IntegrationConnection::query()->where('company_id', $company->id)->where('health_status', 'critical')->count();
        $outboxFailed = IntegrationEvent::query()->where('company_id', $company->id)->where('status', 'failed')->count();

        $signals = [
            'automation' => $activeRules >= 5 ? 'ok' : ($activeRules > 0 ? 'warning' : 'fail'),
            'monitoring' => (string) ($monitoring['status'] ?? 'warning'),
            'backups' => (string) ($backupVerification['status'] ?? 'warning'),
            'ecosystem' => $criticalConnections > 0 || $outboxFailed > 0 ? 'warning' : 'ok',
            'readiness' => ($readiness['status'] ?? 'foundation') === 'ready'
                ? 'ok'
                : (in_array(($readiness['status'] ?? ''), ['progressing', 'foundation'], true) ? 'warning' : 'fail'),
        ];

        $score = $this->weightedScore($signals);

        $status = $score >= 85
            ? 'dominant'
            : ($score >= 70 ? 'competitive' : ($score >= 50 ? 'progressing' : 'fragile'));

        $trend7d = $this->trendFromScore($score, $history, 7);
        $trend30d = $this->trendFromScore($score, $history, 30);
        $slaMet = $score >= $slaTarget;

        $summary = [
            'status' => $status,
            'score' => $score,
            'company_id' => $company->id,
            'company_name' => $company->name,
            'signals' => $signals,
            'metrics' => [
                'active_rules' => $activeRules,
                'matched_last_24h' => $matchedLastDay,
                'integration_connections' => $connections,
                'critical_connections' => $criticalConnections,
                'outbox_failed' => $outboxFailed,
                'monitoring_status' => $monitoring['status'] ?? 'warning',
                'backup_status' => $backupVerification['status'] ?? 'warning',
                'readiness_score' => $readiness['score'] ?? 0,
                'readiness_status' => $readiness['status'] ?? 'foundation',
                'trend_7d' => $trend7d,
                'trend_30d' => $trend30d,
            ],
            'recommendations' => $this->recommendations($signals),
            'sla' => [
                'target_score' => $slaTarget,
                'met' => $slaMet,
                'delta' => $score - $slaTarget,
            ],
            'history' => $history->take(20)->values()->all(),
        ];

        if ($store) {
            $this->storeSnapshot($company, $summary);
            $summary['history'] = $this->history($company->id)
                ->take(20)
                ->values()
                ->all();
        }

        return $summary;
    }

    private function recommendations(array $signals): array
    {
        $actions = [];

        if (($signals['automation'] ?? 'warning') !== 'ok') {
            $actions[] = 'Activer au moins 5 regles d automatisation transverses (approbations, recouvrement, projets, production).';
        }

        if (($signals['monitoring'] ?? 'warning') !== 'ok') {
            $actions[] = 'Nettoyer les signaux logs/failed_jobs et stabiliser le monitoring applicatif avant montée en charge.';
        }

        if (($signals['backups'] ?? 'warning') !== 'ok') {
            $actions[] = 'Verifier la chaine backup locale + hors machine, puis tracer un exercice de restauration.';
        }

        if (($signals['ecosystem'] ?? 'warning') !== 'ok') {
            $actions[] = 'Reduire les connecteurs critiques et vider l outbox en echec pour fiabiliser l ecosysteme.';
        }

        if (($signals['readiness'] ?? 'warning') !== 'ok') {
            $actions[] = 'Ameliorer le score de readiness (queue, mail reel, stockage objet, discipline de release).';
        }

        return array_slice(array_values(array_unique($actions)), 0, 5);
    }

    private function weightedScore(array $signals): int
    {
        $weights = collect(config('ops.core_pulse_weights', []))
            ->map(fn (mixed $weight): int => max((int) $weight, 0));
        $total = max($weights->sum(), 1);

        $map = [
            'ok' => 100,
            'warning' => 60,
            'fail' => 25,
        ];

        $score = 0;

        foreach ($weights as $key => $weight) {
            $signalStatus = (string) ($signals[$key] ?? 'warning');
            $score += (($map[$signalStatus] ?? 60) * $weight);
        }

        return (int) round($score / $total);
    }

    private function storeSnapshot(Company $company, array $summary): void
    {
        CorePulseSnapshot::query()->create([
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'status' => $summary['status'],
            'score' => $summary['score'],
            'sla_target' => (int) data_get($summary, 'sla.target_score', 75),
            'sla_met' => (bool) data_get($summary, 'sla.met', false),
            'signals' => $summary['signals'],
            'metrics' => $summary['metrics'],
            'recommendations' => $summary['recommendations'],
            'captured_at' => now(),
        ]);
    }

    private function history(int $companyId): Collection
    {
        return CorePulseSnapshot::query()
            ->where('company_id', $companyId)
            ->latest('captured_at')
            ->limit(200)
            ->get(['score', 'status', 'sla_met', 'captured_at'])
            ->map(fn (CorePulseSnapshot $snapshot): array => [
                'score' => (int) $snapshot->score,
                'status' => $snapshot->status,
                'sla_met' => (bool) $snapshot->sla_met,
                'captured_at' => optional($snapshot->captured_at)->toDateTimeString(),
            ]);
    }

    private function trendFromScore(int $currentScore, Collection $history, int $days): int
    {
        $reference = $history
            ->first(fn (array $entry): bool => Carbon::parse((string) ($entry['captured_at'] ?? now()->toDateTimeString()))->lte(now()->subDays($days)))
            ?? $history->last();

        if (! $reference) {
            return 0;
        }

        return $currentScore - (int) $reference['score'];
    }
}
