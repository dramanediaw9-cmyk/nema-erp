@extends('layouts.app')

@section('title', 'Categories - Nema ERP')
@section('page-title', 'Categories produits')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Structurer le catalogue</h2>
            <div class="muted">Classe les produits pour faciliter les filtres, le reporting et la lecture du stock.</div>
        </div>
        @allowed('categories.manage')
            <a href="{{ route('categories.create') }}" class="button button-primary">Nouvelle categorie</a>
        @endallowed
    </div>

    <section class="card table-wrap">
        <table>
            <thead>
            <tr>
                <th>Nom</th>
                <th>Description</th>
                <th>Statut</th>
                <th style="width: 160px;">Action</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($categories as $category)
                <tr>
                    <td><strong>{{ $category->name }}</strong></td>
                    <td>{{ $category->description ?: 'Non renseignee' }}</td>
                    <td>
                        <span class="badge {{ $category->is_active ? 'badge-success' : 'badge-muted' }}">
                            {{ $category->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td>
                        @allowed('categories.manage')
                            <a href="{{ route('categories.edit', $category) }}" class="button button-secondary">Modifier</a>
                        @else
                            <span class="muted">Lecture seule</span>
                        @endallowed
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="muted">Aucune categorie enregistree pour le moment.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endsection
