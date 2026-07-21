@extends('layouts.app')

@php
    $supplierLabel = $businessVocabulary['supplier'] ?? 'Fournisseur';
    $suppliersLabel = $businessVocabulary['suppliers'] ?? 'Fournisseurs';
@endphp

@section('title', 'Modifier un '.$supplierLabel.' - Nema ERP')
@section('page-title', 'Modifier le '.$supplierLabel)

@section('content')
    @include('partials.erp-page-head', [
        'eyebrow' => $suppliersLabel,
        'title' => 'Modifier '.$partner->name,
        'description' => 'Mets a jour les coordonnees, les conditions de paiement et le statut du dossier.',
        'actions' => [
            ['label' => 'Retour au dossier', 'url' => route('suppliers.show', $partner), 'style' => 'secondary'],
        ],
        'chips' => [
            ['type' => 'activity', 'value' => $partner->is_active ? 'active' : 'inactive'],
        ],
    ])

    <form method="POST" action="{{ route('suppliers.update', $partner) }}">
        @csrf
        @method('PUT')
        @include('suppliers._form')
    </form>
@endsection
