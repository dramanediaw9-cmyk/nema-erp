<div class="split" style="margin-top:20px;">
    <section class="card" id="attachments">
        <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start; flex-wrap:wrap;">
            <div>
                <h2 style="margin:0;">Pieces jointes</h2>
                <div class="muted" style="margin-top:6px;">Documents internes lies a ce dossier : facture scannee, justificatif, bon signe ou capture utile.</div>
            </div>
            <span class="badge badge-muted">{{ $document->attachments->count() }} fichier(s)</span>
        </div>

        @allowed($managePermission)
            <form method="POST" action="{{ route('documents.attachments.store') }}" enctype="multipart/form-data" style="margin-top:16px;">
                @csrf
                <input type="hidden" name="document_type" value="{{ $documentType }}">
                <input type="hidden" name="document_id" value="{{ $document->getKey() }}">
                <div class="form-grid">
                    <div>
                        <label for="attachment_file_{{ $documentType }}">Ajouter un fichier</label>
                        <input id="attachment_file_{{ $documentType }}" type="file" name="attachment_file" accept=".pdf,.jpg,.jpeg,.png,.webp,.csv,.txt,.xls,.xlsx,.doc,.docx" required>
                        <div class="help" style="margin-top:6px;">Max 12 Mo. Formats usuels bureau, PDF et images.</div>
                        @error('attachment_file')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="actions">
                    <button type="submit" class="button button-secondary">Ajouter la piece jointe</button>
                </div>
            </form>
        @endallowed

        <div style="margin-top:18px; display:grid; gap:14px;">
            @forelse ($document->attachments as $attachment)
                <div style="padding-bottom:14px; border-bottom:1px solid #efe4d3;">
                    <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start; flex-wrap:wrap;">
                        <div>
                            <div style="font-weight:600;">
                                <a href="{{ route('documents.attachments.show', $attachment) }}" target="_blank">{{ $attachment->original_name }}</a>
                            </div>
                            <div class="muted" style="margin-top:6px;">{{ strtoupper($attachment->mime_type ?: 'FICHIER') }} · {{ $attachment->human_size }} · {{ $attachment->creator?->name ?? 'Systeme' }} · {{ $attachment->created_at?->format('d/m/Y H:i') }}</div>
                        </div>
                        <a href="{{ route('documents.attachments.show', $attachment) }}" class="button button-secondary" target="_blank">Ouvrir</a>
                    </div>
                </div>
            @empty
                <p class="muted" style="margin-top:18px;">Aucune piece jointe enregistree pour ce document.</p>
            @endforelse
        </div>
    </section>

    <section class="card" id="internal-comments">
        <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start; flex-wrap:wrap;">
            <div>
                <h2 style="margin:0;">Commentaires internes</h2>
                <div class="muted" style="margin-top:6px;">Trace courte d exploitation, points de blocage, informations partagees entre compta, ventes, achats et direction.</div>
            </div>
            <span class="badge badge-muted">{{ $document->internalComments->count() }} note(s)</span>
        </div>

        @allowed($managePermission)
            <form method="POST" action="{{ route('documents.comments.store') }}" style="margin-top:16px;">
                @csrf
                <input type="hidden" name="document_type" value="{{ $documentType }}">
                <input type="hidden" name="document_id" value="{{ $document->getKey() }}">
                <label for="comment_body_{{ $documentType }}">Ajouter un commentaire</label>
                <textarea id="comment_body_{{ $documentType }}" name="comment_body" rows="4" placeholder="Ex : facture re-verifiee, justificatif attendu, ecart confirme, relance faite...">{{ old('comment_body') }}</textarea>
                @error('comment_body')<div class="field-error">{{ $message }}</div>@enderror
                <div class="actions">
                    <button type="submit" class="button button-secondary">Publier la note interne</button>
                </div>
            </form>
        @endallowed

        <div style="margin-top:18px; display:grid; gap:14px;">
            @forelse ($document->internalComments as $comment)
                <div style="padding-bottom:14px; border-bottom:1px solid #efe4d3;">
                    <div style="font-weight:600;">{{ $comment->creator?->name ?? 'Systeme' }}</div>
                    <div class="muted" style="margin-top:6px;">{{ $comment->created_at?->format('d/m/Y H:i') }}</div>
                    <div style="margin-top:8px; white-space:pre-wrap;">{{ $comment->body }}</div>
                </div>
            @empty
                <p class="muted" style="margin-top:18px;">Aucun commentaire interne sur ce document.</p>
            @endforelse
        </div>
    </section>
</div>