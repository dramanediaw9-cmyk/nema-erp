<?php

namespace App\Modules\Core\Collaboration\Services;

use App\Models\User;
use App\Modules\Core\Collaboration\Models\Attachment;
use App\Modules\Core\Collaboration\Models\InternalComment;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Treasury\Models\Payment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class DocumentCollaborationService
{
    public function definitions(): array
    {
        return [
            'sales_invoice' => [
                'model' => SalesInvoice::class,
                'label' => 'Facture client',
                'view_permission' => 'sales.view',
                'manage_permission' => 'sales.manage',
                'route' => 'sales.show',
            ],
            'purchase_bill' => [
                'model' => PurchaseBill::class,
                'label' => 'Facture fournisseur',
                'view_permission' => 'purchases.view',
                'manage_permission' => 'purchases.manage',
                'route' => 'purchases.show',
            ],
            'expense' => [
                'model' => Expense::class,
                'label' => 'Depense',
                'view_permission' => 'expenses.view',
                'manage_permission' => 'expenses.manage',
                'route' => 'expenses.show',
            ],
            'payment' => [
                'model' => Payment::class,
                'label' => 'Paiement',
                'view_permission' => 'payments.view',
                'manage_permission' => 'payments.validate',
                'route' => 'payments.show',
            ],
        ];
    }

    public function definitionForType(string $type): array
    {
        $definition = $this->definitions()[$type] ?? null;

        if (! $definition) {
            throw new InvalidArgumentException('Type de document non supporte.');
        }

        return $definition;
    }

    public function slugForModel(Model $document): ?string
    {
        foreach ($this->definitions() as $slug => $definition) {
            if ($definition['model'] === $document::class) {
                return $slug;
            }
        }

        return null;
    }

    public function definitionForModel(Model $document): ?array
    {
        $slug = $this->slugForModel($document);

        return $slug ? $this->definitions()[$slug] : null;
    }

    public function resolve(string $type, int|string $id, int $companyId): array
    {
        $definition = $this->definitionForType($type);
        $modelClass = $definition['model'];

        /** @var Model $document */
        $document = $modelClass::query()
            ->where('company_id', $companyId)
            ->findOrFail($id);

        return [$document, $definition];
    }

    public function storeComment(Model $document, User $user, string $body): InternalComment
    {
        return $document->internalComments()->create([
            'tenant_id' => $document->getAttribute('tenant_id'),
            'company_id' => $document->getAttribute('company_id'),
            'body' => trim($body),
            'created_by' => $user->id,
        ]);
    }

    public function storeAttachment(Model $document, User $user, UploadedFile $file): Attachment
    {
        $disk = (string) config('nema.document_attachment_disk', 'public');
        $slug = $this->slugForModel($document) ?: Str::slug(class_basename($document));
        $extension = $file->getClientOriginalExtension() ?: $file->extension() ?: 'bin';
        $relativePath = sprintf(
            'companies/%d/documents/%s/%s/%s.%s',
            (int) $document->getAttribute('company_id'),
            $slug,
            (string) $document->getKey(),
            Str::uuid(),
            Str::lower($extension)
        );

        Storage::disk($disk)->putFileAs(
            dirname($relativePath),
            $file,
            basename($relativePath)
        );

        return $document->attachments()->create([
            'tenant_id' => $document->getAttribute('tenant_id'),
            'company_id' => $document->getAttribute('company_id'),
            'disk' => $disk,
            'path' => $relativePath,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize() ?: 0,
            'created_by' => $user->id,
        ]);
    }

    public function routeFor(Model $document): ?string
    {
        $definition = $this->definitionForModel($document);

        if (! $definition) {
            return null;
        }

        return route(Arr::get($definition, 'route'), $document, false);
    }
}
