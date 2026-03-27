@extends('layouts.app')

@section('title', 'Créer un rôle - Nema ERP')
@section('page-title', 'Nouveau rôle')

@section('content')
    <form method="POST" action="{{ route('roles.store') }}">
        @csrf
        @include('roles._form')
    </form>
@endsection
