@extends('layouts.app')

@section('title', 'Budgets - Nema ERP')
@section('page-title', 'Budgets')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Budgets de pilotage</h2>
            <div class="muted">Comparer objectifs et realise par exercice et par agence.</div>
        </div>
        @allowed('budgets.manage')
            <a href="{{ route('budgets.create') }}" class="button button-primary">Nouveau budget</a>
        @endallowed
    </div>

    <div class="grid stats-grid" style="margin-bottom:20px;">
        <div class="card"><div class="muted">Budgets</div><div class="stat-value">{{ $summary['budget_count'] }}</div></div>
        <div class="card"><div class="muted">Budgets actifs</div><div class="stat-value">{{ $summary['active_count'] }}</div></div>
        <div class="card"><div class="muted">Budget cumule</div><div class="stat-value">{{ number_format($summary['planned_total'], 0, ',', ' ') }}</div></div>
    </div>

    <div class="card table-wrap">
        <table>
            <thead>
            <tr>
                <th>Nom</th>
                <th>Exercice</th>
                <th>Agence</th>
                <th>Statut</th>
                <th>Budget total</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($budgets as $budget)
                <tr>
                    <td>
                        <div style="font-weight:600;">{{ $budget->name }}</div>
                        @if ($budget->notes)
                            <div class="muted" style="margin-top:6px;">{{ \Illuminate\Support\Str::limit($budget->notes, 90) }}</div>
                        @endif
                    </td>
                    <td>{{ $budget->fiscal_year }}</td>
                    <td>{{ $budget->branch?->name ?? 'Toutes les agences' }}</td>
                    <td>
                        <span class="badge {{ $budget->status === 'active' ? 'badge-success' : ($budget->status === 'closed' ? 'badge-warning' : 'badge-muted') }}">{{ $statusOptions[$budget->status] ?? ucfirst($budget->status) }}</span>
                    </td>
                    <td>{{ number_format((float) ($budget->planned_total ?? 0), 0, ',', ' ') }} XOF</td>
                    <td><a href="{{ route('budgets.show', $budget) }}" class="button button-secondary">Ouvrir</a></td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="muted">Aucun budget defini pour le moment.</td>
                </tr>
            @endforelse
            </tbody>
        </table>

        <div style="margin-top:18px;">{{ $budgets->links() }}</div>
    </div>
@endsection
