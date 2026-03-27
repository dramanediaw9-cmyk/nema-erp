@extends('layouts.app')

@section('title', 'Modifier une categorie - Nema ERP')
@section('page-title', 'Modifier la categorie')

@section('content')
    <form method="POST" action="{{ route('categories.update', $category) }}">
        @csrf
        @method('PUT')
        @include('categories._form')
    </form>
@endsection
