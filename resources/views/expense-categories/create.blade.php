@extends('layouts.app')

@section('title', 'Nouvelle categorie de depense - Nema ERP')
@section('page-title', 'Nouvelle categorie de depense')

@section('content')
    <form method="POST" action="{{ route('expense-categories.store') }}">
        @csrf
        @include('expense-categories._form')
    </form>
@endsection
