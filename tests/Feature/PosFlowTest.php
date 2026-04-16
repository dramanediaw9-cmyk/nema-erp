<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Pos\Models\PosComboChoice;
use App\Modules\Pos\Models\PosMenuCategory;
use App\Modules\Pos\Models\PosNoteTemplate;
use App\Modules\Pos\Models\PosPaymentMethod;
use App\Modules\Pos\Models\PosProductTag;
use App\Modules\Pos\Models\PosProfile;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Pos\Models\PosSession;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Treasury\Models\CashAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PosFlowTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

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
            ->assertSeeText('TICKET CAISSE')
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
        $customer = \App\Modules\Partners\Models\Partner::query()
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
        $this->assertStringContainsString('Stock dispo', $html);
        $this->assertStringContainsString('const availableProductQty = (productId, currentLineUid = null) => {', $html);
        $this->assertStringContainsString('HTMLFormElement.prototype.submit.call(saleForm);', $html);
        $this->assertStringNotContainsString('const openThermalReceiptPopup = () => {', $html);
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

