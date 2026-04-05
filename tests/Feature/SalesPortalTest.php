<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Partners\Models\Partner;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Models\SalesQuote;
use App\Modules\Sales\Services\SalesPortalLinkService;
use App\Modules\Treasury\Services\PaymentGatewayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesPortalTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_manager_sees_portal_share_tools_on_quote_and_order_pages(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $manager->company_id)->firstOrFail();
        $product = Product::query()->where('company_id', $manager->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $quote = $this->createQuote($manager, $customer, $product, 'TEST-PORTAL-QUOTE-SHOW');
        $order = $this->createOrder($manager, $customer, $product, 'TEST-PORTAL-ORDER-SHOW');

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->get(route('quotes.show', $quote))
            ->assertOk()
            ->assertSee('Portail client')
            ->assertSee('Lien partageable');

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee('Portail client')
            ->assertSee('Lien partageable');
    }

    public function test_guest_can_view_and_accept_quote_from_signed_portal_with_signature_and_deposit(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $manager->company_id)->firstOrFail();
        $product = Product::query()->where('company_id', $manager->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $quote = $this->createQuote($manager, $customer, $product, 'TEST-PORTAL-QUOTE');

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->post(route('quotes.send', $quote))
            ->assertRedirect(route('quotes.show', $quote));

        $quote->refresh();
        $portal = app(SalesPortalLinkService::class)->quotePortalData($quote);

        $this->get($portal['view_url'])
            ->assertOk()
            ->assertSee($quote->quote_number)
            ->assertSee($customer->name)
            ->assertSee('Signer et confirmer ce devis')
            ->assertSee('Signature graphique')
            ->assertSee('Acompte annonce');

        $this->from($portal['view_url'])
            ->post($portal['accept_url'], [
                'signer_name' => 'Fatou Diallo',
                'signer_phone' => '+22370000000',
                'signer_title' => 'Gerante',
                'signer_company' => 'Client Test SARL',
                'signer_note' => 'Validation commerciale depuis le portail.',
                'signature_data_url' => $this->signatureDataUrl(),
                'accepted_terms' => '1',
                'deposit_amount' => 500,
                'deposit_method' => 'wave',
                'deposit_reference' => 'WAVE-ACOMPTE-01',
                'deposit_note' => 'Acompte annonce en attendant confirmation comptable.',
                'deposit_expected_at' => now()->addDays(1)->toDateString(),
            ])
            ->assertRedirect($portal['view_url']);

        $quote->refresh()->load('latestPortalAction');

        $this->assertSame('accepted', $quote->status);
        $this->assertNotNull($quote->accepted_at);
        $this->assertNotNull($quote->latestPortalAction?->signature_image_data_url);
        $this->assertDatabaseHas('activity_logs', [
            'company_id' => $quote->company_id,
            'branch_id' => $quote->branch_id,
            'action' => 'quotes.portal_accept',
            'subject_id' => $quote->id,
        ]);
        $this->assertDatabaseHas('sales_portal_actions', [
            'company_id' => $quote->company_id,
            'branch_id' => $quote->branch_id,
            'actionable_type' => $quote->getMorphClass(),
            'actionable_id' => $quote->id,
            'action_type' => 'quote_acceptance',
            'signer_name' => 'Fatou Diallo',
            'signer_phone' => '+22370000000',
            'deposit_method' => 'wave',
            'deposit_reference' => 'WAVE-ACOMPTE-01',
        ]);

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->get(route('quotes.show', $quote))
            ->assertOk()
            ->assertSee('Signature client portail')
            ->assertSee('Fatou Diallo')
            ->assertSee('Signature graphique')
            ->assertSee('WAVE-ACOMPTE-01');
    }

    public function test_guest_can_view_and_confirm_order_from_signed_portal_with_signature_and_deposit(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $manager->company_id)->firstOrFail();
        $product = Product::query()->where('company_id', $manager->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $order = $this->createOrder($manager, $customer, $product, 'TEST-PORTAL-ORDER');
        $portal = app(SalesPortalLinkService::class)->orderPortalData($order);

        $this->get($portal['view_url'])
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee($customer->name)
            ->assertSee('Signer et confirmer cette commande')
            ->assertSee('Signature graphique');

        $this->from($portal['view_url'])
            ->post($portal['confirm_url'], [
                'signer_name' => 'Moussa Traore',
                'signer_phone' => '+22371000000',
                'signer_title' => 'Responsable achats',
                'signer_company' => 'Client Test SARL',
                'signature_data_url' => $this->signatureDataUrl(),
                'accepted_terms' => '1',
                'deposit_amount' => 650,
                'deposit_method' => 'bank_transfer',
                'deposit_reference' => 'VIR-12345',
                'deposit_expected_at' => now()->addDays(2)->toDateString(),
            ])
            ->assertRedirect($portal['view_url']);

        $order->refresh()->load('latestPortalAction');

        $this->assertSame('confirmed', $order->status);
        $this->assertNotNull($order->confirmed_at);
        $this->assertNotNull($order->latestPortalAction?->signature_image_data_url);
        $this->assertDatabaseHas('activity_logs', [
            'company_id' => $order->company_id,
            'branch_id' => $order->branch_id,
            'action' => 'orders.portal_confirm',
            'subject_id' => $order->id,
        ]);
        $this->assertDatabaseHas('sales_portal_actions', [
            'company_id' => $order->company_id,
            'branch_id' => $order->branch_id,
            'actionable_type' => $order->getMorphClass(),
            'actionable_id' => $order->id,
            'action_type' => 'order_confirmation',
            'signer_name' => 'Moussa Traore',
            'deposit_method' => 'bank_transfer',
            'deposit_reference' => 'VIR-12345',
        ]);

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee('Signature client portail')
            ->assertSee('Moussa Traore')
            ->assertSee('Signature graphique')
            ->assertSee('VIR-12345');
    }

    public function test_guest_can_send_invoice_payment_notice_from_signed_portal_and_team_can_prefill_receipt(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $manager->company_id)->firstOrFail();
        $product = Product::query()->where('company_id', $manager->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $invoice = $this->createInvoice($manager, $customer, $product, 'TEST-PORTAL-INVOICE');
        $this->assertSame('validated', $invoice->status);
        $this->assertSame('unpaid', $invoice->payment_status);
        $this->configurePaymentGateways($manager->company_id, $manager->tenant_id);

        $portal = app(SalesPortalLinkService::class)->invoicePaymentPortalData($invoice);

        $this->get($portal['view_url'])
            ->assertOk()
            ->assertSee($invoice->invoice_number)
            ->assertSee('Transmettre l avis de reglement')
            ->assertSee('Copier le message de paiement');

        $this->from($portal['view_url'])
            ->post($portal['notify_url'], [
                'signer_name' => 'Aissata Coulibaly',
                'signer_phone' => '+22372000000',
                'signer_title' => 'Comptable',
                'signer_company' => 'Client Test SARL',
                'signature_data_url' => $this->signatureDataUrl(),
                'accepted_terms' => '1',
                'deposit_amount' => 750,
                'deposit_method' => 'orange_money',
                'deposit_reference' => 'OM-PORTAL-001',
                'deposit_note' => 'Reglement annonce depuis le portail client.',
                'deposit_expected_at' => now()->toDateString(),
                'signer_note' => 'Merci de confirmer la reception.',
            ])
            ->assertRedirect($portal['view_url']);

        $invoice->refresh()->load('latestPortalAction');

        $this->assertNotNull($invoice->latestPortalAction?->signature_image_data_url);
        $this->assertDatabaseHas('activity_logs', [
            'company_id' => $invoice->company_id,
            'branch_id' => $invoice->branch_id,
            'action' => 'sales.portal_payment_notice',
            'subject_id' => $invoice->id,
        ]);
        $this->assertDatabaseHas('sales_portal_actions', [
            'company_id' => $invoice->company_id,
            'branch_id' => $invoice->branch_id,
            'actionable_type' => $invoice->getMorphClass(),
            'actionable_id' => $invoice->id,
            'action_type' => 'invoice_payment_notice',
            'signer_name' => 'Aissata Coulibaly',
            'deposit_method' => 'orange_money',
            'deposit_reference' => 'OM-PORTAL-001',
        ]);

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->get(route('sales.show', $invoice))
            ->assertOk()
            ->assertSee('Portail de reglement client')
            ->assertSee('Dernier avis de reglement client')
            ->assertSee('Aissata Coulibaly')
            ->assertSee('Signature graphique')
            ->assertSee('OM-PORTAL-001')
            ->assertSee('Creer l encaissement pre-rempli');

        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->get(route('payments.create', [
                'type' => 'customer_receipt',
                'invoice' => $invoice->id,
                'amount' => 750,
                'method' => 'orange_money',
                'reference' => 'OM-PORTAL-001',
                'notes' => 'Avis portail '.$invoice->invoice_number,
                'source' => 'portal_payment_notice',
            ]))
            ->assertOk()
            ->assertSee('Pre-remplissage depuis le portail client')
            ->assertSee('OM-PORTAL-001')
            ->assertSee('Avis portail '.$invoice->invoice_number);
    }

    private function createQuote(User $manager, Partner $customer, Product $product, string $notes): SalesQuote
    {
        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->post(route('quotes.store'), [
                'customer_id' => $customer->id,
                'quote_date' => now()->format('Y-m-d'),
                'valid_until' => now()->addDays(10)->format('Y-m-d'),
                'notes' => $notes,
                'items' => [[
                    'product_id' => $product->id,
                    'description' => 'Document portail devis',
                    'qty' => 3,
                    'unit_price' => 500,
                ]],
            ])
            ->assertRedirect();

        return SalesQuote::query()->where('company_id', $manager->company_id)->where('notes', $notes)->firstOrFail();
    }

    private function createOrder(User $manager, Partner $customer, Product $product, string $notes): SalesOrder
    {
        $this->actingAs($manager)
            ->withSession($this->workspaceSession($manager))
            ->post(route('orders.store'), [
                'customer_id' => $customer->id,
                'order_date' => now()->format('Y-m-d'),
                'requested_delivery_date' => now()->addDays(6)->format('Y-m-d'),
                'notes' => $notes,
                'items' => [[
                    'product_id' => $product->id,
                    'description' => 'Document portail commande',
                    'qty' => 4,
                    'unit_price' => 550,
                ]],
            ])
            ->assertRedirect();

        return SalesOrder::query()->where('company_id', $manager->company_id)->where('notes', $notes)->firstOrFail();
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
                    'description' => 'Document portail reglement facture',
                    'qty' => 2,
                    'unit_price' => 900,
                ]],
            ])
            ->assertRedirect();

        return SalesInvoice::query()->where('company_id', $manager->company_id)->where('notes', $notes)->firstOrFail();
    }

    private function signatureDataUrl(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4////fwAJ+wP9KobjigAAAABJRU5ErkJggg==';
    }


    private function configurePaymentGateways(int $companyId, ?int $tenantId = null): void
    {
        app(PaymentGatewayService::class)->updateConfiguration($companyId, $tenantId, [
            'wave' => [
                'enabled' => true,
                'label' => 'Wave Collecte',
                'account_name' => 'Nema Distribution',
                'collection_number' => '+22370001111',
                'instructions' => 'Utilise la reference facture dans le commentaire.',
            ],
            'orange_money' => [
                'enabled' => true,
                'label' => 'Orange Money Pro',
                'account_name' => 'Nema Orange',
                'collection_number' => '+22370002222',
                'instructions' => 'Envoie la capture du transfert a l equipe.',
            ],
        ]);
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}

