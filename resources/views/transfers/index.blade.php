@extends('layouts.app')

@section('title', 'Transferts de stock - Nema ERP')
@section('page-title', 'Transferts de stock')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Transferts inter-depots</h2>
            <div class="muted">Suivi des mouvements internes entre entrepots de la meme agence.</div>
        </div>
        <a href="{{ route('transfers.create') }}" class="button button-primary">Nouveau transfert</a>
    </div>

    <section class="card table-wrap">
        <table>
            <thead>
            <tr>
                <th>Numero</th>
                <th>Date</th>
                <th>Source</th>
                <th>Destination</th>
                <th>Etat</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($transfers as $transfer)
                <tr>
                    <td><strong>{{ $transfer->transfer_number }}</strong></td>
                    <td>{{ $transfer->transfer_date?->format('d/m/Y') }}</td>
                    <td>{{ $transfer->sourceWarehouse?->name }}</td>
                    <td>{{ $transfer->destinationWarehouse?->name }}</td>
                    <td><span class="badge badge-success">{{ ucfirst($transfer->status) }}</span></td>
                    <td><a href="{{ route('transfers.show', $transfer) }}" class="button button-secondary">Voir</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">Aucun transfert enregistre.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div style="margin-top:18px;">{{ $transfers->links() }}</div>
    </section>
@endsection
