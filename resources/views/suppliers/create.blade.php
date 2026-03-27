@extends('layouts.app')

@section('title', 'Créer un fournisseur - Nema ERP')
@section('page-title', 'Nouveau fournisseur')

@section('content')
    <form method="POST" action="{{ route('suppliers.store') }}">
        @csrf
        @include('suppliers._form')
    </form>
@endsection
