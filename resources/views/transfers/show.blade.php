@extends('layouts.app')

@section('title', 'Transfert '.$transfer->transfer_number)
@section('page-title', 'Detail transfert de stock')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">{{ $transfer->transfer_number }}</h2>
            <div class="muted">{{ $transfer->sourceWarehouse?->name }} vers {{ $transfer->destinationWarehouse?->name }}</div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="{{ route('transfers.print', $transfer) }}" class="button button-primary" target="_blank">Imprimer</a>
            <a href="{{ route('transfers.index') }}" class="button button-secondary">Retour liste</a>
        </div>
    </div>

    <div class="split">
        <section class="card">
            <div class="grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                <div><div class="muted">Date</div><strong>{{ $transfer->transfer_date?->format('d/m/Y') }}</strong></div>
                <div><div class="muted">Statut</div><span class="badge badge-success">{{ ucfirst($transfer->status) }}</span></div>
                <div><div class="muted">Depot source</div><strong>{{ $transfer->sourceWarehouse?->name }}</strong></div>
                <div><div class="muted">Depot destination</div><strong>{{ $transfer->destinationWarehouse?->name }}</strong></div>
            </div>
            @if ($transfer->notes)
                <div class="card" style="margin-top:18px; padding:16px;">
                    <div class="muted">Notes</div>
                    <div>{{ $transfer->notes }}</div>
                </div>
            @endif
        </section>
        <aside class="card">
            <h2 class="section-title">Lignes</h2>
            @foreach ($transfer->items as $item)
                <div style="padding-bottom:12px; border-bottom:1px solid #efe4d3; margin-bottom:12px;">
                    @include('partials.product-inline', ['product' => $item->product, 'meta' => number_format((float) $item->qty, 3, ',', ' ').' · '.number_format((float) $item->unit_cost, 0, ',', ' ').' XOF', 'size' => 42])
                </div>
            @endforeach
        </aside>
    </div>
@endsection
