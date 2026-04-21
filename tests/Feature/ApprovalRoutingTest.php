<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Access\Models\Role;
use App\Modules\Core\Approvals\Models\ApprovalStep;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\Setting;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Expenses\Models\ExpenseCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_approval_can_be_routed_by_branch_override_and_module_default(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $sikasso = Branch::query()->where('company_id', $manager->company_id)->where('code', 'SIK')->firstOrFail();
        $category = ExpenseCategory::query()->where('company_id', $manager->company_id)->where('is_active', true)->firstOrFail();

        $defaultApprover = User::query()->create([
            'tenant_id' => $manager->tenant_id,
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'name' => 'Validateur Bamako',
            'phone' => '+223 70 00 09 97',
            'email' => 'validateur.bko@nema-erp.test',
            'password' => 'password',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $defaultApprover->roles()->sync(
            Role::query()->where('company_id', $manager->company_id)->where('slug', 'company_admin')->pluck('id')->all()
        );

        $sikassoApprover = User::query()->create([
            'tenant_id' => $manager->tenant_id,
            'company_id' => $manager->company_id,
            'branch_id' => $sikasso->id,
            'name' => 'Validateur Sikasso',
            'phone' => '+223 70 00 09 98',
            'email' => 'validateur.sikasso@nema-erp.test',
            'password' => 'password',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $sikassoApprover->roles()->sync(
            Role::query()->where('company_id', $manager->company_id)->where('slug', 'company_admin')->pluck('id')->all()
        );

        Setting::query()->updateOrCreate(
            ['company_id' => $manager->company_id, 'key' => 'approval_workflows'],
            ['value' => [
                'sales' => [
                    'step2_threshold' => 100000,
                    'critical_threshold' => 500000,
                    'step1_sla_hours' => 24,
                    'step2_sla_hours' => 12,
                ],
                'purchases' => [
                    'step2_threshold' => 100000,
                    'critical_threshold' => 500000,
                    'step1_sla_hours' => 24,
                    'step2_sla_hours' => 12,
                ],
                'expenses' => [
                    'step2_threshold' => 100000,
                    'critical_threshold' => 500000,
                    'step1_sla_hours' => 24,
                    'step2_sla_hours' => 12,
                    'step1_assignee_id' => $defaultApprover->id,
                    'branch_assignments' => [
                        $sikasso->id => [
                            'step1_assignee_id' => $sikassoApprover->id,
                        ],
                    ],
                ],
            ]]
        );

        $this->actingAs($manager)
            ->withSession([
                'current_company_id' => $manager->company_id,
                'current_branch_id' => $sikasso->id,
            ])
            ->post(route('expenses.store'), [
                'expense_category_id' => $category->id,
                'expense_date' => now()->format('Y-m-d'),
                'description' => 'ROUTING-BRANCH-EXPENSE',
                'total' => 900,
                'notes' => 'Routage agence',
            ])
            ->assertRedirect();

        $branchExpense = Expense::query()
            ->where('company_id', $manager->company_id)
            ->where('description', 'ROUTING-BRANCH-EXPENSE')
            ->firstOrFail();

        $branchStep = ApprovalStep::query()
            ->where('approvable_type', Expense::class)
            ->where('approvable_id', $branchExpense->id)
            ->where('status', 'pending')
            ->firstOrFail();

        $this->assertSame($sikassoApprover->id, $branchStep->assigned_to);
        $this->assertSame('branch_assignment', data_get($branchStep->meta, 'assignment.source'));

        $this->actingAs($defaultApprover)
            ->withSession([
                'current_company_id' => $defaultApprover->company_id,
                'current_branch_id' => $defaultApprover->branch_id,
            ])
            ->from(route('expenses.show', $branchExpense))
            ->post(route('expenses.approve', $branchExpense))
            ->assertSessionHasErrors('approval');

        $this->actingAs($sikassoApprover)
            ->withSession([
                'current_company_id' => $sikassoApprover->company_id,
                'current_branch_id' => $sikasso->id,
            ])
            ->post(route('expenses.approve', $branchExpense))
            ->assertRedirect(route('expenses.show', $branchExpense));

        $this->assertSame('validated', $branchExpense->fresh()->status);

        $this->actingAs($manager)
            ->withSession([
                'current_company_id' => $manager->company_id,
                'current_branch_id' => $manager->branch_id,
            ])
            ->post(route('expenses.store'), [
                'expense_category_id' => $category->id,
                'expense_date' => now()->format('Y-m-d'),
                'description' => 'ROUTING-DEFAULT-EXPENSE',
                'total' => 950,
                'notes' => 'Routage module',
            ])
            ->assertRedirect();

        $defaultExpense = Expense::query()
            ->where('company_id', $manager->company_id)
            ->where('description', 'ROUTING-DEFAULT-EXPENSE')
            ->firstOrFail();

        $defaultStep = ApprovalStep::query()
            ->where('approvable_type', Expense::class)
            ->where('approvable_id', $defaultExpense->id)
            ->where('status', 'pending')
            ->firstOrFail();

        $this->assertSame($defaultApprover->id, $defaultStep->assigned_to);
        $this->assertSame('module_default', data_get($defaultStep->meta, 'assignment.source'));
    }
}
