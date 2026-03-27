@extends('layouts.app')

@section('title', 'Nouvelle commande client - Nema ERP')
@section('page-title', 'Nouvelle commande client')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Creer une commande client</h2>
            <div class="muted">Formalise un engagement client avant la facture et garde une trace claire de la livraison attendue.</div>
        </div>
        <a href="{{ route('orders.index') }}" class="button button-secondary">Retour liste</a>
    </div>

    <form method="POST" action="{{ route('orders.store') }}">
        @csrf
        @include('orders._form')
    </form>
@endsection
