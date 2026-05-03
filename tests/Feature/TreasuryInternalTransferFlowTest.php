<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Treasury\Models\CashAccount;
use App\Modules\Treasury\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TreasuryInternalTransferFlowTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_manager_can_record_branch_deposit_to_central_bank_and_trace_both_movements(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $sourceAccount = CashAccount::query()->where('company_id', $user->company_id)->where('name', 'Caisse principale')->firstOrFail();
        $destinationAccount = CashAccount::query()->where('company_id', $user->company_id)->where('name', 'Banque BDM')->firstOrFail();

        $response = $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('payments.store'), [
                'payment_type' => 'internal_transfer',
                'cash_account_id' => $sourceAccount->id,
                'destination_cash_account_id' => $destinationAccount->id,
                'payment_date' => now()->toDateString(),
                'amount' => 85000,
                'method' => 'bank_transfer',
                'reference' => 'BORD-BKO-001',
                'notes' => 'Versement fin de journee agence Bamako',
            ]);

        $outgoingPayment = Payment::query()
            ->where('company_id', $user->company_id)
            ->where('payment_type', 'internal_transfer')
            ->where('direction', 'out')
            ->where('reference', 'BORD-BKO-001')
            ->firstOrFail();
        $incomingPayment = Payment::query()
            ->where('company_id', $user->company_id)
            ->where('payment_type', 'internal_transfer')
            ->where('direction', 'in')
            ->where('reference', 'BORD-BKO-001')
            ->firstOrFail();

        $response->assertRedirect(route('payments.show', $outgoingPayment));

        $this->assertSame($sourceAccount->id, $outgoingPayment->cash_account_id);
        $this->assertSame($destinationAccount->id, $incomingPayment->cash_account_id);
        $this->assertSame($user->branch_id, $outgoingPayment->branch_id);
        $this->assertSame($user->branch_id, $incomingPayment->branch_id);
        $this->assertSame('internal_transfer', $outgoingPayment->payment_type);
        $this->assertSame('internal_transfer', $incomingPayment->payment_type);

        $this->assertDatabaseHas('payment_allocations', [
            'payment_id' => $outgoingPayment->id,
            'allocatable_type' => Payment::class,
            'allocatable_id' => $incomingPayment->id,
            'allocated_amount' => 85000,
        ]);
        $this->assertDatabaseHas('payment_allocations', [
            'payment_id' => $incomingPayment->id,
            'allocatable_type' => Payment::class,
            'allocatable_id' => $outgoingPayment->id,
            'allocated_amount' => 85000,
        ]);

        $entry = JournalEntry::query()
            ->where('company_id', $user->company_id)
            ->where('source_type', Payment::class)
            ->where('source_id', $outgoingPayment->id)
            ->where('journal_code', 'TRE')
            ->firstOrFail();

        $this->assertEqualsWithDelta(85000, (float) $entry->total_debit, 0.001);
        $this->assertEqualsWithDelta(85000, (float) $entry->total_credit, 0.001);
        $this->assertSame(['521000', '571000'], $entry->lines()->with('account')->orderBy('id')->get()->pluck('account.code')->all());

        $this->assertDatabaseHas('integration_events', [
            'company_id' => $user->company_id,
            'aggregate_type' => Payment::class,
            'aggregate_id' => (string) $outgoingPayment->id,
            'event_name' => 'treasury.internal_transfer.recorded',
        ]);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('payments.show', $outgoingPayment))
            ->assertOk()
            ->assertSee('Compte destination')
            ->assertSee('Justificatifs terrain')
            ->assertSee($incomingPayment->payment_number)
            ->assertSee('Banque BDM');
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}
