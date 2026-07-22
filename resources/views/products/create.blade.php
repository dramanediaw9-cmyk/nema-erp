@extends('layouts.app')

@php
    $productLabel = $businessVocabulary['product'] ?? 'Produit';
    $productsLabel = $businessVocabulary['products'] ?? 'Produits';
@endphp

@section('title', 'Nouveau '.$productLabel.' - Nema ERP')
@section('page-title', 'Nouveau '.$productLabel)

@section('content')
    @include('partials.erp-page-head', [
        'eyebrow' => $productsLabel,
        'title' => 'Nouveau '.$productLabel,
        'description' => 'La fiche alimente le stock, les ventes, les achats et la caisse depuis un seul endroit.',
        'actions' => [
            ['label' => 'Retour catalogue', 'url' => route('products.index'), 'style' => 'secondary'],
        ],
    ])

    <form method="POST" action="{{ route('products.store') }}" enctype="multipart/form-data">
        @csrf
        @include('products._form')
    </form>
@endsection
