@extends('layouts.app')

@section('title', 'Immobilisations - Nema ERP')
@section('page-title', 'Immobilisations')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Registre des immobilisations</h2>
            <div class="muted">Suivre les actifs, leur valeur nette et leur plan d amortissement.</div>
        </div>
        @allowed('fixed_assets.manage')
            <a href="{{ route('fixed-assets.create') }}" class="button button-primary">Nouvelle immobilisation</a>
        @endallowed
    </div>

    <div class="grid stats-grid" style="margin-bottom:20px;">
        <div class="card"><div class="muted">Actifs</div><div class="stat-value">{{ $summary['asset_count'] }}</div></div>
        <div class="card"><div class="muted">En service</div><div class="stat-value">{{ $summary['active_count'] }}</div></div>
        <div class="card"><div class="muted">Valeur d acquisition</div><div class="stat-value">{{ number_format($summary['acquisition_total'], 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Valeur nette</div><div class="stat-value">{{ number_format($summary['net_book_total'], 0, ',', ' ') }}</div></div>
    </div>

    <div class="card table-wrap">
        <table>
            <thead>
            <tr>
                <th>Numero</th>
                <th>Immobilisation</th>
                <th>Agence</th>
                <th>Mise en service</th>
                <th>Cout</th>
                <th>Statut</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($assets as $asset)
                <tr>
                    <td>{{ $asset->asset_number }}</td>
                    <td>
                        <div style="font-weight:600;">{{ $asset->name }}</div>
                        <div class="muted" style="margin-top:6px;">{{ $asset->category ?: 'Categorie non renseignee' }}</div>
                    </td>
                    <td>{{ $asset->branch?->name ?? 'Toutes les agences' }}</td>
                    <td>{{ $asset->commissioning_date?->format('d/m/Y') ?: $asset->depreciation_start_date?->format('d/m/Y') }}</td>
                    <td>{{ number_format((float) $asset->acquisition_cost, 0, ',', ' ') }} XOF</td>
                    <td><span class="badge {{ $asset->status === 'active' ? 'badge-success' : ($asset->status === 'disposed' ? 'badge-warning' : 'badge-muted') }}">{{ $statusOptions[$asset->status] ?? ucfirst($asset->status) }}</span></td>
                    <td><a href="{{ route('fixed-assets.show', $asset) }}" class="button button-secondary">Ouvrir</a></td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="muted">Aucune immobilisation enregistree pour le moment.</td>
                </tr>
            @endforelse
            </tbody>
        </table>

        <div style="margin-top:18px;">{{ $assets->links() }}</div>
    </div>
@endsection
