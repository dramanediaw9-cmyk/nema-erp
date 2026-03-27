@extends('layouts.app')

@section('title', 'Operations - Nema ERP')
@section('page-title', 'Operations')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Sante systeme</h2>
            <div class="muted">Vue exploitation, readiness production et surveillance de l outbox.</div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <span class="badge {{ $report['overall_status'] === 'ok' ? 'badge-success' : ($report['overall_status'] === 'warning' ? 'badge-warning' : 'badge-muted') }}">
                Etat global : {{ strtoupper($report['overall_status']) }}
            </span>
            <span class="badge badge-muted">{{ $report['captured_at']->format('d/m/Y H:i') }}</span>
        </div>
    </div>

    <div class="grid stats-grid" style="margin-bottom:20px;">
        <div class="card"><div class="muted">Statut global</div><div class="stat-value">{{ strtoupper($report['overall_status']) }}</div></div>
        <div class="card"><div class="muted">Alertes</div><div class="stat-value">{{ $report['warning_count'] }}</div></div>
        <div class="card"><div class="muted">Echecs</div><div class="stat-value">{{ $report['failure_count'] }}</div></div>
        <div class="card"><div class="muted">Queue</div><div class="stat-value">{{ $report['meta']['queue_connection'] }}</div></div>
    </div>

    <div class="split" style="margin-bottom:18px;">
        <section class="card">
            <h3 class="section-title">Checks</h3>
            <div class="grid" style="gap:12px;">
                @foreach ($report['checks'] as $check)
                    <div class="summary-box">
                        <div style="display:flex; justify-content:space-between; gap:12px; align-items:center;">
                            <strong>{{ $check['label'] }}</strong>
                            <span class="badge {{ $check['status'] === 'ok' ? 'badge-success' : ($check['status'] === 'warning' ? 'badge-warning' : 'badge-muted') }}">{{ strtoupper($check['status']) }}</span>
                        </div>
                        <div class="muted" style="margin-top:8px;">{{ $check['message'] }}</div>
                        @if (! empty($check['meta']))
                            <pre style="margin-top:12px; padding:12px; background:#f8f3ea; border-radius:14px; overflow:auto; font-size:12px;">{{ json_encode($check['meta'], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>

        <section class="card">
            <h3 class="section-title">Exploitation</h3>
            <div class="summary-stack">
                <div class="summary-box">
                    <strong>Commandes utiles</strong>
                    <div class="muted" style="margin-top:8px;">`php artisan nema:ops:health-check --store`</div>
                    <div class="muted">`php artisan nema:ops:outbox-retry-failed --limit=50`</div>
                    <div class="muted">`php artisan nema:ops:outbox-prune --days=30`</div>
                    <div class="muted">`php artisan schedule:list`</div>
                </div>
                <div class="summary-box">
                    <strong>Pipeline</strong>
                    <div class="muted" style="margin-top:8px;">Workflow CI genere dans `.github/workflows/ci.yml`.</div>
                </div>
                <div class="summary-box">
                    <strong>Historique recent</strong>
                    <ul class="summary-list">
                        @forelse ($snapshots as $snapshot)
                            <li>{{ $snapshot->captured_at?->format('d/m H:i') }} - {{ strtoupper($snapshot->overall_status) }} - {{ $snapshot->warning_count }} alerte(s) - {{ $snapshot->failure_count }} echec(s)</li>
                        @empty
                            <li>Aucun snapshot enregistre pour le moment.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </section>
    </div>

    <section class="card">
        <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start; flex-wrap:wrap; margin-bottom:16px;">
            <div>
                <h3 class="section-title">Outbox integration</h3>
                <div class="muted">Surveillance des evenements metier a publier, reprise manuelle des echecs et lecture rapide des files en attente.</div>
            </div>
            <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                <span class="badge badge-warning">En attente : {{ $outboxSummary['pending'] }}</span>
                <span class="badge {{ $outboxSummary['failed'] > 0 ? 'badge-warning' : 'badge-success' }}">En echec : {{ $outboxSummary['failed'] }}</span>
                @if ($outboxSummary['failed'] > 0)
                    <form method="POST" action="{{ route('ops.outbox.retry-failed') }}">
                        @csrf
                        <button type="submit" class="button button-secondary">Relancer les echecs</button>
                    </form>
                @endif
            </div>
        </div>

        @if ($outboxSummary['failed'] > 0 || $outboxSummary['pending'] > 0)
            <div class="help" style="margin-bottom:16px;">
                Plus ancien en attente : {{ $outboxSummary['oldest_pending_at'] ? \Illuminate\Support\Carbon::parse($outboxSummary['oldest_pending_at'])->format('d/m/Y H:i') : 'Aucun' }} · Dernier echec : {{ $outboxSummary['last_failed_at'] ? \Illuminate\Support\Carbon::parse($outboxSummary['last_failed_at'])->format('d/m/Y H:i') : 'Aucun' }}
            </div>
        @endif

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Evenement</th>
                        <th>Aggregate</th>
                        <th>Statut</th>
                        <th>Tentatives</th>
                        <th>Disponible</th>
                        <th>Publie</th>
                        <th>Derniere erreur</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($outboxEvents as $event)
                        <tr>
                            <td>#{{ $event->id }}</td>
                            <td>{{ $event->event_name }}</td>
                            <td>{{ class_basename($event->aggregate_type) }} / {{ $event->aggregate_id }}</td>
                            <td><span class="badge {{ $event->status === 'published' ? 'badge-success' : ($event->status === 'pending' ? 'badge-warning' : 'badge-muted') }}">{{ $event->status }}</span></td>
                            <td>{{ $event->attempts }}</td>
                            <td>{{ $event->available_at?->format('d/m/Y H:i') ?: '-' }}</td>
                            <td>{{ $event->published_at?->format('d/m/Y H:i') ?: '-' }}</td>
                            <td>{{ $event->last_error ?: '-' }}</td>
                            <td>
                                @if ($event->status === 'failed')
                                    <form method="POST" action="{{ route('ops.outbox.retry', $event) }}">
                                        @csrf
                                        <button type="submit" class="button button-secondary">Relancer</button>
                                    </form>
                                @else
                                    <span class="muted">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="muted">Aucun evenement outbox pour cette societe.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection