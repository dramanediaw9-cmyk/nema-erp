<?php

namespace App\Modules\Core\Approvals\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Core\Approvals\Models\ApprovalStep;
use App\Modules\Core\Approvals\Services\ApprovalFlowService;
use App\Modules\Core\Notifications\Services\OutboundNotificationService;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ApprovalStepController extends Controller
{
    public function __construct(
        private readonly ApprovalFlowService $approvalFlowService,
        private readonly OutboundNotificationService $outboundNotificationService,
        private readonly ActivityLogger $activityLogger,
    ) {
    }

    public function delegate(ApprovalStep $approvalStep, CurrentWorkspace $workspace, Request $request): RedirectResponse
    {
        abort_if($workspace->companyId() !== $approvalStep->company_id, 403);

        $data = $request->validate([
            'delegate_to' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('company_id', $approvalStep->company_id)->where('is_active', true)),
            ],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $delegateTo = User::query()
            ->where('company_id', $approvalStep->company_id)
            ->where('is_active', true)
            ->findOrFail((int) $data['delegate_to']);

        $delegatedStep = $this->approvalFlowService->delegate(
            $approvalStep,
            $request->user(),
            $delegateTo,
            $data['note'] ?? null,
        );

        $document = $delegatedStep->approvable;
        if ($document) {
            $this->outboundNotificationService->cancelQueuedForApprovalStep($document, (int) $delegatedStep->step_order, 'Etape reaffectee vers un autre valideur.');
            $this->outboundNotificationService->dispatchApprovalRequest($document, $delegatedStep->module, $delegatedStep);

            $this->activityLogger->log('approvals.delegate', 'Delegation d etape d approbation', $document, [
                'approval_step_id' => $delegatedStep->id,
                'step_order' => $delegatedStep->step_order,
                'module' => $delegatedStep->module,
                'delegated_by' => $request->user()->id,
                'delegate_to' => $delegateTo->id,
            ]);
        }

        return back()->with('success', 'Etape deleguee a '.$delegateTo->name.'.');
    }
}
