<?php

namespace App\Modules\Payroll\Services;

use App\Modules\Hr\Models\HrEmployee;
use App\Modules\Payroll\Models\PayrollSlip;

class PayrollSlipService
{
    public function createSlip(int $companyId, ?int $actorId, array $data): PayrollSlip
    {
        $employee = HrEmployee::query()->where('company_id', $companyId)->findOrFail($data['employee_id']);

        $baseSalary = (float) ($data['base_salary'] ?? $employee->base_salary ?? 0);
        $grossAmount = (float) ($data['gross_amount'] ?? $baseSalary);
        $deductionsAmount = (float) ($data['deductions_amount'] ?? 0);
        $employerContributions = (float) ($data['employer_contributions_amount'] ?? 0);
        $netAmount = (float) ($data['net_amount'] ?? max($grossAmount - $deductionsAmount, 0));

        $slip = PayrollSlip::query()->create([
            'company_id' => $companyId,
            'branch_id' => $data['branch_id'] ?? $employee->branch_id,
            'payroll_run_id' => $data['payroll_run_id'] ?? null,
            'employee_id' => $employee->id,
            'slip_number' => ($data['slip_number'] ?? null) ?: $this->generateSlipNumber($companyId),
            'base_salary' => $baseSalary,
            'gross_amount' => $grossAmount,
            'deductions_amount' => $deductionsAmount,
            'employer_contributions_amount' => $employerContributions,
            'net_amount' => $netAmount,
            'status' => $data['status'] ?? 'draft',
            'payout_mode' => $data['payout_mode'] ?? 'bank',
            'notes' => $data['notes'] ?? null,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);

        $this->syncDefaultLines($slip);

        return $slip->load(['payrollRun', 'employee.department', 'branch', 'lines']);
    }

    public function generateSlipNumber(int $companyId): string
    {
        $sequence = PayrollSlip::query()->where('company_id', $companyId)->count() + 1;

        return 'BUL-'.now()->format('Y').'-'.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }

    public function syncDefaultLines(PayrollSlip $slip): void
    {
        $lines = [
            [
                'line_type' => 'earning',
                'code' => 'SALAIRE_BASE',
                'label' => 'Salaire de base',
                'amount' => (float) $slip->base_salary,
            ],
        ];

        $variableCompensation = round((float) $slip->gross_amount - (float) $slip->base_salary, 2);
        if ($variableCompensation > 0) {
            $lines[] = [
                'line_type' => 'earning',
                'code' => 'PRIMES',
                'label' => 'Primes et variables',
                'amount' => $variableCompensation,
            ];
        }

        if ((float) $slip->deductions_amount > 0) {
            $lines[] = [
                'line_type' => 'deduction',
                'code' => 'RETENUES',
                'label' => 'Retenues salariales',
                'amount' => (float) $slip->deductions_amount,
            ];
        }

        if ((float) $slip->employer_contributions_amount > 0) {
            $lines[] = [
                'line_type' => 'employer_charge',
                'code' => 'CHARGES_PATRONALES',
                'label' => 'Charges patronales',
                'amount' => (float) $slip->employer_contributions_amount,
            ];
        }

        $lines[] = [
            'line_type' => 'net',
            'code' => 'NET_A_PAYER',
            'label' => 'Net a payer',
            'amount' => (float) $slip->net_amount,
        ];

        $slip->lines()->delete();

        foreach ($lines as $index => $line) {
            $slip->lines()->create($line + ['sequence' => $index + 1]);
        }
    }
}
