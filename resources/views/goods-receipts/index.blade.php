@extends('layouts.app')

@section('title', 'Receptions fournisseurs - Nema ERP')
@section('page-title', 'Receptions fournisseurs')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Receptions physiques</h2>
            <div class="muted">Traque les entrees en stock issues des commandes fournisseurs.</div>
        </div>
        <a href="{{ route('goods-receipts.create') }}" class="button button-primary">Nouvelle reception</a>
    </div>

    <section class="card table-wrap">
        <table>
            <thead><tr><th>Numero</th><th>Date</th><th>Commande</th><th>Fournisseur</th><th>Depot</th><th></th></tr></thead>
            <tbody>
            @forelse ($receipts as $receipt)
                <tr>
                    <td><strong>{{ $receipt->receipt_number }}</strong></td>
                    <td>{{ $receipt->receipt_date?->format('d/m/Y') }}</td>
                    <td>{{ $receipt->purchaseOrder?->order_number }}</td>
                    <td>{{ $receipt->supplier?->name }}</td>
                    <td>{{ $receipt->warehouse?->name }}</td>
                    <td><a href="{{ route('goods-receipts.show', $receipt) }}" class="button button-secondary">Voir</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">Aucune reception fournisseur.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div style="margin-top:18px;">{{ $receipts->links() }}</div>
    </section>
@endsection
