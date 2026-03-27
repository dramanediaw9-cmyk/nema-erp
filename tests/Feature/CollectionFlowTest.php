<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Collections\Models\CollectionFollowUp;
use App\Modules\Sales\Models\SalesInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollectionFlowTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_manager_can_open_collections_portfolio_for_overdue_invoices(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $invoice = SalesInvoice::query()->where('company_id', $user->company_id)->where('notes', 'Facture de demonstration initiale')->firstOrFail();

        $invoice->update([
            'due_date' => now()->subDays(5)->toDateString(),
            'payment_status' => 'partial',
        ]);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('collections.index', ['state' => 'overdue']))
            ->assertOk()
            ->assertSee('Portefeuille a encaisser')
            ->assertSee($invoice->invoice_number)
            ->assertSee('Retard');
    }

    public function test_manager_can_record_follow_up_with_payment_promise(): void
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $invoice = SalesInvoice::query()->where('company_id', $user->company_id)->where('notes', 'Facture de demonstration initiale')->firstOrFail();

        $response = $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->post(route('collections.follow-ups.store', $invoice), [
                'action_date' => now()->toDateString(),
                'action_type' => 'call',
                'outcome' => 'promised',
                'contact_name' => 'M. Traore',
                'contact_phone' => '+22370000099',
                'promised_amount' => 500,
                'promised_date' => now()->addDays(2)->toDateString(),
                'next_action_date' => now()->addDays(3)->toDateString(),
                'notes' => 'Promesse de paiement test',
            ]);

        $response->assertRedirect(route('collections.show', $invoice));

        $followUp = CollectionFollowUp::query()
            ->where('company_id', $user->company_id)
            ->where('sales_invoice_id', $invoice->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('call', $followUp->action_type);
        $this->assertSame('promised', $followUp->outcome);
        $this->assertEqualsWithDelta(500, (float) $followUp->promised_amount, 0.001);

        $this->actingAs($user)
            ->withSession($this->workspaceSession($user))
            ->get(route('collections.show', $invoice))
            ->assertOk()
            ->assertSee('Promesse de paiement test')
            ->assertSee('M. Traore');
    }

    private function workspaceSession(User $user): array
    {
        return [
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ];
    }
}
