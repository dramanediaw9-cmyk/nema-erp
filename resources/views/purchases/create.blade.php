@extends('layouts.app')

@php
    $purchaseLabel = $businessVocabulary['purchase'] ?? 'Achat';
    $purchasesLabel = $businessVocabulary['purchases'] ?? 'Achats';
    $supplierLabel = $businessVocabulary['supplier'] ?? 'Fournisseur';
@endphp

@section('title', 'Nouveau '.$purchaseLabel.' - Nema ERP')
@section('page-title', 'Nouveau '.$purchaseLabel)

@section('content')
    @include('partials.erp-page-head', [
        'eyebrow' => $purchasesLabel,
        'title' => 'Nouveau '.$purchaseLabel,
        'description' => 'Renseigne le '.$supplierLabel.', les lignes et l echeance de reglement. Le stock suivra le workflow.',
        'actions' => [
            ['label' => 'Retour aux '.$purchasesLabel, 'url' => route('purchases.index'), 'style' => 'secondary'],
        ],
    ])

    <form method="POST" action="{{ route('purchases.store') }}">
        @csrf
        @include('purchases._form')
    </form>
@endsection
