<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Integrations\Models\ApiToken;
use App\Modules\Hr\Models\HrEmployee;
use App\Modules\Manufacturing\Models\ManufacturingBom;
use App\Modules\Payroll\Models\PayrollRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiV1GrowthDepthTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_api_token_can_list_seeded_depth_resources(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $plainToken = $this->createApiToken($manager);

        $this->withToken($plainToken)
            ->getJson('/api/v1/hr/leave-requests?status=approved')
            ->assertOk()
            ->assertJsonPath('data.0.leave_number', 'CONGE-'.now()->format('Y').'-0001');

        $this->withToken($plainToken)
            ->getJson('/api/v1/payroll/slips?status=review')
            ->assertOk()
            ->assertJsonPath('data.0.slip_number', 'BUL-'.now()->format('Y').'-00001');

        $this->withToken($plainToken)
            ->getJson('/api/v1/manufacturing/boms?status=active')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'BOM-'.now()->format('Y').'-0001');

        $this->withToken($plainToken)
            ->getJson('/api/v1/accounting/localization')
            ->assertOk()
            ->assertJsonPath('standard', 'SYSCOHADA revise')
            ->assertJsonPath('bridges.payroll.payables', '421000');
    }

    public function test_api_token_can_create_depth_records(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $plainToken = $this->createApiToken($manager);
        $employee = HrEmployee::query()->where('company_id', $manager->company_id)->where('employee_number', 'EMP-2026-00002')->firstOrFail();
        $run = PayrollRun::query()->where('company_id', $manager->company_id)->firstOrFail();

        $this->withToken($plainToken)
            ->postJson('/api/v1/hr/leave-requests', [
                'employee_id' => $employee->id,
                'leave_type' => 'sick',
                'start_date' => now()->addDay()->toDateString(),
                'end_date' => now()->addDays(2)->toDateString(),
                'status' => 'draft',
                'coverage_plan' => 'Relais depot par equipe logistique B.',
            ])
            ->assertCreated()
            ->assertJsonPath('employee_id', $employee->id)
            ->assertJsonPath('leave_type', 'sick');

        $slipResponse = $this->withToken($plainToken)
            ->postJson('/api/v1/payroll/slips', [
                'payroll_run_id' => $run->id,
                'employee_id' => $employee->id,
                'base_salary' => 285000,
                'gross_amount' => 300000,
                'deductions_amount' => 18000,
                'employer_contributions_amount' => 45000,
                'net_amount' => 282000,
                'status' => 'ready',
                'payout_mode' => 'bank',
            ])
            ->assertCreated()
            ->assertJsonPath('employee_id', $employee->id)
            ->assertJsonPath('status', 'ready');

        $this->assertCount(5, $slipResponse->json('lines'));

        $bomResponse = $this->withToken($plainToken)
            ->postJson('/api/v1/manufacturing/boms', [
                'item_name' => 'Pack hygiene maison',
                'output_quantity' => 1,
                'status' => 'active',
                'lines' => [
                    ['component_code' => 'SAV-001', 'component_name' => 'Savon', 'quantity' => 4, 'unit' => 'u', 'wastage_rate' => 0],
                    ['component_code' => 'EAU-001', 'component_name' => 'Javel', 'quantity' => 2, 'unit' => 'u', 'wastage_rate' => 0],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('item_name', 'Pack hygiene maison');

        $bomId = (int) $bomResponse->json('id');

        $this->withToken($plainToken)
            ->getJson('/api/v1/manufacturing/boms/'.$bomId)
            ->assertOk()
            ->assertJsonPath('lines.0.component_name', 'Savon');

        $this->withToken($plainToken)
            ->postJson('/api/v1/production-orders', [
                'bill_of_material_id' => $bomId,
                'item_name' => 'Pack hygiene maison',
                'planned_quantity' => 75,
                'material_cost_estimate' => 210000,
                'actual_material_cost' => 25000,
                'planned_start_date' => now()->toDateString(),
                'status' => 'planned',
                'routing_stage' => 'preparation',
            ])
            ->assertCreated()
            ->assertJsonPath('bill_of_material.id', $bomId);

        $this->assertDatabaseHas('hr_leave_requests', [
            'company_id' => $manager->company_id,
            'employee_id' => $employee->id,
            'leave_type' => 'sick',
        ]);
        $this->assertDatabaseHas('payroll_slips', [
            'company_id' => $manager->company_id,
            'employee_id' => $employee->id,
            'status' => 'ready',
        ]);
        $this->assertDatabaseHas('manufacturing_boms', [
            'company_id' => $manager->company_id,
            'item_name' => 'Pack hygiene maison',
        ]);
        $this->assertDatabaseHas('manufacturing_bom_lines', [
            'manufacturing_bom_id' => $bomId,
            'component_name' => 'Savon',
        ]);
    }

    private function createApiToken(User $user): string
    {
        $plainToken = 'nema_growth_depth_api_token_'.$user->id;

        ApiToken::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'name' => 'Growth depth test API',
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addDay(),
            'created_by' => $user->id,
        ]);

        return $plainToken;
    }
}
