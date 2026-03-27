@extends('layouts.app')

@section('title', 'Modifier un rôle - Nema ERP')
@section('page-title', 'Modifier le rôle')

@section('content')
    <form method="POST" action="{{ route('roles.update', $role) }}">
        @csrf
        @method('PUT')
        @include('roles._form')
    </form>
@endsection
