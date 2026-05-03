<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Access\Models\Permission;
use App\Modules\Core\Access\Models\Role;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Integrations\Models\ApiToken;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Treasury\Models\CashAccount;
use App\Modules\Treasury\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentValidationControlTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_branch_limited_payment_validator_cannot_validate_other_branch_payment_on_web(): void
    {
        $operator = User::query()->where('email', 'ops@nema-erp.test')->firstOrFail();
        $this->grantPaymentValidation($operator);
        $sikasso = Branch::query()->where('company_id', $operator->company_id)->where('code', 'SIK')->firstOrFail();
        $invoice = $this->createValidatedInvoice($operator, $sikasso, 'FAC-SIK-WEB-BLOCK-001', 120000);
        $cashAccount = $this->createBranchCashAccount($operator, $sikasso, 'Caisse Sikasso test validation');

        $this->actingAs($operator)
            ->withSession($this->workspaceSession($operator))
            ->from(route('payments.create'))
            ->post(route('payments.store'), [
                'payment_type' => 'customer_receipt',
                'invoice_id' => $invoice->id,
                'cash_account_id' => $cashAccount->id,
                'payment_date' => now()->format('Y-m-d'),
                'amount' => 50000,
                'method' => 'cash',
                'reference' => 'PAY-WEB-BLOCK-001',
                'notes' => 'Tentative autre agence',
            ])
            ->assertRedirect(route('payments.create'))
            ->assertSessionHasErrors(['branch_id']);

        $this->assertDatabaseMissing('payments', [
            'company_id' => $operator->company_id,
            'reference' => 'PAY-WEB-BLOCK-001',
        ]);
    }

    public function test_branch_limited_payment_validator_cannot_see_other_branch_payment_on_web(): void
    {
        $operator = User::query()->where('email', 'ops@nema-erp.test')->firstOrFail();
        $director = User::query()->where('email', 'dg@nema-erp.test')->firstOrFail();
        $this->grantPaymentValidation($operator);
        $sikasso = Branch::query()->where('company_id', $operator->company_id)->where('code', 'SIK')->firstOrFail();
        $invoice = $this->createValidatedInvoice($operator, $sikasso, 'FAC-SIK-WEB-VIEW-001', 95000);
        $cashAccount = $this->createBranchCashAccount($operator, $sikasso, 'Caisse Sikasso test visibilite');

        $payment = Payment::query()->create([
            'tenant_id' => $operator->tenant_id,
            'company_id' => $operator->company_id,
            'branch_id' => $sikasso->id,
            'cash_account_id' => $cashAccount->id,
            'partner_id' => $invoice->customer_id,
            'payment_number' => 'ENC-SIK-VIEW-001',
            'direction' => 'in',
            'payment_type' => 'customer_receipt',
            'payment_date' => now()->toDateString(),
            'amount' => 95000,
            'method' => 'cash',
            'reference' => 'PAY-WEB-VIEW-001',
            'notes' => 'Paiement agence Sikasso',
            'created_by' => $director->id,
        ]);

        $this->actingAs($operator)
            ->withSession($this->workspaceSession($operator))
            ->get(route('payments.index'))
            ->assertOk()
            ->assertDontSee('ENC-SIK-VIEW-001')
            ->assertDontSee('PAY-WEB-VIEW-001');

        $this->actingAs($operator)
            ->withSession($this->workspaceSession($operator))
            ->get(route('payments.show', $payment))
            ->assertForbidden();
    }

    public function test_director_can_validate_payment_for_other_branch_when_document_and_account_match(): void
    {
        $director = User::query()->where('email', 'dg@nema-erp.test')->firstOrFail();
        $sikasso = Branch::query()->where('company_id', $director->company_id)->where('code', 'SIK')->firstOrFail();
        $invoice = $this->createValidatedInvoice($director, $sikasso, 'FAC-SIK-WEB-ALLOW-001', 180000);
        $cashAccount = $this->createBranchCashAccount($director, $sikasso, 'Caisse Sikasso test validation DG');

        $this->actingAs($director)
            ->withSession($this->workspaceSession($director))
            ->post(route('payments.store'), [
                'payment_type' => 'customer_receipt',
                'invoice_id' => $invoice->id,
                'cash_account_id' => $cashAccount->id,
                'payment_date' => now()->format('Y-m-d'),
                'amount' => 80000,
                'method' => 'cash',
                'reference' => 'PAY-WEB-ALLOW-001',
                'notes' => 'Validation DG autre agence',
            ])
            ->assertRedirect(route('sales.show', $invoice));

        $this->assertDatabaseHas('payments', [
            'company_id' => $director->company_id,
            'branch_id' => $sikasso->id,
            'cash_account_id' => $cashAccount->id,
            'reference' => 'PAY-WEB-ALLOW-001',
        ]);
    }

    public function test_director_payment_validation_is_blocked_above_role_ceiling_on_web(): void
    {
        $director = User::query()->where('email', 'dg@nema-erp.test')->firstOrFail();
        $bamako = Branch::query()->where('company_id', $director->company_id)->where('code', 'BKO')->firstOrFail();
        $invoice = $this->createValidatedInvoice($director, $bamako, 'FAC-BKO-LIMIT-WEB-001', 6500000);
        $cashAccount = CashAccount::query()->where('company_id', $director->company_id)->where('name', 'Caisse principale')->firstOrFail();

        $this->actingAs($director)
            ->withSession($this->workspaceSession($director))
            ->from(route('payments.create'))
            ->post(route('payments.store'), [
                'payment_type' => 'customer_receipt',
                'invoice_id' => $invoice->id,
                'cash_account_id' => $cashAccount->id,
                'payment_date' => now()->format('Y-m-d'),
                'amount' => 6000000,
                'method' => 'cash',
                'reference' => 'PAY-WEB-LIMIT-001',
                'notes' => 'Tentative au dessus du plafond',
            ])
            ->assertRedirect(route('payments.create'))
            ->assertSessionHasErrors(['amount']);

        $this->assertDatabaseMissing('payments', [
            'company_id' => $director->company_id,
            'reference' => 'PAY-WEB-LIMIT-001',
        ]);
    }

    public function test_branch_limited_payment_validator_can_record_internal_transfer_to_company_bank_account(): void
    {
        $operator = User::query()->where('email', 'ops@nema-erp.test')->firstOrFail();
        $this->grantPaymentValidation($operator);
        $sourceCashAccount = CashAccount::query()->where('company_id', $operator->company_id)->where('name', 'Caisse principale')->firstOrFail();
        $destinationCashAccount = CashAccount::query()->where('company_id', $operator->company_id)->where('name', 'Banque BDM')->firstOrFail();

        $this->actingAs($operator)
            ->withSession($this->workspaceSession($operator))
            ->post(route('payments.store'), [
                'payment_type' => 'internal_transfer',
                'cash_account_id' => $sourceCashAccount->id,
                'destination_cash_account_id' => $destinationCashAccount->id,
                'payment_date' => now()->format('Y-m-d'),
                'amount' => 95000,
                'method' => 'bank_transfer',
                'reference' => 'PAY-WEB-TRANSFER-001',
                'notes' => 'Versement agence vers banque centrale',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('payments', [
            'company_id' => $operator->company_id,
            'cash_account_id' => $sourceCashAccount->id,
            'payment_type' => 'internal_transfer',
            'direction' => 'out',
            'reference' => 'PAY-WEB-TRANSFER-001',
        ]);
        $this->assertDatabaseHas('payments', [
            'company_id' => $operator->company_id,
            'cash_account_id' => $destinationCashAccount->id,
            'payment_type' => 'internal_transfer',
            'direction' => 'in',
            'reference' => 'PAY-WEB-TRANSFER-001',
        ]);
    }

    public function test_api_token_without_payment_validation_permission_cannot_create_payment(): void
    {
        $cashier = User::query()->where('email', 'caissier@nema-erp.test')->firstOrFail();
        $plainToken = $this->createApiToken($cashier);
        $invoice = SalesInvoice::query()->where('company_id', $cashier->company_id)->where('notes', 'Facture de demonstration initiale')->firstOrFail();
        $cashAccount = CashAccount::query()->where('company_id', $cashier->company_id)->where('name', 'Caisse principale')->firstOrFail();

        $this->withToken($plainToken)
            ->postJson('/api/v1/payments', [
                'payment_type' => 'customer_receipt',
                'invoice_id' => $invoice->id,
                'cash_account_id' => $cashAccount->id,
                'payment_date' => now()->toDateString(),
                'amount' => 1000,
                'method' => 'cash',
                'reference' => 'PAY-API-FORBIDDEN-001',
            ])
            ->assertForbidden();
    }

    public function test_api_branch_limited_payment_validator_cannot_create_other_branch_payment(): void
    {
        $operator = User::query()->where('email', 'ops@nema-erp.test')->firstOrFail();
        $this->grantPaymentValidation($operator);
        $plainToken = $this->createApiToken($operator);
        $sikasso = Branch::query()->where('company_id', $operator->company_id)->where('code', 'SIK')->firstOrFail();
        $invoice = $this->createValidatedInvoice($operator, $sikasso, 'FAC-SIK-API-BLOCK-001', 160000);
        $cashAccount = $this->createBranchCashAccount($operator, $sikasso, 'Caisse Sikasso test validation API');

        $this->withToken($plainToken)
            ->postJson('/api/v1/payments', [
                'payment_type' => 'customer_receipt',
                'invoice_id' => $invoice->id,
                'cash_account_id' => $cashAccount->id,
                'payment_date' => now()->toDateString(),
                'amount' => 70000,
                'method' => 'cash',
                'reference' => 'PAY-API-BLOCK-001',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['branch_id']);
    }

    public function test_api_director_payment_validation_is_blocked_above_role_ceiling(): void
    {
        $director = User::query()->where('email', 'dg@nema-erp.test')->firstOrFail();
        $plainToken = $this->createApiToken($director);
        $bamako = Branch::query()->where('company_id', $director->company_id)->where('code', 'BKO')->firstOrFail();
        $invoice = $this->createValidatedInvoice($director, $bamako, 'FAC-BKO-LIMIT-API-001', 6400000);
        $cashAccount = CashAccount::query()->where('company_id', $director->company_id)->where('name', 'Caisse principale')->firstOrFail();

        $this->withToken($plainToken)
            ->postJson('/api/v1/payments', [
                'payment_type' => 'customer_receipt',
                'invoice_id' => $invoice->id,
                'cash_account_id' => $cashAccount->id,
                'payment_date' => now()->toDateString(),
                'amount' => 5100000,
                'method' => 'cash',
                'reference' => 'PAY-API-LIMIT-001',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    private function grantPaymentValidation(User $user): void
    {
        $role = Role::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'name' => 'Validateur paiements '.$user->id,
            'slug' => 'payment_validator_'.$user->id,
            'description' => 'Validation paiements agence test',
            'is_system' => false,
        ]);

        $role->permissions()->sync(
            Permission::query()->whereIn('slug', [
                'payments.view',
                'payments.validate',
            ])->pluck('id')->all()
        );

        $user->roles()->syncWithoutDetaching([$role->id]);
        $user->load('roles.permissions');
    }

    private function createValidatedInvoice(User $user, Branch $branch, string $invoiceNumber, float $total): SalesInvoice
    {
        $template = SalesInvoice::query()
            ->where('company_id', $user->company_id)
            ->where('status', 'validated')
            ->firstOrFail();

        $invoice = $template->replicate();
        $invoice->fill([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'branch_id' => $branch->id,
            'warehouse_id' => Warehouse::query()
                ->where('company_id', $user->company_id)
                ->where('branch_id', $branch->id)
                ->orderByDesc('is_default')
                ->orderBy('id')
                ->value('id'),
            'invoice_number' => $invoiceNumber,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
            'status' => 'validated',
            'payment_status' => 'unpaid',
            'subtotal' => $total,
            'discount_total' => 0,
            'net_total' => $total,
            'tax_total' => 0,
            'total' => $total,
            'amount_paid' => 0,
            'balance_due' => $total,
            'sale_channel' => 'standard',
            'pos_session_id' => null,
            'pos_sync_key' => null,
            'stock_posted' => false,
            'notes' => $invoiceNumber,
            'validated_at' => now(),
            'approved_at' => now(),
            'approved_by' => $user->id,
            'cancelled_at' => null,
            'cancelled_by' => null,
            'created_by' => $user->id,
        ]);
        $invoice->save();

        return $invoice;
    }

    private function createBranchCashAccount(User $user, Branch $branch, string $name): CashAccount
    {
        return CashAccount::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'branch_id' => $branch->id,
            'name' => $name,
            'type' => 'cash',
            'account_number' => null,
            'opening_balance' => 100000,
            'is_active' => true,
        ]);
    }

    private function createApiToken(User $user): string
    {
        $plainToken = 'nema_payment_control_token_'.$user->id;

        ApiToken::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'name' => 'Test API Payment Control '.$user->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addDay(),
            'created_by' => $user->id,
        ]);

        return $plainToken;
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}
