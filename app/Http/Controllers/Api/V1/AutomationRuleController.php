<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesApiActor;
use App\Modules\Core\Automation\Models\AutomationRule;
use App\Modules\Core\Automation\Services\AutomationEngineService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AutomationRuleController
{
    use ResolvesApiActor;

    public function __construct(private readonly AutomationEngineService $automationEngineService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('automation.view'), 403);

        $status = $request->string('status')->trim()->value();
        $signalKey = $request->string('signal_key')->trim()->value();
        $search = $request->string('search')->trim()->value();

        $rules = AutomationRule::query()
            ->with(['branch', 'owner', 'latestExecution.notification'])
            ->where('company_id', $company->id)
            ->when(in_array($status, ['draft', 'active', 'paused'], true), fn (Builder $query) => $query->where('status', $status))
            ->when(array_key_exists($signalKey, $this->automationEngineService->signalDefinitions()), fn (Builder $query) => $query->where('signal_key', $signalKey))
            ->when($search !== '', function (Builder $query) use ($search) {
                $like = '%'.$search.'%';
                $query->where(function (Builder $nested) use ($like) {
                    $nested->where('code', 'like', $like)
                        ->orWhere('name', 'like', $like);
                });
            })
            ->orderBy('status')
            ->orderBy('name')
            ->paginate(min(max((int) $request->integer('per_page', 50), 1), 200));

        $definitions = $this->automationEngineService->signalDefinitions();

        return response()->json([
            'catalog' => [
                'signals' => collect($definitions)->map(fn (array $definition, string $key): array => array_merge($definition, ['key' => $key]))->values()->all(),
                'statuses' => $this->automationEngineService->statusOptions(),
                'severities' => $this->automationEngineService->severityOptions(),
                'actions' => $this->automationEngineService->actionTypeOptions(),
            ],
            'data' => $rules->items(),
            'meta' => [
                'current_page' => $rules->currentPage(),
                'last_page' => $rules->lastPage(),
                'per_page' => $rules->perPage(),
                'total' => $rules->total(),
            ],
        ]);
    }

    public function show(Request $request, AutomationRule $automationRule): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        abort_unless($automationRule->company_id === $company->id, 404);

        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('automation.view'), 403);

        return response()->json(
            $automationRule->load(['branch', 'owner', 'executions.notification'])
        );
    }

    public function store(Request $request): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('automation.manage'), 403);

        $rule = $this->automationEngineService->createRule(
            $company->id,
            $this->validatedPayload($request, $company->id),
            $actor,
        );

        return response()->json($rule->load(['branch', 'owner', 'latestExecution.notification']), 201);
    }

    public function update(Request $request, AutomationRule $automationRule): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        abort_unless($automationRule->company_id === $company->id, 404);

        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('automation.manage'), 403);

        $rule = $this->automationEngineService->updateRule(
            $automationRule,
            $this->validatedPayload($request, $company->id, $automationRule),
            $actor,
        );

        return response()->json($rule);
    }

    public function run(Request $request, AutomationRule $automationRule): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        abort_unless($automationRule->company_id === $company->id, 404);

        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('automation.manage'), 403);

        $execution = $this->automationEngineService->runRule($automationRule);

        return response()->json($execution->load(['rule', 'notification']));
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
