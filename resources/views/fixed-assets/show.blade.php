@extends('layouts.app')

@section('title', 'Immobilisation '.$asset->asset_number.' - Nema ERP')
@section('page-title', 'Immobilisation '.$asset->asset_number)

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">{{ $asset->name }}</h2>
            <div class="muted">{{ $asset->category ?: 'Categorie non renseignee' }} · {{ $asset->branch?->name ?? 'Toutes les agences' }}</div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="{{ route('fixed-assets.index') }}" class="button button-secondary">Retour immobilisations</a>
            @allowed('accounting.view')
                <a href="{{ route('accounting.balance.index') }}" class="button button-secondary">Voir la balance</a>
            @endallowed
        </div>
    </div>

    <div class="grid stats-grid" style="margin-bottom:20px;">
        <div class="card"><div class="muted">Cout d acquisition</div><div class="stat-value">{{ number_format((float) $asset->acquisition_cost, 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Base amortissable</div><div class="stat-value">{{ number_format($metrics['depreciable_base'], 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Amortissement cumule</div><div class="stat-value">{{ number_format($metrics['accumulated_depreciation'], 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Valeur nette</div><div class="stat-value">{{ number_format($metrics['net_book_value'], 0, ',', ' ') }}</div></div>
    </div>

    <div class="split" style="margin-bottom:20px;">
        <section class="card">
            <h2 style="margin-top:0;">Lecture actif</h2>
            <div class="grid">
                <div><strong>Numero</strong><div class="muted">{{ $asset->asset_number }}</div></div>
                <div><strong>Statut</strong><div class="muted">{{ $statusOptions[$asset->status] ?? ucfirst($asset->status) }}</div></div>
                <div><strong>Acquisition</strong><div class="muted">{{ $asset->acquisition_date?->format('d/m/Y') }}</div></div>
                <div><strong>Mise en service</strong><div class="muted">{{ $asset->commissioning_date?->format('d/m/Y') ?: 'Non renseignee' }}</div></div>
                <div><strong>Debut amortissement</strong><div class="muted">{{ $asset->depreciation_start_date?->format('d/m/Y') }}</div></div>
                <div><strong>Methode</strong><div class="muted">{{ $methodOptions[$asset->depreciation_method] ?? ucfirst($asset->depreciation_method) }}</div></div>
                <div><strong>Duree utile</strong><div class="muted">{{ $asset->useful_life_months }} mois</div></div>
                <div><strong>Valeur residuelle</strong><div class="muted">{{ number_format((float) $asset->salvage_value, 0, ',', ' ') }} XOF</div></div>
            </div>
            @if ($asset->notes)
                <div class="muted" style="margin-top:14px;">{{ $asset->notes }}</div>
            @endif
        </section>

        <section class="card">
            <h2 style="margin-top:0;">Synthese amortissement</h2>
            <div class="grid">
                <div><strong>Base amortissable</strong><div class="muted">{{ number_format($metrics['depreciable_base'], 0, ',', ' ') }} XOF</div></div>
                <div><strong>Dotation mensuelle</strong><div class="muted">{{ number_format($metrics['monthly_depreciation'], 0, ',', ' ') }} XOF</div></div>
                <div><strong>Mois ecoules</strong><div class="muted">{{ $metrics['elapsed_months'] }}</div></div>
                <div><strong>Valeur nette</strong><div class="muted">{{ number_format($metrics['net_book_value'], 0, ',', ' ') }} XOF</div></div>
            </div>
        </section>
    </div>

    <section class="card table-wrap">
        <h2 style="margin-top:0;">Plan d amortissement lineaire</h2>
        <table>
            <thead>
            <tr>
                <th>Periode</th>
                <th>Valeur debut</th>
                <th>Dotation</th>
                <th>Valeur fin</th>
                <th>Etat</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($schedule as $row)
                <tr>
                    <td>{{ $row['period']->translatedFormat('F Y') }}</td>
                    <td>{{ number_format($row['opening_value'], 0, ',', ' ') }} XOF</td>
                    <td>{{ number_format($row['depreciation'], 0, ',', ' ') }} XOF</td>
                    <td>{{ number_format($row['closing_value'], 0, ',', ' ') }} XOF</td>
                    <td><span class="badge {{ $row['is_posted_month'] ? 'badge-success' : 'badge-muted' }}">{{ $row['is_posted_month'] ? 'Echu' : 'Previsionnel' }}</span></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>
@endsection
