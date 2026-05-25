<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesApiActor;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectTask;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectController
{
    use ResolvesApiActor;

    public function index(Request $request): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('projects.view'), 403);

        $status = $request->string('status')->trim()->value();
        $search = $request->string('search')->trim()->value();

        $projects = Project::query()
            ->with(['branch', 'owner', 'tasks'])
            ->where('company_id', $company->id)
            ->when($request->integer('owner_id') > 0, fn (Builder $query) => $query->where('owner_id', $request->integer('owner_id')))
            ->when($request->integer('branch_id') > 0, fn (Builder $query) => $query->where('branch_id', $request->integer('branch_id')))
            ->when(in_array($status, ['planning', 'active', 'at_risk', 'complete'], true), fn (Builder $query) => $query->where('status', $status))
            ->when($search !== '', function (Builder $query) use ($search) {
                $like = '%'.$search.'%';

                $query->where(function (Builder $nested) use ($like) {
                    $nested->where('code', 'like', $like)
                        ->orWhere('name', 'like', $like)
                        ->orWhere('customer_name', 'like', $like);
                });
            })
            ->orderByDesc('start_date')
            ->paginate(min(max((int) $request->integer('per_page', 50), 1), 200));

        $projects->getCollection()->transform(function (Project $project) {
            $project->setAttribute('execution_summary', $this->executionSummary($project->tasks));

            return $project;
        });

        return response()->json($projects);
    }

    public function show(Request $request, Project $project): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        abort_unless($project->company_id === $company->id, 404);

        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('projects.view'), 403);

        return response()->json(
            $project->load(['branch', 'owner', 'creator', 'tasks.owner'])
                ->setAttribute('execution_summary', $this->executionSummary($project->tasks))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('projects.manage'), 403);

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:30', Rule::unique('projects', 'code')->where(fn ($query) => $query->where('company_id', $company->id))],
            'name' => ['required', 'string', 'max:255'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'owner_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->where('company_id', $company->id))],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where(fn ($query) => $query->where('company_id', $company->id))],
            'start_date' => ['required', 'date'],
            'target_end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', Rule::in(['planning', 'active', 'at_risk', 'complete'])],
            'progress' => ['nullable', 'integer', 'between:0,100'],
            'budget_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $project = Project::query()->create([
            'company_id' => $company->id,
            'branch_id' => $data['branch_id'] ?? null,
            'code' => ($data['code'] ?? null) ?: $this->generateCode($company->id),
            'name' => $data['name'],
            'customer_name' => $data['customer_name'] ?? null,
            'owner_id' => $data['owner_id'] ?? null,
            'start_date' => $data['start_date'],
            'target_end_date' => $data['target_end_date'] ?? null,
            'status' => $data['status'] ?? 'planning',
            'progress' => (int) ($data['progress'] ?? 0),
            'budget_amount' => $data['budget_amount'] ?? 0,
            'notes' => $data['notes'] ?? null,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        return response()->json($project->load(['branch', 'owner']), 201);
    }

    public function storeTask(Request $request, Project $project): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        abort_unless($project->company_id === $company->id, 404);

        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('projects.manage'), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'item_type' => ['required', Rule::in(['task', 'milestone'])],
            'owner_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->where('company_id', $company->id))],
            'due_date' => ['nullable', 'date', 'after_or_equal:'.$project->start_date?->toDateString()],
            'status' => ['nullable', Rule::in(['todo', 'in_progress', 'blocked', 'done'])],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'critical'])],
            'progress' => ['nullable', 'integer', 'between:0,100'],
            'notes' => ['nullable', 'string'],
        ]);

        $normalized = $this->normalizeTaskPayload($data);

        $task = $project->tasks()->create([
            'company_id' => $company->id,
            'owner_id' => $normalized['owner_id'],
            'title' => $normalized['title'],
            'item_type' => $normalized['item_type'],
            'status' => $normalized['status'],
            'priority' => $normalized['priority'],
            'progress' => $normalized['progress'],
            'due_date' => $normalized['due_date'],
            'completed_at' => $normalized['completed_at'],
            'notes' => $normalized['notes'],
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        $this->refreshProjectProgress($project->fresh('tasks'), $actor->id);

        return response()->json($task->load(['owner', 'project']), 201);
    }

    public function updateTask(Request $request, Project $project, ProjectTask $projectTask): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        abort_unless($project->company_id === $company->id && $projectTask->company_id === $company->id && $projectTask->project_id === $project->id, 404);

        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('projects.manage'), 403);

        $data = $request->validate([
            'status' => ['required', Rule::in(['todo', 'in_progress', 'blocked', 'done'])],
            'progress' => ['nullable', 'integer', 'between:0,100'],
        ]);

        $normalized = $this->normalizeTaskPayload([
            'title' => $projectTask->title,
            'item_type' => $projectTask->item_type,
            'owner_id' => $projectTask->owner_id,
            'due_date' => optional($projectTask->due_date)->toDateString(),
            'notes' => $projectTask->notes,
            'priority' => $projectTask->priority,
            'status' => $data['status'],
            'progress' => $data['progress'] ?? $projectTask->progress,
        ]);

        $projectTask->update([
            'status' => $normalized['status'],
            'progress' => $normalized['progress'],
            'completed_at' => $normalized['completed_at'],
            'updated_by' => $actor->id,
        ]);

        $this->refreshProjectProgress($project->fresh('tasks'), $actor->id);

        return response()->json($projectTask->fresh(['owner', 'project']));
    }

    private function executionSummary(Collection $tasks): array
    {
        $open = $tasks->where('status', '!=', 'done');
        $overdue = $open->filter(fn (ProjectTask $task) => $task->due_date && $task->due_date->isPast());
        $milestones = $tasks->filter(fn (ProjectTask $task) => $task->item_type === 'milestone' && $task->status !== 'done');

        return [
            'total' => $tasks->count(),
            'open' => $open->count(),
            'done' => $tasks->where('status', 'done')->count(),
            'overdue' => $overdue->count(),
            'milestones_open' => $milestones->count(),
        ];
    }

    private function normalizeTaskPayload(array $payload): array
    {
        $status = $payload['status'] ?? 'todo';
        $progress = (int) ($payload['progress'] ?? 0);

        if ($status === 'done') {
            $progress = 100;
        } elseif ($status === 'todo' && $progress > 20) {
            $progress = 20;
        } elseif ($status === 'in_progress' && $progress === 0) {
            $progress = 40;
        }

        return [
            'title' => trim((string) $payload['title']),
            'item_type' => $payload['item_type'],
            'owner_id' => $payload['owner_id'] ?? null,
            'due_date' => $payload['due_date'] ?? null,
            'status' => $status,
            'priority' => $payload['priority'] ?? 'normal',
            'progress' => $progress,
            'completed_at' => $status === 'done' ? now() : null,
            'notes' => $payload['notes'] ?? null,
        ];
    }

    private function refreshProjectProgress(Project $project, ?int $actorId = null): void
    {
        $tasks = $project->tasks instanceof Collection ? $project->tasks : $project->tasks()->get();
        if ($tasks->isEmpty()) {
            return;
        }

        $progress = (int) round($tasks->avg(fn (ProjectTask $task) => $task->progress));
        $status = $project->status;

        if ($tasks->every(fn (ProjectTask $task) => $task->status === 'done')) {
            $status = 'complete';
            $progress = 100;
        } elseif ($status === 'complete' && $tasks->contains(fn (ProjectTask $task) => $task->status !== 'done')) {
            $status = 'active';
        } elseif ($status === 'planning' && $tasks->contains(fn (ProjectTask $task) => in_array($task->status, ['in_progress', 'blocked', 'done'], true))) {
            $status = 'active';
        } elseif ($tasks->contains(fn (ProjectTask $task) => ! $task->isDone() && $task->due_date && $task->due_date->isPast())) {
            $status = 'at_risk';
        }

        $project->update([
            'progress' => $progress,
            'status' => $status,
            'updated_by' => $actorId ?? $project->updated_by,
        ]);
    }

    private function generateCode(int $companyId): string
    {
        return app(\App\Modules\Core\Company\Services\DocumentNumberService::class)
            ->nextNumber($companyId, 'project_code');
    }
}
