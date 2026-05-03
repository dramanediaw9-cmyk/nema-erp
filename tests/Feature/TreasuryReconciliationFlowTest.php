<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Collaboration\Models\Attachment;
use App\Modules\Treasury\Models\CashAccount;
use App\Modules\Treasury\Models\Payment;
use App\Modules\Treasury\Models\TreasuryReconciliation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TreasuryReconciliationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_manager_can_create_treasury_reconciliation_from_bank_movements(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $account = CashAccount::query()->where('company_id', $user->company_id)->where('type', 'bank')->firstOrFail();
        $statementDate = '2026-03-25';

        $incoming = $this->makePayment($user, $account, 'REC-BANK-001', 'in', 200000);
        $outgoing = $this->makePayment($user, $account, 'PAY-BANK-001', 'out', 50000);
        $expectedBookBalance = round((float) $account->opening_balance + Payment::query()
            ->where('company_id', $user->company_id)
            ->where('cash_account_id', $account->id)
            ->whereDate('payment_date', '<=', $statementDate)
            ->get()
            ->sum(fn (Payment $payment) => $payment->direction === 'in' ? (float) $payment->amount : -1 * (float) $payment->amount), 2);

        $response = $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('treasury-reconciliations.store'), [
                'cash_account_id' => $account->id,
                'statement_date' => $statementDate,
                'statement_reference' => 'Releve BDM mars',
                'statement_balance' => $expectedBookBalance,
                'payment_ids' => [$incoming->id, $outgoing->id],
                'notes' => 'Controle mensuel banque BDM',
            ]);

        $reconciliation = TreasuryReconciliation::query()->where('company_id', $user->company_id)->firstOrFail();

        $response->assertRedirect(route('treasury-reconciliations.show', $reconciliation));
        $this->assertSame('RAP-BKO-2026-00001', $reconciliation->reconciliation_number);
        $this->assertSame('balanced', $reconciliation->status);
        $this->assertSame(number_format($expectedBookBalance, 2, '.', ''), $reconciliation->book_balance);
        $this->assertSame(number_format($expectedBookBalance, 2, '.', ''), $reconciliation->statement_balance);
        $this->assertSame('0.00', $reconciliation->difference);
        $this->assertEquals(2, $reconciliation->payments_count);
        $this->assertDatabaseHas('treasury_reconciliation_payments', ['payment_id' => $incoming->id]);
        $this->assertDatabaseHas('treasury_reconciliation_payments', ['payment_id' => $outgoing->id]);
    }

    public function test_reconciled_payment_no_longer_appears_in_candidate_list(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $account = CashAccount::query()->where('company_id', $user->company_id)->where('type', 'bank')->firstOrFail();

        $paymentA = $this->makePayment($user, $account, 'REC-BANK-010', 'in', 120000);
        $paymentB = $this->makePayment($user, $account, 'REC-BANK-011', 'in', 50000);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('treasury-reconciliations.store'), [
                'cash_account_id' => $account->id,
                'statement_date' => '2026-03-25',
                'statement_reference' => 'Releve BDM mars',
                'statement_balance' => 670000,
                'payment_ids' => [$paymentA->id],
            ])
            ->assertSessionHas('success');

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('treasury-reconciliations.create', [
                'cash_account_id' => $account->id,
                'statement_date' => '2026-03-25',
            ]))
            ->assertOk()
            ->assertDontSee('REC-BANK-010')
            ->assertSee('REC-BANK-011');
    }

    public function test_reconciliation_create_prioritizes_documented_internal_transfer_deposits(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $account = CashAccount::query()->where('company_id', $user->company_id)->where('type', 'bank')->firstOrFail();

        $depositWithReference = Payment::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'cash_account_id' => $account->id,
            'partner_id' => null,
            'payment_number' => 'REC-DEP-DOC-001',
            'direction' => 'in',
            'payment_type' => 'internal_transfer',
            'payment_date' => now()->subDays(3)->toDateString(),
            'amount' => 42000,
            'method' => 'bank_transfer',
            'reference' => 'BORD-DEP-DOC-001',
            'notes' => 'Depot agence documente par reference',
            'created_by' => $user->id,
        ]);

        $depositWithAttachment = Payment::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'cash_account_id' => $account->id,
            'partner_id' => null,
            'payment_number' => 'REC-DEP-DOC-002',
            'direction' => 'in',
            'payment_type' => 'internal_transfer',
            'payment_date' => now()->subDays(2)->toDateString(),
            'amount' => 37000,
            'method' => 'bank_transfer',
            'reference' => null,
            'notes' => 'Depot agence documente par bordereau',
            'created_by' => $user->id,
        ]);

        Attachment::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'attachable_type' => Payment::class,
            'attachable_id' => $depositWithAttachment->id,
            'disk' => 'public',
            'path' => 'tests/bordereau-reconciliation.pdf',
            'original_name' => 'bordereau-reconciliation.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 4096,
            'created_by' => $user->id,
        ]);

        Payment::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'cash_account_id' => $account->id,
            'partner_id' => null,
            'payment_number' => 'REC-DEP-DOC-003',
            'direction' => 'in',
            'payment_type' => 'internal_transfer',
            'payment_date' => now()->subDays(4)->toDateString(),
            'amount' => 18000,
            'method' => 'bank_transfer',
            'reference' => null,
            'notes' => 'Depot agence sans preuve',
            'created_by' => $user->id,
        ]);

        Payment::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'cash_account_id' => $account->id,
            'partner_id' => null,
            'payment_number' => 'REC-DEP-DOC-004',
            'direction' => 'in',
            'payment_type' => 'customer_receipt',
            'payment_date' => now()->subDay()->toDateString(),
            'amount' => 25000,
            'method' => 'bank_transfer',
            'reference' => 'CLI-DEP-004',
            'notes' => 'Encaissement client classique',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('treasury-reconciliations.create', [
                'cash_account_id' => $account->id,
                'statement_date' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Depots documentes')
            ->assertSee('79 000')
            ->assertSee('2 candidat(s) prets a rapprocher')
            ->assertSee('Depots a verifier')
            ->assertSee('18 000 XOF sans preuve exploitable')
            ->assertSee('Pret a rapprocher')
            ->assertSee('Reference depot')
            ->assertSee('Bordereau joint')
            ->assertSee('A verifier')
            ->assertSeeInOrder([
                'REC-DEP-DOC-001',
                'REC-DEP-DOC-002',
                'REC-DEP-DOC-003',
                'REC-DEP-DOC-004',
            ]);
    }

    private function makePayment(User $user, CashAccount $account, string $number, string $direction, float $amount): Payment
    {
        return Payment::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'cash_account_id' => $account->id,
            'partner_id' => null,
            'payment_number' => $number,
            'direction' => $direction,
            'payment_type' => $direction === 'in' ? 'customer_receipt' : 'supplier_payment',
            'payment_date' => '2026-03-25',
            'amount' => $amount,
            'method' => $account->type === 'mobile_money' ? 'mobile_money' : 'bank_transfer',
            'reference' => $number,
            'notes' => 'Flux test rapprochement',
            'created_by' => $user->id,
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
