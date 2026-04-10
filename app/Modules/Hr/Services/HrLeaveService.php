<?php

namespace App\Modules\Hr\Services;

use App\Modules\Hr\Models\HrEmployee;
use App\Modules\Hr\Models\HrLeaveRequest;
use Carbon\Carbon;

class HrLeaveService
{
    public function createLeaveRequest(int $companyId, ?int $actorId, array $data): HrLeaveRequest
    {
        $leaveRequest = HrLeaveRequest::query()->create([
            'company_id' => $companyId,
            'branch_id' => $data['branch_id'] ?? null,
            'employee_id' => $data['employee_id'],
            'leave_number' => ($data['leave_number'] ?? null) ?: $this->generateLeaveNumber($companyId),
            'leave_type' => $data['leave_type'] ?? 'annual',
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'total_days' => $data['total_days'] ?? $this->calculateTotalDays($data['start_date'], $data['end_date']),
            'status' => $data['status'] ?? 'draft',
            'coverage_plan' => $data['coverage_plan'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);

        $this->syncEmployeeStatus($leaveRequest);

        return $leaveRequest->load(['employee.department', 'branch']);
    }

    public function generateLeaveNumber(int $companyId): string
    {
        $sequence = HrLeaveRequest::query()->where('company_id', $companyId)->count() + 1;

        return 'CONGE-'.now()->format('Y').'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    public function calculateTotalDays(string $startDate, string $endDate): float
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();

        return (float) max($start->diffInDays($end) + 1, 1);
    }

    private function syncEmployeeStatus(HrLeaveRequest $leaveRequest): void
    {
        if ($leaveRequest->status !== 'approved') {
            return;
        }

        $employee = HrEmployee::query()->whereKey($leaveRequest->employee_id)->first();
        if (! $employee) {
            return;
        }

        $today = now()->startOfDay();

        if ($today->betweenIncluded($leaveRequest->start_date, $leaveRequest->end_date) && $employee->status !== 'on_leave') {
            $employee->forceFill(['status' => 'on_leave'])->save();
        }
    }
}
