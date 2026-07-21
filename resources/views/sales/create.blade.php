@extends('layouts.app')

@php
    $saleLabel = $businessVocabulary['sale'] ?? 'Vente';
    $salesLabel = $businessVocabulary['sales'] ?? 'Ventes';
    $customerLabel = $businessVocabulary['client'] ?? 'Client';
    $stockLabel = $businessVocabulary['stock'] ?? 'Stock';
@endphp

@section('title', 'Nouvelle '.$saleLabel.' - Nema ERP')
@section('page-title', 'Nouvelle '.$saleLabel)

@section('content')
    @include('partials.erp-page-head', [
        'eyebrow' => 'Facturation',
        'title' => 'Nouvelle '.$saleLabel,
        'description' => 'Renseigne le '.$customerLabel.', les lignes et l echeance. Le '.$stockLabel.' et la comptabilite se declencheront a la validation finale.',
        'actions' => [
            ['label' => 'Retour aux '.$salesLabel, 'url' => route('sales.index'), 'style' => 'secondary'],
        ],
    ])

    <form method="POST" action="{{ route('sales.store') }}">
        @csrf
        @include('sales._form')
    </form>
@endsection
