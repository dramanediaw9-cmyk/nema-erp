@extends('layouts.app')

@section('title', 'Nouveau devis - Nema ERP')
@section('page-title', 'Nouveau devis client')

@section('content')
    <form method="POST" action="{{ route('quotes.store') }}">
        @csrf
        @include('quotes._form')
    </form>
@endsection
