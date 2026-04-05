<?php

namespace App\Modules\Core\Collaboration\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Collaboration\Models\Attachment;
use App\Modules\Core\Collaboration\Services\DocumentCollaborationService;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentCollaborationController extends Controller
{
    public function __construct(
        private readonly DocumentCollaborationService $documentCollaborationService,
        private readonly ActivityLogger $activityLogger,
    ) {
    }

    public function storeComment(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $data = $request->validate([
            'document_type' => ['required', 'string', Rule::in(array_keys($this->documentCollaborationService->definitions()))],
            'document_id' => ['required', 'integer'],
            'comment_body' => ['required', 'string', 'max:5000'],
        ]);

        [$document, $definition] = $this->documentCollaborationService->resolve($data['document_type'], $data['document_id'], $companyId);
        abort_unless($request->user()?->hasPermission($definition['manage_permission']), 403);

        $comment = $this->documentCollaborationService->storeComment($document, $request->user(), $data['comment_body']);

        $this->activityLogger->log('documents.comment', 'Ajout commentaire interne', $document, [
            'document_type' => $data['document_type'],
            'comment_id' => $comment->id,
            'length' => mb_strlen($comment->body),
        ]);

        return back()->with('success', 'Commentaire interne ajoute avec succes.');
    }

    public function storeAttachment(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $data = $request->validate([
            'document_type' => ['required', 'string', Rule::in(array_keys($this->documentCollaborationService->definitions()))],
            'document_id' => ['required', 'integer'],
            'attachment_file' => ['required', 'file', 'max:12288', 'mimes:pdf,jpg,jpeg,png,webp,csv,txt,xls,xlsx,doc,docx'],
        ]);

        [$document, $definition] = $this->documentCollaborationService->resolve($data['document_type'], $data['document_id'], $companyId);
        abort_unless($request->user()?->hasPermission($definition['manage_permission']), 403);

        $attachment = $this->documentCollaborationService->storeAttachment($document, $request->user(), $request->file('attachment_file'));

        $this->activityLogger->log('documents.attach', 'Ajout piece jointe', $document, [
            'document_type' => $data['document_type'],
            'attachment_id' => $attachment->id,
            'original_name' => $attachment->original_name,
            'size_bytes' => $attachment->size_bytes,
        ]);

        return back()->with('success', 'Piece jointe ajoutee avec succes.');
    }

    public function showAttachment(Attachment $attachment, CurrentWorkspace $workspace): StreamedResponse
    {
        abort_if($workspace->companyId() !== $attachment->company_id, 403);

        $attachable = $attachment->attachable;
        abort_if(! $attachable, 404);

        $definition = $this->documentCollaborationService->definitionForModel($attachable);
        abort_if(! $definition, 404);
        abort_unless(request()->user()?->hasPermission($definition['view_permission']), 403);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->response($attachment->path, $attachment->original_name);
    }
}