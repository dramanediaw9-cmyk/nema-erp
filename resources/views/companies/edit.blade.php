@extends('layouts.app')

@section('title', 'Modifier une entreprise - Nema ERP')
@section('page-title', 'Modifier l\'entreprise')

@section('content')
    <form method="POST" action="{{ route('companies.update', $company) }}">
        @csrf
        @method('PUT')
        @include('companies._form')
    </form>
@endsection
