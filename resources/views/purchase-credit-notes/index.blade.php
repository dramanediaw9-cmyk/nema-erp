@extends('layouts.app')

@section('title', 'Avoirs fournisseurs - Nema ERP')
@section('page-title', 'Avoirs fournisseurs')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Avoirs fournisseurs</h2>
            <div class="muted">Suivi des credits obtenus aupres des fournisseurs et des retours de stock associes.</div>
        </div>
    </div>

    <section class="card table-wrap">
        <table>
            <thead>
            <tr>
                <th>Numero</th>
                <th>Date</th>
                <th>Facture</th>
                <th>Fournisseur</th>
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
                    <td>{{ $creditNote->bill?->bill_number }}</td>
                    <td>{{ $creditNote->supplier?->name }}</td>
                    <td>{{ number_format((float) $creditNote->total, 0, ',', ' ') }} XOF</td>
                    <td><span class="badge {{ $creditNote->destock_items ? 'badge-success' : 'badge-muted' }}">{{ $creditNote->destock_items ? 'Retour fournisseur' : 'Sans sortie stock' }}</span></td>
                    <td><a href="{{ route('purchase-credit-notes.show', $creditNote) }}" class="button button-secondary">Voir</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">Aucun avoir fournisseur enregistre pour le moment.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div style="margin-top:18px;">{{ $creditNotes->links() }}</div>
    </section>
@endsection
