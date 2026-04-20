<?php

namespace App\Modules\Core\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Notifications\Services\OutboundNotificationService;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OutboundNotificationController extends Controller
{
    public function __construct(
        private readonly OutboundNotificationService $outboundNotificationService,
        private readonly ActivityLogger $activityLogger,
    ) {
    }

    public function index(Request $request, CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $channel = $request->string('channel')->value() ?: null;
        if (! in_array($channel, ['email', 'whatsapp'], true)) {
            $channel = null;
        }

        $status = $request->string('status')->value() ?: null;
        if (! in_array($status, ['queued', 'sent', 'failed', 'cancelled'], true)) {
            $status = null;
        }

        return view('notifications.outbound', [
            'notifications' => $this->outboundNotificationService
                ->indexQuery($companyId, $channel, $status)
                ->paginate(20)
                ->withQueryString(),
            'summary' => $this->outboundNotificationService->summaryForCompany($companyId),
            'filters' => [
                'channel' => $channel,
                'status' => $status,
            ],
        ]);
    }

    public function process(CurrentWorkspace $workspace): RedirectResponse
    {
        $company = $workspace->company();
        abort_if(! $company, 403);

        $summary = $this->outboundNotificationService->processQueued($company->id, 50);

        $this->activityLogger->log('notifications.outbound.process', 'Traitement file notifications sortantes', $company, $summary);

        $message = $summary['processed'] > 0
            ? $summary['sent'].' notification(s) envoyee(s), '.$summary['failed'].' en echec.'
            : 'Aucune notification en attente a traiter.';

        return redirect()->route('notifications.outbound.index')->with('success', $message);
    }

    public function retryFailed(CurrentWorkspace $workspace): RedirectResponse
    {
        $company = $workspace->company();
        abort_if(! $company, 403);

        $count = $this->outboundNotificationService->requeueFailed($company->id, 50);

        $this->activityLogger->log('notifications.outbound.retry_failed', 'Reprogrammation notifications sortantes en echec', $company, [
            'requeued' => $count,
        ]);

        $message = $count > 0
            ? $count.' notification(s) reprogrammee(s) dans la file.'
            : 'Aucune notification en echec a reprogrammer.';

        return redirect()->route('notifications.outbound.index')->with('success', $message);
    }
}
