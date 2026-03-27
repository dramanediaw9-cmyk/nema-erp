@extends('layouts.app')

@section('title', 'Nouvel achat - Nema ERP')
@section('page-title', 'Nouvelle facture fournisseur')

@section('content')
    <form method="POST" action="{{ route('purchases.store') }}">
        @csrf
        @include('purchases._form')
    </form>
@endsection
