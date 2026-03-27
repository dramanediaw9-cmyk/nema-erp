@extends('layouts.app')

@section('title', 'Fournisseurs - Nema ERP')
@section('page-title', 'Fournisseurs')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Base fournisseurs</h2>
            <div class="muted">Lecture dettes fournisseurs et suivi des echeances.</div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            @allowed('imports.manage')
                <a href="{{ route('imports.index') }}" class="button button-secondary">Importer CSV</a>
            @endallowed
            @allowed('suppliers.manage')
                <a href="{{ route('suppliers.create') }}" class="button button-primary">Nouveau fournisseur</a>
            @endallowed
        </div>
    </div>

    <div class="grid stats-grid" style="margin-bottom:20px;">
        <div class="card"><div class="muted">Fournisseurs</div><div class="stat-value">{{ $summary['supplier_count'] }}</div></div>
        <div class="card"><div class="muted">Fournisseurs actifs</div><div class="stat-value">{{ $summary['active_count'] }}</div></div>
        <div class="card"><div class="muted">Dettes ouvertes</div><div class="stat-value">{{ number_format($summary['open_balance_total'], 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Dettes echues</div><div class="stat-value">{{ number_format($summary['overdue_balance_total'], 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Fournisseurs en retard</div><div class="stat-value">{{ $summary['overdue_supplier_count'] }}</div></div>
    </div>

    <section class="card" style="margin-bottom:18px;">
        <form method="GET" action="{{ route('suppliers.index') }}" class="form-grid" style="align-items:end; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));">
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
                <label for="balance_state">Suivi dette</label>
                <select id="balance_state" name="balance_state">
                    <option value="">Tous</option>
                    <option value="open" @selected(($filters['balance_state'] ?? null) === 'open')>Avec dette ouverte</option>
                    <option value="overdue" @selected(($filters['balance_state'] ?? null) === 'overdue')>En retard</option>
                    <option value="clear" @selected(($filters['balance_state'] ?? null) === 'clear')>A jour</option>
                </select>
            </div>
            <div class="actions" style="margin-top:0; justify-content:flex-start; align-self:end;">
                <button type="submit" class="button button-primary">Filtrer</button>
                <a href="{{ route('suppliers.index') }}" class="button button-secondary">Reinitialiser</a>
            </div>
        </form>
    </section>

    <div class="card table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Nom</th>
                    <th>Ville</th>
                    <th>Dette ouverte</th>
                    <th>Echue</th>
                    <th>0-30j</th>
                    <th>31-60j</th>
                    <th>60+j</th>
                    <th>Statut</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($suppliers as $supplier)
                @php
                    $isOverdue = (float) $supplier->overdue_balance > 0;
                    $isOpen = (float) $supplier->open_balance > 0;
                @endphp
                <tr>
                    <td>{{ $supplier->code }}</td>
                    <td>
                        <a href="{{ route('suppliers.show', $supplier) }}" style="font-weight:600;">{{ $supplier->name }}</a>
                        <div class="muted" style="font-size:14px;">{{ $supplier->phone ?: 'Sans telephone' }}</div>
                        <div class="muted" style="font-size:14px;">{{ $supplier->email ?: 'Sans e-mail' }}</div>
                    </td>
                    <td>{{ $supplier->city ?: 'Non renseignee' }}</td>
                    <td>{{ number_format((float) $supplier->open_balance, 0, ',', ' ') }} XOF</td>
                    <td>{{ number_format((float) $supplier->overdue_balance, 0, ',', ' ') }} XOF</td>
                    <td>{{ number_format((float) $supplier->bucket_1_30, 0, ',', ' ') }}</td>
                    <td>{{ number_format((float) $supplier->bucket_31_60, 0, ',', ' ') }}</td>
                    <td>{{ number_format((float) $supplier->bucket_61_plus, 0, ',', ' ') }}</td>
                    <td>
                        <div style="display:grid; gap:8px;">
                            <span class="badge {{ $supplier->is_active ? 'badge-success' : 'badge-muted' }}">{{ $supplier->is_active ? 'Actif' : 'Inactif' }}</span>
                            <span class="badge {{ $isOverdue ? 'badge-warning' : ($isOpen ? 'badge-muted' : 'badge-success') }}">
                                {{ $isOverdue ? 'En retard' : ($isOpen ? 'A suivre' : 'A jour') }}
                            </span>
                        </div>
                    </td>
                    <td>
                        <div style="display:flex; gap:10px; flex-wrap:wrap;">
                            <a href="{{ route('suppliers.show', $supplier) }}" class="button button-secondary">Voir</a>
                            @allowed('suppliers.manage')
                                <a href="{{ route('suppliers.edit', $supplier) }}" class="button button-secondary">Modifier</a>
                            @endallowed
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="10"><span class="muted">Aucun fournisseur ne correspond aux filtres selectionnes.</span></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 18px;">{{ $suppliers->links() }}</div>
@endsection
