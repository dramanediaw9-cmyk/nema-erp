@extends('layouts.app')

@section('title', 'Balance - Nema ERP')
@section('page-title', 'Balance comptable')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Balance generale</h2>
            <div class="muted">Vue synthetique des comptes sur la periode selectionnee.</div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="{{ route('accounting.balance.export', request()->query()) }}" class="button button-secondary">Exporter CSV</a>
            <a href="{{ route('accounting.balance.print', request()->query()) }}" class="button button-secondary" target="_blank">Imprimer</a>
        </div>
    </div>

    <section class="card" style="margin-bottom:20px;">
        <form method="GET" action="{{ route('accounting.balance.index') }}" class="form-grid" style="align-items:end;">
            <div>
                <label for="date_from">Date debut</label>
                <input type="date" id="date_from" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
            </div>
            <div>
                <label for="date_to">Date fin</label>
                <input type="date" id="date_to" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
            </div>
            <div class="full actions" style="margin-top:0; justify-content:flex-start;">
                <button type="submit" class="button button-primary">Filtrer la balance</button>
                <a href="{{ route('accounting.balance.index') }}" class="button button-secondary">Reinitialiser</a>
            </div>
        </form>
    </section>

    <div class="grid stats-grid" style="margin-bottom:20px;">
        <div class="card"><div class="muted">Debit cumule</div><div class="stat-value">{{ number_format((float) $summary['debit'], 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Credit cumule</div><div class="stat-value">{{ number_format((float) $summary['credit'], 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Resultat cumule</div><div class="stat-value">{{ number_format((float) $summary['result'], 0, ',', ' ') }}</div></div>
    </div>

    <section class="card table-wrap">
        <table>
            <thead>
            <tr>
                <th>Code</th>
                <th>Libelle</th>
                <th>Type</th>
                <th>Debit</th>
                <th>Credit</th>
                <th>Solde</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($balances as $balance)
                <tr>
                    <td><strong>{{ $balance['code'] }}</strong></td>
                    <td>{{ $balance['name'] }}</td>
                    <td>{{ ucfirst($balance['type']) }}</td>
                    <td>{{ number_format((float) $balance['total_debit'], 0, ',', ' ') }} XOF</td>
                    <td>{{ number_format((float) $balance['total_credit'], 0, ',', ' ') }} XOF</td>
                    <td>{{ number_format((float) $balance['balance'], 0, ',', ' ') }} XOF</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="muted">Aucune donnee comptable disponible.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endsection
