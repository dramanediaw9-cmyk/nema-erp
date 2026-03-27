@extends('layouts.app')

@section('title', 'Créer une entreprise - Nema ERP')
@section('page-title', 'Nouvelle entreprise')

@section('content')
    <form method="POST" action="{{ route('companies.store') }}">
        @csrf
        @include('companies._form')
    </form>
@endsection
