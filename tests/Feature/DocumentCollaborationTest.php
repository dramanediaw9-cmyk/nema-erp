<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Collaboration\Models\Attachment;
use App\Modules\Core\Collaboration\Models\InternalComment;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Core\Integrations\Models\IntegrationEvent;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Sales\Models\SalesInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentCollaborationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_manager_can_add_internal_comment_to_sales_invoice(): void
    {
        $user = $this->actingAsManager();
        $invoice = SalesInvoice::query()->firstOrFail();

        $this->from(route('sales.show', $invoice))
            ->post(route('documents.comments.store'), [
                'document_type' => 'sales_invoice',
                'document_id' => $invoice->id,
                'comment_body' => 'Commentaire test sur la facture client.',
            ])
            ->assertRedirect(route('sales.show', $invoice));

        $this->assertDatabaseHas('internal_comments', [
            'company_id' => $invoice->company_id,
            'commentable_type' => SalesInvoice::class,
            'commentable_id' => $invoice->id,
            'created_by' => $user->id,
        ]);

        $comment = InternalComment::query()->latest('id')->firstOrFail();
        $this->assertSame('Commentaire test sur la facture client.', $comment->body);
    }

    public function test_manager_can_upload_attachment_to_purchase_bill_and_open_it(): void
    {
        Storage::fake('public');

        $this->actingAsManager();
        $bill = PurchaseBill::query()->firstOrFail();

        $this->from(route('purchases.show', $bill))
            ->post(route('documents.attachments.store'), [
                'document_type' => 'purchase_bill',
                'document_id' => $bill->id,
                'attachment_file' => UploadedFile::fake()->create('facture-fournisseur.pdf', 120, 'application/pdf'),
            ])
            ->assertRedirect(route('purchases.show', $bill));

        $attachment = Attachment::query()->latest('id')->firstOrFail();

        $this->assertDatabaseHas('attachments', [
            'company_id' => $bill->company_id,
            'attachable_type' => PurchaseBill::class,
            'attachable_id' => $bill->id,
            'original_name' => 'facture-fournisseur.pdf',
        ]);

        Storage::disk('public')->assertExists($attachment->path);

        $this->get(route('documents.attachments.show', $attachment))
            ->assertOk();
    }

    public function test_manager_can_retry_failed_outbox_event_from_operations_screen(): void
    {
        $user = $this->actingAsManager();
        $company = Company::query()->whereKey($user->company_id)->firstOrFail();

        $event = IntegrationEvent::query()->create([
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'aggregate_type' => Company::class,
            'aggregate_id' => (string) $company->id,
            'event_name' => 'company.export.failed',
            'payload' => ['company' => $company->name],
            'status' => 'failed',
            'available_at' => now()->subMinutes(30),
            'attempts' => 3,
            'last_error' => 'HTTP 504 upstream timeout',
        ]);

        $this->from(route('ops.index'))
            ->post(route('ops.outbox.retry', $event))
            ->assertRedirect(route('ops.index'));

        $event->refresh();

        $this->assertSame('pending', $event->status);
        $this->assertNull($event->last_error);
        $this->assertNull($event->published_at);
    }

    private function actingAsManager(): User
    {
        $user = User::query()->where('email', 'manager@nema-erp.test')->firstOrFail();

        $this->actingAs($user)->withSession([
            'current_tenant_id' => $user->tenant_id,
            'current_company_id' => $user->company_id,
            'current_branch_id' => $user->branch_id,
        ]);

        return $user;
    }
}