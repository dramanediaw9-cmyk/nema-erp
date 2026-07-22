@extends('layouts.app')

@php
    $customerLabel = $businessVocabulary['client'] ?? 'Client';
    $customersLabel = $businessVocabulary['clients'] ?? 'Clients';
@endphp

@section('title', 'Modifier un '.$customerLabel.' - Nema ERP')
@section('page-title', 'Modifier le '.$customerLabel)

@section('content')
    @include('partials.erp-page-head', [
        'eyebrow' => $customersLabel,
        'title' => 'Modifier '.$partner->name,
        'description' => 'Mets a jour les coordonnees, les conditions de paiement et le statut du dossier.',
        'actions' => [
            ['label' => 'Retour au dossier', 'url' => route('customers.show', $partner), 'style' => 'secondary'],
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
