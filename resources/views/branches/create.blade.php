@extends('layouts.app')

@section('title', 'Créer une agence - Nema ERP')
@section('page-title', 'Nouvelle agence')

@section('content')
    <form method="POST" action="{{ route('branches.store') }}">
        @csrf
        @include('branches._form')
    </form>
@endsection
