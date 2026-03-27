@extends('layouts.app')

@section('title', 'Budget '.$budget->name.' - Nema ERP')
@section('page-title', 'Budget '.$budget->name)

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">{{ $budget->name }}</h2>
            <div class="muted">Exercice {{ $budget->fiscal_year }} · {{ $budget->branch?->name ?? 'Toutes les agences' }} · {{ $statusOptions[$budget->status] ?? ucfirst($budget->status) }}</div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="{{ route('budgets.index') }}" class="button button-secondary">Retour budgets</a>
            @allowed('reports.view')
                <a href="{{ route('reports.index') }}" class="button button-secondary">Voir les rapports</a>
            @endallowed
        </div>
    </div>

    <div class="grid stats-grid" style="margin-bottom:20px;">
        <div class="card"><div class="muted">Budget total</div><div class="stat-value">{{ number_format($totals['planned'], 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Realise total</div><div class="stat-value">{{ number_format($totals['actual'], 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Ecart</div><div class="stat-value">{{ number_format($totals['variance'], 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Lignes budgetaires</div><div class="stat-value">{{ $lineDetails->count() }}</div></div>
    </div>

    <div class="split" style="margin-bottom:20px;">
        <section class="card">
            <h2 style="margin-top:0;">Lecture budgetaire</h2>
            <div class="grid">
                <div><strong>Statut</strong><div class="muted">{{ $statusOptions[$budget->status] ?? ucfirst($budget->status) }}</div></div>
                <div><strong>Agence</strong><div class="muted">{{ $budget->branch?->name ?? 'Toutes les agences' }}</div></div>
                <div><strong>Cree par</strong><div class="muted">{{ $budget->creator?->name ?? 'Systeme' }}</div></div>
                <div><strong>Mis a jour par</strong><div class="muted">{{ $budget->updater?->name ?? 'Systeme' }}</div></div>
            </div>
            @if ($budget->notes)
                <div class="muted" style="margin-top:14px;">{{ $budget->notes }}</div>
            @endif
        </section>

        <section class="card">
            <h2 style="margin-top:0;">Synthese par axe</h2>
            <div class="summary-stack">
                @foreach ($summaryByMetric as $metric => $metricSummary)
                    <div class="summary-box">
                        <div style="font-weight:600;">{{ $metricOptions[$metric] ?? ucfirst($metric) }}</div>
                        <div class="help" style="margin-top:6px;">Budget {{ number_format($metricSummary['planned'], 0, ',', ' ') }} XOF · Realise {{ number_format($metricSummary['actual'], 0, ',', ' ') }} XOF</div>
                        <div class="help" style="margin-top:6px;">Ecart {{ number_format($metricSummary['variance'], 0, ',', ' ') }} XOF</div>
                    </div>
                @endforeach
            </div>
        </section>
    </div>

    <section class="card table-wrap">
        <h2 style="margin-top:0;">Detail mensuel objectif / realise</h2>
        <table>
            <thead>
            <tr>
                <th>Mois</th>
                <th>Axe</th>
                <th>Budget</th>
                <th>Realise</th>
                <th>Ecart</th>
                <th>Taux</th>
                <th>Note</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($lineDetails as $detail)
                @php($line = $detail['line'])
                <tr>
                    <td>{{ $monthOptions[$line->period_month] ?? $line->period_month }}</td>
                    <td>{{ $metricOptions[$line->metric] ?? ucfirst($line->metric) }}</td>
                    <td>{{ number_format($detail['planned_amount'], 0, ',', ' ') }} XOF</td>
                    <td>{{ number_format($detail['actual_amount'], 0, ',', ' ') }} XOF</td>
                    <td>{{ number_format($detail['variance'], 0, ',', ' ') }} XOF</td>
                    <td>{{ $detail['achievement_rate'] !== null ? number_format($detail['achievement_rate'], 1, ',', ' ').' %' : 'n/a' }}</td>
                    <td>{{ $line->notes ?: '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="muted">Aucune ligne budgetaire definie.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endsection
