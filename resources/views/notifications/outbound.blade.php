@extends('layouts.app')

@section('title', 'Notifications sortantes')
@section('page-title', 'Notifications sortantes')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">File d envoi approbations</h2>
            <div class="muted">Les notifications email utilisent le mailer Laravel configure. WhatsApp passe par un webhook/API configurable via les variables d environnement.</div>
        </div>
        @allowed('settings.manage')
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <form method="POST" action="{{ route('notifications.outbound.process') }}">
                    @csrf
                    <button type="submit" class="button button-primary">Traiter la file</button>
                </form>
                @if (($summary['failed'] ?? 0) > 0)
                    <form method="POST" action="{{ route('notifications.outbound.retry-failed') }}">
                        @csrf
                        <button type="submit" class="button button-secondary">Relancer les echecs</button>
                    </form>
                @endif
            </div>
        @endallowed
    </div>

    <div class="grid stats-grid" style="margin-bottom:20px;">
        <div class="card"><div class="muted">En attente</div><div class="stat-value">{{ $summary['queued'] }}</div></div>
        <div class="card"><div class="muted">Envoyees</div><div class="stat-value">{{ $summary['sent'] }}</div></div>
        <div class="card"><div class="muted">En echec</div><div class="stat-value">{{ $summary['failed'] }}</div></div>
        <div class="card"><div class="muted">Annulees</div><div class="stat-value">{{ $summary['cancelled'] ?? 0 }}</div></div>
    </div>

    @if (($summary['queued'] ?? 0) > 0 || ($summary['failed'] ?? 0) > 0 || ($summary['sent'] ?? 0) > 0 || ($summary['cancelled'] ?? 0) > 0)
        <div class="help" style="margin-bottom:18px;">
            Plus ancienne en attente : {{ $summary['oldest_queued_at'] ? \Illuminate\Support\Carbon::parse($summary['oldest_queued_at'])->format('d/m/Y H:i') : 'Aucune' }}
            · Dernier envoi : {{ $summary['last_sent_at'] ? \Illuminate\Support\Carbon::parse($summary['last_sent_at'])->format('d/m/Y H:i') : 'Aucun' }}
            · Dernier echec : {{ $summary['last_failed_at'] ? \Illuminate\Support\Carbon::parse($summary['last_failed_at'])->format('d/m/Y H:i') : 'Aucun' }}
            · Derniere annulation : {{ ($summary['last_cancelled_at'] ?? null) ? \Illuminate\Support\Carbon::parse($summary['last_cancelled_at'])->format('d/m/Y H:i') : 'Aucune' }}
        </div>
    @endif

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
                    <option value="cancelled" @selected(($filters['status'] ?? null) === 'cancelled')>Annule</option>
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
                    <th>Dernier evenement</th>
                    <th>Erreur</th>
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
                            <span class="badge {{ $notification->status === 'failed' ? 'badge-warning' : ($notification->status === 'sent' ? 'badge-success' : ($notification->status === 'cancelled' ? 'badge-danger' : 'badge-muted')) }}">
                                {{ $notification->status === 'queued' ? 'En attente' : ($notification->status === 'sent' ? 'Envoye' : ($notification->status === 'cancelled' ? 'Annulee' : 'En erreur')) }}
                            </span>
                        </td>
                        <td>
                            @if ($notification->status === 'sent')
                                <div>{{ $notification->sent_at?->format('d/m/Y H:i') ?: 'N/A' }}</div>
                                <div class="muted">{{ strtoupper((string) data_get($notification->meta, 'delivery.transport', '')) }}</div>
                            @elseif ($notification->status === 'cancelled')
                                <div>{{ $notification->failed_at?->format('d/m/Y H:i') ?: 'N/A' }}</div>
                                <div class="muted">Annulee par le workflow</div>
                            @elseif ($notification->status === 'failed')
                                <div>{{ $notification->failed_at?->format('d/m/Y H:i') ?: 'N/A' }}</div>
                                <div class="muted">Tentative en echec</div>
                            @else
                                <div>{{ $notification->queued_at?->format('d/m/Y H:i') ?: $notification->created_at?->format('d/m/Y H:i') }}</div>
                                <div class="muted">File d attente</div>
                            @endif
                        </td>
                        <td>{{ $notification->failure_reason ?: '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="muted">Aucune notification sortante pour ce filtre.</td>
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
