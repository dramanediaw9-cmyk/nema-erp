@extends('layouts.print')

@php
    $supplierLabel = $businessVocabulary['supplier'] ?? 'Fournisseur';
    $productLabel = $businessVocabulary['product'] ?? 'Produit';
@endphp

@section('title', 'Facture '.$supplierLabel.' '.$bill->bill_number.' - Nema ERP')

@section('content')
    <section class="doc-header">
        <div>
            <div class="doc-chip">Facture {{ strtolower($supplierLabel) }}</div>
            <h1>{{ $bill->bill_number }}</h1>
            <div><strong>{{ $bill->company?->legal_name ?: $bill->company?->name }}</strong></div>
            <div class="meta">{{ $bill->company?->address }}</div>
            <div class="meta">Tel : {{ $bill->company?->phone ?: 'N/A' }} @if($bill->company?->email)· {{ $bill->company?->email }} @endif</div>
            <div class="meta">NIF : {{ $bill->company?->nif ?: 'N/A' }} · RCCM : {{ $bill->company?->rccm ?: 'N/A' }}</div>
        </div>
        <div class="right">
            <div><strong>Date :</strong> {{ $bill->bill_date?->format('d/m/Y') }}</div>
            <div class="meta">Echeance : {{ $bill->due_date?->format('d/m/Y') ?: 'Non definie' }}</div>
            <div class="meta">Agence : {{ $bill->branch?->name }}</div>
            <div class="meta">Workflow : {{ $bill->status === 'validated' ? 'Approuvee' : ($bill->status === 'rejected' ? 'Rejetee' : 'En attente') }}</div>
            <div class="meta">Devise : {{ $bill->company?->currency_code ?: 'XOF' }}</div>
        </div>
    </section>

    <section class="grid grid-2">
        <div class="panel">
            <h2>{{ $supplierLabel }}</h2>
            <div><strong>{{ $bill->supplier?->name }}</strong></div>
            <div class="muted">Code : {{ $bill->supplier?->code ?: 'N/A' }}</div>
            <div class="muted">Adresse : {{ $bill->supplier?->address ?: 'Non renseignee' }}</div>
            <div class="muted">Telephone : {{ $bill->supplier?->phone ?: 'Non renseigne' }}</div>
            <div class="muted">Email : {{ $bill->supplier?->email ?: 'Non renseigne' }}</div>
        </div>
        <div class="panel">
            <h2>Situation</h2>
            <div><strong>Workflow :</strong> {{ $bill->status === 'validated' ? 'Approuvee' : ($bill->status === 'rejected' ? 'Rejetee' : 'En attente d approbation') }}</div>
            <div><strong>Rejetee par :</strong> {{ $bill->rejector?->name ?? 'Non rejetee' }}</div>
            <div><strong>Statut paiement :</strong> {{ $bill->payment_status === 'paid' ? 'Payee' : ($bill->payment_status === 'partial' ? 'Partiellement reglee' : 'Impayee') }}</div>
            <div><strong>Montant regle :</strong> {{ number_format((float) $bill->amount_paid, 0, ',', ' ') }} XOF</div>
            <div><strong>Solde restant :</strong> {{ number_format((float) $bill->balance_due, 0, ',', ' ') }} XOF</div>
        </div>
    </section>

    <table>
        <thead>
            <tr>
                <th>{{ $productLabel }}</th>
                <th>Description</th>
                <th class="right">Quantite</th>
                <th class="right">Cout unitaire</th>
                <th class="right">Montant</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($bill->items as $item)
                <tr>
                    <td>@include('partials.product-inline', ['product' => $item->product, 'meta' => $item->product?->barcode ?: $item->product?->sku, 'size' => 34, 'link' => null])</td>
                    <td>{{ $item->description }}</td>
                    <td class="right">{{ number_format((float) $item->qty, 3, ',', ' ') }}</td>
                    <td class="right">{{ number_format((float) $item->unit_cost, 0, ',', ' ') }} XOF</td>
                    <td class="right">{{ number_format((float) $item->line_total, 0, ',', ' ') }} XOF</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>Total facture</td>
            <td class="right">{{ number_format((float) $bill->total, 0, ',', ' ') }} XOF</td>
        </tr>
        <tr>
            <td>Montant regle</td>
            <td class="right">{{ number_format((float) $bill->amount_paid, 0, ',', ' ') }} XOF</td>
        </tr>
        <tr class="grand-total">
            <td>Reste a regler</td>
            <td class="right">{{ number_format((float) $bill->balance_due, 0, ',', ' ') }} XOF</td>
        </tr>
    </table>

    @if ($bill->notes)
        <div class="footer">
            <strong>Notes :</strong> {{ $bill->notes }}
        </div>
    @endif

    <div class="signatures">
        <div class="signature-box">Signature / cachet entreprise</div>
        <div class="signature-box">Visa comptable</div>
    </div>
@endsection
