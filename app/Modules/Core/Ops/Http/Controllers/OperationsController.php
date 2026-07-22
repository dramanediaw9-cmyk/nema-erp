<?php

namespace App\Modules\Core\Ops\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\OpsTestMail;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Core\Integrations\Models\IntegrationConnection;
use App\Modules\Core\Integrations\Models\IntegrationEvent;
use App\Modules\Core\Integrations\Models\IntegrationEventDelivery;
use App\Modules\Core\Integrations\Services\IntegrationOutboxService;
use App\Modules\Core\Integrations\Services\IntegrationSecretGovernanceService;
use App\Modules\Core\Ops\Models\SystemHealthSnapshot;
use App\Modules\Core\Ops\Services\ApplicationMonitoringService;
use App\Modules\Core\Ops\Services\BackupService;
use App\Modules\Core\Ops\Services\CoreNucleusHealthService;
use App\Modules\Core\Ops\Services\SystemHealthService;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class OperationsController extends Controller
{
    public function __construct(
        private readonly SystemHealthService $systemHealthService,
        private readonly IntegrationOutboxService $integrationOutboxService,
        private readonly IntegrationSecretGovernanceService $integrationSecretGovernanceService,
        private readonly BackupService $backupService,
        private readonly ApplicationMonitoringService $applicationMonitoringService,
        private readonly CoreNucleusHealthService $coreNucleusHealthService,
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
            'coreNucleus' => $this->coreNucleusHealthService->report($company),
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
            'integrationConnections' => IntegrationConnection::query()
                ->with(['branch', 'owner', 'secretOwner'])
                ->where('company_id', $company->id)
                ->orderByRaw("CASE health_status WHEN 'critical' THEN 0 WHEN 'watch' THEN 1 ELSE 2 END")
                ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'paused' THEN 1 ELSE 2 END")
                ->orderBy('partner_name')
                ->limit(12)
                ->get(),
            'secretGovernance' => $this->integrationSecretGovernanceService->summary(
                IntegrationConnection::query()
                    ->with(['branch', 'owner', 'secretOwner'])
                    ->where('company_id', $company->id)
                    ->orderBy('partner_name')
                    ->get()
            ),
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

    public function sendTestEmail(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $company = Company::query()->findOrFail($companyId);
        $payload = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'subject' => ['nullable', 'string', 'max:120'],
        ]);

        if (config('mail.default') === 'log') {
            return back()->withErrors([
                'mail_test' => 'Le mailer actif est encore en mode log. Configure un SMTP reel dans Laravel Cloud avant de lancer ce test.',
            ]);
        }

        $recipient = strtolower(trim((string) $payload['email']));
        $subject = trim((string) ($payload['subject'] ?? ''));
        $subject = $subject !== '' ? $subject : 'Test email - '.$company->name;

        Mail::to($recipient)->send(new OpsTestMail(
            companyName: $company->name,
            recipient: $recipient,
            subjectLine: $subject,
            sentBy: (string) optional($request->user())->email,
        ));

        $this->activityLogger->log('ops.mail.test', 'Envoi email de test', $company, [
            'recipient' => $recipient,
            'subject' => $subject,
            'mailer' => config('mail.default'),
        ]);

        return back()->with('success', 'Email de test envoye vers '.$recipient.'.');
    }
}
