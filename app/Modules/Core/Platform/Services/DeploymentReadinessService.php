<?php

namespace App\Modules\Core\Platform\Services;

use App\Modules\Core\Company\Models\Company;
use App\Modules\Core\Platform\Models\DeploymentProfile;
use App\Modules\Core\Ops\Services\SystemHealthService;
use Illuminate\Support\Collection;

class DeploymentReadinessService
{
    public function __construct(
        private readonly SystemHealthService $systemHealthService,
        private readonly DeploymentProfileService $deploymentProfileService,
    ) {
    }

    public function summary(Company $company, ?DeploymentProfile $profile = null): array
    {
        $profile = $profile ?: $this->deploymentProfileService->profileForCompany($company);
        $healthReport = $this->systemHealthService->report($company);
        $healthChecks = collect($healthReport['checks'])->keyBy('key');
        $requiresPilotGrade = in_array($profile->lifecycle_stage, ['pilot', 'live', 'scale'], true);
        $requiresCloudGrade = in_array($profile->deployment_mode, ['cloud', 'hybrid'], true);
        $requiresLiveGrade = in_array($profile->lifecycle_stage, ['live', 'scale'], true);

        $items = collect([
            $this->healthItem($healthReport),
            $this->backupItem($healthChecks, $profile, $requiresPilotGrade, $requiresLiveGrade),
            $this->monitoringItem($healthChecks),
            $this->queueItem($requiresPilotGrade, $requiresCloudGrade),
            $this->mailerItem($requiresPilotGrade, $requiresLiveGrade),
            $this->storageItem($requiresPilotGrade, $requiresCloudGrade),
            $this->releaseItem($profile, $requiresLiveGrade),
            $this->restoreDrillItem($profile, $requiresPilotGrade, $requiresLiveGrade),
        ])->values();

        $score = (int) round($items->avg(fn (array $item): int => match ($item['status']) {
            'ok' => 100,
            'warning' => 55,
            default => 0,
        }));

        $blockers = $items
            ->where('status', 'fail')
            ->pluck('label')
            ->values()
            ->all();

        $warnings = $items
            ->where('status', 'warning')
            ->pluck('label')
            ->values()
            ->all();

        $status = ! empty($blockers)
            ? 'at_risk'
            : ($score >= 85 ? 'ready' : ($score >= 60 ? 'progressing' : 'foundation'));

        return [
            'status' => $status,
            'score' => $score,
            'lifecycle_stage' => $profile->lifecycle_stage,
            'deployment_mode' => $profile->deployment_mode,
            'blockers' => $blockers,
            'warnings' => $warnings,
            'items' => $items->all(),
            'next_actions' => $items
                ->where('status', '!=', 'ok')
                ->pluck('action')
                ->filter()
                ->unique()
                ->values()
                ->take(5)
                ->all(),
        ];
    }

    private function healthItem(array $healthReport): array
    {
        return [
            'key' => 'health',
            'label' => 'Checks systeme',
            'status' => $healthReport['overall_status'] === 'fail'
                ? 'fail'
                : ($healthReport['overall_status'] === 'warning' ? 'warning' : 'ok'),
            'message' => sprintf(
                '%d alerte(s), %d echec(s) sur les checks systeme.',
                (int) ($healthReport['warning_count'] ?? 0),
                (int) ($healthReport['failure_count'] ?? 0)
            ),
            'action' => 'Traiter les checks Operations qui restent en alerte ou en echec avant le passage client.',
        ];
    }

    private function backupItem(Collection $checks, DeploymentProfile $profile, bool $requiresPilotGrade, bool $requiresLiveGrade): array
    {
        $backupCheck = $checks->get('backups');
        $status = $backupCheck['status'] ?? 'warning';

        if ($requiresLiveGrade && $status !== 'ok') {
            $status = 'fail';
        } elseif ($requiresPilotGrade && $status === 'fail') {
            $status = 'warning';
        }

        if (in_array($profile->backup_strategy, ['daily', 'verified'], true) && blank($profile->last_backup_verified_at)) {
            $status = $requiresLiveGrade ? 'fail' : 'warning';
        }

        return [
            'key' => 'backups',
            'label' => 'Sauvegardes et verification',
            'status' => $status,
            'message' => $backupCheck['message'] ?? 'Aucune verification de sauvegarde disponible.',
            'action' => 'Executer une sauvegarde verifiee puis renseigner la date de verification dans le profil de deploiement.',
        ];
    }

