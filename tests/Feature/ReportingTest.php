<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportingTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_company_admin_can_open_reports_page_with_filters(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $this->actingAs($user)
            ->withSession([
                'current_company_id' => $user->company_id,
                'current_branch_id' => $user->branch_id,
            ])
            ->get(route('reports.index', [
                'date_from' => now()->startOfMonth()->format('Y-m-d'),
                'date_to' => now()->format('Y-m-d'),
            ]))
            ->assertOk()
            ->assertSee('Rapports dirigeant')
            ->assertSee('7 derniers jours')
            ->assertSee('Flux net');
    }

    public function test_company_admin_can_export_sales_csv(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $response = $this->actingAs($user)
            ->withSession([
                'current_company_id' => $user->company_id,
                'current_branch_id' => $user->branch_id,
            ])
            ->get(route('sales.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_company_admin_can_export_stock_csv(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $response = $this->actingAs($user)
            ->withSession([
                'current_company_id' => $user->company_id,
                'current_branch_id' => $user->branch_id,
            ])
            ->get(route('stock.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}

