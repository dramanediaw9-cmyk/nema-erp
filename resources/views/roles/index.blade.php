@extends('layouts.app')

@section('title', 'Rôles et permissions - Nema ERP')
@section('page-title', 'Rôles et permissions')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Matrice d'accès</h2>
            <div class="muted">Rôles globaux système + rôles propres à l'entreprise active.</div>
        </div>
        @allowed('roles.manage')
            <a href="{{ route('roles.create') }}" class="button button-primary">Nouveau rôle</a>
        @endallowed
    </div>

    <div class="card table-wrap">
        <table>
            <thead>
            <tr>
                <th>Rôle</th>
                <th>Description</th>
                <th>Permissions</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($roles as $role)
                <tr>
                    <td>
                        <strong>{{ $role->name }}</strong>
                        <div class="muted">{{ $role->slug }}</div>
                        @if ($role->is_system)
                            <span class="badge badge-muted">Système</span>
                        @endif
                    </td>
                    <td>{{ $role->description ?: 'Aucune description' }}</td>
                    <td>
                        @foreach ($role->permissions as $permission)
                            <span class="badge badge-success" style="margin: 0 6px 6px 0;">{{ $permission->name }}</span>
                        @endforeach
                    </td>
                    <td>
                        @if (! $role->is_system)
                            @allowed('roles.manage')
                                <a href="{{ route('roles.edit', $role) }}" class="button button-secondary">Modifier</a>
                            @endallowed
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4"><span class="muted">Aucun rôle disponible.</span></td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if ($permissions->isNotEmpty())
        <div class="card" style="margin-top: 20px;">
            <h3 style="margin-top:0;">Catalogue des permissions</h3>
            @foreach ($permissions as $module => $modulePermissions)
                <div style="margin-top: 18px;">
                    <strong style="text-transform: capitalize;">{{ $module }}</strong>
                    <div class="checkbox-grid" style="margin-top: 10px;">
                        @foreach ($modulePermissions as $permission)
                            <div class="checkbox-card">
                                <strong>{{ $permission->name }}</strong>
                                <div class="muted" style="margin-top: 6px;">{{ $permission->slug }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
