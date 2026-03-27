@extends('layouts.app')

@section('title', 'Modifier un produit - Nema ERP')
@section('page-title', 'Modifier le produit')

@section('content')
    <form method="POST" action="{{ route('products.update', $product) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('products._form')
    </form>
@endsection
