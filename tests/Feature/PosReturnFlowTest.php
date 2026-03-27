<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Pos\Models\PosReturn;
use App\Modules\Pos\Models\PosSession;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Treasury\Models\CashAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PosReturnFlowTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_cashier_can_process_pos_return_and_open_daily_report(): void
    {
        $user = User::query()->where('email', 'caissier@nema-erp.test')->firstOrFail();
        $cashAccount = CashAccount::query()->where('company_id', $user->company_id)->where('branch_id', $user->branch_id)->where('name', 'Caisse principale')->firstOrFail();
        $warehouse = Warehouse::query()->where('company_id', $user->company_id)->where('branch_id', $user->branch_id)->where('is_default', true)->firstOrFail();
        $product = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $initialStock = $this->stockBalance($user->company_id, $user->branch_id, $product->id, $warehouse->id);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('pos.open'), [
                'cash_account_id' => $cashAccount->id,
                'warehouse_id' => $warehouse->id,
                'opening_amount' => 0,
            ])
            ->assertRedirect();

        $session = PosSession::query()
            ->where('company_id', $user->company_id)
            ->where('opened_by', $user->id)
            ->where('status', 'open')
            ->firstOrFail();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('pos.sales.store'), [
                'sale_date' => now()->format('Y-m-d'),
                'method' => 'cash',
                'reference' => 'POS-RET-001',
                'notes' => 'VENTE-RETOUR-TEST',
                'discount_type' => 'fixed',
                'discount_value' => 50,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'description' => 'Article POS retour test',
                        'qty' => 2,
                        'unit_price' => 500,
                        'discount_type' => 'none',
                        'discount_value' => 0,
                    ],
                ],
            ])
            ->assertRedirect();

        $invoice = SalesInvoice::query()
            ->where('company_id', $user->company_id)
            ->where('pos_session_id', $session->id)
            ->where('notes', 'VENTE-RETOUR-TEST')
            ->firstOrFail();

        $item = $invoice->items()->firstOrFail();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('pos.returns.store', $invoice), [
                'return_date' => now()->format('Y-m-d'),
                'method' => 'cash',
                'reference' => 'POS-REFUND-001',
                'notes' => 'RETOUR-TEST',
                'return_mode' => 'partial',
                'items' => [
                    [
                        'sales_invoice_item_id' => $item->id,
                        'qty' => 1,
                    ],
                ],
            ])
            ->assertRedirect(route('pos.show', $session));

        $return = PosReturn::query()
            ->where('company_id', $user->company_id)
            ->where('sales_invoice_id', $invoice->id)
            ->firstOrFail();

        $this->assertMatchesRegularExpression('/^RET-BKO-\d{4}-\d{5}$/', $return->return_number);
        $this->assertEqualsWithDelta(475, (float) $return->total, 0.001);

        $this->assertDatabaseHas('payments', [
            'company_id' => $user->company_id,
            'payment_type' => 'pos_refund',
            'direction' => 'out',
            'reference' => 'POS-REFUND-001',
            'amount' => 475,
            'pos_session_id' => $session->id,
        ]);

        $finalStock = $this->stockBalance($user->company_id, $user->branch_id, $product->id, $warehouse->id);
        $this->assertEqualsWithDelta($initialStock - 1, $finalStock, 0.001);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('pos.report', ['date' => now()->format('Y-m-d')]))
            ->assertOk()
            ->assertSeeText('Rapport journalier POS')
            ->assertSeeText('Remises')
            ->assertSeeText('Retours traites')
            ->assertSeeText($session->session_number);
    }

    public function test_cashier_can_process_return_with_exchange_article_against_article(): void
    {
        $user = User::query()->where('email', 'caissier@nema-erp.test')->firstOrFail();
        $cashAccount = CashAccount::query()->where('company_id', $user->company_id)->where('branch_id', $user->branch_id)->where('name', 'Caisse principale')->firstOrFail();
        $warehouse = Warehouse::query()->where('company_id', $user->company_id)->where('branch_id', $user->branch_id)->where('is_default', true)->firstOrFail();
        $productA = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0001')->firstOrFail();
        $productB = Product::query()->where('company_id', $user->company_id)->where('sku', 'PRD-0002')->firstOrFail();

        $initialStockA = $this->stockBalance($user->company_id, $user->branch_id, $productA->id, $warehouse->id);
        $initialStockB = $this->stockBalance($user->company_id, $user->branch_id, $productB->id, $warehouse->id);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('pos.open'), [
                'cash_account_id' => $cashAccount->id,
                'warehouse_id' => $warehouse->id,
                'opening_amount' => 0,
            ])
            ->assertRedirect();

        $session = PosSession::query()
            ->where('company_id', $user->company_id)
            ->where('opened_by', $user->id)
            ->where('status', 'open')
            ->firstOrFail();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('pos.sales.store'), [
                'sale_date' => now()->format('Y-m-d'),
                'method' => 'cash',
                'reference' => 'POS-EXCHANGE-001',
                'notes' => 'VENTE-ECHANGE-TEST',
                'discount_type' => 'none',
                'discount_value' => 0,
                'items' => [
                    [
                        'product_id' => $productA->id,
                        'description' => 'Article a echanger',
                        'qty' => 1,
                        'unit_price' => 400,
                        'discount_type' => 'none',
                        'discount_value' => 0,
                    ],
                ],
            ])
            ->assertRedirect();

        $invoice = SalesInvoice::query()
            ->where('company_id', $user->company_id)
            ->where('pos_session_id', $session->id)
            ->where('notes', 'VENTE-ECHANGE-TEST')
            ->firstOrFail();

        $item = $invoice->items()->firstOrFail();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('pos.returns.store', $invoice), [
                'return_date' => now()->format('Y-m-d'),
                'method' => 'cash',
                'reference' => 'POS-EXCHANGE-RET',
                'notes' => 'ECHANGE-TEST',
                'return_mode' => 'partial',
                'items' => [
                    [
                        'sales_invoice_item_id' => $item->id,
                        'qty' => 1,
                    ],
                ],
                'exchange_items' => [
                    [
                        'product_id' => $productB->id,
                        'description' => 'Article remis en echange',
                        'qty' => 1,
                        'unit_price' => 700,
                        'discount_type' => 'fixed',
                        'discount_value' => 50,
                    ],
                ],
            ])
            ->assertRedirect(route('pos.show', $session));

        $return = PosReturn::query()
            ->where('company_id', $user->company_id)
            ->where('sales_invoice_id', $invoice->id)
            ->firstOrFail();

        $this->assertNotNull($return->exchange_sales_invoice_id);
        $exchangeInvoice = SalesInvoice::query()->findOrFail($return->exchange_sales_invoice_id);

        $this->assertEqualsWithDelta(400, (float) $return->total, 0.001);
        $this->assertEqualsWithDelta(700, (float) $exchangeInvoice->subtotal, 0.001);
        $this->assertEqualsWithDelta(50, (float) $exchangeInvoice->discount_total, 0.001);
        $this->assertEqualsWithDelta(650, (float) $exchangeInvoice->total, 0.001);
        $this->assertEqualsWithDelta(650, (float) $exchangeInvoice->amount_paid, 0.001);

        $this->assertDatabaseHas('payments', [
            'company_id' => $user->company_id,
            'payment_type' => 'pos_refund',
            'direction' => 'out',
            'amount' => 400,
            'reference' => 'POS-EXCHANGE-RET',
        ]);
        $this->assertDatabaseHas('payments', [
            'company_id' => $user->company_id,
            'payment_type' => 'customer_receipt',
            'direction' => 'in',
            'amount' => 650,
            'reference' => 'POS-EXCHANGE-RET-ECH',
        ]);

        $finalStockA = $this->stockBalance($user->company_id, $user->branch_id, $productA->id, $warehouse->id);
        $finalStockB = $this->stockBalance($user->company_id, $user->branch_id, $productB->id, $warehouse->id);
        $this->assertEqualsWithDelta($initialStockA, $finalStockA, 0.001);
        $this->assertEqualsWithDelta($initialStockB - 1, $finalStockB, 0.001);
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
