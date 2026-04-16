<?php

namespace App\Modules\Core\Automation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Core\Automation\Models\AutomationRule;
use App\Modules\Core\Automation\Services\AutomationEngineService;
use App\Modules\Core\Branch\Models\Branch;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AutomationController extends Controller
{
    public function __construct(
        private readonly AutomationEngineService $automationEngineService,
        private readonly ActivityLogger $activityLogger,
    ) {
    }

    public function index(CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        return view('automation.index', [
            'catalog' => $this->automationEngineService->summary($companyId),
            'branches' => Branch::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'owners' => User::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'signalDefinitions' => $this->automationEngineService->signalDefinitions(),
            'statusOptions' => $this->automationEngineService->statusOptions(),
            'severityOptions' => $this->automationEngineService->severityOptions(),
            'actionTypeOptions' => $this->automationEngineService->actionTypeOptions(),
        ]);
    }

    public function store(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $rule = $this->automationEngineService->createRule(
            $companyId,
            $this->validatedPayload($request, $companyId),
            $request->user(),
        );

        $this->activityLogger->log('automation.rules.create', 'Creation regle automatisation', $rule, [
            'code' => $rule->code,
            'signal_key' => $rule->signal_key,
            'status' => $rule->status,
        ]);

        return redirect()->route('automation.index')->with('success', 'Regle d automatisation enregistree.');
    }

    public function update(Request $request, CurrentWorkspace $workspace, AutomationRule $automationRule): RedirectResponse
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId || $automationRule->company_id !== $companyId, 403);

        $rule = $this->automationEngineService->updateRule(
            $automationRule,
            $this->validatedPayload($request, $companyId, $automationRule),
            $request->user(),
        );

        $this->activityLogger->log('automation.rules.update', 'Mise a jour regle automatisation', $rule, [
            'code' => $rule->code,
            'signal_key' => $rule->signal_key,
            'status' => $rule->status,
        ]);

        return redirect()->route('automation.index')->with('success', 'Regle d automatisation mise a jour.');
    }

    public function run(CurrentWorkspace $workspace, AutomationRule $automationRule): RedirectResponse
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId || $automationRule->company_id !== $companyId, 403);

        $execution = $this->automationEngineService->runRule($automationRule);

        $this->activityLogger->log('automation.rules.run', 'Execution manuelle regle automatisation', $automationRule, [
            'execution_id' => $execution->id,
            'matched' => $execution->matched,
            'observed_value' => $execution->observed_value,
            'status' => $execution->status,
        ]);

        return redirect()->route('automation.index')->with(
            $execution->matched ? 'success' : 'info',
            $execution->matched
                ? 'Regle executee avec signal detecte.'
                : 'Regle executee sans signal critique.'
        );
    }

    public function runAll(CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $summary = $this->automationEngineService->runActiveRulesForCompany($companyId);

        return redirect()->route('automation.index')->with(
            'success',
            sprintf(
                'Automatisations executees: %d regle(s), %d signal(s), %d en cooldown.',
                (int) $summary['rules'],
                (int) $summary['matched'],
                (int) $summary['cooldown'],
            )
        );
    }

    private function validatedPayload(Request $request, int $companyId, ?AutomationRule $rule = null): array
    {
        return $request->validate([
            'code' => [
                'nullable',
                'string',
                'max:40',
                Rule::unique('automation_rules', 'code')
                    ->ignore($rule?->id)
                    ->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'name' => ['required', 'string', 'max:255'],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'owner_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'signal_key' => ['required', Rule::in(array_keys($this->automationEngineService->signalDefinitions()))],
            'status' => ['required', Rule::in(array_keys($this->automationEngineService->statusOptions()))],
            'severity' => ['required', Rule::in(array_keys($this->automationEngineService->severityOptions()))],
            'action_type' => ['required', Rule::in(array_keys($this->automationEngineService->actionTypeOptions()))],
            'threshold_value' => ['required', 'integer', 'min:1', 'max:100000'],
            'window_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
            'cooldown_minutes' => ['nullable', 'integer', 'min:0', 'max:10080'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
