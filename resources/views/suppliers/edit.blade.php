@extends('layouts.app')

@section('title', 'Modifier un fournisseur - Nema ERP')
@section('page-title', 'Modifier le fournisseur')

@section('content')
    @include('partials.erp-page-head', [
        'eyebrow' => 'Fournisseurs',
        'title' => 'Modifier '.$partner->name,
        'description' => 'Mets a jour les coordonnees, les conditions de paiement et le statut du fournisseur.',
        'actions' => [
            ['label' => 'Retour au fournisseur', 'url' => route('suppliers.show', $partner), 'style' => 'secondary'],
        ],
        'chips' => [
            ['type' => 'activity', 'value' => $partner->is_active ? 'active' : 'inactive'],
        ],
    ])

    <form method="POST" action="{{ route('suppliers.update', $partner) }}">
        @csrf
        @method('PUT')
        @include('suppliers._form')
    </form>
@endsection
