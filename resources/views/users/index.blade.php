@extends('layouts.app')

@section('title', 'Utilisateurs - Nema ERP')
@section('page-title', 'Utilisateurs')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Équipe de l'entreprise active</h2>
            <div class="muted">Création et gestion des accès opérationnels.</div>
        </div>
        @allowed('users.manage')
            <a href="{{ route('users.create') }}" class="button button-primary">Nouvel utilisateur</a>
        @endallowed
    </div>

    <div class="card table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Contact</th>
                    <th>Agence</th>
                    <th>Rôles</th>
                    <th>Statut</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($users as $user)
                <tr>
                    <td>
                        <strong>{{ $user->name }}</strong>
                        <div class="muted">Connexion : {{ $user->last_login_at?->format('d/m/Y H:i') ?? 'Jamais' }}</div>
                    </td>
                    <td>
                        <div>{{ $user->email }}</div>
                        <div class="muted">{{ $user->phone ?: 'Sans téléphone' }}</div>
                    </td>
                    <td>{{ $user->branch?->name ?: 'Non assignée' }}</td>
                    <td>
                        @foreach ($user->roles as $role)
                            <span class="badge badge-muted" style="margin: 0 6px 6px 0;">{{ $role->name }}</span>
                        @endforeach
                    </td>
                    <td>
                        <span class="badge {{ $user->is_active ? 'badge-success' : 'badge-muted' }}">
                            {{ $user->is_active ? 'Actif' : 'Inactif' }}
                        </span>
                    </td>
                    <td>
                        @allowed('users.manage')
                            <a href="{{ route('users.edit', $user) }}" class="button button-secondary">Modifier</a>
                        @endallowed
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6"><span class="muted">Aucun utilisateur enregistré.</span></td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 18px;">{{ $users->links() }}</div>
@endsection
