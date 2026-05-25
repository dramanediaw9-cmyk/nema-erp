@extends('layouts.app')

@section('title', 'Créer un fournisseur - Nema ERP')
@section('page-title', 'Nouveau fournisseur')

@section('content')
    @include('partials.erp-page-head', [
        'eyebrow' => 'Fournisseurs',
        'title' => 'Nouveau fournisseur',
        'description' => 'Renseigne le tiers une fois pour l utiliser ensuite en achat, depense et reglement fournisseur.',
        'actions' => [
            ['label' => 'Retour aux fournisseurs', 'url' => route('suppliers.index'), 'style' => 'secondary'],
        ],
    ])

    <form method="POST" action="{{ route('suppliers.store') }}">
        @csrf
        @include('suppliers._form')
    </form>
@endsection
