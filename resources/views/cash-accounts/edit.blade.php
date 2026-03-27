@extends('layouts.app')

@section('title', 'Modifier compte - Nema ERP')
@section('page-title', 'Modifier le compte de tresorerie')

@section('content')
    <form method="POST" action="{{ route('cash-accounts.update', $account) }}">
        @csrf
        @method('PUT')
        @include('cash-accounts._form')
    </form>
@endsection
