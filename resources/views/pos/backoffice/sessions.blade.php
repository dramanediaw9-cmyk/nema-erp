@extends('layouts.app')

@section('title', 'Sessions POS - Nema ERP')
@section('page-title', 'Sessions POS')

@section('content')
    <div class="grid" style="gap:18px;">
        @include('pos.partials.backoffice-nav')

        <div class="page-head">
            <div>
                <h2 style="margin:0;">Pilotage des sessions</h2>
                <div class="muted">Ouvertures, clotures, ecarts de caisse et suivi de la performance par session.</div>
            </div>
            <a href="{{ route('pos.index') }}" class="button button-secondary">Retour tableau POS</a>
        </div>

        <div class="grid stats-grid">
            <div class="card"><div class="muted">Ouvertes</div><div class="stat-value">{{ $data['summary']['open_sessions'] }}</div></div>
            <div class="card"><div class="muted">Cloturees</div><div class="stat-value">{{ $data['summary']['closed_sessions'] }}</div></div>
            <div class="card"><div class="muted">Caisse attendue</div><div class="stat-value">{{ number_format($data['summary']['expected_cash'], 0, ',', ' ') }} XOF</div></div>
            <div class="card"><div class="muted">Variance totale</div><div class="stat-value">{{ number_format($data['summary']['variance_total'], 0, ',', ' ') }} XOF</div></div>
        </div>

        <section class="card">
            <h3 class="section-title">Sessions recentes</h3>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Session</th>
                            <th>Entrepot</th>
                            <th>Caisse</th>
                            <th>Ouverte par</th>
                            <th>Statut</th>
                            <th>Variance</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($data['sessions'] as $session)
                            <tr>
                                <td>{{ $session->session_number }}</td>
                                <td>{{ $session->warehouse?->name ?? 'n/a' }}</td>
                                <td>{{ $session->cashAccount?->name ?? 'n/a' }}</td>
                                <td>{{ $session->opener?->name ?? 'n/a' }}</td>
                                <td><span class="badge {{ $session->status === 'open' ? 'badge-success' : 'badge-muted' }}">{{ strtoupper($session->status) }}</span></td>
                                <td>{{ number_format((float) $session->variance_amount, 0, ',', ' ') }} XOF</td>
                                <td><a href="{{ route('pos.show', $session) }}" class="button button-secondary">Voir</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="muted">Aucune session POS disponible.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
