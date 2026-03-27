@extends('layouts.app')

@section('title', 'Nouveau compte - Nema ERP')
@section('page-title', 'Nouveau compte de tresorerie')

@section('content')
    <form method="POST" action="{{ route('cash-accounts.store') }}">
        @csrf
        @include('cash-accounts._form')
    </form>
@endsection
