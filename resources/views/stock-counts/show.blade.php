@extends('layouts.app')

@section('title', 'Inventaire de stock')
@section('page-title', 'Inventaire de stock')

@section('content')
    <div class="page-head">
        <div>
            <h2 class="section-title">{{ $stockCount->count_number }}</h2>
            <div class="muted">Entrepot {{ $stockCount->warehouse?->name }} · {{ $stockCount->count_date?->format('d/m/Y') }}</div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            @if ($stockCount->status === 'draft')
                @allowed('stock_counts.manage')
                    <form method="POST" action="{{ route('stock-counts.post', $stockCount) }}" class="inline-form">@csrf<button class="button button-primary" type="submit">Valider l inventaire</button></form>
                @endallowed
            @endif
            <a href="{{ route('stock-counts.print', $stockCount) }}" class="button button-primary" target="_blank">Imprimer</a>
            <a href="{{ route('stock-counts.index') }}" class="button button-secondary">Retour</a>
        </div>
    </div>

    <div class="split">
        <div class="card summary-stack">
            <div class="summary-box"><strong>Statut</strong><div class="value">{{ $stockCount->status === 'posted' ? 'Valide' : 'Brouillon' }}</div></div>
            <div class="summary-box"><strong>Date</strong><div class="value" style="font-size:22px;">{{ $stockCount->count_date?->format('d/m/Y') }}</div></div>
            @if ($stockCount->notes)
                <div class="summary-box"><strong>Notes</strong><div style="margin-top:8px;">{{ $stockCount->notes }}</div></div>
            @endif
        </div>

        <div class="card">
            <h3 class="section-title">Ecarts releves</h3>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Produit</th>
                        <th>Theorique</th>
                        <th>Compte</th>
                        <th>Ecart</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($stockCount->items as $item)
                        <tr>
                            <td>@include('partials.product-inline', ['product' => $item->product, 'meta' => $item->product?->barcode ?: $item->product?->sku, 'size' => 42])</td>
                            <td>{{ number_format((float) $item->expected_qty, 3, ',', ' ') }}</td>
                            <td>{{ number_format((float) $item->counted_qty, 3, ',', ' ') }}</td>
                            <td>
                                <span class="badge {{ (float) $item->variance_qty === 0.0 ? 'badge-muted' : ((float) $item->variance_qty > 0 ? 'badge-success' : 'badge-warning') }}">
                                    {{ number_format((float) $item->variance_qty, 3, ',', ' ') }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
