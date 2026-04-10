<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Hr\Models\HrDepartment;
use App\Modules\Hr\Models\HrEmployee;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HrController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger)
    {
    }

    public function index(CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        return view('hr.index', [
            'branches' => Branch::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'departments' => HrDepartment::query()
                ->with(['branch', 'employees'])
                ->where('company_id', $companyId)
                ->orderBy('name')
                ->get(),
            'employees' => HrEmployee::query()
                ->with(['branch', 'department'])
                ->where('company_id', $companyId)
                ->orderBy('full_name')
                ->get(),
            'summary' => [
                'departments' => (int) HrDepartment::query()->where('company_id', $companyId)->count(),
                'employees' => (int) HrEmployee::query()->where('company_id', $companyId)->count(),
                'active_employees' => (int) HrEmployee::query()->where('company_id', $companyId)->where('status', 'active')->count(),
                'monthly_payroll' => (float) HrEmployee::query()->where('company_id', $companyId)->where('payroll_cycle', 'monthly')->sum('base_salary'),
            ],
            'departmentStatusOptions' => $this->departmentStatusOptions(),
            'employeeStatusOptions' => $this->employeeStatusOptions(),
            'contractOptions' => $this->contractOptions(),
            'payrollCycleOptions' => $this->payrollCycleOptions(),
        ]);
    }

    public function storeDepartment(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $payload = $request->validate([
            'code' => ['nullable', 'string', 'max:30', Rule::unique('hr_departments', 'code')->where(fn ($query) => $query->where('company_id', $companyId))],
            'name' => ['required', 'string', 'max:255'],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'manager_name' => ['nullable', 'string', 'max:255'],
            'headcount_target' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'status' => ['required', Rule::in(array_keys($this->departmentStatusOptions()))],
            'notes' => ['nullable', 'string'],
        ]);

        $department = HrDepartment::query()->create([
            'company_id' => $companyId,
            'branch_id' => $payload['branch_id'] ?? null,
            'code' => ($payload['code'] ?? null) ?: $this->generateDepartmentCode($companyId),
            'name' => $payload['name'],
            'manager_name' => $payload['manager_name'] ?? null,
            'headcount_target' => (int) ($payload['headcount_target'] ?? 0),
            'status' => $payload['status'],
            'notes' => $payload['notes'] ?? null,
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        $this->activityLogger->log('hr.departments.create', 'Creation departement RH', $department, [
            'code' => $department->code,
            'status' => $department->status,
        ]);

        return redirect()->route('hr.index')->with('success', 'Departement enregistre avec succes.');
    }

    public function storeEmployee(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $payload = $request->validate([
            'employee_number' => ['nullable', 'string', 'max:30', Rule::unique('hr_employees', 'employee_number')->where(fn ($query) => $query->where('company_id', $companyId))],
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('hr_employees', 'email')->where(fn ($query) => $query->where('company_id', $companyId))],
            'phone' => ['nullable', 'string', 'max:50'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'department_id' => ['nullable', Rule::exists('hr_departments', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'contract_type' => ['required', Rule::in(array_keys($this->contractOptions()))],
            'hire_date' => ['required', 'date'],
            'status' => ['required', Rule::in(array_keys($this->employeeStatusOptions()))],
            'payroll_cycle' => ['required', Rule::in(array_keys($this->payrollCycleOptions()))],
            'base_salary' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $employee = HrEmployee::query()->create([
            'company_id' => $companyId,
            'branch_id' => $payload['branch_id'] ?? null,
            'department_id' => $payload['department_id'] ?? null,
            'employee_number' => ($payload['employee_number'] ?? null) ?: $this->generateEmployeeNumber($companyId),
            'full_name' => $payload['full_name'],
            'email' => $payload['email'] ?? null,
            'phone' => $payload['phone'] ?? null,
            'job_title' => $payload['job_title'] ?? null,
            'contract_type' => $payload['contract_type'],
            'hire_date' => $payload['hire_date'],
            'status' => $payload['status'],
            'payroll_cycle' => $payload['payroll_cycle'],
            'base_salary' => $payload['base_salary'] ?? 0,
            'notes' => $payload['notes'] ?? null,
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        $this->activityLogger->log('hr.employees.create', 'Creation collaborateur RH', $employee, [
            'employee_number' => $employee->employee_number,
            'status' => $employee->status,
        ]);

        return redirect()->route('hr.index')->with('success', 'Collaborateur enregistre avec succes.');
    }

    private function departmentStatusOptions(): array
    {
        return [
            'active' => 'Actif',
            'scaling' => 'En renfort',
            'paused' => 'En veille',
        ];
    }

    private function employeeStatusOptions(): array
    {
        return [
            'active' => 'Actif',
            'on_leave' => 'En conge',
            'probation' => 'Periode essai',
            'inactive' => 'Inactif',
        ];
    }

    private function contractOptions(): array
    {
        return [
            'permanent' => 'CDI',
            'fixed_term' => 'CDD',
            'consultant' => 'Consultant',
            'intern' => 'Stage',
        ];
    }

    private function payrollCycleOptions(): array
    {
        return [
            'monthly' => 'Mensuel',
            'biweekly' => 'Quinzaine',
            'weekly' => 'Hebdomadaire',
        ];
    }

    private function generateDepartmentCode(int $companyId): string
    {
        $sequence = HrDepartment::query()->where('company_id', $companyId)->count() + 1;

        return 'DEP-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function generateEmployeeNumber(int $companyId): string
    {
        $sequence = HrEmployee::query()->where('company_id', $companyId)->count() + 1;

        return 'EMP-'.now()->format('Y').'-'.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }
}
