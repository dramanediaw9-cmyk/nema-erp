<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesApiActor;
use App\Modules\Hr\Models\HrDepartment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HrDepartmentController
{
    use ResolvesApiActor;

    public function index(Request $request): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('hr.view'), 403);

        $search = $request->string('search')->trim()->value();
        $status = $request->string('status')->trim()->value();

        $departments = HrDepartment::query()
            ->with(['branch', 'employees'])
            ->where('company_id', $company->id)
            ->when($request->integer('branch_id') > 0, fn (Builder $query) => $query->where('branch_id', $request->integer('branch_id')))
            ->when(in_array($status, ['active', 'scaling', 'paused'], true), fn (Builder $query) => $query->where('status', $status))
            ->when($search !== '', function (Builder $query) use ($search) {
                $like = '%'.$search.'%';

                $query->where(function (Builder $nested) use ($like) {
                    $nested->where('code', 'like', $like)
                        ->orWhere('name', 'like', $like)
                        ->orWhere('manager_name', 'like', $like);
                });
            })
            ->orderBy('name')
            ->paginate(min(max((int) $request->integer('per_page', 50), 1), 200));

        return response()->json($departments);
    }

    public function show(Request $request, HrDepartment $hrDepartment): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        abort_unless($hrDepartment->company_id === $company->id, 404);

        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('hr.view'), 403);

        return response()->json($hrDepartment->load(['branch', 'employees.branch']));
    }

    public function store(Request $request): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('hr.manage'), 403);

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:30', Rule::unique('hr_departments', 'code')->where(fn ($query) => $query->where('company_id', $company->id))],
            'name' => ['required', 'string', 'max:255'],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where(fn ($query) => $query->where('company_id', $company->id))],
            'manager_name' => ['nullable', 'string', 'max:255'],
            'headcount_target' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'status' => ['nullable', Rule::in(['active', 'scaling', 'paused'])],
            'notes' => ['nullable', 'string'],
        ]);

        $department = HrDepartment::query()->create([
            'company_id' => $company->id,
            'branch_id' => $data['branch_id'] ?? null,
            'code' => ($data['code'] ?? null) ?: $this->generateCode($company->id),
            'name' => $data['name'],
            'manager_name' => $data['manager_name'] ?? null,
            'headcount_target' => (int) ($data['headcount_target'] ?? 0),
            'status' => $data['status'] ?? 'active',
            'notes' => $data['notes'] ?? null,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        return response()->json($department->load('branch'), 201);
    }

    private function generateCode(int $companyId): string
    {
        return app(\App\Modules\Core\Company\Services\DocumentNumberService::class)
            ->nextNumber($companyId, 'hr_department_code');
    }
}
