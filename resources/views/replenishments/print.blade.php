@extends('layouts.print')

@php
    $supplierLabel = $businessVocabulary['supplier'] ?? 'Fournisseur';
    $productLabel = $businessVocabulary['product'] ?? 'Produit';
    $purchaseLabel = $businessVocabulary['purchase'] ?? 'Achat';
    $replenishmentLabel = $businessVocabulary['replenishment'] ?? 'Reapprovisionnement';
@endphp

@section('title', 'Plan '.$replenishmentLabel.' - Nema ERP')

@section('content')
    <section class="doc-header">
        <div>
            <div class="doc-chip">Plan {{ strtolower($replenishmentLabel) }}</div>
            <h1>{{ $selectedWarehouse?->name ?: 'Depot non defini' }}</h1>
            <div><strong>{{ $company?->legal_name ?: $company?->name }}</strong></div>
            <div class="meta">{{ $company?->address ?: 'Adresse non renseignee' }}</div>
            <div class="meta">Tel : {{ $company?->phone ?: 'N/A' }} @if($company?->email)· {{ $company?->email }} @endif</div>
            <div class="meta">NIF : {{ $company?->nif ?: 'N/A' }} · RCCM : {{ $company?->rccm ?: 'N/A' }}</div>
        </div>
        <div class="right">
            <div><strong>Agence :</strong> {{ $branch?->name ?: 'N/A' }}</div>
            <div class="meta">Depot : {{ $selectedWarehouse?->name ?: 'N/A' }}</div>
            <div class="meta">Suggestions : {{ number_format((int) ($stats['count'] ?? 0), 0, ',', ' ') }}</div>
            <div class="meta">Urgences : {{ number_format((int) ($stats['urgent_count'] ?? 0), 0, ',', ' ') }}</div>
            <div class="meta">Imprime le : {{ now()->format('d/m/Y H:i') }}</div>
        </div>
    </section>

    <section class="grid grid-2">
        <div class="panel">
            <h2>Resume</h2>
            <div><strong>Quantite proposee :</strong> {{ number_format((float) ($stats['quantity'] ?? 0), 3, ',', ' ') }}</div>
            <div><strong>Valeur estimee :</strong> {{ number_format((float) ($stats['estimated_total'] ?? 0), 0, ',', ' ') }} XOF</div>
            <div class="muted">Le calcul tient compte du stock reel, des commandes {{ strtolower($supplierLabel) }} en cours et des demandes {{ strtolower($purchaseLabel) }} deja ouvertes.</div>
        </div>
        <div class="panel">
            <h2>Utilisation</h2>
            <div>Ce document sert de base de controle avant creation de la demande {{ strtolower($purchaseLabel) }} ou de la commande {{ strtolower($supplierLabel) }}.</div>
            <div class="muted">Les quantites restent ajustables selon le budget, le {{ strtolower($supplierLabel) }} et la place disponible en depot.</div>
        </div>
    </section>

    @if ($suggestions->isEmpty())
        <div class="panel" style="margin-top:24px;">
            Aucune suggestion {{ strtolower($replenishmentLabel) }} pour ce depot.
        </div>
    @else
        <table>
            <thead>
                <tr>
                    <th>{{ $productLabel }}</th>
                    <th>{{ $supplierLabel }}</th>
                    <th>Priorite</th>
                    <th class="right">Stock reel</th>
                    <th class="right">En cours</th>
                    <th class="right">Projete</th>
                    <th class="right">Mini</th>
                    <th class="right">Cible</th>
                    <th class="right">A commander</th>
                    <th class="right">Valeur</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($suggestions as $suggestion)
                    @php($product = $suggestion['product'])
                    <tr>
                        <td>
                            <strong>{{ $product->display_name }}</strong>
                            <div class="muted">{{ $product->sku }}{{ $product->barcode ? ' · '.$product->barcode : '' }}</div>
                            @if ($suggestion['purchase_lead_time_days'])
                                <div class="muted">Delai : {{ $suggestion['purchase_lead_time_days'] }} j</div>
                            @endif
                        </td>
                        <td>
                            {{ $suggestion['supplier_name'] ?: 'A definir' }}
                            @if ($suggestion['supplier_product_code'])
                                <div class="muted">{{ $suggestion['supplier_product_code'] }}</div>
                            @endif
                        </td>
                        <td>{{ $suggestion['priority'] === 'urgent' ? 'Urgent' : ($suggestion['priority'] === 'high' ? 'Haute' : 'Normale') }}</td>
                        <td class="right">{{ number_format((float) $suggestion['current_stock'], 3, ',', ' ') }}</td>
                        <td class="right">{{ number_format((float) ($suggestion['incoming_qty'] + $suggestion['open_request_qty']), 3, ',', ' ') }}</td>
                        <td class="right">{{ number_format((float) $suggestion['projected_stock'], 3, ',', ' ') }}</td>
                        <td class="right">{{ number_format((float) $suggestion['min_stock'], 3, ',', ' ') }}</td>
                        <td class="right">{{ number_format((float) $suggestion['target_stock'], 3, ',', ' ') }}</td>
                        <td class="right"><strong>{{ number_format((float) $suggestion['suggested_qty'], 3, ',', ' ') }}</strong></td>
                        <td class="right">{{ number_format((float) $suggestion['estimated_total'], 0, ',', ' ') }} XOF</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="totals">
            <tr>
                <td>References</td>
                <td class="right">{{ number_format((int) ($stats['count'] ?? 0), 0, ',', ' ') }}</td>
            </tr>
            <tr class="grand-total">
                <td>Valeur estimee</td>
                <td class="right">{{ number_format((float) ($stats['estimated_total'] ?? 0), 0, ',', ' ') }} XOF</td>
            </tr>
        </table>
    @endif

    <div class="signatures">
        <div class="signature-box">Responsable stock</div>
        <div class="signature-box">Validation {{ strtolower($purchaseLabel) }} / direction</div>
    </div>
@endsection
