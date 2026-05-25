<?php

namespace App\Modules\Projects\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectTask;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use Illuminate\Support\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger)
    {
    }

    public function index(CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $projects = Project::query()
            ->with(['branch', 'owner', 'tasks.owner'])
            ->where('company_id', $companyId)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get()
            ->each(fn (Project $project) => $project->setAttribute('execution_snapshot', $this->executionSnapshot($project)));

        return view('projects.index', [
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
            'projects' => $projects,
            'summary' => [
                'projects' => (int) Project::query()->where('company_id', $companyId)->count(),
                'active' => (int) Project::query()->where('company_id', $companyId)->whereIn('status', ['planning', 'active', 'at_risk'])->count(),
                'at_risk' => (int) Project::query()->where('company_id', $companyId)->where('status', 'at_risk')->count(),
                'budget' => (float) Project::query()->where('company_id', $companyId)->sum('budget_amount'),
                'open_tasks' => (int) ProjectTask::query()->where('company_id', $companyId)->where('status', '!=', 'done')->count(),
                'overdue_tasks' => (int) ProjectTask::query()->where('company_id', $companyId)->where('status', '!=', 'done')->whereDate('due_date', '<', now()->toDateString())->count(),
                'milestones_due' => (int) ProjectTask::query()->where('company_id', $companyId)->where('item_type', 'milestone')->where('status', '!=', 'done')->whereBetween('due_date', [now()->toDateString(), now()->addDays(7)->toDateString()])->count(),
            ],
            'statusOptions' => $this->statusOptions(),
            'taskStatusOptions' => $this->taskStatusOptions(),
            'taskPriorityOptions' => $this->taskPriorityOptions(),
            'itemTypeOptions' => $this->itemTypeOptions(),
        ]);
    }

    public function store(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $payload = $request->validate([
            'code' => ['nullable', 'string', 'max:30', Rule::unique('projects', 'code')->where(fn ($query) => $query->where('company_id', $companyId))],
            'name' => ['required', 'string', 'max:255'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'owner_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'start_date' => ['required', 'date'],
            'target_end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(array_keys($this->statusOptions()))],
            'progress' => ['nullable', 'integer', 'between:0,100'],
            'budget_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $project = Project::query()->create([
            'company_id' => $companyId,
            'branch_id' => $payload['branch_id'] ?? null,
            'code' => ($payload['code'] ?? null) ?: $this->generateProjectCode($companyId),
            'name' => $payload['name'],
            'customer_name' => $payload['customer_name'] ?? null,
            'owner_id' => $payload['owner_id'] ?? null,
            'start_date' => $payload['start_date'],
            'target_end_date' => $payload['target_end_date'] ?? null,
            'status' => $payload['status'],
            'progress' => (int) ($payload['progress'] ?? 0),
            'budget_amount' => $payload['budget_amount'] ?? 0,
            'notes' => $payload['notes'] ?? null,
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        $this->activityLogger->log('projects.create', 'Creation projet execution', $project, [
            'code' => $project->code,
            'status' => $project->status,
        ]);

        return redirect()->route('projects.index')->with('success', 'Projet enregistre avec succes.');
    }

    public function storeTask(Request $request, CurrentWorkspace $workspace, Project $project): RedirectResponse
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId || $project->company_id !== $companyId, 403);

        $payload = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'item_type' => ['required', Rule::in(array_keys($this->itemTypeOptions()))],
            'owner_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'due_date' => ['nullable', 'date', 'after_or_equal:'.$project->start_date?->toDateString()],
            'status' => ['required', Rule::in(array_keys($this->taskStatusOptions()))],
            'priority' => ['required', Rule::in(array_keys($this->taskPriorityOptions()))],
            'progress' => ['nullable', 'integer', 'between:0,100'],
            'notes' => ['nullable', 'string'],
        ]);

        $normalized = $this->normalizeTaskPayload($payload);

        $task = $project->tasks()->create([
            'company_id' => $companyId,
            'owner_id' => $normalized['owner_id'],
            'title' => $normalized['title'],
            'item_type' => $normalized['item_type'],
            'status' => $normalized['status'],
            'priority' => $normalized['priority'],
            'progress' => $normalized['progress'],
            'due_date' => $normalized['due_date'],
            'completed_at' => $normalized['completed_at'],
            'notes' => $normalized['notes'],
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        $this->refreshProjectProgress($project->fresh('tasks'), $request->user()?->id);
        $this->activityLogger->log('projects.tasks.create', 'Creation jalon ou tache projet', $task, [
            'project_id' => $project->id,
            'project_code' => $project->code,
            'item_type' => $task->item_type,
            'status' => $task->status,
        ]);

        return redirect()->route('projects.index')->with('success', 'Element d execution ajoute au projet.');
    }

    public function updateTaskStatus(Request $request, CurrentWorkspace $workspace, ProjectTask $projectTask): RedirectResponse
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId || $projectTask->company_id !== $companyId, 403);

        $payload = $request->validate([
            'status' => ['required', Rule::in(array_keys($this->taskStatusOptions()))],
            'progress' => ['nullable', 'integer', 'between:0,100'],
        ]);

        $normalized = $this->normalizeTaskPayload([
            'title' => $projectTask->title,
            'item_type' => $projectTask->item_type,
            'owner_id' => $projectTask->owner_id,
            'due_date' => optional($projectTask->due_date)->toDateString(),
            'notes' => $projectTask->notes,
            'priority' => $projectTask->priority,
            'status' => $payload['status'],
            'progress' => $payload['progress'] ?? $projectTask->progress,
        ]);

        $projectTask->update([
            'status' => $normalized['status'],
            'progress' => $normalized['progress'],
            'completed_at' => $normalized['completed_at'],
            'updated_by' => $request->user()?->id,
        ]);

        $this->refreshProjectProgress($projectTask->project()->with('tasks')->firstOrFail(), $request->user()?->id);
        $this->activityLogger->log('projects.tasks.status', 'Mise a jour execution projet', $projectTask, [
            'project_id' => $projectTask->project_id,
            'status' => $projectTask->status,
            'progress' => $projectTask->progress,
        ]);

        return redirect()->route('projects.index')->with('success', 'Statut d execution mis a jour.');
    }

    private function statusOptions(): array
    {
        return [
            'planning' => 'Planification',
            'active' => 'Actif',
            'at_risk' => 'Sous tension',
            'complete' => 'Cloture',
        ];
    }

    private function taskStatusOptions(): array
    {
        return [
            'todo' => 'A lancer',
            'in_progress' => 'En cours',
            'blocked' => 'Bloque',
            'done' => 'Termine',
        ];
    }

    private function taskPriorityOptions(): array
    {
        return [
            'low' => 'Faible',
            'normal' => 'Normale',
            'high' => 'Haute',
            'critical' => 'Critique',
        ];
    }

    private function itemTypeOptions(): array
    {
        return [
            'task' => 'Tache',
            'milestone' => 'Jalon',
        ];
    }

    private function executionSnapshot(Project $project): array
    {
        $tasks = $project->tasks instanceof Collection ? $project->tasks : collect();
        $open = $tasks->where('status', '!=', 'done');
        $overdue = $open->filter(fn (ProjectTask $task) => $task->due_date && $task->due_date->isPast());
        $milestonesDue = $tasks->filter(fn (ProjectTask $task) => $task->item_type === 'milestone' && $task->status !== 'done' && $task->due_date && $task->due_date->between(now()->startOfDay(), now()->copy()->addDays(7)->endOfDay()));
        $done = $tasks->where('status', 'done');
        $health = 'stable';
        $healthLabel = 'Sous controle';

        if ($overdue->isNotEmpty() || ($project->target_end_date && $project->target_end_date->isPast() && $project->status !== 'complete')) {
            $health = 'risk';
            $healthLabel = 'Sous tension';
        } elseif ($milestonesDue->isNotEmpty()) {
            $health = 'watch';
            $healthLabel = 'Jalon proche';
        } elseif ($tasks->isNotEmpty() && $done->count() === $tasks->count()) {
            $health = 'done';
            $healthLabel = 'Execution terminee';
        }

        return [
            'total' => $tasks->count(),
            'open' => $open->count(),
            'done' => $done->count(),
            'overdue' => $overdue->count(),
            'milestones_due' => $milestonesDue->count(),
            'health' => $health,
            'health_label' => $healthLabel,
        ];
    }

    private function normalizeTaskPayload(array $payload): array
    {
        $status = $payload['status'];
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
            'priority' => $payload['priority'],
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
        } elseif ($tasks->contains(fn (ProjectTask $task) => $task->isOverdue())) {
            $status = 'at_risk';
        }

        $project->update([
            'progress' => $progress,
            'status' => $status,
            'updated_by' => $actorId ?? $project->updated_by,
        ]);
    }

    private function generateProjectCode(int $companyId): string
    {
        return app(\App\Modules\Core\Company\Services\DocumentNumberService::class)
            ->nextNumber($companyId, 'project_code');
    }
}
