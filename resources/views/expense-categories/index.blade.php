@extends('layouts.app')

@section('title', 'Categories de depenses - Nema ERP')
@section('page-title', 'Categories de depenses')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Structurer les sorties</h2>
            <div class="muted">Ces categories facilitent la lecture des depenses et la preparation de la comptabilite de base.</div>
        </div>
        @allowed('expense_categories.manage')
            <a href="{{ route('expense-categories.create') }}" class="button button-primary">Nouvelle categorie</a>
        @endallowed
    </div>

    <section class="card table-wrap">
        <table>
            <thead>
            <tr>
                <th>Nom</th>
                <th>Description</th>
                <th>Statut</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($categories as $category)
                <tr>
                    <td><strong>{{ $category->name }}</strong></td>
                    <td>{{ $category->description ?: 'Non renseignee' }}</td>
                    <td><span class="badge {{ $category->is_active ? 'badge-success' : 'badge-muted' }}">{{ $category->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td>
                        @allowed('expense_categories.manage')
                            <a href="{{ route('expense-categories.edit', $category) }}" class="button button-secondary">Modifier</a>
                        @else
                            <span class="muted">Lecture seule</span>
                        @endallowed
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="muted">Aucune categorie de depense pour le moment.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endsection
