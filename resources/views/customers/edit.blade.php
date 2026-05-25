@extends('layouts.app')

@section('title', 'Modifier un client - Nema ERP')
@section('page-title', 'Modifier le client')

@section('content')
    @include('partials.erp-page-head', [
        'eyebrow' => 'Clients',
        'title' => 'Modifier '.$partner->name,
        'description' => 'Mets a jour les coordonnees, les conditions de paiement et le statut du client.',
        'actions' => [
            ['label' => 'Retour au client', 'url' => route('customers.show', $partner), 'style' => 'secondary'],
        ],
        'chips' => [
            ['type' => 'activity', 'value' => $partner->is_active ? 'active' : 'inactive'],
        ],
    ])

    <form method="POST" action="{{ route('customers.update', $partner) }}">
        @csrf
        @method('PUT')
        @include('customers._form')
    </form>
@endsection
