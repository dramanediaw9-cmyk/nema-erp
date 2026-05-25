@extends('layouts.app')

@section('title', 'Nouveau produit - Nema ERP')
@section('page-title', 'Nouveau produit')

@section('content')
    @include('partials.erp-page-head', [
        'eyebrow' => 'Produits',
        'title' => 'Nouveau produit',
        'description' => 'La fiche produit alimente le stock, les ventes, les achats et le POS depuis un seul endroit.',
        'actions' => [
            ['label' => 'Retour catalogue', 'url' => route('products.index'), 'style' => 'secondary'],
        ],
    ])

    <form method="POST" action="{{ route('products.store') }}" enctype="multipart/form-data">
        @csrf
        @include('products._form')
    </form>
@endsection
