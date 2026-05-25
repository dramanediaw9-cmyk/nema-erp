@extends('layouts.app')

@section('title', 'Créer un client - Nema ERP')
@section('page-title', 'Nouveau client')

@section('content')
    @include('partials.erp-page-head', [
        'eyebrow' => 'Clients',
        'title' => 'Nouveau client',
        'description' => 'Renseigne le tiers une fois pour l utiliser ensuite en vente, facturation et recouvrement.',
        'actions' => [
            ['label' => 'Retour aux clients', 'url' => route('customers.index'), 'style' => 'secondary'],
        ],
    ])

    <form method="POST" action="{{ route('customers.store') }}">
        @csrf
        @include('customers._form')
    </form>
@endsection
