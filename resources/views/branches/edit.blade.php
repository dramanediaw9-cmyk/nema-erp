@extends('layouts.app')

@section('title', 'Modifier une agence - Nema ERP')
@section('page-title', 'Modifier l\'agence')

@section('content')
    <form method="POST" action="{{ route('branches.update', $branch) }}">
        @csrf
        @method('PUT')
        @include('branches._form')
    </form>
@endsection
