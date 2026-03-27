<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Notifications\Models\InternalNotification;
use App\Modules\Partners\Models\Partner;
use App\Modules\Sales\Models\SalesInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalPortalTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_portal_shows_only_documents_current_user_can_approve(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $director = User::query()->where('email', 'dg@nema-erp.test')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $manager->company_id)->firstOrFail();
        $product = Product::query()->where('company_id', $manager->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $this->actingAs($manager)
            ->withSession([
                'current_company_id' => $manager->company_id,
                'current_branch_id' => $manager->branch_id,
            ])
            ->post(route('sales.store'), [
                'customer_id' => $customer->id,
                'invoice_date' => now()->format('Y-m-d'),
                'notes' => 'PORTAL-SALE-DIRECTOR',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'description' => 'Portail approbation',
                        'qty' => 1,
                        'unit_price' => 120000,
                    ],
                ],
            ])
            ->assertRedirect();

        $invoice = SalesInvoice::query()->where('company_id', $manager->company_id)->where('notes', 'PORTAL-SALE-DIRECTOR')->firstOrFail();

        $this->actingAs($director)
            ->withSession([
                'current_company_id' => $director->company_id,
                'current_branch_id' => $director->branch_id,
            ])
            ->get(route('approvals.index'))
            ->assertOk()
            ->assertSee($invoice->invoice_number)
            ->assertSee('Valider l etape');

        $this->actingAs($manager)
            ->withSession([
                'current_company_id' => $manager->company_id,
                'current_branch_id' => $manager->branch_id,
            ])
            ->get(route('approvals.index'))
            ->assertOk()
            ->assertDontSee($invoice->invoice_number);
    }

    public function test_portal_can_filter_by_search_term(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $director = User::query()->where('email', 'dg@nema-erp.test')->firstOrFail();
        $customers = Partner::query()->customers()->where('company_id', $manager->company_id)->orderBy('id')->take(2)->get();
        $product = Product::query()->where('company_id', $manager->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $this->actingAs($manager)
            ->withSession([
                'current_company_id' => $manager->company_id,
                'current_branch_id' => $manager->branch_id,
            ])
            ->post(route('sales.store'), [
                'customer_id' => $customers[0]->id,
                'invoice_date' => now()->format('Y-m-d'),
                'notes' => 'PORTAL-SEARCH-ONE',
                'items' => [[
                    'product_id' => $product->id,
                    'description' => 'Portail filtre un',
                    'qty' => 1,
                    'unit_price' => 125000,
                ]],
            ])
            ->assertRedirect();

        $this->actingAs($manager)
            ->withSession([
                'current_company_id' => $manager->company_id,
                'current_branch_id' => $manager->branch_id,
            ])
            ->post(route('sales.store'), [
                'customer_id' => $customers[1]->id,
                'invoice_date' => now()->format('Y-m-d'),
                'notes' => 'PORTAL-SEARCH-TWO',
                'items' => [[
                    'product_id' => $product->id,
                    'description' => 'Portail filtre deux',
                    'qty' => 1,
                    'unit_price' => 130000,
                ]],
            ])
            ->assertRedirect();

        $firstInvoice = SalesInvoice::query()->where('company_id', $manager->company_id)->where('notes', 'PORTAL-SEARCH-ONE')->firstOrFail();
        $secondInvoice = SalesInvoice::query()->where('company_id', $manager->company_id)->where('notes', 'PORTAL-SEARCH-TWO')->firstOrFail();

        $this->actingAs($director)
            ->withSession([
                'current_company_id' => $director->company_id,
                'current_branch_id' => $director->branch_id,
            ])
            ->get(route('approvals.index', ['search' => $firstInvoice->invoice_number]))
            ->assertOk()
            ->assertSee($firstInvoice->invoice_number)
            ->assertDontSee($secondInvoice->invoice_number);
    }
}