    private function monitoringItem(Collection $checks): array
    {
        $statuses = collect([
            data_get($checks->get('application_logs'), 'status', 'ok'),
            data_get($checks->get('failed_jobs'), 'status', 'ok'),
            data_get($checks->get('integration_connections'), 'status', 'ok'),
        ]);

        $status = $statuses->contains('fail')
            ? 'fail'
            : ($statuses->contains('warning') ? 'warning' : 'ok');

        return [
            'key' => 'monitoring',
            'label' => 'Monitoring et connecteurs',
            'status' => $status,
            'message' => 'Logs, jobs en echec et connecteurs partenaires sont inclus dans la surveillance.',
            'action' => 'Stabiliser les connecteurs critiques et reduire les signaux Operations avant mise en service.',
        ];
    }

    private function queueItem(bool $requiresPilotGrade, bool $requiresCloudGrade): array
    {
        $queue = (string) config('queue.default');

        $status = $queue === 'sync'
            ? ($requiresCloudGrade ? 'fail' : ($requiresPilotGrade ? 'warning' : 'ok'))
            : 'ok';

        return [
            'key' => 'queue',
            'label' => 'Queue et traitement asynchrone',
            'status' => $status,
            'message' => $queue === 'sync'
                ? 'La queue tourne encore en mode sync.'
                : 'La queue utilise '.$queue.'.',
            'action' => 'Passer la queue sur database ou redis avant la mise en service pilote/cloud.',
        ];
    }

    private function mailerItem(bool $requiresPilotGrade, bool $requiresLiveGrade): array
    {
        $mailer = (string) config('mail.default');
        $isLogLike = in_array($mailer, ['log', 'array'], true);

        $status = $isLogLike
            ? ($requiresLiveGrade ? 'fail' : ($requiresPilotGrade ? 'warning' : 'ok'))
            : 'ok';

        return [
            'key' => 'mailer',
            'label' => 'Canal email reel',
            'status' => $status,
            'message' => $isLogLike
                ? 'Le mailer '.$mailer.' ne permet pas encore les notifications reelles.'
                : 'Le mailer '.$mailer.' est pret pour les notifications reelles.',
            'action' => 'Configurer un SMTP reel avant ouverture pilote ou production.',
        ];
    }

    private function storageItem(bool $requiresPilotGrade, bool $requiresCloudGrade): array
    {
        $productDisk = (string) config('nema.product_media_disk', 'public');
        $documentDisk = (string) config('nema.document_attachment_disk', 'public');
        $objectReady = $productDisk === 's3' && $documentDisk === 's3';

        $status = $objectReady
            ? 'ok'
            : ($requiresCloudGrade ? 'fail' : ($requiresPilotGrade ? 'warning' : 'ok'));

        return [
            'key' => 'object_storage',
            'label' => 'Stockage metier persistant',
            'status' => $status,
            'message' => 'Images produit sur '.$productDisk.' · pieces jointes sur '.$documentDisk.'.',
            'action' => 'Basculer les disques metier sur un stockage objet avant une exploitation cloud multi-client.',
        ];
    }

    private function releaseItem(DeploymentProfile $profile, bool $requiresLiveGrade): array
    {
        $status = 'ok';
        $message = 'La cadence de release est tracee.';

        if ($requiresLiveGrade && blank($profile->last_release_at)) {
            $status = 'warning';
            $message = 'Aucune date de release recente n est renseignee.';
        } elseif ($profile->last_release_at && $profile->last_release_at->lt(now()->subDays(45))) {
            $status = 'warning';
            $message = 'La derniere release remonte a plus de 45 jours.';
        }

        return [
            'key' => 'release_hygiene',
            'label' => 'Discipline de release',
            'status' => $status,
            'message' => $message,
            'action' => 'Renseigner la derniere release et garder un rythme de mise a jour suivi pour les clients actifs.',
        ];
    }

    private function restoreDrillItem(DeploymentProfile $profile, bool $requiresPilotGrade, bool $requiresLiveGrade): array
    {
        $status = 'ok';
        $message = 'Le dernier exercice de restauration est documente.';

        if (blank($profile->last_restore_drill_at)) {
            $status = $requiresLiveGrade ? 'fail' : ($requiresPilotGrade ? 'warning' : 'ok');
            $message = 'Aucun exercice de restauration n est renseigne.';
        } elseif ($profile->last_restore_drill_at->lt(now()->subDays(90))) {
            $status = $requiresLiveGrade ? 'warning' : ($requiresPilotGrade ? 'warning' : 'ok');
            $message = 'Le dernier exercice de restauration date de plus de 90 jours.';
        }

        return [
            'key' => 'restore_drill',
            'label' => 'Exercice de restauration',
            'status' => $status,
            'message' => $message,
            'action' => 'Realiser un exercice de restauration et renseigner sa date avant l ouverture client.',
        ];
    }
}
