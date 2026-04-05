@extends('layouts.app')

@section('title', 'Demandes d achat')
@section('page-title', 'Demandes d achat')

@section('content')
    <div class="page-head">
        <div>
            <h2 class="section-title">Demandes d achat</h2>
            <div class="muted">Flux amont avant commande fournisseur : besoin interne, validation, puis conversion en commande.</div>
        </div>
        @allowed('purchase_requests.manage')
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <a href="{{ route('replenishments.index') }}" class="button button-secondary">Reappro auto</a>
                <a href="{{ route('purchase-requests.create') }}" class="button button-primary">Nouvelle demande</a>
            </div>
        @endallowed
    </div>

    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Numero</th>
                    <th>Date</th>
                    <th>Source</th>
                    <th>Entrepot</th>
                    <th>Priorite</th>
                    <th>Statut</th>
                    <th>Total</th>
                    <th>Commandes</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($requests as $requestRow)
                    @php($generatedOrders = $requestRow->generatedPurchaseOrders->isNotEmpty() ? $requestRow->generatedPurchaseOrders : ($requestRow->convertedPurchaseOrder ? collect([$requestRow->convertedPurchaseOrder]) : collect()))
                    <tr>
                        <td>{{ $requestRow->request_number }}</td>
                        <td>{{ $requestRow->request_date?->format('d/m/Y') }}</td>
                        <td>
                            @if ($requestRow->originSalesOrder)
                                <a href="{{ route('orders.show', $requestRow->originSalesOrder) }}">{{ $requestRow->originSalesOrder->order_number }}</a>
                            @else
                                <span class="muted">Manuelle</span>
                            @endif
                        </td>
                        <td>{{ $requestRow->warehouse?->name }}</td>
                        <td>{{ ucfirst($requestRow->priority) }}</td>
                        <td>
                            <span class="badge {{ in_array($requestRow->status, ['approved', 'converted'], true) ? 'badge-success' : ($requestRow->status === 'rejected' ? 'badge-muted' : 'badge-warning') }}">
                                {{ str_replace('_', ' ', $requestRow->status) }}
                            </span>
                        </td>
                        <td>{{ number_format((float) $requestRow->total, 0, ',', ' ') }} XOF</td>
                        <td>
                            @if ($generatedOrders->isNotEmpty())
                                <span class="badge badge-success">{{ $generatedOrders->count() }} commande(s)</span>
                            @else
                                <span class="muted">Aucune</span>
                            @endif
                        </td>
                        <td><a href="{{ route('purchase-requests.show', $requestRow) }}" class="button button-secondary">Voir</a></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="muted">Aucune demande d achat enregistree.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top:18px;">{{ $requests->links() }}</div>
    </div>
@endsection
