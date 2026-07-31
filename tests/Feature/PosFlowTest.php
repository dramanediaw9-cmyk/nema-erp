<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Audit\Models\ActivityLog;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Partners\Models\Partner;
use App\Modules\Pos\Models\PosComboChoice;
use App\Modules\Pos\Models\PosMenuCategory;
use App\Modules\Pos\Models\PosNoteTemplate;
use App\Modules\Pos\Models\PosPaymentMethod;
use App\Modules\Pos\Models\PosProductTag;
use App\Modules\Pos\Models\PosProfile;
use App\Modules\Pos\Models\PosSession;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Treasury\Models\CashAccount;
use App\Modules\Treasury\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PosFlowTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_cashier_opening_session_records_required_opening_details(): void
    {
        $user = User::query()->where('email', 'caissier@nema-erp.test')->firstOrFail();
        $cashAccount = CashAccount::query()->where('company_id', $user->company_id)->where('branch_id', $user->branch_id)->where('name', 'Caisse principale')->firstOrFail();
        $warehouse = Warehouse::query()->where('company_id', $user->company_id)->where('branch_id', $user->branch_id)->where('is_default', true)->firstOrFail();

        $this->openSession($user, $cashAccount, $warehouse, 50000);

        $session = $this->currentSession($user);

        $this->assertEqualsWithDelta(50000, (float) $session->opening_amount, 0.001);
        $this->assertSame($user->id, $session->opened_by);
        $this->assertSame('open', $session->status);
        $this->assertNotNull($session->opened_at);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('pos.index'))
            ->assertOk()
            ->assertSeeText('Ouverte')
            ->assertSeeText('50 000')
            ->assertSeeText($user->name);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('pos.show', $session))
            ->assertOk()
            ->assertSeeText('Ouverte')
            ->assertSeeText('Montant initial')
            ->assertSeeText('50 000 XOF')
            ->assertSeeText($user->name);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('pos.session.print', $session))
            ->assertOk();
    }

    public function test_pos_stock_availability_refresh_reflects_stock_adjustments(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $cashAccount = CashAccount::query()->where('company_id', $user->company_id)->where('branch_id', $user->branch_id)->where('name', 'Caisse principale')->firstOrFail();
        $warehouse = Warehouse::query()->where('company_id', $user->company_id)->where('branch_id', $user->branch_id)->where('is_default', true)->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $this->openSession($user, $cashAccount, $warehouse, 0);
        $session = $this->currentSession($user);

        $before = $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->getJson(route('pos.stock-availability', ['session' => $session->id]))
            ->assertOk();

        $initialQty = (float) collect($before->json('products'))
            ->firstWhere('id', $product->id)['available_qty'];

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('stock.adjustments.store'), [
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'movement_date' => now()->toDateString(),
                'direction' => 'in',
                'quantity' => 7,
                'unit_cost' => 300,
                'reason' => 'Reprise stock caisse',
                'notes' => 'Actualisation test POS',
            ])
            ->assertRedirect(route('stock.index'));

        $after = $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->getJson(route('pos.stock-availability', ['session' => $session->id]))
            ->assertOk()
            ->assertJsonPath('warehouse_id', $warehouse->id);

        $updatedQty = (float) collect($after->json('products'))
            ->firstWhere('id', $product->id)['available_qty'];

        $this->assertEqualsWithDelta($initialQty + 7, $updatedQty, 0.001);
    }

    public function test_session_screen_displays_dense_pos_orders_statuses_and_detail_payload(): void
    {
        $user = User::query()->where('email', 'caissier@nema-erp.test')->firstOrFail();
        $cashAccount = CashAccount::query()->where('company_id', $user->company_id)->where('branch_id', $user->branch_id)->where('name', 'Caisse principale')->firstOrFail();
        $warehouse = Warehouse::query()->where('company_id', $user->company_id)->where('branch_id', $user->branch_id)->where('is_default', true)->firstOrFail();
        $product = Product::query()->create([
            'company_id' => $user->company_id,
            'sku' => 'POS-SERVICE-DENSE',
            'name' => 'Service test liste POS dense',
            'unit' => 'unite',
            'type' => 'service',
            'sale_ok' => true,
            'purchase_ok' => false,
            'is_active' => true,
            'sale_price' => 100,
            'purchase_price' => 0,
        ]);

        $this->openSession($user, $cashAccount, $warehouse, 0);
        $session = $this->currentSession($user);

        for ($index = 0; $index < 16; $index++) {
            $payload = [
                'sale_date' => now()->format('Y-m-d'),
                'method' => 'cash',
                'reference' => 'DENSE-'.$index,
                'notes' => 'TEST-POS-DENSE-'.$index,
                'discount_type' => 'none',
                'discount_value' => 0,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'description' => 'Ligne POS dense '.$index,
                        'qty' => 1,
                        'unit_price' => 100 + $index,
                        'discount_type' => 'none',
                        'discount_value' => 0,
                    ],
                ],
            ];

            if ($index === 0) {
                $payload['cash_received_amount'] = 50;
                $payload['payments'] = [
                    ['method' => 'cash', 'amount' => 50, 'cash_account_id' => $cashAccount->id],
                ];
            } else {
                $payload['cash_received_amount'] = 100 + $index;
            }

            $this->actingAs($user)
                ->withSession($this->workspaceSession($user))
                ->post(route('pos.sales.store'), $payload)
                ->assertRedirect();
        }

        $invoiceToReturn = SalesInvoice::query()
            ->with('items')
            ->where('company_id', $user->company_id)
            ->where('pos_session_id', $session->id)
            ->where('notes', 'TEST-POS-DENSE-1')
            ->firstOrFail();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('pos.returns.store', $invoiceToReturn), [
                'return_date' => now()->format('Y-m-d'),
                'method' => 'cash',
                'reference' => 'DENSE-RETURN',
                'notes' => 'Retour test liste dense',
                'return_mode' => 'partial',
                'items' => [
                    [
                        'sales_invoice_item_id' => $invoiceToReturn->items->first()->id,
                        'qty' => 1,
                    ],
                ],
            ])
            ->assertRedirect(route('pos.show', $session));

        $response = $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('pos.show', $session))
            ->assertOk()
            ->assertSee('data-layout-mode="focus"', false)
            ->assertSee('data-focus-active="true"', false)
            ->assertSee('data-sidebar-collapse-toggle', false)
            ->assertSee('data-focus-toggle', false)
            ->assertSee('pos-command-shell', false)
            ->assertSee('data-pos-command-search', false)
            ->assertSee('pos-ticket-detail', false)
            ->assertSeeText('F2 recherche')
            ->assertSeeText('Payer')
            ->assertSeeText('Remboursement')
            ->assertSeeText('Paye')
            ->assertSeeText('Partiel')
            ->assertSeeText('Rembourse')
            ->assertSeeText('Ligne POS dense');

        $this->assertGreaterThanOrEqual(16, substr_count($response->getContent(), 'data-ticket-row'));
    }

    public function test_cashier_can_open_session_sell_and_close_pos_shift_with_breakdown(): void
    {
        $user = User::query()->where('email', 'caissier@nema-erp.test')->firstOrFail();
        $cashAccount = CashAccount::query()->where('company_id', $user->company_id)->where('branch_id', $user->branch_id)->where('name', 'Caisse principale')->firstOrFail();
        $warehouse = Warehouse::query()->where('company_id', $user->company_id)->where('branch_id', $user->branch_id)->where('is_default', true)->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $initialStock = $this->stockBalance($user->company_id, $user->branch_id, $product->id, $warehouse->id);

        $this->openSession($user, $cashAccount, $warehouse, 0, ['5000' => 2]);

        $session = $this->currentSession($user);
        $this->assertEqualsWithDelta(10000, (float) $session->opening_amount, 0.001);
        $this->assertSame(2, (int) ($session->opening_cash_breakdown['5000'] ?? 0));

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('pos.count-sheet', $session))
            ->assertOk()
            ->assertSeeText('Comptage caisse')
            ->assertSeeText('Billet 10 000');

        $response = $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('pos.sales.store'), [
                'sale_date' => now()->format('Y-m-d'),
                'method' => 'cash',
                'reference' => 'POS-TEST-001',
                'notes' => 'TEST-POS-SALE',
                'discount_type' => 'none',
                'discount_value' => 0,
                'cash_received_amount' => 1000,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'description' => 'Vente comptoir test',
                        'qty' => 2,
                        'unit_price' => 500,
                        'discount_type' => 'none',
                        'discount_value' => 0,
                    ],
                ],
            ]);

        $invoice = SalesInvoice::query()
            ->where('company_id', $user->company_id)
            ->where('pos_session_id', $session->id)
            ->where('sale_channel', 'pos')
            ->where('notes', 'TEST-POS-SALE')
            ->firstOrFail();

        $response->assertRedirect(route('pos.receipt', $invoice));
        $this->assertSame('validated', $invoice->status);
        $this->assertSame('paid', $invoice->payment_status);
        $this->assertEqualsWithDelta(1000, (float) $invoice->subtotal, 0.001);
        $this->assertEqualsWithDelta(0, (float) $invoice->discount_total, 0.001);
        $this->assertEqualsWithDelta(1000, (float) $invoice->total, 0.001);
        $this->assertEqualsWithDelta(1000, (float) $invoice->amount_paid, 0.001);
        $this->assertEqualsWithDelta(0, (float) $invoice->balance_due, 0.001);
        $this->assertMatchesRegularExpression('/^POS-BKO-\d{4}-\d{5}$/', $session->session_number);

        $this->assertDatabaseHas('payments', [
            'company_id' => $user->company_id,
            'pos_session_id' => $session->id,
            'reference' => 'POS-TEST-001',
            'amount' => 1000,
        ]);

        $updatedStock = $this->stockBalance($user->company_id, $user->branch_id, $product->id, $warehouse->id);
        $this->assertEqualsWithDelta($initialStock - 2, $updatedStock, 0.001);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('pos.close', $session), [
                'counted_methods' => [
                    'cash' => 0,
                    'mobile_money' => 0,
                    'bank_transfer' => 0,
                    'other' => 0,
                ],
                'closing_cash_breakdown' => [
                    '10000' => 1,
                    '1000' => 1,
                ],
                'variance_notes' => [
                    'cash' => '',
                    'mobile_money' => '',
                    'bank_transfer' => '',
                    'other' => '',
                ],
                'closing_notes' => 'Cloture test POS',
            ])
            ->assertRedirect(route('pos.show', $session));

        $session->refresh();

        $this->assertSame('closed', $session->status);
        $this->assertEqualsWithDelta(11000, (float) $session->expected_amount, 0.001);
        $this->assertEqualsWithDelta(11000, (float) $session->closing_amount, 0.001);
        $this->assertEqualsWithDelta(0, (float) $session->variance_amount, 0.001);
        $this->assertSame(11000.0, (float) ($session->expected_breakdown['cash'] ?? 0));
        $this->assertSame(11000.0, (float) ($session->counted_breakdown['cash'] ?? 0));
        $this->assertSame(1, (int) ($session->closing_cash_breakdown['10000'] ?? 0));
        $this->assertSame(1, (int) ($session->closing_cash_breakdown['1000'] ?? 0));
    }

    public function test_closed_pos_session_locks_sales_payments_returns_and_stock_records(): void
    {
        $user = User::query()->where('email', 'caissier@nema-erp.test')->firstOrFail();
        $cashAccount = CashAccount::query()->where('company_id', $user->company_id)->where('branch_id', $user->branch_id)->where('name', 'Caisse principale')->firstOrFail();
        $warehouse = Warehouse::query()->where('company_id', $user->company_id)->where('branch_id', $user->branch_id)->where('is_default', true)->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $this->openSession($user, $cashAccount, $warehouse, 50000);
        $session = $this->currentSession($user);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('pos.sales.store'), [
                'sale_date' => now()->format('Y-m-d'),
                'method' => 'cash',
                'reference' => 'POS-LOCK-001',
                'notes' => 'TEST-POS-LOCK',
                'discount_type' => 'none',
                'discount_value' => 0,
                'cash_received_amount' => 500,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'description' => 'Ticket verrouillage',
                        'qty' => 1,
                        'unit_price' => 500,
                        'discount_type' => 'none',
                        'discount_value' => 0,
                    ],
                ],
            ])
            ->assertRedirect();

        $invoice = SalesInvoice::query()
            ->with('items')
            ->where('company_id', $user->company_id)
            ->where('pos_session_id', $session->id)
            ->where('notes', 'TEST-POS-LOCK')
            ->firstOrFail();
        $payment = Payment::query()
            ->where('company_id', $user->company_id)
            ->where('pos_session_id', $session->id)
            ->where('reference', 'POS-LOCK-001')
            ->firstOrFail();
        $movement = StockMovement::query()
            ->where('company_id', $user->company_id)
            ->where('reference_type', SalesInvoice::class)
            ->where('reference_id', $invoice->id)
            ->firstOrFail();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('pos.close', $session), [
                'counted_methods' => [
                    'cash' => 50500,
                    'mobile_money' => 0,
                    'bank_transfer' => 0,
                    'other' => 0,
                ],
                'variance_notes' => [
                    'cash' => '',
                    'mobile_money' => '',
                    'bank_transfer' => '',
                    'other' => '',
                ],
                'closing_notes' => 'Cloture verrouillage',
            ])
            ->assertRedirect(route('pos.show', $session));

        $session->refresh();
        $this->assertSame('closed', $session->status);

        $this->assertPosLockBlocks(fn () => $invoice->fresh()->update(['notes' => 'Modification interdite']));
        $this->assertPosLockBlocks(fn () => $invoice->fresh()->delete());
        $this->assertPosLockBlocks(fn () => $invoice->items()->firstOrFail()->update(['qty' => 2]));
        $this->assertPosLockBlocks(fn () => $payment->fresh()->update(['amount' => 400]));
        $this->assertPosLockBlocks(fn () => $payment->allocations()->firstOrFail()->update(['allocated_amount' => 400]));
        $this->assertPosLockBlocks(fn () => $movement->fresh()->update(['quantity_out' => 2]));

        $this->openSession($user, $cashAccount, $warehouse, 0);
        $newSession = $this->currentSession($user);
        $invoiceItem = $invoice->items()->firstOrFail();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->from(route('pos.returns.create', ['sale' => $invoice, 'session' => $newSession->id]))
            ->post(route('pos.returns.store', $invoice), [
                'session' => $newSession->id,
                'return_date' => now()->format('Y-m-d'),
                'method' => 'cash',
                'return_mode' => 'partial',
                'items' => [
                    [
                        'sales_invoice_item_id' => $invoiceItem->id,
                        'qty' => 1,
                    ],
                ],
            ])
            ->assertRedirect(route('pos.returns.create', ['sale' => $invoice, 'session' => $newSession->id]))
            ->assertSessionHasErrors('sale');
    }

    public function test_only_supervision_can_unlock_closed_pos_session_with_audit_reason(): void
    {
        $cashier = User::query()->where('email', 'caissier@nema-erp.test')->firstOrFail();
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $director = User::query()->where('email', 'dg@nema-erp.test')->firstOrFail();
        $cashAccount = CashAccount::query()->where('company_id', $cashier->company_id)->where('branch_id', $cashier->branch_id)->where('name', 'Caisse principale')->firstOrFail();
        $warehouse = Warehouse::query()->where('company_id', $cashier->company_id)->where('branch_id', $cashier->branch_id)->where('is_default', true)->firstOrFail();

        $this->assertFalse($cashier->hasPermission('pos.sessions.unlock'));
        $this->assertTrue($manager->hasPermission('pos.sessions.unlock'));
        $this->assertTrue($director->hasPermission('pos.sessions.unlock'));

        $this->openSession($cashier, $cashAccount, $warehouse, 50000);
        $session = $this->currentSession($cashier);

        $this->actingAs($cashier)
            ->withSession($this->workspaceSession($cashier))
            ->post(route('pos.close', $session), [
                'counted_methods' => [
                    'cash' => 50000,
                    'mobile_money' => 0,
                    'bank_transfer' => 0,
                    'other' => 0,
                ],
                'variance_notes' => [
                    'cash' => '',
                    'mobile_money' => '',
                    'bank_transfer' => '',
                    'other' => '',
                ],
                'closing_notes' => 'Cloture avant deverrouillage',
            ])
            ->assertRedirect(route('pos.show', $session));

        $session->refresh();
        $this->assertSame('closed', $session->status);

        $this->actingAs($cashier)
            ->withSession($this->workspaceSession($cashier))
            ->post(route('pos.unlock', $session), [
                'unlock_reason' => 'Tentative non autorisee',
            ])
            ->assertForbidden();

        $reason = 'Correction controlee apres erreur de saisie validee par superviseur.';

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->post(route('pos.unlock', $session), [
                'unlock_reason' => $reason,
            ])
            ->assertRedirect(route('pos.show', $session));

        $session->refresh();
        $this->assertSame('open', $session->status);
        $this->assertSame($manager->id, $session->unlocked_by);
        $this->assertNotNull($session->unlocked_at);
        $this->assertSame($reason, $session->unlock_reason);

        $this->assertDatabaseHas('activity_logs', [
            'company_id' => $session->company_id,
            'branch_id' => $manager->branch_id,
            'user_id' => $manager->id,
            'action' => 'pos.session.unlock',
            'subject_type' => PosSession::class,
            'subject_id' => $session->id,
        ]);

        $activity = ActivityLog::query()
            ->where('action', 'pos.session.unlock')
            ->where('subject_id', $session->id)
            ->firstOrFail();
        $this->assertSame($reason, $activity->properties['unlock_reason'] ?? null);
        $this->assertSame('closed', $activity->properties['old_values']['status'] ?? null);
        $this->assertSame('open', $activity->properties['new_values']['status'] ?? null);
        $this->assertSame($reason, $activity->properties['reason'] ?? null);

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->get(route('pos.show', $session))
            ->assertOk()
            ->assertSeeText('Dernier deverrouillage')
            ->assertSeeText($manager->name)
            ->assertSeeText($reason);
    }

    public function test_cashier_can_apply_line_discount_global_discount_and_open_thermal_receipt(): void
    {
        $user = User::query()->where('email', 'caissier@nema-erp.test')->firstOrFail();
        $cashAccount = CashAccount::query()->where('company_id', $user->company_id)->where('branch_id', $user->branch_id)->where('name', 'Caisse principale')->firstOrFail();
        $warehouse = Warehouse::query()->where('company_id', $user->company_id)->where('branch_id', $user->branch_id)->where('is_default', true)->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $this->openSession($user, $cashAccount, $warehouse, 0);
        $session = $this->currentSession($user);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('pos.sales.store'), [
                'sale_date' => now()->format('Y-m-d'),
                'method' => 'mobile_money',
                'reference' => 'POS-DISCOUNT-001',
                'notes' => 'TEST-POS-DISCOUNT',
                'discount_type' => 'percent',
                'discount_value' => 10,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'description' => 'Ticket remise',
                        'qty' => 2,
                        'unit_price' => 500,
                        'discount_type' => 'fixed',
                        'discount_value' => 50,
                    ],
                ],
            ])
            ->assertRedirect();

        $invoice = SalesInvoice::query()
            ->where('company_id', $user->company_id)
            ->where('pos_session_id', $session->id)
            ->where('notes', 'TEST-POS-DISCOUNT')
            ->firstOrFail();

        $item = $invoice->items()->firstOrFail();

        $this->assertSame('percent', $invoice->discount_type);
        $this->assertEqualsWithDelta(10, (float) $invoice->discount_value, 0.001);
        $this->assertEqualsWithDelta(1000, (float) $invoice->subtotal, 0.001);
        $this->assertEqualsWithDelta(145, (float) $invoice->discount_total, 0.001);
        $this->assertEqualsWithDelta(855, (float) $invoice->total, 0.001);
        $this->assertEqualsWithDelta(855, (float) $invoice->amount_paid, 0.001);

        $this->assertEqualsWithDelta(1000, (float) $item->line_subtotal, 0.001);
        $this->assertSame('fixed', $item->discount_type);
        $this->assertEqualsWithDelta(50, (float) $item->discount_total, 0.001);
        $this->assertEqualsWithDelta(950, (float) $item->line_total, 0.001);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('pos.receipt.thermal', $invoice))
            ->assertOk()
            ->assertSeeText('Ticket de caisse')
            ->assertSeeText($invoice->invoice_number)
            ->assertSeeText('Remise')
            ->assertSeeText('855');
    }

    public function test_cashier_cannot_validate_pos_sale_when_cash_received_is_insufficient(): void
    {
        $user = User::query()->where('email', 'caissier@nema-erp.test')->firstOrFail();
        $cashAccount = CashAccount::query()->where('company_id', $user->company_id)->where('branch_id', $user->branch_id)->where('name', 'Caisse principale')->firstOrFail();
        $warehouse = Warehouse::query()->where('company_id', $user->company_id)->where('branch_id', $user->branch_id)->where('is_default', true)->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $this->openSession($user, $cashAccount, $warehouse, 0);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->from(route('pos.sales.create'))
            ->post(route('pos.sales.store'), [
                'sale_date' => now()->format('Y-m-d'),
                'method' => 'cash',
                'reference' => 'POS-CASH-LOW-001',
                'notes' => 'TEST-POS-CASH-LOW',
                'discount_type' => 'none',
                'discount_value' => 0,
                'cash_received_amount' => 900,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'description' => 'Ticket cash insuffisant',
                        'qty' => 2,
                        'unit_price' => 500,
                        'discount_type' => 'none',
                        'discount_value' => 0,
                    ],
                ],
            ])
            ->assertRedirect(route('pos.sales.create'))
            ->assertSessionHasErrors('cash_received_amount');

        $this->assertDatabaseMissing('sales_invoices', [
            'company_id' => $user->company_id,
            'notes' => 'TEST-POS-CASH-LOW',
        ]);
    }

    public function test_pos_sale_uses_physical_stock_even_when_sales_orders_reserve_inventory(): void
    {
        $user = User::query()->where('email', 'caissier@nema-erp.test')->firstOrFail();
        $cashAccount = CashAccount::query()->where('company_id', $user->company_id)->where('branch_id', $user->branch_id)->where('name', 'Caisse principale')->firstOrFail();
        $warehouse = Warehouse::query()->where('company_id', $user->company_id)->where('branch_id', $user->branch_id)->where('is_default', true)->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();
        $customer = Partner::query()
            ->customers()
            ->where('company_id', $user->company_id)
            ->firstOrFail();

        $physicalStock = $this->stockBalance($user->company_id, $user->branch_id, $product->id, $warehouse->id);
        $this->assertGreaterThanOrEqual(2, $physicalStock);

        $reservedQty = max(1, round($physicalStock - 1, 3));

        $order = SalesOrder::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'order_number' => 'CMD-RESERVE-POS-001',
            'order_date' => now()->toDateString(),
            'requested_delivery_date' => now()->toDateString(),
            'status' => 'confirmed',
            'subtotal' => $reservedQty * 500,
            'total' => $reservedQty * 500,
            'confirmed_at' => now(),
            'created_by' => $user->id,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'description' => 'Reservation prioritaire',
            'qty' => $reservedQty,
            'delivered_qty' => 0,
            'unit_price' => 500,
            'line_total' => $reservedQty * 500,
        ]);

        $this->openSession($user, $cashAccount, $warehouse, 0);

        $response = $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('pos.sales.store'), [
                'sale_date' => now()->format('Y-m-d'),
                'method' => 'cash',
                'reference' => 'POS-RESERVE-BYPASS-001',
                'notes' => 'TEST-POS-RESERVE-BYPASS',
                'discount_type' => 'none',
                'discount_value' => 0,
                'cash_received_amount' => 1000,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'description' => 'Vente comptoir reservee',
                        'qty' => 2,
                        'unit_price' => 500,
                        'discount_type' => 'none',
                        'discount_value' => 0,
                    ],
                ],
            ]);

        $invoice = SalesInvoice::query()
            ->where('company_id', $user->company_id)
            ->where('notes', 'TEST-POS-RESERVE-BYPASS')
            ->firstOrFail();

        $response->assertRedirect(route('pos.receipt', $invoice));
        $this->assertSame('validated', $invoice->status);
    }

    public function test_pos_form_submission_can_redirect_directly_to_thermal_receipt(): void
    {
        $user = User::query()->where('email', 'caissier@nema-erp.test')->firstOrFail();
        $cashAccount = CashAccount::query()->where('company_id', $user->company_id)->where('branch_id', $user->branch_id)->where('name', 'Caisse principale')->firstOrFail();
        $warehouse = Warehouse::query()->where('company_id', $user->company_id)->where('branch_id', $user->branch_id)->where('is_default', true)->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $this->openSession($user, $cashAccount, $warehouse, 0);

        $response = $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('pos.sales.store'), [
                'sale_date' => now()->format('Y-m-d'),
                'method' => 'cash',
                'reference' => 'POS-THERMAL-REDIRECT-001',
                'notes' => 'TEST-POS-THERMAL-REDIRECT',
                'discount_type' => 'none',
                'discount_value' => 0,
                'cash_received_amount' => 500,
                'print_thermal' => 1,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'description' => 'Ticket redirection thermique',
                        'qty' => 1,
                        'unit_price' => 500,
                        'discount_type' => 'none',
                        'discount_value' => 0,
                    ],
                ],
            ]);

        $invoice = SalesInvoice::query()
            ->where('company_id', $user->company_id)
            ->where('notes', 'TEST-POS-THERMAL-REDIRECT')
            ->firstOrFail();

        $expectedRedirect = route('pos.receipt.thermal', $invoice).'?'.http_build_query([
            'auto_print' => 1,
            'from_pos' => 1,
            'next' => route('pos.receipt', $invoice),
            'return_to' => route('pos.sales.create', ['session' => $invoice->pos_session_id]),
        ]);

        $response->assertRedirect($expectedRedirect);
    }

    public function test_pos_sale_screen_renders_mobile_friendly_submission_guards(): void
    {
        $user = User::query()->where('email', 'caissier@nema-erp.test')->firstOrFail();
        $cashAccount = CashAccount::query()->where('company_id', $user->company_id)->where('branch_id', $user->branch_id)->where('name', 'Caisse principale')->firstOrFail();
        $warehouse = Warehouse::query()->where('company_id', $user->company_id)->where('branch_id', $user->branch_id)->where('is_default', true)->firstOrFail();

        $this->openSession($user, $cashAccount, $warehouse, 0);

        $response = $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('pos.sales.create'));

        $response->assertOk();

        $html = $response->getContent();

        $this->assertIsString($html);
        $this->assertMatchesRegularExpression('/<form(?=[^>]*id="pos-sale-form")(?=[^>]*novalidate)[^>]*>/s', $html);
        $this->assertMatchesRegularExpression('/<input(?=[^>]*id="cash_received_amount")(?=[^>]*inputmode="decimal")(?=[^>]*type="text")[^>]*>/s', $html);
        $this->assertMatchesRegularExpression('/<button(?=[^>]*id="pos-submit-button")(?=[^>]*type="button")[^>]*>/s', $html);
        $this->assertStringContainsString('action="/point-de-vente/vente"', $html);
        $this->assertMatchesRegularExpression('/const serviceWorkerUrl = "\\\\?\/pos-sw\.js";/', $html);
        $this->assertStringContainsString('name="print_thermal" value="1"', $html);
        $this->assertStringContainsString("saleForm.addEventListener('submit'", $html);
        $this->assertStringContainsString("submitButton.addEventListener('click'", $html);
        $this->assertStringContainsString('const attemptSaleSubmission = () => {', $html);
        $this->assertStringContainsString('"available_qty":', $html);
        $this->assertStringContainsString('${esc(posVocabulary.stock || \'Stock\')} dispo', $html);
        $this->assertStringContainsString('const availableProductQty = (productId, currentLineUid = null) => {', $html);
        $this->assertStringContainsString('HTMLFormElement.prototype.submit.call(saleForm);', $html);
        $this->assertStringNotContainsString('const openThermalReceiptPopup = () => {', $html);
        $this->assertStringNotContainsString('Modules ERP', $html);
        $this->assertStringNotContainsString('Modules favoris', $html);
    }

    public function test_pos_sale_screen_uses_backoffice_profile_runtime_configuration(): void
    {
        $user = User::query()->where('email', 'caissier@nema-erp.test')->firstOrFail();
        $cashAccount = CashAccount::query()->where('company_id', $user->company_id)->where('branch_id', $user->branch_id)->where('name', 'Caisse principale')->firstOrFail();
        $warehouse = Warehouse::query()->where('company_id', $user->company_id)->where('branch_id', $user->branch_id)->where('is_default', true)->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        PosPaymentMethod::query()
            ->where('company_id', $user->company_id)
            ->where('method_code', 'other')
            ->update([
                'label' => 'Bon d achat fidelite',
                'transaction_label' => 'Bon d achat POS',
                'is_active' => true,
            ]);

        $noteTemplate = PosNoteTemplate::query()
            ->where('company_id', $user->company_id)
            ->where('code', 'NOTE-0001')
            ->firstOrFail();
        $noteTemplate->update([
            'content' => 'Merci pour votre passage en caisse premium.',
            'usage' => 'receipt',
            'is_active' => true,
        ]);

        PosMenuCategory::query()
            ->where('company_id', $user->company_id)
            ->where('code', 'CAT-0001')
            ->update([
                'name' => 'Snacking express',
                'color' => '#ff7a18',
                'product_ids' => [$product->id],
                'is_active' => true,
            ]);

        PosProductTag::query()
            ->where('company_id', $user->company_id)
            ->where('code', 'TAG-0001')
            ->update([
                'name' => 'Populaire',
                'color' => '#0f9d58',
                'product_ids' => [$product->id],
                'is_active' => true,
            ]);

        PosComboChoice::query()
            ->where('company_id', $user->company_id)
            ->where('code', 'CBO-0001')
            ->update([
                'name' => 'Combo signature caisse',
                'parent_product_id' => $product->id,
                'pricing_mode' => 'fixed',
                'price_override' => 1750,
                'is_active' => true,
            ]);

        PosProfile::query()
            ->where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->update(['is_default' => false]);

        $profile = PosProfile::query()
            ->where('company_id', $user->company_id)
            ->where('code', 'POS-0001')
            ->firstOrFail();

        $profile->update([
            'branch_id' => $user->branch_id,
            'warehouse_id' => $warehouse->id,
            'cash_account_id' => $cashAccount->id,
            'note_template_id' => $noteTemplate->id,
            'name' => 'Profil caisse premium',
            'active_payment_methods' => ['cash', 'other'],
            'allow_draft_orders' => false,
            'auto_print_receipt' => false,
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->openSession($user, $cashAccount, $warehouse, 0);

        $response = $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('pos.sales.create'));

        $response->assertOk()
            ->assertSeeText('Profil caisse premium')
            ->assertSeeText('Bon d achat fidelite')
            ->assertDontSeeText('Cheque')
            ->assertSeeText('Snacking express')
            ->assertDontSeeText('Mettre en attente');

        $html = $response->getContent();

        $this->assertIsString($html);
        $this->assertStringContainsString('Merci pour votre passage en caisse premium.', $html);
        $this->assertStringContainsString('name="print_thermal" value="0"', $html);
        $this->assertStringContainsString('Combo signature caisse', $html);
        $this->assertStringContainsString('Populaire', $html);
        $this->assertStringContainsString('Bon d achat fidelite', $html);
        $this->assertStringContainsString('"allow_draft_orders":false', $html);
        $this->assertStringContainsString('"auto_print_receipt":false', $html);
    }

    public function test_thermal_receipt_keeps_print_actions_visible_for_touch_devices(): void
    {
        $user = User::query()->where('email', 'caissier@nema-erp.test')->firstOrFail();
        $cashAccount = CashAccount::query()->where('company_id', $user->company_id)->where('branch_id', $user->branch_id)->where('name', 'Caisse principale')->firstOrFail();
        $warehouse = Warehouse::query()->where('company_id', $user->company_id)->where('branch_id', $user->branch_id)->where('is_default', true)->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $this->openSession($user, $cashAccount, $warehouse, 0);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('pos.sales.store'), [
                'sale_date' => now()->format('Y-m-d'),
                'method' => 'cash',
                'reference' => 'POS-THERMAL-MOBILE-001',
                'notes' => 'TEST-POS-THERMAL-MOBILE',
                'discount_type' => 'none',
                'discount_value' => 0,
                'cash_received_amount' => 500,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'description' => 'Ticket thermique mobile',
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
            ->where('notes', 'TEST-POS-THERMAL-MOBILE')
            ->firstOrFail();

        $response = $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('pos.receipt.thermal', $invoice).'?'.http_build_query([
                'auto_print' => 1,
                'from_pos' => 1,
                'next' => route('pos.receipt', $invoice),
                'return_to' => route('pos.sales.create', ['session' => $invoice->pos_session_id]),
            ]));

        $response->assertOk()
            ->assertSeeText('Ticket pret a imprimer')
            ->assertSeeText('appuyez sur')
            ->assertSeeText('Ticket detaille')
            ->assertSeeText('Retour caisse');

        $html = $response->getContent();

        $this->assertIsString($html);
        $this->assertStringContainsString('const canAutoPrint = supportsFinePointer && !hasTouchInput;', $html);
        $this->assertStringContainsString("window.addEventListener('afterprint'", $html);
        $this->assertStringContainsString('window.location.replace(cashierReturnUrl);', $html);
    }

    public function test_cashier_can_record_mixed_pos_payment_and_show_change_on_receipts(): void
    {
        $user = User::query()->where('email', 'caissier@nema-erp.test')->firstOrFail();
        $cashAccount = CashAccount::query()->where('company_id', $user->company_id)->where('branch_id', $user->branch_id)->where('name', 'Caisse principale')->firstOrFail();
        $warehouse = Warehouse::query()->where('company_id', $user->company_id)->where('branch_id', $user->branch_id)->where('is_default', true)->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $this->openSession($user, $cashAccount, $warehouse, 0);
        $session = $this->currentSession($user);

        $response = $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('pos.sales.store'), [
                'sale_date' => now()->format('Y-m-d'),
                'method' => 'wave',
                'reference' => 'POS-MIXED-001',
                'notes' => 'TEST-POS-MIXED',
                'discount_type' => 'none',
                'discount_value' => 0,
                'cash_received_amount' => 500,
                'payments' => [
                    [
                        'method' => 'wave',
                        'amount' => 600,
                    ],
                    [
                        'method' => 'cash',
                        'amount' => 400,
                        'cash_account_id' => $cashAccount->id,
                    ],
                ],
                'items' => [
                    [
                        'product_id' => $product->id,
                        'description' => 'Ticket mixte',
                        'qty' => 2,
                        'unit_price' => 500,
                        'discount_type' => 'none',
                        'discount_value' => 0,
                    ],
                ],
            ]);

        $invoice = SalesInvoice::query()
            ->where('company_id', $user->company_id)
            ->where('pos_session_id', $session->id)
            ->where('notes', 'TEST-POS-MIXED')
            ->firstOrFail();

        $response->assertRedirect(route('pos.receipt', $invoice));
        $this->assertEqualsWithDelta(1000, (float) $invoice->total, 0.001);
        $this->assertEqualsWithDelta(1000, (float) $invoice->amount_paid, 0.001);
        $this->assertEqualsWithDelta(500, (float) $invoice->pos_cash_received, 0.001);
        $this->assertEqualsWithDelta(100, (float) $invoice->pos_change_due, 0.001);

        $this->assertDatabaseHas('payments', [
            'company_id' => $user->company_id,
            'pos_session_id' => $session->id,
            'method' => 'wave',
            'amount' => 600,
        ]);
        $this->assertDatabaseHas('payments', [
            'company_id' => $user->company_id,
            'pos_session_id' => $session->id,
            'method' => 'cash',
            'amount' => 400,
        ]);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('pos.receipt', $invoice))
            ->assertOk()
            ->assertSeeText('Mixte')
            ->assertSeeText('Wave')
            ->assertSeeText('Monnaie rendue')
            ->assertSeeText('100');

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('pos.receipt.thermal', $invoice))
            ->assertOk()
            ->assertSeeText('Mixte')
            ->assertSeeText('Montant recu')
            ->assertSeeText('Monnaie rendue');
    }

    public function test_cashier_can_record_partial_pos_payment_and_receipt_shows_balance_due(): void
    {
        $user = User::query()->where('email', 'caissier@nema-erp.test')->firstOrFail();
        $cashAccount = CashAccount::query()->where('company_id', $user->company_id)->where('branch_id', $user->branch_id)->where('name', 'Caisse principale')->firstOrFail();
        $warehouse = Warehouse::query()->where('company_id', $user->company_id)->where('branch_id', $user->branch_id)->where('is_default', true)->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $this->openSession($user, $cashAccount, $warehouse, 0);
        $session = $this->currentSession($user);

        $response = $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('pos.sales.store'), [
                'sale_date' => now()->format('Y-m-d'),
                'method' => 'cash',
                'reference' => 'POS-PARTIAL-001',
                'notes' => 'TEST-POS-PARTIAL',
                'discount_type' => 'none',
                'discount_value' => 0,
                'cash_received_amount' => 400,
                'payments' => [
                    [
                        'method' => 'cash',
                        'amount' => 400,
                        'cash_account_id' => $cashAccount->id,
                    ],
                ],
                'items' => [
                    [
                        'product_id' => $product->id,
                        'description' => 'Ticket paiement partiel',
                        'qty' => 2,
                        'unit_price' => 500,
                        'discount_type' => 'none',
                        'discount_value' => 0,
                    ],
                ],
            ]);

        $invoice = SalesInvoice::query()
            ->where('company_id', $user->company_id)
            ->where('pos_session_id', $session->id)
            ->where('notes', 'TEST-POS-PARTIAL')
            ->firstOrFail();

        $response->assertRedirect(route('pos.receipt', $invoice));
        $this->assertSame('partial', $invoice->payment_status);
        $this->assertEqualsWithDelta(1000, (float) $invoice->total, 0.001);
        $this->assertEqualsWithDelta(400, (float) $invoice->amount_paid, 0.001);
        $this->assertEqualsWithDelta(600, (float) $invoice->balance_due, 0.001);

        $this->assertDatabaseHas('payments', [
            'company_id' => $user->company_id,
            'pos_session_id' => $session->id,
            'method' => 'cash',
            'amount' => 400,
        ]);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('pos.receipt', $invoice))
            ->assertOk()
            ->assertSeeText('Encaisse')
            ->assertSeeText('400')
            ->assertSeeText('Reste')
            ->assertSeeText('600');

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('pos.close', $session), [
                'counted_methods' => [
                    'cash' => 400,
                    'mobile_money' => 0,
                    'bank_transfer' => 0,
                    'other' => 0,
                ],
                'variance_notes' => [
                    'cash' => '',
                    'mobile_money' => '',
                    'bank_transfer' => '',
                    'other' => '',
                ],
                'closing_notes' => 'Cloture avec ticket partiel',
            ])
            ->assertRedirect(route('pos.show', $session));

        $session->refresh();
        $this->assertSame('closed', $session->status);
        $this->assertEqualsWithDelta(400, (float) $session->expected_amount, 0.001);
        $this->assertEqualsWithDelta(400, (float) ($session->expected_breakdown['cash'] ?? 0), 0.001);
    }

    public function test_pos_sync_key_prevents_duplicate_ticket_creation_when_resynchronised(): void
    {
        $user = User::query()->where('email', 'caissier@nema-erp.test')->firstOrFail();
        $cashAccount = CashAccount::query()->where('company_id', $user->company_id)->where('branch_id', $user->branch_id)->where('name', 'Caisse principale')->firstOrFail();
        $warehouse = Warehouse::query()->where('company_id', $user->company_id)->where('branch_id', $user->branch_id)->where('is_default', true)->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $this->openSession($user, $cashAccount, $warehouse, 0);
        $session = $this->currentSession($user);

        $payload = [
            'sale_date' => now()->format('Y-m-d'),
            'method' => 'cash',
            'reference' => 'POS-OFFLINE-SYNC-001',
            'notes' => 'TEST-POS-OFFLINE-SYNC',
            'discount_type' => 'none',
            'discount_value' => 0,
            'cash_received_amount' => 1000,
            'pos_sync_key' => 'pos-offline-sync-key-001',
            'items' => [
                [
                    'product_id' => $product->id,
                    'description' => 'Ticket hors ligne resynchronise',
                    'qty' => 2,
                    'unit_price' => 500,
                    'discount_type' => 'none',
                    'discount_value' => 0,
                ],
            ],
        ];

        $first = $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->postJson(route('pos.sales.store'), $payload);

        $first->assertCreated()
            ->assertJsonPath('invoice.already_processed', false);

        $invoiceId = $first->json('invoice.id');

        $second = $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->postJson(route('pos.sales.store'), $payload);

        $second->assertOk()
            ->assertJsonPath('invoice.id', $invoiceId)
            ->assertJsonPath('invoice.already_processed', true);

        $this->assertSame(1, SalesInvoice::query()
            ->where('company_id', $user->company_id)
            ->where('pos_session_id', $session->id)
            ->where('pos_sync_key', 'pos-offline-sync-key-001')
            ->count());

        $this->assertSame(1, DB::table('payments')
            ->where('company_id', $user->company_id)
            ->where('pos_session_id', $session->id)
            ->where('reference', 'POS-OFFLINE-SYNC-001')
            ->count());
    }

    public function test_pos_json_sale_response_returns_relative_receipt_urls(): void
    {
        $user = User::query()->where('email', 'caissier@nema-erp.test')->firstOrFail();
        $cashAccount = CashAccount::query()->where('company_id', $user->company_id)->where('branch_id', $user->branch_id)->where('name', 'Caisse principale')->firstOrFail();
        $warehouse = Warehouse::query()->where('company_id', $user->company_id)->where('branch_id', $user->branch_id)->where('is_default', true)->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $this->openSession($user, $cashAccount, $warehouse, 0);

        $response = $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->postJson(route('pos.sales.store'), [
                'sale_date' => now()->format('Y-m-d'),
                'method' => 'cash',
                'reference' => 'POS-JSON-RELATIVE-001',
                'notes' => 'TEST-POS-JSON-RELATIVE',
                'discount_type' => 'none',
                'discount_value' => 0,
                'cash_received_amount' => 500,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'description' => 'Ticket json relatif',
                        'qty' => 1,
                        'unit_price' => 500,
                        'discount_type' => 'none',
                        'discount_value' => 0,
                    ],
                ],
            ]);

        $response->assertCreated();

        $receiptUrl = $response->json('invoice.receipt_url');
        $thermalReceiptUrl = $response->json('invoice.thermal_receipt_url');

        $this->assertIsString($receiptUrl);
        $this->assertIsString($thermalReceiptUrl);
        $this->assertStringStartsWith('/point-de-vente/tickets/', $receiptUrl);
        $this->assertStringStartsWith('/point-de-vente/tickets/', $thermalReceiptUrl);
    }

    public function test_operations_officer_can_enter_another_open_branch_session_when_session_id_is_provided(): void
    {
        $cashier = User::query()->where('email', 'caissier@nema-erp.test')->firstOrFail();
        $operator = User::query()->where('email', 'ops@nema-erp.test')->firstOrFail();
        $cashAccount = CashAccount::query()->where('company_id', $cashier->company_id)->where('branch_id', $cashier->branch_id)->where('name', 'Caisse principale')->firstOrFail();
        $warehouse = Warehouse::query()->where('company_id', $cashier->company_id)->where('branch_id', $cashier->branch_id)->where('is_default', true)->firstOrFail();
        $product = Product::query()->where('company_id', $cashier->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $this->openSession($cashier, $cashAccount, $warehouse, 0);
        $session = $this->currentSession($cashier);

        $this->actingAs($operator)
            ->withSession($this->workspaceSession($operator))
            ->get(route('pos.sales.create', ['session' => $session->id]))
            ->assertOk()
            ->assertSeeText($session->session_number)
            ->assertSeeText('Panier en cours');

        $response = $this->actingAs($operator)
            ->withSession($this->workspaceSession($operator))
            ->post(route('pos.sales.store'), [
                'pos_session_id' => $session->id,
                'sale_date' => now()->format('Y-m-d'),
                'method' => 'cash',
                'reference' => 'POS-SHARED-SESSION-001',
                'notes' => 'TEST-POS-SHARED-SESSION',
                'discount_type' => 'none',
                'discount_value' => 0,
                'cash_received_amount' => 500,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'description' => 'Vente sur session ouverte par un autre operateur',
                        'qty' => 1,
                        'unit_price' => 500,
                        'discount_type' => 'none',
                        'discount_value' => 0,
                    ],
                ],
            ]);

        $invoice = SalesInvoice::query()
            ->where('company_id', $cashier->company_id)
            ->where('pos_session_id', $session->id)
            ->where('notes', 'TEST-POS-SHARED-SESSION')
            ->firstOrFail();

        $response->assertRedirect(route('pos.receipt', $invoice));
        $this->assertSame($session->id, $invoice->pos_session_id);
        $this->assertSame($operator->id, $invoice->created_by);
    }

    public function test_session_variance_requires_justification_and_count_sheet_is_printable(): void
    {
        $user = User::query()->where('email', 'caissier@nema-erp.test')->firstOrFail();
        $cashAccount = CashAccount::query()->where('company_id', $user->company_id)->where('branch_id', $user->branch_id)->where('name', 'Caisse principale')->firstOrFail();
        $warehouse = Warehouse::query()->where('company_id', $user->company_id)->where('branch_id', $user->branch_id)->where('is_default', true)->firstOrFail();

        $this->openSession($user, $cashAccount, $warehouse, 1000);
        $session = $this->currentSession($user);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('pos.count-sheet', $session))
            ->assertOk()
            ->assertSeeText('Comptage caisse')
            ->assertSeeText($session->session_number);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->from(route('pos.show', $session))
            ->post(route('pos.close', $session), [
                'counted_methods' => [
                    'cash' => 900,
                    'mobile_money' => 0,
                    'bank_transfer' => 0,
                    'other' => 0,
                ],
                'variance_notes' => [
                    'cash' => '',
                    'mobile_money' => '',
                    'bank_transfer' => '',
                    'other' => '',
                ],
            ])
            ->assertRedirect(route('pos.show', $session))
            ->assertSessionHasErrors('variance_notes.cash');

        $session->refresh();
        $this->assertSame('open', $session->status);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('pos.close', $session), [
                'counted_methods' => [
                    'cash' => 900,
                    'mobile_money' => 0,
                    'bank_transfer' => 0,
                    'other' => 0,
                ],
                'variance_notes' => [
                    'cash' => 'Justification ecart test',
                    'mobile_money' => '',
                    'bank_transfer' => '',
                    'other' => '',
                ],
                'closing_notes' => 'Ecart justifie',
            ])
            ->assertRedirect(route('pos.show', $session));

        $session->refresh();
        $this->assertSame('closed', $session->status);
        $this->assertEqualsWithDelta(-100, (float) $session->variance_amount, 0.001);
        $this->assertSame('Justification ecart test', $session->variance_notes['cash'] ?? null);
    }

    private function openSession(User $user, CashAccount $cashAccount, Warehouse $warehouse, float $openingAmount, array $openingCashBreakdown = []): void
    {
        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('pos.open'), [
                'cash_account_id' => $cashAccount->id,
                'warehouse_id' => $warehouse->id,
                'opening_amount' => $openingAmount,
                'opening_notes' => 'Ouverture test POS',
                'opening_cash_breakdown' => $openingCashBreakdown,
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

    private function assertPosLockBlocks(callable $operation): void
    {
        try {
            $operation();
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Caisse fermee', collect($exception->errors())->flatten()->implode(' '));

            return;
        }

        $this->fail('Le verrouillage de caisse fermee aurait du bloquer cette operation.');
    }

    private function stockBalance(int $companyId, int $branchId, int $productId, int $warehouseId): float
    {
        return (float) DB::table('stock_movements')
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->selectRaw('COALESCE(SUM(quantity_in - quantity_out), 0) as balance')
            ->value('balance');
    }
}
