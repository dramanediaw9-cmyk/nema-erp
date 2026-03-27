@extends('layouts.app')

@section('title', 'Comptes de tresorerie - Nema ERP')
@section('page-title', 'Comptes de tresorerie')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Caisses, banques et mobile money</h2>
            <div class="muted">Ces comptes servent a enregistrer les encaissements et alimenter la lecture de tresorerie.</div>
        </div>
        @allowed('cash_accounts.manage')
            <a href="{{ route('cash-accounts.create') }}" class="button button-primary">Nouveau compte</a>
        @endallowed
    </div>

    <section class="card table-wrap">
        <table>
            <thead>
            <tr>
                <th>Nom</th>
                <th>Type</th>
                <th>Agence</th>
                <th>Numero</th>
                <th>Solde initial</th>
                <th>Statut</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($accounts as $account)
                <tr>
                    <td><strong>{{ $account->name }}</strong></td>
                    <td>{{ str($account->type)->replace('_', ' ')->title() }}</td>
                    <td>{{ $account->branch?->name ?? 'Toutes agences' }}</td>
                    <td>{{ $account->account_number ?: 'Non renseigne' }}</td>
                    <td>{{ number_format((float) $account->opening_balance, 0, ',', ' ') }} XOF</td>
                    <td><span class="badge {{ $account->is_active ? 'badge-success' : 'badge-muted' }}">{{ $account->is_active ? 'Actif' : 'Inactif' }}</span></td>
                    <td>
                        @allowed('cash_accounts.manage')
                            <a href="{{ route('cash-accounts.edit', $account) }}" class="button button-secondary">Modifier</a>
                        @else
                            <span class="muted">Lecture seule</span>
                        @endallowed
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="muted">Aucun compte de tresorerie configure.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endsection
