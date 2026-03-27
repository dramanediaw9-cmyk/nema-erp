@extends('layouts.app')

@section('title', 'Créer un client - Nema ERP')
@section('page-title', 'Nouveau client')

@section('content')
    <form method="POST" action="{{ route('customers.store') }}">
        @csrf
        @include('customers._form')
    </form>
@endsection
