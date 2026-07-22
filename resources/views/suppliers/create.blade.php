@extends('layouts.app')

@php
    $supplierLabel = $businessVocabulary['supplier'] ?? 'Fournisseur';
    $suppliersLabel = $businessVocabulary['suppliers'] ?? 'Fournisseurs';
    $purchaseLabel = $businessVocabulary['purchase'] ?? 'Achat';
@endphp

@section('title', 'Créer un '.$supplierLabel.' - Nema ERP')
@section('page-title', 'Nouveau '.$supplierLabel)

@section('content')
    @include('partials.erp-page-head', [
        'eyebrow' => $suppliersLabel,
        'title' => 'Nouveau '.$supplierLabel,
        'description' => 'Renseigne ce partenaire une fois pour l utiliser ensuite en '.$purchaseLabel.', depense et reglement.',
        'actions' => [
            ['label' => 'Retour', 'url' => route('suppliers.index'), 'style' => 'secondary'],
        ],
    ])

    <form method="POST" action="{{ route('suppliers.store') }}">
        @csrf
        @include('suppliers._form')
    </form>
@endsection
