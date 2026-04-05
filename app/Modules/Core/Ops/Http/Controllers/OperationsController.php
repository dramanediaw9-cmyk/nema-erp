<?php

namespace App\Modules\Core\Ops\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Core\Integrations\Models\IntegrationEvent;
use App\Modules\Core\Integrations\Models\IntegrationEventDelivery;
use App\Modules\Core\Integrations\Services\IntegrationOutboxService;
use App\Modules\Core\Ops\Models\SystemHealthSnapshot;
use App\Modules\Core\Ops\Services\ApplicationMonitoringService;
use App\Modules\Core\Ops\Services\BackupService;
use App\Modules\Core\Ops\Services\SystemHealthService;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OperationsController extends Controller
{
    public function __construct(
        private readonly SystemHealthService $systemHealthService,
        private readonly IntegrationOutboxService $integrationOutboxService,
        private readonly BackupService $backupService,
        private readonly ApplicationMonitoringService $applicationMonitoringService,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function index(CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $company = Company::query()->findOrFail($companyId);
        $report = $this->systemHealthService->report($company);

        return view('ops.index', [
            'report' => $report,
            'snapshots' => SystemHealthSnapshot::query()
                ->where('company_id', $company->id)
                ->latest('captured_at')
                ->limit(12)
                ->get(),
            'outboxEvents' => IntegrationEvent::query()
                ->with('latestDelivery')
                ->where('company_id', $company->id)
                ->orderByRaw("CASE status WHEN 'failed' THEN 0 WHEN 'pending' THEN 1 ELSE 2 END")
                ->latest('id')
                ->limit(20)
                ->get(),
            'deliveryLogs' => IntegrationEventDelivery::query()
                ->with('event')
                ->where('company_id', $company->id)
                ->latest('id')
                ->limit(20)
                ->get(),
            'outboxSummary' => $this->systemHealthService->outboxSummary($company),
            'outboxWebhook' => $this->integrationOutboxService->configurationForCompany($company->id),
            'backupVerification' => $this->backupService->verify(),
            'backupRestorePreview' => $this->backupService->restorePreview(),
            'appMonitoring' => $this->applicationMonitoringService->summary(),
        ]);
    }

    public function processOutbox(CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $company = Company::query()->findOrFail($companyId);
        $summary = $this->integrationOutboxService->processPending($company->id, 50);

        $this->activityLogger->log('ops.outbox.process', 'Traitement manuel outbox', $company, $summary);

        return back()->with('success', sprintf(
            '%d evenement(s) traite(s), %d publie(s), %d echec(s), %d ignore(s).',
            (int) $summary['processed'],
            (int) $summary['published'],
            (int) $summary['failed'],
            (int) $summary['skipped'],
        ));
    }

    public function retryOutboxEvent(IntegrationEvent $integrationEvent, CurrentWorkspace $workspace): RedirectResponse
    {
        abort_if($workspace->companyId() !== $integrationEvent->company_id, 403);

        $this->systemHealthService->retryOutboxEvent($integrationEvent);
        $this->activityLogger->log('ops.outbox.retry', 'Reprogrammation evenement outbox', $integrationEvent, [
            'event_id' => $integrationEvent->id,
            'event_name' => $integrationEvent->event_name,
        ]);

        return back()->with('success', 'Evenement outbox reprogramme avec succes.');
    }

    public function retryFailedOutbox(CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $company = Company::query()->findOrFail($companyId);
        $count = $this->systemHealthService->retryFailedOutbox($company);

        $this->activityLogger->log('ops.outbox.retry_failed', 'Reprogrammation batch outbox', $company, [
            'requeued' => $count,
        ]);

        return back()->with('success', $count > 0 ? $count.' evenement(s) outbox reprogramme(s).' : 'Aucun evenement outbox en echec a relancer.');
    }
}
