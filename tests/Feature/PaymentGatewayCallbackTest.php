<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Partners\Models\Partner;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Treasury\Models\CashAccount;
use App\Modules\Treasury\Models\Payment;
use App\Modules\Treasury\Models\PaymentGatewayCallback;
use App\Modules\Treasury\Services\PaymentGatewayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentGatewayCallbackTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_success_callback_can_auto_record_customer_receipt(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $manager->company_id)->firstOrFail();
        $product = Product::query()->where('company_id', $manager->company_id)->where('sku', 'PRD-0001')->firstOrFail();
        $waveAccount = CashAccount::query()->where('company_id', $manager->company_id)->where('name', 'Wave')->firstOrFail();

        $invoice = $this->createInvoice($manager, $customer, $product, 'TEST-CALLBACK-WAVE-AUTO');
        $this->configureGateways($manager, [
            'wave' => [
                'enabled' => true,
                'label' => 'Wave Collecte',
                'account_name' => 'Nema Wave',
                'collection_number' => '+22370001111',
                'instructions' => 'Ref facture obligatoire',
                'cash_account_id' => $waveAccount->id,
                'auto_record' => true,
                'callback_secret' => 'wave-secret',
            ],
        ]);

        $response = $this->postJson(route('payment-gateways.callbacks.store', ['company' => $manager->company_id, 'method' => 'wave']), [
            'invoice_number' => $invoice->invoice_number,
            'status' => 'success',
            'amount' => 700,
            'reference' => 'WAVE-CB-001',
            'external_reference' => 'WAVE-EXT-001',
            'payer_name' => 'Client Mobile',
            'payer_phone' => '+22370000099',
            'paid_at' => now()->toDateString(),
            'notes' => 'Retour temps reel Wave',
        ], ['X-Nema-Gateway-Secret' => 'wave-secret']);

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('processing_status', 'auto_recorded');

        $invoice->refresh();
        $callback = PaymentGatewayCallback::query()->where('sales_invoice_id', $invoice->id)->where('reference', 'WAVE-CB-001')->firstOrFail();
        $payment = Payment::query()->where('company_id', $manager->company_id)->where('reference', 'WAVE-CB-001')->firstOrFail();

        $this->assertSame('auto_recorded', $callback->processing_status);
        $this->assertSame($payment->id, $callback->payment_id);
        $this->assertSame('wave', $payment->method);
        $this->assertSame('partial', $invoice->payment_status);
        $this->assertEqualsWithDelta((float) $invoice->total - 700, (float) $invoice->balance_due, 0.001);

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->get(route('sales.show', $invoice))
            ->assertOk()
            ->assertSee('Callbacks paiements entrants')
            ->assertSee('WAVE-CB-001')
            ->assertSee('Voir l encaissement');
    }

    public function test_success_callback_can_stay_pending_review_when_auto_record_is_not_enabled(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $manager->company_id)->firstOrFail();
        $product = Product::query()->where('company_id', $manager->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $invoice = $this->createInvoice($manager, $customer, $product, 'TEST-CALLBACK-OM-REVIEW');
        $this->configureGateways($manager, [
            'orange_money' => [
                'enabled' => true,
                'label' => 'Orange Money Pro',
                'account_name' => 'Nema Orange',
                'collection_number' => '+22370002222',
                'instructions' => 'Ref facture obligatoire',
                'cash_account_id' => null,
                'auto_record' => false,
                'callback_secret' => 'orange-secret',
            ],
        ]);

        $this->postJson(route('payment-gateways.callbacks.store', ['company' => $manager->company_id, 'method' => 'orange_money']), [
            'invoice_number' => $invoice->invoice_number,
            'status' => 'success',
            'amount' => 500,
            'reference' => 'OM-CB-001',
        ], ['X-Nema-Gateway-Secret' => 'orange-secret'])
            ->assertStatus(202)
            ->assertJsonPath('processing_status', 'pending_review');

        $invoice->refresh();
        $callback = PaymentGatewayCallback::query()->where('sales_invoice_id', $invoice->id)->where('reference', 'OM-CB-001')->firstOrFail();

        $this->assertSame('pending_review', $callback->processing_status);
        $this->assertNull($callback->payment_id);
        $this->assertSame('unpaid', $invoice->payment_status);
        $this->assertDatabaseMissing('payments', [
            'company_id' => $manager->company_id,
            'reference' => 'OM-CB-001',
        ]);

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->get(route('sales.show', $invoice))
            ->assertOk()
            ->assertSee('OM-CB-001')
            ->assertSee('A rapprocher')
            ->assertSee('Creer l encaissement pre-rempli');
    }

    public function test_callback_rejects_invalid_secret(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $manager->company_id)->firstOrFail();
        $product = Product::query()->where('company_id', $manager->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $invoice = $this->createInvoice($manager, $customer, $product, 'TEST-CALLBACK-SECRET');
        $this->configureGateways($manager, [
            'wave' => [
                'enabled' => true,
                'label' => 'Wave',
                'account_name' => 'Nema Wave',
                'collection_number' => '+22370001111',
                'instructions' => 'Ref facture obligatoire',
                'cash_account_id' => null,
                'auto_record' => false,
                'callback_secret' => 'wave-secret',
            ],
        ]);

        $this->postJson(route('payment-gateways.callbacks.store', ['company' => $manager->company_id, 'method' => 'wave']), [
            'invoice_number' => $invoice->invoice_number,
            'status' => 'success',
            'amount' => 300,
            'reference' => 'WAVE-CB-BAD-001',
        ], ['X-Nema-Gateway-Secret' => 'bad-secret'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('secret');

        $this->assertDatabaseMissing('payment_gateway_callbacks', [
            'sales_invoice_id' => $invoice->id,
            'reference' => 'WAVE-CB-BAD-001',
        ]);
    }

    private function createInvoice(User $manager, Partner $customer, Product $product, string $notes): SalesInvoice
    {
        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->post(route('sales.store'), [
                'customer_id' => $customer->id,
                'invoice_date' => now()->format('Y-m-d'),
                'due_date' => now()->addDays(7)->format('Y-m-d'),
                'notes' => $notes,
                'items' => [[
                    'product_id' => $product->id,
                    'description' => 'Facture test callback',
                    'qty' => 2,
                    'unit_price' => 900,
                ]],
            ])
            ->assertRedirect();

        return SalesInvoice::query()->where('company_id', $manager->company_id)->where('notes', $notes)->firstOrFail();
    }

    private function configureGateways(User $user, array $overrides): void
    {
        $defaults = [
            'wave' => [
                'enabled' => false,
                'label' => 'Wave',
                'account_name' => '',
                'collection_number' => '',
                'instructions' => '',
                'cash_account_id' => null,
                'auto_record' => false,
                'callback_secret' => '',
            ],
            'orange_money' => [
                'enabled' => false,
                'label' => 'Orange Money',
                'account_name' => '',
                'collection_number' => '',
                'instructions' => '',
                'cash_account_id' => null,
                'auto_record' => false,
                'callback_secret' => '',
            ],
            'moov_money' => [
                'enabled' => false,
                'label' => 'Moov Money',
                'account_name' => '',
                'collection_number' => '',
                'instructions' => '',
                'cash_account_id' => null,
                'auto_record' => false,
                'callback_secret' => '',
            ],
            'bank_transfer' => [
                'enabled' => false,
                'label' => 'Virement bancaire',
                'account_name' => '',
                'collection_number' => '',
                'instructions' => '',
                'cash_account_id' => null,
                'auto_record' => false,
                'callback_secret' => '',
            ],
        ];

        foreach ($overrides as $method => $config) {
            $defaults[$method] = array_merge($defaults[$method], $config);
        }

        app(PaymentGatewayService::class)->updateConfiguration($user->company_id, $user->tenant_id, $defaults);
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}
