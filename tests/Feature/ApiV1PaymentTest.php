<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Integrations\Models\ApiToken;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Treasury\Models\CashAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiV1PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_api_token_can_record_and_show_customer_receipt(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $plainToken = $this->createApiToken($manager);
        $invoice = SalesInvoice::query()->where('company_id', $manager->company_id)->where('notes', 'Facture de demonstration initiale')->firstOrFail();
        $cashAccount = CashAccount::query()->where('company_id', $manager->company_id)->where('name', 'Caisse principale')->firstOrFail();
        $initialPaid = (float) $invoice->amount_paid;
        $initialBalance = (float) $invoice->balance_due;

        $response = $this->withToken($plainToken)
            ->postJson('/api/v1/payments', [
                'payment_type' => 'customer_receipt',
                'invoice_id' => $invoice->id,
                'cash_account_id' => $cashAccount->id,
                'payment_date' => now()->toDateString(),
                'amount' => 500,
                'method' => 'cash',
                'reference' => 'API-PAY-001',
                'notes' => 'Encaissement via API',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('payment_type', 'customer_receipt')
            ->assertJsonPath('reference', 'API-PAY-001')
            ->assertJsonPath('allocations.0.allocatable_id', $invoice->id)
            ->assertJsonPath('allocations.0.allocatable_type', SalesInvoice::class);

        $paymentId = (int) $response->json('id');
        $invoice->refresh();

        $this->assertEqualsWithDelta($initialPaid + 500, (float) $invoice->amount_paid, 0.001);
        $this->assertEqualsWithDelta($initialBalance - 500, (float) $invoice->balance_due, 0.001);

        $this->withToken($plainToken)
            ->getJson('/api/v1/payments/'.$paymentId)
            ->assertOk()
            ->assertJsonPath('id', $paymentId)
            ->assertJsonPath('reference', 'API-PAY-001')
            ->assertJsonPath('cash_account.id', $cashAccount->id);
    }

    public function test_api_token_can_filter_payments(): void
    {
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $plainToken = $this->createApiToken($manager);
        $invoice = SalesInvoice::query()->where('company_id', $manager->company_id)->where('notes', 'Facture de demonstration initiale')->firstOrFail();
        $cashAccount = CashAccount::query()->where('company_id', $manager->company_id)->where('name', 'Caisse principale')->firstOrFail();

        $this->withToken($plainToken)
            ->postJson('/api/v1/payments', [
                'payment_type' => 'customer_receipt',
                'invoice_id' => $invoice->id,
                'cash_account_id' => $cashAccount->id,
                'payment_date' => now()->toDateString(),
                'amount' => 250,
                'method' => 'wave',
                'reference' => 'API-PAY-FILTER',
            ])
            ->assertCreated();

        $this->withToken($plainToken)
            ->getJson('/api/v1/payments?payment_type=customer_receipt&method=wave&search=API-PAY-FILTER')
            ->assertOk()
            ->assertJsonPath('data.0.reference', 'API-PAY-FILTER')
            ->assertJsonPath('data.0.method', 'wave');
    }

    private function createApiToken(User $user): string
    {
        $plainToken = 'nema_test_payment_api_token_'.$user->id;

        ApiToken::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'name' => 'Test API Payment',
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addDay(),
            'created_by' => $user->id,
        ]);

        return $plainToken;
    }
}
