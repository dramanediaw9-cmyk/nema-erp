<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Pos\Models\PosDraft;
use App\Modules\Pos\Models\PosSession;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Treasury\Models\CashAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosDraftFlowTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_cashier_can_put_pos_order_on_hold_resume_it_and_consume_it_on_checkout(): void
    {
        $user = User::query()->where('email', 'caissier@nema-erp.test')->firstOrFail();
        $cashAccount = CashAccount::query()->where('company_id', $user->company_id)->where('branch_id', $user->branch_id)->where('name', 'Caisse principale')->firstOrFail();
        $warehouse = Warehouse::query()->where('company_id', $user->company_id)->where('branch_id', $user->branch_id)->where('is_default', true)->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $this->openSession($user, $cashAccount, $warehouse);
        $session = $this->currentSession($user);

        $response = $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->postJson(route('pos.drafts.store'), [
                'label' => 'Commande test brouillon',
                'sale_date' => now()->format('Y-m-d'),
                'method' => 'cash',
                'reference' => 'BROUILLON-001',
                'notes' => 'BROUILLON POS TEST',
                'discount_type' => 'none',
                'discount_value' => 0,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'description' => 'Article brouillon POS',
                        'qty' => 1,
                        'unit_price' => 500,
                        'discount_type' => 'none',
                        'discount_value' => 0,
                    ],
                ],
            ]);

        $response->assertOk()->assertJsonPath('draft.label', 'Commande test brouillon');

        $draftId = (int) $response->json('draft.id');
        $this->assertDatabaseHas('pos_drafts', [
            'id' => $draftId,
            'company_id' => $user->company_id,
            'pos_session_id' => $session->id,
            'label' => 'Commande test brouillon',
            'reference' => 'BROUILLON-001',
        ]);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('pos.show', $session))
            ->assertOk()
            ->assertSeeText('Commandes en attente')
            ->assertSeeText('Commande test brouillon')
            ->assertSeeText('Reprendre');

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('pos.sales.create', ['draft' => $draftId]))
            ->assertOk()
            ->assertSeeText('Commande test brouillon')
            ->assertSee('source_draft_id', false);

        $saleResponse = $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('pos.sales.store'), [
                'sale_date' => now()->format('Y-m-d'),
                'method' => 'cash',
                'reference' => 'BROUILLON-001-VALIDE',
                'notes' => 'VENTE ISSUE BROUILLON POS',
                'discount_type' => 'none',
                'discount_value' => 0,
                'cash_received_amount' => 500,
                'source_draft_id' => $draftId,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'description' => 'Article brouillon POS',
                        'qty' => 1,
                        'unit_price' => 500,
                        'discount_type' => 'none',
                        'discount_value' => 0,
                    ],
                ],
            ]);

        $invoice = SalesInvoice::query()
            ->where('company_id', $user->company_id)
            ->where('pos_session_id', $session->id)
            ->where('notes', 'VENTE ISSUE BROUILLON POS')
            ->firstOrFail();

        $saleResponse->assertRedirect(route('pos.receipt', $invoice));
        $this->assertDatabaseMissing('pos_drafts', [
            'id' => $draftId,
        ]);
    }

    public function test_cashier_can_delete_pos_draft_without_validating_it(): void
    {
        $user = User::query()->where('email', 'caissier@nema-erp.test')->firstOrFail();
        $cashAccount = CashAccount::query()->where('company_id', $user->company_id)->where('branch_id', $user->branch_id)->where('name', 'Caisse principale')->firstOrFail();
        $warehouse = Warehouse::query()->where('company_id', $user->company_id)->where('branch_id', $user->branch_id)->where('is_default', true)->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0002')->firstOrFail();

        $this->openSession($user, $cashAccount, $warehouse);
        $session = $this->currentSession($user);

        $draft = PosDraft::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'pos_session_id' => $session->id,
            'customer_id' => null,
            'label' => 'Commande a supprimer',
            'sale_date' => now()->toDateString(),
            'method' => 'cash',
            'reference' => 'BROUILLON-SUP-001',
            'notes' => 'SUPPRESSION POS TEST',
            'discount_type' => 'none',
            'discount_value' => 0,
            'items' => [[
                'product_id' => $product->id,
                'description' => 'Article suppression brouillon',
                'qty' => 1,
                'unit_price' => 700,
                'discount_type' => 'none',
                'discount_value' => 0,
            ]],
            'items_count' => 1,
            'total' => 700,
            'last_activity_at' => now(),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->deleteJson(route('pos.drafts.destroy', $draft))
            ->assertOk()
            ->assertJsonPath('message', 'Commande brouillon supprimee avec succes.');

        $this->assertDatabaseMissing('pos_drafts', [
            'id' => $draft->id,
        ]);
    }

    private function openSession(User $user, CashAccount $cashAccount, Warehouse $warehouse): void
    {
        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('pos.open'), [
                'cash_account_id' => $cashAccount->id,
                'warehouse_id' => $warehouse->id,
                'opening_amount' => 0,
            ])
            ->assertRedirect();
    }

    private function currentSession(User $user): PosSession
    {
        return PosSession::query()
            ->where('company_id', $user->company_id)
            ->where('opened_by', $user->id)
            ->where('status', 'open')
            ->latest('id')
            ->firstOrFail();
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}
