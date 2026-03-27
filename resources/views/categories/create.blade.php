@extends('layouts.app')

@section('title', 'Nouvelle categorie - Nema ERP')
@section('page-title', 'Nouvelle categorie')

@section('content')
    <form method="POST" action="{{ route('categories.store') }}">
        @csrf
        @include('categories._form')
    </form>
@endsection
