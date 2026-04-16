<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Pos\Models\PosPreparationDisplay;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Pos\Models\PosPreparationTicket;
use App\Modules\Pos\Models\PosPreparationTicketItem;
use App\Modules\Pos\Models\PosSession;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Treasury\Models\CashAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosPreparationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_pos_sale_creates_preparation_ticket_visible_on_board_and_receipt(): void
    {
        $user = User::query()->where('email', 'caissier@nema-erp.test')->firstOrFail();
        $cashAccount = CashAccount::query()->where('company_id', $user->company_id)->where('branch_id', $user->branch_id)->where('name', 'Caisse principale')->firstOrFail();
        $warehouse = Warehouse::query()->where('company_id', $user->company_id)->where('branch_id', $user->branch_id)->where('is_default', true)->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $this->openSession($user, $cashAccount, $warehouse);
        $session = $this->currentSession($user);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('pos.sales.store'), [
                'sale_date' => now()->format('Y-m-d'),
                'method' => 'cash',
                'reference' => 'POS-PREP-001',
                'notes' => 'TEST-POS-PREP',
                'discount_type' => 'none',
                'discount_value' => 0,
                'cash_received_amount' => 500,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'description' => 'Sandwich caisse test',
                        'qty' => 1,
                        'unit_price' => 500,
                        'discount_type' => 'none',
                        'discount_value' => 0,
                    ],
                ],
            ])
            ->assertRedirect();

        $invoice = SalesInvoice::query()
            ->where('company_id', $user->company_id)
            ->where('notes', 'TEST-POS-PREP')
            ->firstOrFail();

        $ticket = PosPreparationTicket::query()
            ->with(['items.product', 'printer', 'display'])
            ->where('sales_invoice_id', $invoice->id)
            ->firstOrFail();

        $this->assertSame('queued', $ticket->status);
        $this->assertNotNull($ticket->printer_id);
        $this->assertNotNull($ticket->display_id);
        $this->assertSame($invoice->pos_session_id, $ticket->pos_session_id);
        $this->assertCount(1, $ticket->items);
        $this->assertSame('Sandwich caisse test', $ticket->items->first()->description);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('pos.preparation.index'))
            ->assertOk()
            ->assertSeeText($ticket->ticket_number)
            ->assertSeeText($invoice->invoice_number)
            ->assertSeeText('Sandwich caisse test');

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('pos.receipt', $invoice))
            ->assertOk()
            ->assertSeeText('Preparation')
            ->assertSeeText($ticket->ticket_number);
    }

    public function test_preparation_board_can_progress_ticket_and_print_it(): void
    {
        $user = User::query()->where('email', 'caissier@nema-erp.test')->firstOrFail();
        $cashAccount = CashAccount::query()->where('company_id', $user->company_id)->where('branch_id', $user->branch_id)->where('name', 'Caisse principale')->firstOrFail();
        $warehouse = Warehouse::query()->where('company_id', $user->company_id)->where('branch_id', $user->branch_id)->where('is_default', true)->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $this->openSession($user, $cashAccount, $warehouse);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('pos.sales.store'), [
                'sale_date' => now()->format('Y-m-d'),
                'method' => 'cash',
                'reference' => 'POS-PREP-002',
                'notes' => 'TEST-POS-PREP-STATUS',
                'discount_type' => 'none',
                'discount_value' => 0,
                'cash_received_amount' => 500,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'description' => 'Burger board test',
                        'qty' => 1,
                        'unit_price' => 500,
                        'discount_type' => 'none',
                        'discount_value' => 0,
                    ],
                ],
            ])
            ->assertRedirect();

        $invoice = SalesInvoice::query()
            ->where('company_id', $user->company_id)
            ->where('notes', 'TEST-POS-PREP-STATUS')
            ->firstOrFail();

        $ticket = PosPreparationTicket::query()->where('sales_invoice_id', $invoice->id)->firstOrFail();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('pos.preparation.update', $ticket), ['status' => 'in_progress'])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame('in_progress', $ticket->status);
        $this->assertNotNull($ticket->started_at);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('pos.preparation.update', $ticket), ['status' => 'ready'])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame('ready', $ticket->status);
        $this->assertNotNull($ticket->ready_at);
        $this->assertSame('ready', $ticket->items()->firstOrFail()->status);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('pos.preparation.print', $ticket))
            ->assertOk()
            ->assertSeeText('BON PREPARATION')
            ->assertSeeText($ticket->ticket_number)
            ->assertSeeText($invoice->invoice_number)
            ->assertSeeText('Burger board test');
    }

    public function test_preparation_display_shows_only_its_tickets_and_supports_display_flow(): void
    {
        $user = User::query()->where('email', 'caissier@nema-erp.test')->firstOrFail();
        $cashAccount = CashAccount::query()->where('company_id', $user->company_id)->where('branch_id', $user->branch_id)->where('name', 'Caisse principale')->firstOrFail();
        $warehouse = Warehouse::query()->where('company_id', $user->company_id)->where('branch_id', $user->branch_id)->where('is_default', true)->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $this->openSession($user, $cashAccount, $warehouse);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('pos.sales.store'), [
                'sale_date' => now()->format('Y-m-d'),
                'method' => 'cash',
                'reference' => 'POS-PREP-003',
                'notes' => 'TEST-POS-PREP-DISPLAY',
                'discount_type' => 'none',
                'discount_value' => 0,
                'cash_received_amount' => 500,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'description' => 'Wrap display test',
                        'qty' => 1,
                        'unit_price' => 500,
                        'discount_type' => 'none',
                        'discount_value' => 0,
                    ],
                ],
            ])
            ->assertRedirect();

        $invoice = SalesInvoice::query()
            ->where('company_id', $user->company_id)
            ->where('notes', 'TEST-POS-PREP-DISPLAY')
            ->firstOrFail();

        $ticket = PosPreparationTicket::query()
            ->with(['items', 'display'])
            ->where('sales_invoice_id', $invoice->id)
            ->firstOrFail();

        $display = $ticket->display;
        $this->assertNotNull($display);

        $otherDisplay = PosPreparationDisplay::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'code' => 'DSP-TEST-SECOND',
            'name' => 'Display secondaire test',
            'target_area' => 'Bar',
            'display_mode' => 'counter',
            'endpoint' => 'https://display.nema.test/bar',
            'refresh_seconds' => 9,
            'prep_time_target_minutes' => 5,
            'is_active' => true,
        ]);

        $otherTicket = PosPreparationTicket::query()->create([
            'tenant_id' => $ticket->tenant_id,
            'company_id' => $ticket->company_id,
            'branch_id' => $ticket->branch_id,
            'pos_session_id' => $ticket->pos_session_id,
            'sales_invoice_id' => $ticket->sales_invoice_id,
            'pos_profile_id' => $ticket->pos_profile_id,
            'printer_id' => $ticket->printer_id,
            'display_id' => $otherDisplay->id,
            'ticket_number' => 'PREP-SECOND-TEST',
            'target_area' => 'Bar',
            'status' => 'queued',
            'priority' => 'normal',
            'target_minutes' => 5,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        PosPreparationTicketItem::query()->create([
            'preparation_ticket_id' => $otherTicket->id,
            'sales_invoice_item_id' => $invoice->items()->firstOrFail()->id,
            'product_id' => $product->id,
            'description' => 'Ticket autre display',
            'qty' => 1,
            'status' => 'queued',
            'menu_category_labels' => ['Bar'],
            'tag_labels' => [],
        ]);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('pos.preparation.display', $display))
            ->assertOk()
            ->assertSeeText('Display plein ecran')
            ->assertSeeText($display->name)
            ->assertSeeText($ticket->ticket_number)
            ->assertSeeText('Wrap display test')
            ->assertDontSeeText('PREP-SECOND-TEST')
            ->assertDontSeeText('Ticket autre display');

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('pos.preparation.update', $ticket), ['status' => 'in_progress'])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame('in_progress', $ticket->status);
        $this->assertNotNull($ticket->started_at);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('pos.preparation.update', $ticket), ['status' => 'ready'])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame('ready', $ticket->status);
        $this->assertNotNull($ticket->ready_at);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('pos.preparation.update', $ticket), ['status' => 'served'])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame('served', $ticket->status);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('pos.preparation.display', $display))
            ->assertOk()
            ->assertDontSeeText($ticket->ticket_number)
            ->assertDontSeeText('Wrap display test')
            ->assertSeeText('Aucun ticket pret');
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
