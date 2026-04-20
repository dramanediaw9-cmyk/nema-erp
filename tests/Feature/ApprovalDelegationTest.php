<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Approvals\Models\ApprovalStep;
use App\Modules\Partners\Models\Partner;
use App\Modules\Sales\Models\SalesInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalDelegationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_current_approval_step_can_be_delegated_to_another_approver(): void
    {
        $operator = User::query()->where('email', 'ops@nema-erp.test')->firstOrFail();
        $manager = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();
        $director = User::query()->where('email', 'dg@nema-erp.test')->firstOrFail();
        $customer = Partner::query()->customers()->where('company_id', $operator->company_id)->firstOrFail();
        $product = Product::query()->where('company_id', $operator->company_id)->where('sku', 'PRD-0001')->firstOrFail();

        $this->actingAs($operator)
            ->withSession([
                'current_company_id' => $operator->company_id,
                'current_branch_id' => $operator->branch_id,
            ])
            ->post(route('sales.store'), [
                'customer_id' => $customer->id,
                'invoice_date' => now()->format('Y-m-d'),
                'notes' => 'APPROVAL-DELEGATION-SALE',
                'items' => [[
                    'product_id' => $product->id,
                    'description' => 'Delegation approbation',
                    'qty' => 1,
                    'unit_price' => 450,
                ]],
            ])
            ->assertRedirect();

        $invoice = SalesInvoice::query()
            ->where('company_id', $operator->company_id)
            ->where('notes', 'APPROVAL-DELEGATION-SALE')
            ->firstOrFail();

        $step = ApprovalStep::query()
            ->where('approvable_type', SalesInvoice::class)
            ->where('approvable_id', $invoice->id)
            ->where('status', 'pending')
            ->firstOrFail();

        $this->actingAs($manager)
            ->withSession([
                'current_company_id' => $manager->company_id,
                'current_branch_id' => $manager->branch_id,
            ])
            ->from(route('approvals.index'))
            ->post(route('approvals.steps.delegate', $step), [
                'delegate_to' => $director->id,
                'note' => 'Escalade manuelle au directeur commercial.',
            ])
            ->assertRedirect(route('approvals.index'))
            ->assertSessionHas('success');

        $step->refresh();

        $this->assertSame($director->id, $step->assigned_to);
        $this->assertSame($manager->id, $step->delegated_by);
        $this->assertNotNull($step->delegated_at);
        $this->assertSame($director->id, data_get($step->meta, 'delegation.delegated_to'));

        $this->actingAs($manager)
            ->withSession([
                'current_company_id' => $manager->company_id,
                'current_branch_id' => $manager->branch_id,
            ])
            ->from(route('sales.show', $invoice))
            ->post(route('sales.approve', $invoice))
            ->assertSessionHasErrors('approval');

        $this->assertSame('pending_approval', $invoice->fresh()->status);

        $this->actingAs($director)
            ->withSession([
                'current_company_id' => $director->company_id,
                'current_branch_id' => $director->branch_id,
            ])
            ->post(route('sales.approve', $invoice))
            ->assertRedirect(route('sales.show', $invoice));

        $invoice->refresh();

        $this->assertSame('validated', $invoice->status);
        $this->assertSame($director->id, $invoice->approved_by);
    }
}
