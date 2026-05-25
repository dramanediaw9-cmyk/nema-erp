@extends('layouts.app')

@section('title', 'Nouvel achat - Nema ERP')
@section('page-title', 'Nouvelle facture fournisseur')

@section('content')
    @include('partials.erp-page-head', [
        'eyebrow' => 'Achats',
        'title' => 'Nouvelle facture fournisseur',
        'description' => 'Renseigne le fournisseur, les lignes et l echeance de reglement. Le stock suivra le workflow d achat.',
        'actions' => [
            ['label' => 'Retour aux achats', 'url' => route('purchases.index'), 'style' => 'secondary'],
        ],
    ])

    <form method="POST" action="{{ route('purchases.store') }}">
        @csrf
        @include('purchases._form')
    </form>
@endsection
