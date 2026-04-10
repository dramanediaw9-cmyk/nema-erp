<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesApiActor;
use App\Modules\Projects\Models\Project;
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
            ->with(['branch', 'owner'])
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

        return response()->json($projects);
    }

    public function show(Request $request, Project $project): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        abort_unless($project->company_id === $company->id, 404);

        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('projects.view'), 403);

        return response()->json($project->load(['branch', 'owner', 'creator']));
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

    private function generateCode(int $companyId): string
    {
        $number = Project::query()->where('company_id', $companyId)->count() + 1;

        return 'PRJ-'.now()->format('Y').'-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }
}
