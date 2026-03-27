@extends('layouts.app')

@section('title', 'Avoirs clients - Nema ERP')
@section('page-title', 'Avoirs clients')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Avoirs emis</h2>
            <div class="muted">Suivi des reductions de creance clients et des retours remis en stock.</div>
        </div>
    </div>

    <section class="card table-wrap">
        <table>
            <thead>
            <tr>
                <th>Numero</th>
                <th>Date</th>
                <th>Facture</th>
                <th>Client</th>
                <th>Montant</th>
                <th>Stock</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($creditNotes as $creditNote)
                <tr>
                    <td><strong>{{ $creditNote->credit_note_number }}</strong></td>
                    <td>{{ $creditNote->credit_note_date?->format('d/m/Y') }}</td>
                    <td>{{ $creditNote->invoice?->invoice_number }}</td>
                    <td>{{ $creditNote->customer?->name }}</td>
                    <td>{{ number_format((float) $creditNote->total, 0, ',', ' ') }} XOF</td>
                    <td><span class="badge {{ $creditNote->restock_items ? 'badge-success' : 'badge-muted' }}">{{ $creditNote->restock_items ? 'Reintegre' : 'Sans retour stock' }}</span></td>
                    <td><a href="{{ route('credit-notes.show', $creditNote) }}" class="button button-secondary">Voir</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">Aucun avoir client enregistre pour le moment.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div style="margin-top:18px;">{{ $creditNotes->links() }}</div>
    </section>
@endsection
