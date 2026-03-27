@extends('layouts.app')

@section('title', 'Plan comptable - Nema ERP')
@section('page-title', 'Plan comptable')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Comptes de la societe</h2>
            <div class="muted">Le plan comptable de base est alimente automatiquement pour supporter les ecritures du noyau ERP.</div>
        </div>
    </div>

    <section class="card table-wrap">
        <table>
            <thead>
            <tr>
                <th>Code</th>
                <th>Libelle</th>
                <th>Type</th>
                <th>Origine</th>
                <th>Statut</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($accounts as $account)
                <tr>
                    <td><strong>{{ $account->code }}</strong></td>
                    <td>{{ $account->name }}</td>
                    <td>{{ ucfirst($account->type) }}</td>
                    <td>{{ $account->is_system ? 'Systeme' : 'Manuel' }}</td>
                    <td>{{ $account->is_active ? 'Actif' : 'Inactif' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="muted">Aucun compte disponible.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endsection
