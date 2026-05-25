@extends('layouts.app')

@section('title', 'Entreprises - Nema ERP')
@section('page-title', 'Entreprises')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Liste des entreprises</h2>
            <div class="muted">Base légale du futur ERP multi-entreprise.</div>
        </div>
        @if (auth()->user()?->hasRole('platform_admin'))
            <a href="{{ route('companies.create') }}" class="button button-primary">Nouvelle entreprise</a>
        @endif
    </div>

    <div class="card table-wrap">
        <table>
            <thead>
            <tr>
                <th>Nom</th>
                <th>NIF / RCCM</th>
                <th>Contact</th>
                <th>Devise</th>
                <th>Statut</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($companies as $company)
                <tr>
                    <td>
                        <strong>{{ $company->name }}</strong>
                        @if ($company->legal_name)
                            <div class="muted">{{ $company->legal_name }}</div>
                        @endif
                    </td>
                    <td>
                        <div>{{ $company->nif ?: 'Non renseigné' }}</div>
                        <div class="muted">{{ $company->rccm ?: 'Non renseigné' }}</div>
                    </td>
                    <td>
                        <div>{{ $company->phone ?: 'Sans téléphone' }}</div>
                        <div class="muted">{{ $company->email ?: 'Sans e-mail' }}</div>
                    </td>
                    <td>{{ $company->currency_code }}</td>
                    <td>
                        <span class="badge {{ $company->is_active ? 'badge-success' : 'badge-muted' }}">
                            {{ $company->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td>
                        @allowed('companies.manage')
                            <a href="{{ route('companies.edit', $company) }}" class="button button-secondary">Modifier</a>
                        @endallowed
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">
                        <div class="muted">Aucune entreprise disponible pour le moment.</div>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 18px;">{{ $companies->links() }}</div>
@endsection
