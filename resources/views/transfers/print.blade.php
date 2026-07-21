@extends('layouts.print')

@section('title', 'Transfert '.$transfer->transfer_number.' - Nema ERP')

@section('content')
    <section class="doc-header">
        <div>
            <div class="doc-chip">Transfert de stock</div>
            <h1>{{ $transfer->transfer_number }}</h1>
            @include('partials.print-company-block', ['company' => $transfer->company])
        </div>
        <div class="right">
            <div><strong>Date :</strong> {{ $transfer->transfer_date?->format('d/m/Y') }}</div>
            <div class="meta">Agence : {{ $transfer->branch?->name }}</div>
            <div class="meta">Source : {{ $transfer->sourceWarehouse?->name }}</div>
            <div class="meta">Destination : {{ $transfer->destinationWarehouse?->name }}</div>
            <div class="meta">Statut : {{ ucfirst($transfer->status) }}</div>
        </div>
    </section>

    <table>
        <thead>
        <tr>
            <th>Produit</th>
            <th>Description</th>
            <th class="right">Quantite</th>
            <th class="right">Cout unitaire</th>
            <th class="right">Valeur</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($transfer->items as $item)
            <tr>
                <td>
                    <strong>{{ $item->product?->name }}</strong>
                    <div class="meta">{{ $item->product?->barcode ?: $item->product?->sku }}</div>
                </td>
                <td>{{ $item->description ?: 'Transfert interne' }}</td>
                <td class="right">{{ number_format((float) $item->qty, 3, ',', ' ') }}</td>
                <td class="right">{{ number_format((float) $item->unit_cost, 0, ',', ' ') }} {{ $transfer->company?->currency_code ?: 'XOF' }}</td>
                <td class="right">{{ number_format((float) $item->qty * (float) $item->unit_cost, 0, ',', ' ') }} {{ $transfer->company?->currency_code ?: 'XOF' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    @if ($transfer->notes)
        <div class="footer"><strong>Notes :</strong> {{ $transfer->notes }}</div>
    @endif

    <div class="signatures">
        <div class="signature-box">Depot source</div>
        <div class="signature-box">Depot destination</div>
    </div>
@endsection
