@extends('layouts.app')

@section('title', 'Créer un utilisateur - Nema ERP')
@section('page-title', 'Nouvel utilisateur')

@section('content')
    <form method="POST" action="{{ route('users.store') }}">
        @csrf
        @include('users._form')
    </form>
@endsection
