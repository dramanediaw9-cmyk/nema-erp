<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Hr\Models\HrEmployee;
use App\Modules\Manufacturing\Models\ManufacturingBom;
use App\Modules\Payroll\Models\PayrollRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GrowthDepthTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_manager_can_open_depth_pages(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $this->actingAs($user)->withSession($this->workspaceSession($user));

        $this->get(route('hr.index'))->assertOk()->assertSee('Conges et absences');
        $this->get(route('payroll.index'))->assertOk()->assertSee('Bulletins detailles');
        $this->get(route('manufacturing.index'))->assertOk()->assertSee('Nomenclatures');
        $this->get(route('accounting.ohada.index'))->assertOk()->assertSee('Localisation OHADA');
    }

    public function test_manager_can_register_depth_records(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $employee = HrEmployee::query()->where('company_id', $user->company_id)->where('employee_number', 'EMP-2026-00001')->firstOrFail();
        $run = PayrollRun::query()->where('company_id', $user->company_id)->firstOrFail();

        $this->actingAs($user)->withSession($this->workspaceSession($user));

        $this->post(route('hr.leave-requests.store'), [
            'employee_id' => $employee->id,
            'leave_type' => 'special',
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(11)->toDateString(),
            'total_days' => 2,
            'status' => 'draft',
            'coverage_plan' => 'Couverture assuree par le superviseur adjoint.',
        ])->assertRedirect(route('hr.index'));

        $this->post(route('payroll.slips.store'), [
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
            'base_salary' => 325000,
            'gross_amount' => 360000,
            'deductions_amount' => 30000,
            'employer_contributions_amount' => 55000,
            'net_amount' => 330000,
            'status' => 'ready',
            'payout_mode' => 'mobile_money',
        ])->assertRedirect(route('payroll.index'));

        $this->post(route('manufacturing.boms.store'), [
            'item_name' => 'Pack cereales famille',
            'output_quantity' => 1,
            'status' => 'pilot',
            'components' => "Farine 1kg|3|u|0\nSucre 1kg|2|u|1.5\nCarton|1|u|0",
        ])->assertRedirect(route('manufacturing.index'));

        $bom = ManufacturingBom::query()->where('company_id', $user->company_id)->where('item_name', 'Pack cereales famille')->firstOrFail();

        $this->post(route('manufacturing.store'), [
            'bill_of_material_id' => $bom->id,
            'item_name' => 'Pack cereales famille',
            'planned_quantity' => 120,
            'completed_quantity' => 12,
            'material_cost_estimate' => 650000,
            'actual_material_cost' => 54000,
            'planned_start_date' => now()->toDateString(),
            'due_date' => now()->addWeek()->toDateString(),
            'status' => 'in_progress',
            'routing_stage' => 'assembly',
        ])->assertRedirect(route('manufacturing.index'));

        $this->assertDatabaseHas('hr_leave_requests', [
            'company_id' => $user->company_id,
            'employee_id' => $employee->id,
            'leave_type' => 'special',
        ]);
        $this->assertDatabaseHas('payroll_slips', [
            'company_id' => $user->company_id,
            'employee_id' => $employee->id,
            'status' => 'ready',
            'payout_mode' => 'mobile_money',
        ]);
        $this->assertDatabaseHas('manufacturing_boms', [
            'company_id' => $user->company_id,
            'item_name' => 'Pack cereales famille',
            'status' => 'pilot',
        ]);
        $this->assertDatabaseHas('manufacturing_bom_lines', [
            'manufacturing_bom_id' => $bom->id,
            'component_name' => 'Farine 1kg',
        ]);
        $this->assertDatabaseHas('production_orders', [
            'company_id' => $user->company_id,
            'bill_of_material_id' => $bom->id,
            'item_name' => 'Pack cereales famille',
        ]);
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_tenant_id' => $user->tenant_id,
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}
