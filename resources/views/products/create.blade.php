@extends('layouts.app')

@section('title', 'Nouveau produit - Nema ERP')
@section('page-title', 'Nouveau produit')

@section('content')
    <form method="POST" action="{{ route('products.store') }}" enctype="multipart/form-data">
        @csrf
        @include('products._form')
    </form>
@endsection
