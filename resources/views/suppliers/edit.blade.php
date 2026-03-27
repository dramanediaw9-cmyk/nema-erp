@extends('layouts.app')

@section('title', 'Modifier un fournisseur - Nema ERP')
@section('page-title', 'Modifier le fournisseur')

@section('content')
    <form method="POST" action="{{ route('suppliers.update', $partner) }}">
        @csrf
        @method('PUT')
        @include('suppliers._form')
    </form>
@endsection
