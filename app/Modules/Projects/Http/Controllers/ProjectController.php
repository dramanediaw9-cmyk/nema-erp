<?php

namespace App\Modules\Projects\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Projects\Models\Project;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
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
            'projects' => Project::query()
                ->with(['branch', 'owner'])
                ->where('company_id', $companyId)
                ->orderByDesc('start_date')
                ->orderByDesc('id')
                ->get(),
            'summary' => [
                'projects' => (int) Project::query()->where('company_id', $companyId)->count(),
                'active' => (int) Project::query()->where('company_id', $companyId)->whereIn('status', ['planning', 'active', 'at_risk'])->count(),
                'at_risk' => (int) Project::query()->where('company_id', $companyId)->where('status', 'at_risk')->count(),
                'budget' => (float) Project::query()->where('company_id', $companyId)->sum('budget_amount'),
            ],
            'statusOptions' => $this->statusOptions(),
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

    private function statusOptions(): array
    {
        return [
            'planning' => 'Planification',
            'active' => 'Actif',
            'at_risk' => 'Sous tension',
            'complete' => 'Cloture',
        ];
    }

    private function generateProjectCode(int $companyId): string
    {
        $sequence = Project::query()->where('company_id', $companyId)->count() + 1;

        return 'PRJ-'.now()->format('Y').'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
