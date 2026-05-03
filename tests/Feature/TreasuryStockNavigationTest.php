<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Audit\Models\ActivityLog;
use App\Modules\Treasury\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TreasuryStockNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_payment_detail_page_shows_linked_documents_and_accounting_entries(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $payment = Payment::query()->where('company_id', $user->company_id)->where('reference', 'REC-DEMO-001')->firstOrFail();
        $document = optional($payment->allocations()->with('allocatable')->first())->allocatable;

        ActivityLog::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'user_id' => $user->id,
            'action' => 'payments.review',
            'description' => 'Controle paiement test',
            'subject_type' => $payment->getMorphClass(),
            'subject_id' => $payment->id,
            'properties' => ['source' => 'test'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);

        $response = $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('payments.show', $payment));

        $response
            ->assertOk()
            ->assertSee($payment->payment_number)
            ->assertSee('Documents lies')
            ->assertSee('Ecritures comptables liees')
            ->assertSee('Historique des actions')
            ->assertSee('Controle paiement test')
            ->assertSee('Pieces jointes')
            ->assertSee('Commentaires internes');

        if ($document && property_exists($document, 'invoice_number')) {
            $response->assertSee($document->invoice_number);
        }
    }

    public function test_stock_product_detail_page_shows_movements_documents_and_accounting_entries(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('stock.show', $product))
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee('Mouvements recents')
            ->assertSee('Documents lies')
            ->assertSee('Ecritures comptables liees');
    }

    public function test_stock_movements_page_links_source_documents_and_product_detail(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('stock.movements', ['search' => 'PRD-0001']))
            ->assertOk()
            ->assertSee('Source')
            ->assertSee('Eau minerale 1.5L')
            ->assertSee('Facture client');
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}
