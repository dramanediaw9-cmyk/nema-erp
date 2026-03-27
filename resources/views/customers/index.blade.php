@extends('layouts.app')

@section('title', 'Clients - Nema ERP')
@section('page-title', 'Clients')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Portefeuille clients</h2>
            <div class="muted">Lecture commerciale et recouvrement du portefeuille client.</div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            @allowed('imports.manage')
                <a href="{{ route('imports.index') }}" class="button button-secondary">Importer CSV</a>
            @endallowed
            @allowed('customers.manage')
                <a href="{{ route('customers.create') }}" class="button button-primary">Nouveau client</a>
            @endallowed
        </div>
    </div>

    <div class="grid stats-grid" style="margin-bottom:20px;">
        <div class="card"><div class="muted">Clients</div><div class="stat-value">{{ $summary['customer_count'] }}</div></div>
        <div class="card"><div class="muted">Clients actifs</div><div class="stat-value">{{ $summary['active_count'] }}</div></div>
        <div class="card"><div class="muted">Solde ouvert</div><div class="stat-value">{{ number_format($summary['open_balance_total'], 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Echu</div><div class="stat-value">{{ number_format($summary['overdue_balance_total'], 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Clients en retard</div><div class="stat-value">{{ $summary['overdue_customer_count'] }}</div></div>
    </div>

    <section class="card" style="margin-bottom:18px;">
        <form method="GET" action="{{ route('customers.index') }}" class="form-grid" style="align-items:end; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));">
            <div style="grid-column:span 2; min-width:220px;">
                <label for="search">Recherche</label>
                <input type="text" id="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Code, nom, telephone, email, NIF...">
            </div>
            <div>
                <label for="city">Ville</label>
                <select id="city" name="city">
                    <option value="">Toutes les villes</option>
                    @foreach ($cities as $city)
                        <option value="{{ $city }}" @selected(($filters['city'] ?? null) === $city)>{{ $city }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="status">Statut</label>
                <select id="status" name="status">
                    <option value="">Tous</option>
                    <option value="active" @selected(($filters['status'] ?? null) === 'active')>Actifs</option>
                    <option value="inactive" @selected(($filters['status'] ?? null) === 'inactive')>Inactifs</option>
                </select>
            </div>
            <div>
                <label for="balance_state">Suivi solde</label>
                <select id="balance_state" name="balance_state">
                    <option value="">Tous</option>
                    <option value="open" @selected(($filters['balance_state'] ?? null) === 'open')>Avec solde ouvert</option>
                    <option value="overdue" @selected(($filters['balance_state'] ?? null) === 'overdue')>En retard</option>
                    <option value="clear" @selected(($filters['balance_state'] ?? null) === 'clear')>A jour</option>
                </select>
            </div>
            <div class="actions" style="margin-top:0; justify-content:flex-start; align-self:end;">
                <button type="submit" class="button button-primary">Filtrer</button>
                <a href="{{ route('customers.index') }}" class="button button-secondary">Reinitialiser</a>
            </div>
        </form>
    </section>

    <div class="card table-wrap">
        <table>
            <thead>
            <tr>
                <th>Code</th>
                <th>Client</th>
                <th>Ville</th>
                <th>Solde ouvert</th>
                <th>Echu</th>
                <th>0-30j</th>
                <th>31-60j</th>
                <th>60+j</th>
                <th>Statut</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($customers as $customer)
                @php
                    $isOverdue = (float) $customer->overdue_balance > 0;
                    $isOpen = (float) $customer->open_balance > 0;
                @endphp
                <tr>
                    <td><strong>{{ $customer->code }}</strong></td>
                    <td>
                        <a href="{{ route('customers.show', $customer) }}" style="font-weight:600;">{{ $customer->name }}</a>
                        <div class="muted" style="font-size:14px;">{{ $customer->email ?: 'Email non renseigne' }}</div>
                        <div class="muted" style="font-size:14px;">{{ $customer->phone ?: 'Telephone non renseigne' }}</div>
                    </td>
                    <td>{{ $customer->city ?: 'Non renseignee' }}</td>
                    <td>{{ number_format((float) $customer->open_balance, 0, ',', ' ') }} XOF</td>
                    <td>{{ number_format((float) $customer->overdue_balance, 0, ',', ' ') }} XOF</td>
                    <td>{{ number_format((float) $customer->bucket_1_30, 0, ',', ' ') }}</td>
                    <td>{{ number_format((float) $customer->bucket_31_60, 0, ',', ' ') }}</td>
                    <td>{{ number_format((float) $customer->bucket_61_plus, 0, ',', ' ') }}</td>
                    <td>
                        <div style="display:grid; gap:8px;">
                            <span class="badge {{ $customer->is_active ? 'badge-success' : 'badge-muted' }}">{{ $customer->is_active ? 'Actif' : 'Inactif' }}</span>
                            <span class="badge {{ $isOverdue ? 'badge-warning' : ($isOpen ? 'badge-muted' : 'badge-success') }}">
                                {{ $isOverdue ? 'En retard' : ($isOpen ? 'A suivre' : 'A jour') }}
                            </span>
                        </div>
                    </td>
                    <td>
                        <div style="display:flex; gap:10px; flex-wrap:wrap;">
                            <a href="{{ route('customers.show', $customer) }}" class="button button-secondary">Voir</a>
                            @allowed('customers.manage')
                                <a href="{{ route('customers.edit', $customer) }}" class="button button-secondary">Modifier</a>
                            @endallowed
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="muted">Aucun client ne correspond aux filtres selectionnes.</td>
                </tr>
            @endforelse
            </tbody>
        </table>

        @if (method_exists($customers, 'links'))
            <div style="margin-top:18px;">{{ $customers->links() }}</div>
        @endif
    </div>
@endsection
