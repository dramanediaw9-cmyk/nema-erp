@extends('layouts.app')

@section('title', 'Nouvelle facture - Nema ERP')
@section('page-title', 'Nouvelle facture de vente')

@section('content')
    <form method="POST" action="{{ route('sales.store') }}">
        @csrf
        @include('sales._form')
    </form>
@endsection
