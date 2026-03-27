@extends('layouts.app')

@section('title', 'Commandes fournisseurs - Nema ERP')
@section('page-title', 'Commandes fournisseurs')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Cycle achat avance</h2>
            <div class="muted">Les commandes fournisseurs preparant les receptions partielles et le suivi des reliquats.</div>
        </div>
        <a href="{{ route('purchase-orders.create') }}" class="button button-primary">Nouvelle commande fournisseur</a>
    </div>

    <section class="card table-wrap">
        <table>
            <thead><tr><th>Numero</th><th>Date</th><th>Fournisseur</th><th>Depot</th><th>Statut</th><th>Total</th><th></th></tr></thead>
            <tbody>
            @forelse ($orders as $order)
                <tr>
                    <td><strong>{{ $order->order_number }}</strong></td>
                    <td>{{ $order->order_date?->format('d/m/Y') }}</td>
                    <td>{{ $order->supplier?->name }}</td>
                    <td>{{ $order->warehouse?->name }}</td>
                    <td><span class="badge {{ in_array($order->status, ['confirmed', 'received'], true) ? 'badge-success' : 'badge-muted' }}">{{ $order->status }}</span></td>
                    <td>{{ number_format((float) $order->total, 0, ',', ' ') }} XOF</td>
                    <td><a href="{{ route('purchase-orders.show', $order) }}" class="button button-secondary">Voir</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">Aucune commande fournisseur.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div style="margin-top:18px;">{{ $orders->links() }}</div>
    </section>
@endsection
