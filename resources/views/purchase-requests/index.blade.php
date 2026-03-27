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
            <a href="{{ route('purchase-requests.create') }}" class="button button-primary">Nouvelle demande</a>
        @endallowed
    </div>

    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Numero</th>
                    <th>Date</th>
                    <th>Entrepot</th>
                    <th>Priorite</th>
                    <th>Statut</th>
                    <th>Total</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($requests as $requestRow)
                    <tr>
                        <td>{{ $requestRow->request_number }}</td>
                        <td>{{ $requestRow->request_date?->format('d/m/Y') }}</td>
                        <td>{{ $requestRow->warehouse?->name }}</td>
                        <td>{{ ucfirst($requestRow->priority) }}</td>
                        <td>
                            <span class="badge {{ in_array($requestRow->status, ['approved', 'converted'], true) ? 'badge-success' : ($requestRow->status === 'rejected' ? 'badge-muted' : 'badge-warning') }}">
                                {{ str_replace('_', ' ', $requestRow->status) }}
                            </span>
                        </td>
                        <td>{{ number_format((float) $requestRow->total, 0, ',', ' ') }} XOF</td>
                        <td><a href="{{ route('purchase-requests.show', $requestRow) }}" class="button button-secondary">Voir</a></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="muted">Aucune demande d achat enregistree.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top:18px;">{{ $requests->links() }}</div>
    </div>
@endsection
