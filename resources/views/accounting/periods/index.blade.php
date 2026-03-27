@extends('layouts.app')

@section('title', 'Periodes comptables')
@section('page-title', 'Periodes comptables')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Cloture et reouverture</h2>
            <div class="muted">Controle les dates autorisees pour les ventes, achats, depenses, paiements et ecritures.</div>
        </div>
    </div>

    @if ($currentPeriodSummary)
        <div class="card" style="margin-bottom:18px; border-color:#d8c79a; background:linear-gradient(135deg, #fffaf0 0%, #f9f4e8 100%);">
            <div style="display:flex; justify-content:space-between; gap:18px; align-items:flex-start; flex-wrap:wrap;">
                <div style="max-width:760px;">
                    <div class="badge {{ $currentPeriodSummary['can_close'] ? 'badge-success' : 'badge-warning' }}">
                        {{ $currentPeriodSummary['can_close'] ? 'Cloture possible' : 'Cloture bloquee' }}
                    </div>
                    <h3 style="margin:12px 0 8px;">{{ $currentPeriodSummary['period']?->name }}</h3>
                    <div class="muted">Etat de la periode courante du {{ $currentPeriodSummary['start_date']->format('d/m/Y') }} au {{ $currentPeriodSummary['end_date']->format('d/m/Y') }}.</div>
                </div>
            </div>
            <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); margin-top:18px;">
                @foreach ($currentPeriodSummary['checklist'] as $item)
                    <div class="card" style="padding:16px;">
                        <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start;">
                            <strong style="max-width:70%;">{{ $item['title'] }}</strong>
                            <span class="badge {{ $item['state'] === 'blocked' ? 'badge-warning' : ($item['state'] === 'warning' ? 'badge-muted' : 'badge-success') }}">
                                {{ $item['state'] === 'blocked' ? 'Bloquant' : ($item['state'] === 'warning' ? 'A suivre' : 'OK') }}
                            </span>
                        </div>
                        <div class="stat-value" style="font-size:24px; margin-top:8px;">{{ $item['count'] }}</div>
                        <div class="muted">{{ $item['message'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="split">
        <div class="card">
            <h3 style="margin-top:0;">Nouvelle periode</h3>
            <form method="POST" action="{{ route('accounting.periods.store') }}">
                @csrf
                <div class="form-grid">
                    <div class="full">
                        <label for="name">Nom de la periode</label>
                        <input id="name" name="name" type="text" value="{{ old('name') }}" placeholder="Ex. Periode 04/2026" required>
                    </div>
                    <div>
                        <label for="start_date">Date debut</label>
                        <input id="start_date" name="start_date" type="date" value="{{ old('start_date', now()->startOfMonth()->toDateString()) }}" required>
                    </div>
                    <div>
                        <label for="end_date">Date fin</label>
                        <input id="end_date" name="end_date" type="date" value="{{ old('end_date', now()->endOfMonth()->toDateString()) }}" required>
                    </div>
                </div>
                <div class="actions">
                    <button type="submit" class="button button-primary">Creer la periode</button>
                </div>
            </form>
        </div>

        <div class="card">
            <h3 style="margin-top:0;">Regles du verrouillage</h3>
            <ul style="margin:0;padding-left:18px;line-height:1.7;">
                <li>Une periode cloturee bloque toute operation datee sur son intervalle.</li>
                <li>Les imports historiques respectent aussi ce verrouillage.</li>
                <li>La reouverture permet de corriger une periode si necessaire.</li>
            </ul>
        </div>
    </div>

    <div class="card" style="margin-top:18px;">
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Periode</th>
                    <th>Intervalle</th>
                    <th>Statut</th>
                    <th>Pilotage</th>
                    <th>Cloturee par</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($periods as $period)
                    @php($snapshot = $periodSnapshots[$period->id] ?? null)
                    <tr>
                        <td>
                            <strong>{{ $period->name }}</strong>
                            <div class="muted">Creee le {{ $period->created_at?->format('d/m/Y H:i') }}</div>
                        </td>
                        <td>{{ $period->start_date?->format('d/m/Y') }} - {{ $period->end_date?->format('d/m/Y') }}</td>
                        <td>
                            <span class="badge {{ $period->isClosed() ? 'badge-warning' : 'badge-success' }}">
                                {{ $period->isClosed() ? 'Cloturee' : 'Ouverte' }}
                            </span>
                        </td>
                        <td>
                            @if ($snapshot)
                                <div class="muted">{{ $snapshot['can_close'] ? 'Cloture possible' : 'Cloture bloquee' }}</div>
                                <div class="muted" style="margin-top:6px;">Blocages : {{ $snapshot['blockers'] }} · Alertes : {{ $snapshot['warnings'] }}</div>
                                <div class="muted" style="margin-top:6px;">{{ $snapshot['journal_entries_count'] }} ecriture(s) · {{ $snapshot['open_sales_count'] }} creance(s) · {{ $snapshot['open_purchases_count'] }} dette(s)</div>
                            @else
                                <span class="muted">Aucun indicateur</span>
                            @endif
                        </td>
                        <td>
                            @if ($period->closer)
                                <strong>{{ $period->closer->name }}</strong>
                                <div class="muted">{{ $period->closed_at?->format('d/m/Y H:i') }}</div>
                            @else
                                <span class="muted">Non cloturee</span>
                            @endif
                        </td>
                        <td>
                            @if ($period->isClosed())
                                <form method="POST" action="{{ route('accounting.periods.reopen', $period) }}" class="inline-form">
                                    @csrf
                                    <button type="submit" class="button button-secondary">Reouvrir</button>
                                </form>
                            @elseif ($snapshot && ! $snapshot['can_close'])
                                <button type="button" class="button button-secondary" style="opacity:.6; cursor:not-allowed;" disabled>Cloture bloquee</button>
                                <div class="muted" style="margin-top:8px; max-width:220px;">Approuve les documents en attente avant de fermer cette periode.</div>
                            @else
                                <form method="POST" action="{{ route('accounting.periods.close', $period) }}" class="inline-form">
                                    @csrf
                                    <button type="submit" class="button button-danger">Cloturer</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="muted">Aucune periode comptable definie.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top:18px;">
            {{ $periods->links() }}
        </div>
    </div>
@endsection
