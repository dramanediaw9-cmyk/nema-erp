<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesApiActor;
use App\Modules\Payroll\Models\PayrollSlip;
use App\Modules\Payroll\Services\PayrollSlipService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PayrollSlipController
{
    use ResolvesApiActor;

    public function __construct(private readonly PayrollSlipService $payrollSlipService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('payroll.view'), 403);

        $status = $request->string('status')->trim()->value();
        $search = $request->string('search')->trim()->value();

        $slips = PayrollSlip::query()
            ->with(['payrollRun', 'employee.department', 'branch', 'lines'])
            ->where('company_id', $company->id)
            ->when($request->integer('payroll_run_id') > 0, fn (Builder $query) => $query->where('payroll_run_id', $request->integer('payroll_run_id')))
            ->when($request->integer('employee_id') > 0, fn (Builder $query) => $query->where('employee_id', $request->integer('employee_id')))
            ->when(in_array($status, ['draft', 'review', 'ready', 'paid'], true), fn (Builder $query) => $query->where('status', $status))
            ->when($search !== '', function (Builder $query) use ($search) {
                $like = '%'.$search.'%';

                $query->where(function (Builder $nested) use ($like) {
                    $nested->where('slip_number', 'like', $like)
                        ->orWhereHas('employee', fn (Builder $employeeQuery) => $employeeQuery->where('full_name', 'like', $like));
                });
            })
            ->orderByDesc('id')
            ->paginate(min(max((int) $request->integer('per_page', 50), 1), 200));

        return response()->json($slips);
    }

    public function show(Request $request, PayrollSlip $payrollSlip): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        abort_unless($payrollSlip->company_id === $company->id, 404);

        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('payroll.view'), 403);

        return response()->json($payrollSlip->load(['payrollRun', 'employee.department', 'branch', 'lines']));
    }

    public function store(Request $request): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('payroll.manage'), 403);

        $data = $request->validate([
            'slip_number' => ['nullable', 'string', 'max:30', Rule::unique('payroll_slips', 'slip_number')->where(fn ($query) => $query->where('company_id', $company->id))],
            'payroll_run_id' => ['nullable', Rule::exists('payroll_runs', 'id')->where(fn ($query) => $query->where('company_id', $company->id))],
            'employee_id' => ['required', Rule::exists('hr_employees', 'id')->where(fn ($query) => $query->where('company_id', $company->id))],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where(fn ($query) => $query->where('company_id', $company->id))],
            'base_salary' => ['nullable', 'numeric', 'min:0'],
            'gross_amount' => ['nullable', 'numeric', 'min:0'],
            'deductions_amount' => ['nullable', 'numeric', 'min:0'],
            'employer_contributions_amount' => ['nullable', 'numeric', 'min:0'],
            'net_amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in(['draft', 'review', 'ready', 'paid'])],
            'payout_mode' => ['nullable', Rule::in(['bank', 'cash', 'mobile_money', 'mixed'])],
            'notes' => ['nullable', 'string'],
        ]);

        $slip = $this->payrollSlipService->createSlip($company->id, $actor->id, $data);

        return response()->json($slip, 201);
    }
}
