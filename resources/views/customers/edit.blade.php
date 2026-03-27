@extends('layouts.app')

@section('title', 'Modifier un client - Nema ERP')
@section('page-title', 'Modifier le client')

@section('content')
    <form method="POST" action="{{ route('customers.update', $partner) }}">
        @csrf
        @method('PUT')
        @include('customers._form')
    </form>
@endsection
