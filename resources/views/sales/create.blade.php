@extends('layouts.app')

@section('title', 'Nouvelle facture - Nema ERP')
@section('page-title', 'Nouvelle facture de vente')

@section('content')
    @include('partials.erp-page-head', [
        'eyebrow' => 'Facturation',
        'title' => 'Nouvelle facture client',
        'description' => 'Renseigne le client, les lignes et l echeance. Le stock et la comptabilite se declencheront a la validation finale.',
        'actions' => [
            ['label' => 'Retour aux factures', 'url' => route('sales.index'), 'style' => 'secondary'],
        ],
    ])

    <form method="POST" action="{{ route('sales.store') }}">
        @csrf
        @include('sales._form')
    </form>
@endsection
