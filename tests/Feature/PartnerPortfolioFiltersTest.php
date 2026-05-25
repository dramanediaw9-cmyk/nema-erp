<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Partners\Models\Partner;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Sales\Models\SalesInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerPortfolioFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_customers_index_filters_by_search_status_and_overdue_balance(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $user->company_id)->where('name', 'Sahel Market')->firstOrFail();
        $otherCustomer = Partner::query()->customers()->where('company_id', $user->company_id)->where('name', 'Boutique Djoliba')->firstOrFail();

        SalesInvoice::query()
            ->where('company_id', $user->company_id)
            ->where('customer_id', $customer->id)
            ->update([
                'due_date' => now()->subDays(45)->toDateString(),
                'payment_status' => 'partial',
            ]);

        $otherCustomer->update(['is_active' => false]);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('customers.index', [
                'search' => 'Sahel',
                'status' => 'active',
                'balance_state' => 'overdue',
            ]))
            ->assertOk()
            ->assertSee('Sahel Market')
            ->assertSee('En retard')
            ->assertDontSee('Boutique Djoliba');
    }

    public function test_suppliers_index_filters_by_city_status_and_overdue_balance(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $supplier = Partner::query()->suppliers()->where('company_id', $user->company_id)->where('name', 'Mali Fournitures Pro')->firstOrFail();
        $otherSupplier = Partner::query()->suppliers()->where('company_id', $user->company_id)->where('name', 'Agro Import Mali')->firstOrFail();
        $bill = PurchaseBill::query()->where('company_id', $user->company_id)->where('supplier_id', $supplier->id)->firstOrFail();

        $bill->update([
            'due_date' => now()->subDays(75)->toDateString(),
            'payment_status' => 'partial',
        ]);

        $otherSupplier->update(['is_active' => false]);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('suppliers.index', [
                'city' => 'Bamako',
                'status' => 'active',
                'balance_state' => 'overdue',
            ]))
            ->assertOk()
            ->assertSee('Mali Fournitures Pro')
            ->assertSee('En retard')
            ->assertDontSee('Agro Import Mali');
    }

    public function test_partner_portfolios_can_render_kanban_views(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('customers.index', [
                'view' => 'kanban',
                'search' => 'Sahel',
            ]))
            ->assertOk()
            ->assertSee('Kanban')
            ->assertSee('Sahel Market')
            ->assertSee('Voir la fiche');

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('suppliers.index', [
                'view' => 'kanban',
                'search' => 'Mali',
            ]))
            ->assertOk()
            ->assertSee('Kanban')
            ->assertSee('Mali Fournitures Pro')
            ->assertSee('Voir la fiche');
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}
