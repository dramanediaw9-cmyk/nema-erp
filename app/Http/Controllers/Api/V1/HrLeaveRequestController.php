<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesApiActor;
use App\Modules\Hr\Models\HrLeaveRequest;
use App\Modules\Hr\Services\HrLeaveService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HrLeaveRequestController
{
    use ResolvesApiActor;

    public function __construct(private readonly HrLeaveService $hrLeaveService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('hr.view'), 403);

        $status = $request->string('status')->trim()->value();
        $leaveType = $request->string('leave_type')->trim()->value();
        $search = $request->string('search')->trim()->value();

        $requests = HrLeaveRequest::query()
            ->with(['employee.department', 'branch'])
            ->where('company_id', $company->id)
            ->when($request->integer('employee_id') > 0, fn (Builder $query) => $query->where('employee_id', $request->integer('employee_id')))
            ->when(in_array($status, ['draft', 'approved', 'completed', 'declined'], true), fn (Builder $query) => $query->where('status', $status))
            ->when(in_array($leaveType, ['annual', 'sick', 'special', 'unpaid'], true), fn (Builder $query) => $query->where('leave_type', $leaveType))
            ->when($search !== '', function (Builder $query) use ($search) {
                $like = '%'.$search.'%';

                $query->where(function (Builder $nested) use ($like) {
                    $nested->where('leave_number', 'like', $like)
                        ->orWhere('coverage_plan', 'like', $like)
                        ->orWhereHas('employee', fn (Builder $employeeQuery) => $employeeQuery->where('full_name', 'like', $like));
                });
            })
            ->orderByDesc('start_date')
            ->paginate(min(max((int) $request->integer('per_page', 50), 1), 200));

        return response()->json($requests);
    }

    public function show(Request $request, HrLeaveRequest $leaveRequest): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        abort_unless($leaveRequest->company_id === $company->id, 404);

        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('hr.view'), 403);

        return response()->json($leaveRequest->load(['employee.department', 'branch']));
    }

    public function store(Request $request): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('hr.manage'), 403);

        $data = $request->validate([
            'leave_number' => ['nullable', 'string', 'max:30', Rule::unique('hr_leave_requests', 'leave_number')->where(fn ($query) => $query->where('company_id', $company->id))],
            'employee_id' => ['required', Rule::exists('hr_employees', 'id')->where(fn ($query) => $query->where('company_id', $company->id))],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where(fn ($query) => $query->where('company_id', $company->id))],
            'leave_type' => ['nullable', Rule::in(['annual', 'sick', 'special', 'unpaid'])],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'total_days' => ['nullable', 'numeric', 'min:0.5'],
            'status' => ['nullable', Rule::in(['draft', 'approved', 'completed', 'declined'])],
            'coverage_plan' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $leaveRequest = $this->hrLeaveService->createLeaveRequest($company->id, $actor->id, $data);

        return response()->json($leaveRequest, 201);
    }
}
