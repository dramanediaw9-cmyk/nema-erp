@extends('layouts.app')

@section('title', 'Modifier un produit - Nema ERP')
@section('page-title', 'Modifier le produit')

@section('content')
    @include('partials.erp-page-head', [
        'eyebrow' => 'Produits',
        'title' => 'Modifier '.$product->display_name,
        'description' => 'Ajuste prix, stock mini, blocages et configuration commerciale sans quitter la fiche produit.',
        'actions' => [
            ['label' => 'Retour au produit', 'url' => route('products.show', $product), 'style' => 'secondary'],
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
