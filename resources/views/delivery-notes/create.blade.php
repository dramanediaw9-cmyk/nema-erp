@extends('layouts.app')

@section('title', 'Nouveau bon de livraison - Nema ERP')
@section('page-title', 'Nouveau bon de livraison')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Generer un bon de livraison</h2>
            <div class="muted">Choisis une commande confirmee. Le bon de livraison sortira le stock de l agence active.</div>
        </div>
        <a href="{{ route('delivery-notes.index') }}" class="button button-secondary">Retour liste</a>
    </div>

    <form method="POST" action="{{ route('delivery-notes.store') }}">
        @csrf
        @include('delivery-notes._form')
    </form>
@endsection
