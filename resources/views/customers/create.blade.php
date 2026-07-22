@extends('layouts.app')

@php
    $customerLabel = $businessVocabulary['client'] ?? 'Client';
    $customersLabel = $businessVocabulary['clients'] ?? 'Clients';
@endphp

@section('title', 'Créer un '.$customerLabel.' - Nema ERP')
@section('page-title', 'Nouveau '.$customerLabel)

@section('content')
    @include('partials.erp-page-head', [
        'eyebrow' => $customersLabel,
        'title' => 'Nouveau '.$customerLabel,
        'description' => 'Renseigne ce dossier une fois pour l utiliser ensuite en facturation, paiement et suivi.',
        'actions' => [
            ['label' => 'Retour', 'url' => route('customers.index'), 'style' => 'secondary'],
        ],
    ])

    <form method="POST" action="{{ route('customers.store') }}">
        @csrf
        @include('customers._form')
    </form>
@endsection
