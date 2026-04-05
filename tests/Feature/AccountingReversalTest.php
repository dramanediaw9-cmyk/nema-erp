<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Sales\Models\SalesInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingReversalTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_company_admin_can_reverse_posted_journal_entry_with_audit_trail(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $invoice = SalesInvoice::query()
            ->where('company_id', $user->company_id)
            ->where('notes', 'Facture de demonstration initiale')
            ->firstOrFail();
        $entry = JournalEntry::query()
            ->with('lines')
            ->where('company_id', $user->company_id)
            ->where('source_type', SalesInvoice::class)
            ->where('source_id', $invoice->id)
            ->firstOrFail();

        $response = $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('accounting.journal-entries.reverse', $entry), [
                'reversal_date' => now()->toDateString(),
                'reversal_reason' => 'Correction de saisie controlee',
            ]);

        $entry->refresh()->load(['lines', 'reversalEntry.lines']);
        $reversal = $entry->reversalEntry;

        $response->assertRedirect(route('accounting.journal-entries.show', $reversal));

        $this->assertNotNull($reversal);
        $this->assertTrue((bool) $reversal->is_reversal);
        $this->assertSame($entry->id, $reversal->reverses_journal_entry_id);
        $this->assertSame('Correction de saisie controlee', $reversal->reversal_reason);
        $this->assertSame((float) $entry->total_debit, (float) $reversal->total_credit);
        $this->assertSame((float) $entry->total_credit, (float) $reversal->total_debit);
        $this->assertCount($entry->lines->count(), $reversal->lines);

        foreach ($entry->lines->values() as $index => $line) {
            $reversalLine = $reversal->lines->values()->get($index);

            $this->assertSame($line->account_id, $reversalLine->account_id);
            $this->assertSame((float) $line->debit, (float) $reversalLine->credit);
            $this->assertSame((float) $line->credit, (float) $reversalLine->debit);
        }

        $this->assertDatabaseHas('activity_logs', [
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'action' => 'accounting.journal_entries.reverse',
            'description' => 'Contrepassation ecriture comptable',
        ]);
    }

    public function test_journal_entry_reversal_rejects_past_date_and_duplicate_request(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $entry = JournalEntry::query()
            ->where('company_id', $user->company_id)
            ->where('source_type', SalesInvoice::class)
            ->firstOrFail();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->from(route('accounting.journal-entries.show', $entry))
            ->post(route('accounting.journal-entries.reverse', $entry), [
                'reversal_date' => $entry->entry_date?->copy()->subDay()->toDateString(),
                'reversal_reason' => 'Date invalide',
            ])
            ->assertRedirect(route('accounting.journal-entries.show', $entry))
            ->assertSessionHasErrors('reversal_date');

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('accounting.journal-entries.reverse', $entry), [
                'reversal_date' => now()->toDateString(),
                'reversal_reason' => 'Contrepassation unique',
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->from(route('accounting.journal-entries.show', $entry))
            ->post(route('accounting.journal-entries.reverse', $entry), [
                'reversal_date' => now()->toDateString(),
                'reversal_reason' => 'Deuxieme tentative interdite',
            ])
            ->assertRedirect(route('accounting.journal-entries.show', $entry))
            ->assertSessionHasErrors('journal_entry');
    }

    public function test_operations_user_cannot_reverse_posted_journal_entry(): void
    {
        $user = User::query()->where('email', 'ops@nema-erp.test')->firstOrFail();
        $entry = JournalEntry::query()
            ->where('company_id', $user->company_id)
            ->where('source_type', SalesInvoice::class)
            ->firstOrFail();

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('accounting.journal-entries.reverse', $entry), [
                'reversal_date' => now()->toDateString(),
                'reversal_reason' => 'Tentative sans permission',
            ])
            ->assertForbidden();
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}
