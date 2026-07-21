@extends('layouts.app')

@php
    $productLabel = $businessVocabulary['product'] ?? 'Produit';
    $productsLabel = $businessVocabulary['products'] ?? 'Produits';
@endphp

@section('title', 'Modifier un '.$productLabel.' - Nema ERP')
@section('page-title', 'Modifier le '.$productLabel)

@section('content')
    @include('partials.erp-page-head', [
        'eyebrow' => $productsLabel,
        'title' => 'Modifier '.$product->display_name,
        'description' => 'Ajuste prix, stock minimum, blocages et regles metier sans quitter la fiche.',
        'actions' => [
            ['label' => 'Retour a la fiche', 'url' => route('products.show', $product), 'style' => 'secondary'],
        ],
        'chips' => [
            ['label' => $product->is_active ? 'Actif' : 'Archive', 'tone' => $product->is_active ? 'success' : 'muted'],
        ],
    ])

    <form method="POST" action="{{ route('products.update', $product) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('products._form')
    </form>
@endsection
