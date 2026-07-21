@extends('layouts.app')

@section('title', 'Agences - Nema ERP')
@section('page-title', 'Agences')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Agences de {{ $company?->name ?? 'l\'entreprise active' }}</h2>
            <div class="muted">Organisation par site pour préparer les modules stock, ventes et caisse.</div>
        </div>
        @if ($company)
            @allowed('branches.manage')
                <a href="{{ route('branches.create') }}" class="button button-primary">Nouvelle agence</a>
            @endallowed
        @endif
    </div>

    @if (! $company)
        <div class="card">
            <p class="muted">Aucune entreprise active. Commencez par créer une entreprise pour pouvoir gérer ses agences.</p>
            @if (auth()->user()?->hasRole('platform_admin'))
                <a href="{{ route('companies.create') }}" class="button button-primary">Créer une entreprise</a>
            @endif
        </div>
    @else
        <div class="card table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Nom</th>
                    <th>Code</th>
                    <th>Ville</th>
                    <th>Statut</th>
                    <th>Activation</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($branches as $branch)
                    <tr>
                        <td>
                            <strong>{{ $branch->name }}</strong>
                            @if ($branch->address)
                                <div class="muted">{{ $branch->address }}</div>
                            @endif
                        </td>
                        <td>{{ $branch->code }}</td>
                        <td>{{ $branch->city ?: 'Non renseignée' }}</td>
                        <td>
                            <span class="badge {{ $branch->is_active ? 'badge-success' : 'badge-muted' }}">
                                {{ $branch->is_active ? 'Active' : 'Inactive' }}
                            </span>
                            @if ($branch->is_default)
                                <span class="badge badge-muted">Par défaut</span>
                            @endif
                        </td>
                        <td>
                            @if ($workspace->branchId() === $branch->id)
                                <span class="badge badge-success">Active maintenant</span>
                            @else
                                <form method="POST" action="{{ route('branches.switch', $branch) }}" class="inline-form">
                                    @csrf
                                    <button class="button button-secondary" type="submit">Activer</button>
                                </form>
                            @endif
                        </td>
                        <td>
                            @allowed('branches.manage')
                                <a href="{{ route('branches.edit', $branch) }}" class="button button-secondary">Modifier</a>
                            @endallowed
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6"><span class="muted">Aucune agence enregistrée.</span></td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top: 18px;">{{ $branches->links() }}</div>
    @endif
@endsection
