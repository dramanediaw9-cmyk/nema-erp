@extends('layouts.print')

@section('title', 'Mouvements de stock - Nema ERP')

@section('content')
    <section class="doc-header">
        <div>
            <div class="doc-chip">Mouvements de stock</div>
            <h1>Journal des mouvements</h1>
            @include('partials.print-company-block', ['company' => $company])
        </div>
        <div class="right">
            <div><strong>Agence :</strong> {{ $branch?->name ?: 'Toutes' }}</div>
            <div class="meta">Periode : {{ $filters['date_from'] ?: 'Debut' }} - {{ $filters['date_to'] ?: 'Aujourd hui' }}</div>
            <div class="meta">Type : {{ $filters['movement_type'] ? ($movementTypes[$filters['movement_type']] ?? $filters['movement_type']) : 'Tous' }}</div>
            <div class="meta">Imprime le {{ now()->format('d/m/Y H:i') }}</div>
        </div>
    </section>

    <section class="grid grid-2">
        <div class="panel">
            <h2>Synthese quantites</h2>
            <div>Mouvements : <strong>{{ number_format((float) $movementSummary['count'], 0, ',', ' ') }}</strong></div>
            <div>Entrees : <strong>{{ number_format((float) $movementSummary['quantity_in'], 3, ',', ' ') }}</strong></div>
            <div>Sorties : <strong>{{ number_format((float) $movementSummary['quantity_out'], 3, ',', ' ') }}</strong></div>
            <div>Solde net : <strong>{{ number_format((float) $movementSummary['net_quantity'], 3, ',', ' ') }}</strong></div>
        </div>
        <div class="panel">
            <h2>Valeur estimee</h2>
            <div>Valeur entrees : <strong>{{ number_format((float) $movementSummary['estimated_in_value'], 0, ',', ' ') }} XOF</strong></div>
            <div>Valeur sorties : <strong>{{ number_format((float) $movementSummary['estimated_out_value'], 0, ',', ' ') }} XOF</strong></div>
            <div>Impact net : <strong>{{ number_format((float) ($movementSummary['estimated_in_value'] - $movementSummary['estimated_out_value']), 0, ',', ' ') }} XOF</strong></div>
        </div>
    </section>

    <table>
        <thead>
        <tr>
            <th>Date</th>
            <th>Produit</th>
            <th>Agence</th>
            <th>Entrepot</th>
            <th>Type</th>
            <th>Source</th>
            <th class="right">Entree</th>
            <th class="right">Sortie</th>
            <th class="right">Valeur</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($movements as $movement)
            @php($sourceContext = $movementContexts[$movement->id] ?? null)
            @php($movementValue = ((float) $movement->quantity_in - (float) $movement->quantity_out) * (float) $movement->unit_cost)
            <tr>
                <td>{{ $movement->movement_date?->format('d/m/Y H:i') }}</td>
                <td>
                    <strong>{{ $movement->product?->name }}</strong>
                    <div class="meta">{{ $movement->product?->sku }}</div>
                </td>
                <td>{{ $movement->branch?->name }}</td>
                <td>{{ $movement->warehouse?->name ?? 'N/A' }}</td>
                <td>{{ $movementTypes[$movement->movement_type] ?? str($movement->movement_type)->replace('_', ' ')->title() }}</td>
                <td>{{ $sourceContext ? $sourceContext['label'].' '.$sourceContext['number'] : ($movement->reason ?: 'Operation interne') }}</td>
                <td class="right">{{ number_format((float) $movement->quantity_in, 3, ',', ' ') }}</td>
                <td class="right">{{ number_format((float) $movement->quantity_out, 3, ',', ' ') }}</td>
                <td class="right">{{ number_format((float) $movementValue, 0, ',', ' ') }} XOF</td>
            </tr>
        @empty
            <tr><td colspan="9" class="meta">Aucun mouvement.</td></tr>
        @endforelse
        </tbody>
    </table>

    <div class="footer">Document limite aux 500 mouvements les plus recents correspondant aux filtres.</div>
@endsection
