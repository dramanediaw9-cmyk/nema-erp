<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesApiActor;
use App\Modules\Hr\Models\HrEmployee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HrEmployeeController
{
    use ResolvesApiActor;

    public function index(Request $request): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('hr.view'), 403);

        $search = $request->string('search')->trim()->value();
        $status = $request->string('status')->trim()->value();
        $cycle = $request->string('payroll_cycle')->trim()->value();

        $employees = HrEmployee::query()
            ->with(['branch', 'department'])
            ->where('company_id', $company->id)
            ->when($request->integer('department_id') > 0, fn (Builder $query) => $query->where('department_id', $request->integer('department_id')))
            ->when($request->integer('branch_id') > 0, fn (Builder $query) => $query->where('branch_id', $request->integer('branch_id')))
            ->when(in_array($status, ['active', 'on_leave', 'probation', 'inactive'], true), fn (Builder $query) => $query->where('status', $status))
            ->when(in_array($cycle, ['monthly', 'biweekly', 'weekly'], true), fn (Builder $query) => $query->where('payroll_cycle', $cycle))
            ->when($search !== '', function (Builder $query) use ($search) {
                $like = '%'.$search.'%';

                $query->where(function (Builder $nested) use ($like) {
                    $nested->where('employee_number', 'like', $like)
                        ->orWhere('full_name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('job_title', 'like', $like);
                });
            })
            ->orderBy('full_name')
            ->paginate(min(max((int) $request->integer('per_page', 50), 1), 200));

        return response()->json($employees);
    }

    public function show(Request $request, HrEmployee $hrEmployee): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        abort_unless($hrEmployee->company_id === $company->id, 404);

        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('hr.view'), 403);

        return response()->json($hrEmployee->load(['branch', 'department']));
    }

    public function store(Request $request): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('hr.manage'), 403);

        $data = $request->validate([
            'employee_number' => ['nullable', 'string', 'max:30', Rule::unique('hr_employees', 'employee_number')->where(fn ($query) => $query->where('company_id', $company->id))],
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('hr_employees', 'email')->where(fn ($query) => $query->where('company_id', $company->id))],
            'phone' => ['nullable', 'string', 'max:50'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where(fn ($query) => $query->where('company_id', $company->id))],
            'department_id' => ['nullable', Rule::exists('hr_departments', 'id')->where(fn ($query) => $query->where('company_id', $company->id))],
            'contract_type' => ['nullable', Rule::in(['permanent', 'fixed_term', 'consultant', 'intern'])],
            'hire_date' => ['required', 'date'],
            'status' => ['nullable', Rule::in(['active', 'on_leave', 'probation', 'inactive'])],
            'payroll_cycle' => ['nullable', Rule::in(['monthly', 'biweekly', 'weekly'])],
            'base_salary' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $employee = HrEmployee::query()->create([
            'company_id' => $company->id,
            'branch_id' => $data['branch_id'] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'employee_number' => ($data['employee_number'] ?? null) ?: $this->generateNumber($company->id),
            'full_name' => $data['full_name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'job_title' => $data['job_title'] ?? null,
            'contract_type' => $data['contract_type'] ?? 'permanent',
            'hire_date' => $data['hire_date'],
            'status' => $data['status'] ?? 'active',
            'payroll_cycle' => $data['payroll_cycle'] ?? 'monthly',
            'base_salary' => $data['base_salary'] ?? 0,
            'notes' => $data['notes'] ?? null,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        return response()->json($employee->load(['branch', 'department']), 201);
    }

    private function generateNumber(int $companyId): string
    {
        $number = HrEmployee::query()->where('company_id', $companyId)->count() + 1;

        return 'EMP-'.now()->format('Y').'-'.str_pad((string) $number, 5, '0', STR_PAD_LEFT);
    }
}
