@extends('layouts.app')

@section('title', 'Inventaires de stock')
@section('page-title', 'Inventaires de stock')

@section('content')
    <div class="page-head">
        <div>
            <h2 class="section-title">Inventaires de stock</h2>
            <div class="muted">Comptage physique, ecarts et regularisation du stock par entrepot.</div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            @allowed('stock_counts.manage')
                <a href="{{ route('stock-counts.quick') }}" class="button button-secondary">Inventaire rapide</a>
                <a href="{{ route('stock-counts.create') }}" class="button button-primary">Nouvel inventaire</a>
            @endallowed
        </div>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Numero</th>
                    <th>Date</th>
                    <th>Entrepot</th>
                    <th>Statut</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($counts as $count)
                    <tr>
                        <td>{{ $count->count_number }}</td>
                        <td>{{ $count->count_date?->format('d/m/Y') }}</td>
                        <td>{{ $count->warehouse?->name }}</td>
                        <td><span class="badge {{ $count->status === 'posted' ? 'badge-success' : 'badge-warning' }}">{{ $count->status === 'posted' ? 'Valide' : 'Brouillon' }}</span></td>
                        <td><a href="{{ route('stock-counts.show', $count) }}" class="button button-secondary">Voir</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">Aucun inventaire enregistre.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top:18px;">{{ $counts->links() }}</div>
    </div>
@endsection
