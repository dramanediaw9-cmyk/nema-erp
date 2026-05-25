<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Automation\Models\AutomationRule;
use App\Modules\Core\Notifications\Models\InternalNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWholesaleCoverageScenario;
use Tests\TestCase;

class AutomationSalesSignalTest extends TestCase
{
    use BuildsWholesaleCoverageScenario;
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_manager_can_create_and_run_wholesale_orders_at_risk_rule(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $this->seedWholesaleCoverageScenario($user, 'AUTORISK');

        $this->actingAs($user)->withSession($this->workspaceSession($user));

        $this->post(route('automation.store'), [
            'name' => 'Veille commandes grossiste a risque',
            'signal_key' => 'sales.wholesale_orders_at_risk',
            'status' => 'active',
            'severity' => 'danger',
            'action_type' => 'internal_alert',
            'threshold_value' => 1,
            'window_hours' => 24,
            'cooldown_minutes' => 60,
            'branch_id' => $user->branch_id,
            'description' => 'Remonter les commandes confirmees dont la promesse stock reste incomplete.',
        ])->assertRedirect(route('automation.index'));

        $rule = AutomationRule::query()
            ->where('company_id', $user->company_id)
            ->where('name', 'Veille commandes grossiste a risque')
            ->firstOrFail();

        $this->post(route('automation.run', $rule))
            ->assertRedirect(route('automation.index'));

        $this->assertDatabaseHas('automation_executions', [
            'company_id' => $user->company_id,
            'automation_rule_id' => $rule->id,
            'matched' => true,
            'observed_value' => 1,
        ]);

        $notification = InternalNotification::query()
            ->where('company_id', $user->company_id)
            ->where('code', 'automation-rule-'.$rule->id)
            ->firstOrFail();

        $this->assertStringContainsString('1 commande(s) grossiste restent a risque sur 1 ligne(s)', (string) $notification->message);
        $this->assertStringContainsString('ORDER-AUTORISK-RISK-001', (string) $notification->message);

        $this->get(route('automation.index'))
            ->assertOk()
            ->assertSee('Commandes grossiste a risque')
            ->assertSee('Veille commandes grossiste a risque');
    }

    public function test_manager_can_create_and_run_wholesale_overdue_commitments_rule(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $this->seedWholesaleCoverageScenario($user, 'AUTOOVER');

        $this->actingAs($user)->withSession($this->workspaceSession($user));

        $this->post(route('automation.store'), [
            'name' => 'Veille engagements grossiste en retard',
            'signal_key' => 'sales.wholesale_overdue_commitments',
            'status' => 'active',
            'severity' => 'warning',
            'action_type' => 'internal_alert',
            'threshold_value' => 1,
            'window_hours' => 24,
            'cooldown_minutes' => 60,
            'branch_id' => $user->branch_id,
            'description' => 'Remonter les reliquats encore ouverts apres la date promise au client.',
        ])->assertRedirect(route('automation.index'));

        $rule = AutomationRule::query()
            ->where('company_id', $user->company_id)
            ->where('name', 'Veille engagements grossiste en retard')
            ->firstOrFail();

        $this->post(route('automation.run', $rule))
            ->assertRedirect(route('automation.index'));

        $this->assertDatabaseHas('automation_executions', [
            'company_id' => $user->company_id,
            'automation_rule_id' => $rule->id,
            'matched' => true,
            'observed_value' => 1,
        ]);

        $notification = InternalNotification::query()
            ->where('company_id', $user->company_id)
            ->where('code', 'automation-rule-'.$rule->id)
            ->firstOrFail();

        $this->assertStringContainsString('1 commande(s) grossiste gardent encore 3,000 unite(s) en reliquat apres la date promise', (string) $notification->message);
        $this->assertStringContainsString('ORDER-AUTOOVER-OVERDUE-001', (string) $notification->message);

        $this->get(route('automation.index'))
            ->assertOk()
            ->assertSee('Engagements grossiste en retard')
            ->assertSee('Veille engagements grossiste en retard');
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}
