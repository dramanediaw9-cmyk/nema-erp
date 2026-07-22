@extends('layouts.print')

@section('title', 'Inventaire '.$stockCount->count_number.' - Nema ERP')

@section('content')
    @php
        $items = $stockCount->items;
        $positiveVariance = $items->sum(fn ($item) => max((float) $item->variance_qty, 0));
        $negativeVariance = abs($items->sum(fn ($item) => min((float) $item->variance_qty, 0)));
        $estimatedPositiveValue = $items->sum(fn ($item) => max((float) $item->variance_qty, 0) * (float) $item->unit_cost);
        $estimatedNegativeValue = abs($items->sum(fn ($item) => min((float) $item->variance_qty, 0) * (float) $item->unit_cost));
    @endphp

    <section class="doc-header">
        <div>
            <div class="doc-chip">Inventaire</div>
            <h1>{{ $stockCount->count_number }}</h1>
            @include('partials.print-company-block', ['company' => $stockCount->company])
        </div>
        <div class="right">
            <div><strong>Date :</strong> {{ $stockCount->count_date?->format('d/m/Y') }}</div>
            <div class="meta">Agence : {{ $stockCount->branch?->name }}</div>
            <div class="meta">Entrepot : {{ $stockCount->warehouse?->name }}</div>
            <div class="meta">Statut : {{ $stockCount->status === 'posted' ? 'Valide' : 'Brouillon' }}</div>
        </div>
    </section>

    <section class="grid grid-2">
        <div class="panel">
            <h2>Synthese comptage</h2>
            <div>Lignes comptees : <strong>{{ number_format($items->count(), 0, ',', ' ') }}</strong></div>
            <div>Ecarts positifs : <strong>{{ number_format((float) $positiveVariance, 3, ',', ' ') }}</strong></div>
            <div>Ecarts negatifs : <strong>{{ number_format((float) $negativeVariance, 3, ',', ' ') }}</strong></div>
        </div>
        <div class="panel">
            <h2>Valeur estimee</h2>
            <div>Valeur ecarts positifs : <strong>{{ number_format((float) $estimatedPositiveValue, 0, ',', ' ') }} XOF</strong></div>
            <div>Valeur ecarts negatifs : <strong>{{ number_format((float) $estimatedNegativeValue, 0, ',', ' ') }} XOF</strong></div>
            <div>Impact net estime : <strong>{{ number_format((float) ($estimatedPositiveValue - $estimatedNegativeValue), 0, ',', ' ') }} XOF</strong></div>
        </div>
    </section>

    <table>
        <thead>
        <tr>
            <th>Produit</th>
            <th>SKU / code-barres</th>
            <th class="right">Theorique</th>
            <th class="right">Compte</th>
            <th class="right">Ecart</th>
            <th class="right">Valeur ecart</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($stockCount->items as $item)
            <tr>
                <td><strong>{{ $item->product?->name }}</strong></td>
                <td>{{ $item->product?->barcode ?: $item->product?->sku }}</td>
                <td class="right">{{ number_format((float) $item->expected_qty, 3, ',', ' ') }}</td>
                <td class="right">{{ number_format((float) $item->counted_qty, 3, ',', ' ') }}</td>
                <td class="right">{{ number_format((float) $item->variance_qty, 3, ',', ' ') }}</td>
                <td class="right">{{ number_format((float) $item->variance_qty * (float) $item->unit_cost, 0, ',', ' ') }} XOF</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    @if ($stockCount->notes)
        <div class="footer"><strong>Notes :</strong> {{ $stockCount->notes }}</div>
    @endif

    <div class="signatures">
        <div class="signature-box">Controle par</div>
        <div class="signature-box">Valide par</div>
    </div>
@endsection
