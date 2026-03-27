@extends('layouts.app')

@section('title', 'CRM - Nema ERP')
@section('page-title', 'CRM')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Pipeline commercial</h2>
            <div class="muted">Suivre les opportunites avant qu elles deviennent devis, commandes ou clients actifs.</div>
        </div>
        @allowed('crm.manage')
            <a href="{{ route('crm.create') }}" class="button button-primary">Nouvelle opportunite</a>
        @endallowed
    </div>

    <div class="grid stats-grid" style="margin-bottom:20px;">
        <div class="card"><div class="muted">Opportunites</div><div class="stat-value">{{ $summary['count'] }}</div></div>
        <div class="card"><div class="muted">Pipeline ouvert</div><div class="stat-value">{{ $summary['open_count'] }}</div></div>
        <div class="card"><div class="muted">Montant pipeline</div><div class="stat-value">{{ number_format($summary['pipeline_total'], 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Montant gagne</div><div class="stat-value">{{ number_format($summary['won_total'], 0, ',', ' ') }}</div></div>
    </div>

    <div class="card table-wrap">
        <table>
            <thead>
            <tr>
                <th>Lead</th>
                <th>Opportunite</th>
                <th>Agence</th>
                <th>Etape</th>
                <th>Montant</th>
                <th>Echeance</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($opportunities as $opportunity)
                <tr>
                    <td>
                        <div style="font-weight:600;">{{ $opportunity->lead_name }}</div>
                        <div class="muted" style="margin-top:6px;">{{ $opportunity->contact_phone ?: ($opportunity->partner?->code ?? 'Contact non renseigne') }}</div>
                    </td>
                    <td>
                        <div style="font-weight:600;">{{ $opportunity->title }}</div>
                        <div class="muted" style="margin-top:6px;">{{ $opportunity->source ?: 'Source non renseignee' }}</div>
                    </td>
                    <td>{{ $opportunity->branch?->name ?? 'Agence active' }}</td>
                    <td><span class="badge {{ in_array($opportunity->stage, ['won'], true) ? 'badge-success' : (in_array($opportunity->stage, ['lost'], true) ? 'badge-warning' : 'badge-muted') }}">{{ $stageOptions[$opportunity->stage] ?? ucfirst($opportunity->stage) }}</span></td>
                    <td>{{ number_format((float) ($opportunity->expected_amount ?? 0), 0, ',', ' ') }} XOF</td>
                    <td>{{ $opportunity->expected_close_date?->format('d/m/Y') ?: 'Non renseignee' }}</td>
                    <td><a href="{{ route('crm.show', $opportunity) }}" class="button button-secondary">Ouvrir</a></td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="muted">Aucune opportunite commerciale enregistree.</td>
                </tr>
            @endforelse
            </tbody>
        </table>

        <div style="margin-top:18px;">{{ $opportunities->links() }}</div>
    </div>
@endsection
