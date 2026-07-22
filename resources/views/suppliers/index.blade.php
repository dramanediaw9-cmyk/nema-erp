@extends('layouts.app')

@php
    $supplierLabel = $businessVocabulary['supplier'] ?? 'Fournisseur';
    $suppliersLabel = $businessVocabulary['suppliers'] ?? 'Fournisseurs';
@endphp

@section('title', $suppliersLabel.' - Nema ERP')
@section('page-title', $suppliersLabel)

@section('content')
    @php
        $headerActions = [];
        $currentView = $filters['view'] ?? 'list';

        if (auth()->user()?->hasPermission('imports.manage')) {
            $headerActions[] = ['label' => 'Importer Excel/CSV', 'url' => route('imports.index'), 'style' => 'secondary'];
        }

        if (auth()->user()?->hasPermission('suppliers.manage')) {
            $headerActions[] = ['label' => 'Nouveau '.$supplierLabel, 'url' => route('suppliers.create'), 'style' => 'primary'];
        }
    @endphp

    @include('partials.erp-page-head', [
        'eyebrow' => $suppliersLabel,
        'title' => 'Portefeuille '.$suppliersLabel,
        'description' => 'Lecture dettes et suivi des echeances.',
        'actions' => $headerActions,
    ])

    <div class="grid stats-grid" style="margin-bottom:20px;">
        <div class="card"><div class="muted">{{ $suppliersLabel }}</div><div class="stat-value">{{ $summary['supplier_count'] }}</div></div>
        <div class="card"><div class="muted">Actifs</div><div class="stat-value">{{ $summary['active_count'] }}</div></div>
        <div class="card"><div class="muted">Dettes ouvertes</div><div class="stat-value">{{ number_format($summary['open_balance_total'], 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Dettes echues</div><div class="stat-value">{{ number_format($summary['overdue_balance_total'], 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">En retard</div><div class="stat-value">{{ $summary['overdue_supplier_count'] }}</div></div>
    </div>

    <section class="card" style="margin-bottom:18px;">
        <form method="GET" action="{{ route('suppliers.index') }}" class="form-grid" style="align-items:end; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));">
            <input type="hidden" name="view" value="{{ $currentView }}">
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
                <a href="{{ route('suppliers.index', ['view' => $currentView]) }}" class="button button-secondary">Reinitialiser</a>
            </div>
        </form>
    </section>

    <div style="display:flex; justify-content:space-between; gap:16px; align-items:center; flex-wrap:wrap; margin-bottom:18px;">
        <div class="muted">Alterne entre lecture detaillee et vue portefeuille par cartes.</div>
        @include('partials.erp-view-switcher', [
            'view' => $currentView,
            'label' => 'Vue '.$suppliersLabel,
            'listUrl' => route('suppliers.index', array_merge(request()->query(), ['view' => 'list'])),
            'kanbanUrl' => route('suppliers.index', array_merge(request()->query(), ['view' => 'kanban'])),
        ])
    </div>

    @if ($currentView === 'kanban')
        <div class="erp-kanban-grid">
            @forelse ($suppliers as $supplier)
                @php
                    $isOverdue = (float) $supplier->overdue_balance > 0;
                    $isOpen = (float) $supplier->open_balance > 0;
                    $cardTone = $isOverdue ? 'danger' : ($isOpen ? 'warning' : 'success');
                @endphp
                <section class="card erp-kanban-card erp-kanban-card--{{ $cardTone }}">
                    <div class="erp-kanban-head">
                        <div class="erp-kanban-copy">
                            <div class="erp-kanban-code">{{ $supplier->code }}</div>
                            <h3>{{ $supplier->name }}</h3>
                            <p class="muted">{{ $supplier->city ?: 'Ville non renseignee' }}</p>
                        </div>
                        <div style="display:grid; gap:8px; justify-items:end;">
                            @include('partials.erp-status-badge', [
                                'type' => 'activity',
                                'value' => $supplier->is_active ? 'active' : 'inactive',
                            ])
                            @include('partials.erp-status-badge', [
                                'type' => 'portfolio',
                                'value' => $isOverdue ? 'overdue' : ($isOpen ? 'open' : 'clear'),
                            ])
                        </div>
                    </div>
                    <div class="erp-kanban-stats">
                        <div class="erp-kanban-stat">
                            <div class="label">Dette ouverte</div>
                            <div class="value">{{ number_format((float) $supplier->open_balance, 0, ',', ' ') }}</div>
                        </div>
                        <div class="erp-kanban-stat">
                            <div class="label">Echue</div>
                            <div class="value">{{ number_format((float) $supplier->overdue_balance, 0, ',', ' ') }}</div>
                        </div>
                    </div>
                    <div class="erp-kanban-copy">
                        <p class="muted">{{ $supplier->phone ?: 'Sans telephone' }}</p>
                        <p class="muted">{{ $supplier->email ?: 'Sans e-mail' }}</p>
                        <p class="muted">0-30j {{ number_format((float) $supplier->bucket_1_30, 0, ',', ' ') }} · 31-60j {{ number_format((float) $supplier->bucket_31_60, 0, ',', ' ') }} · 60+j {{ number_format((float) $supplier->bucket_61_plus, 0, ',', ' ') }}</p>
                    </div>
                    <div class="erp-kanban-actions">
                        <a href="{{ route('suppliers.show', $supplier) }}" class="button button-secondary">Voir la fiche</a>
                        @allowed('suppliers.manage')
                            <a href="{{ route('suppliers.edit', $supplier) }}" class="button button-secondary">Modifier</a>
                        @endallowed
                    </div>
                </section>
            @empty
                <section class="card empty-state" style="grid-column:1 / -1;">
                    <h3>Aucun {{ strtolower($supplierLabel) }} ne correspond aux filtres selectionnes.</h3>
                    <p class="muted">Ajuste la ville, le statut ou le suivi dette.</p>
                </section>
            @endforelse
        </div>
    @else
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
                                @include('partials.erp-status-badge', [
                                    'type' => 'activity',
                                    'value' => $supplier->is_active ? 'active' : 'inactive',
                                ])
                                @include('partials.erp-status-badge', [
                                    'type' => 'portfolio',
                                    'value' => $isOverdue ? 'overdue' : ($isOpen ? 'open' : 'clear'),
                                ])
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
                    <tr><td colspan="10"><span class="muted">Aucun {{ strtolower($supplierLabel) }} ne correspond aux filtres selectionnes.</span></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    @endif

    <div style="margin-top: 18px;">{{ $suppliers->links() }}</div>
@endsection
