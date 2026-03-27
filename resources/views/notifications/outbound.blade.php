@extends('layouts.app')

@section('title', 'Notifications sortantes')
@section('page-title', 'Notifications sortantes')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">File d envoi approbations</h2>
            <div class="muted">Base email et WhatsApp prete pour raccordement fournisseur. Les messages sont traces ici avant integration d envoi reel.</div>
        </div>
    </div>

    <div class="card" style="margin-bottom:18px;">
        <form method="GET" class="form-grid" style="grid-template-columns: repeat(3, minmax(0, 1fr)); align-items:end;">
            <div>
                <label for="channel">Canal</label>
                <select id="channel" name="channel">
                    <option value="">Tous</option>
                    <option value="email" @selected(($filters['channel'] ?? null) === 'email')>Email</option>
                    <option value="whatsapp" @selected(($filters['channel'] ?? null) === 'whatsapp')>WhatsApp</option>
                </select>
            </div>
            <div>
                <label for="status">Statut</label>
                <select id="status" name="status">
                    <option value="">Tous</option>
                    <option value="queued" @selected(($filters['status'] ?? null) === 'queued')>En attente</option>
                    <option value="sent" @selected(($filters['status'] ?? null) === 'sent')>Envoye</option>
                    <option value="failed" @selected(($filters['status'] ?? null) === 'failed')>En erreur</option>
                </select>
            </div>
            <div class="actions" style="margin-top:0; justify-content:flex-start;">
                <button class="button button-primary" type="submit">Filtrer</button>
                <a class="button button-secondary" href="{{ route('notifications.outbound.index') }}">Reinitialiser</a>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Canal</th>
                    <th>Destinataire</th>
                    <th>Document</th>
                    <th>Etape</th>
                    <th>Statut</th>
                    <th>Cree le</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($notifications as $notification)
                    <tr>
                        <td>{{ strtoupper($notification->channel) }}</td>
                        <td>
                            <div>{{ $notification->recipient }}</div>
                            <div class="muted">{{ $notification->user?->name ?? 'Copie libre' }}</div>
                        </td>
                        <td>
                            <div>{{ $notification->meta['document_number'] ?? 'N/A' }}</div>
                            <div class="muted">{{ str($notification->meta['module'] ?? '')->title() }}</div>
                        </td>
                        <td>{{ $notification->meta['step_label'] ?? 'N/A' }}</td>
                        <td>
                            <span class="badge {{ $notification->status === 'failed' ? 'badge-warning' : ($notification->status === 'sent' ? 'badge-success' : 'badge-muted') }}">
                                {{ $notification->status === 'queued' ? 'En attente' : ($notification->status === 'sent' ? 'Envoye' : 'En erreur') }}
                            </span>
                        </td>
                        <td>{{ $notification->created_at?->format('d/m/Y H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="muted">Aucune notification sortante pour ce filtre.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div style="margin-top:18px;">
        {{ $notifications->links() }}
    </div>
@endsection
