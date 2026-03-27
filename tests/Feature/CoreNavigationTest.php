<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoreNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_company_admin_can_access_main_pages(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $this->actingAs($user)->withSession([
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ]);

        foreach ([
            route('dashboard'),
            route('onboarding.index'),
            route('approvals.index'),
            route('reports.index'),
            route('notifications.index'),
            route('notifications.outbound.index'),
            route('imports.index'),
            route('ops.index'),
            route('customers.index'),
            route('suppliers.index'),
            route('categories.index'),
            route('products.index'),
            route('stock.index'),
            route('crm.index'),
            route('sales.index'),
            route('purchases.index'),
            route('cash-accounts.index'),
            route('payments.index'),
            route('treasury-reconciliations.index'),
            route('expenses.index'),
            route('accounting.accounts.index'),
            route('accounting.periods.index'),
            route('accounting.journal-entries.index'),
            route('accounting.general-ledger.index'),
            route('accounting.balance.index'),
            route('accounting.profit-loss.index'),
            route('accounting.balance-sheet.index'),
            route('fixed-assets.index'),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_company_admin_can_dismiss_and_reopen_onboarding_banner(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $this->actingAs($user)->withSession([
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ]);

        $this->post(route('onboarding.dismiss'))->assertSessionHas('success');

        $setting = \App\Modules\Core\Company\Models\Setting::query()->where('company_id', $user->company_id)->where('key', 'onboarding')->first();
        $this->assertNotNull($setting);
        $this->assertNotEmpty($setting->value['dashboard_dismissed_at'] ?? null);

        $this->post(route('onboarding.reopen'))->assertSessionHas('success');

        $setting->refresh();
        $this->assertNull($setting->value['dashboard_dismissed_at'] ?? null);
    }

    public function test_dashboard_and_periods_page_show_period_control_information(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $this->actingAs($user)->withSession([
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ]);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Periode en cours')
            ->assertSee('Achats en attente d approbation')
            ->assertSee('Actions rapides');

        $this->get(route('accounting.periods.index'))
            ->assertOk()
            ->assertSee('Cloture possible')
            ->assertSee('Dettes fournisseurs ouvertes');
    }

    public function test_director_cannot_access_sales_creation_page(): void
    {
        $user = User::query()->where('email', 'dg@nema-erp.test')->firstOrFail();

        $this->actingAs($user)->withSession([
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ]);

        $this->get(route('sales.create'))->assertForbidden();
    }

    public function test_operations_user_cannot_access_approval_portal_or_outbound_notifications(): void
    {
        $user = User::query()->where('email', 'ops@nema-erp.test')->firstOrFail();

        $this->actingAs($user)->withSession([
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ]);

        $this->get(route('approvals.index'))->assertForbidden();
        $this->get(route('notifications.outbound.index'))->assertForbidden();
    }
}

