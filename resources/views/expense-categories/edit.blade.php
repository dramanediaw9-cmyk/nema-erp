@extends('layouts.app')

@section('title', 'Modifier categorie de depense - Nema ERP')
@section('page-title', 'Modifier la categorie de depense')

@section('content')
    <form method="POST" action="{{ route('expense-categories.update', $category) }}">
        @csrf
        @method('PUT')
        @include('expense-categories._form')
    </form>
@endsection
