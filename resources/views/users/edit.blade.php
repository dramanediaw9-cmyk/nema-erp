@extends('layouts.app')

@section('title', 'Modifier un utilisateur - Nema ERP')
@section('page-title', 'Modifier l\'utilisateur')

@section('content')
    <form method="POST" action="{{ route('users.update', $userModel) }}">
        @csrf
        @method('PUT')
        @include('users._form')
    </form>
@endsection
