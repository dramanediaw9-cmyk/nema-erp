@extends('layouts.app')

@php
    $customerLabel = $businessVocabulary['client'] ?? 'Client';
    $customersLabel = $businessVocabulary['clients'] ?? 'Clients';
@endphp

@section('title', $customersLabel.' - Nema ERP')
@section('page-title', $customersLabel)

@section('content')
    @php
        $headerActions = [];
        $currentView = $filters['view'] ?? 'list';

        if (auth()->user()?->hasPermission('imports.manage')) {
            $headerActions[] = ['label' => 'Importer Excel/CSV', 'url' => route('imports.index'), 'style' => 'secondary'];
        }

        if (auth()->user()?->hasPermission('customers.manage')) {
            $headerActions[] = ['label' => 'Nouveau '.$customerLabel, 'url' => route('customers.create'), 'style' => 'primary'];
        }
    @endphp

    @include('partials.erp-page-head', [
        'eyebrow' => $customersLabel,
        'title' => 'Portefeuille '.$customersLabel,
        'description' => 'Recherche, suivi commercial et recouvrement du portefeuille.',
        'actions' => $headerActions,
    ])

    <div class="grid stats-grid" style="margin-bottom:20px;">
        <div class="card"><div class="muted">{{ $customersLabel }}</div><div class="stat-value">{{ $summary['customer_count'] }}</div></div>
        <div class="card"><div class="muted">Actifs</div><div class="stat-value">{{ $summary['active_count'] }}</div></div>
        <div class="card"><div class="muted">Solde ouvert</div><div class="stat-value">{{ number_format($summary['open_balance_total'], 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Echu</div><div class="stat-value">{{ number_format($summary['overdue_balance_total'], 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">En retard</div><div class="stat-value">{{ $summary['overdue_customer_count'] }}</div></div>
    </div>

    <section class="card" style="margin-bottom:18px;">
        <form method="GET" action="{{ route('customers.index') }}" class="form-grid" style="align-items:end; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));">
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
                <a href="{{ route('customers.index', ['view' => $currentView]) }}" class="button button-secondary">Reinitialiser</a>
            </div>
        </form>
    </section>

    <div style="display:flex; justify-content:space-between; gap:16px; align-items:center; flex-wrap:wrap; margin-bottom:18px;">
        <div class="muted">Choisis la lecture qui convient le mieux au suivi portefeuille.</div>
        @include('partials.erp-view-switcher', [
            'view' => $currentView,
            'label' => 'Vue '.$customersLabel,
            'listUrl' => route('customers.index', array_merge(request()->query(), ['view' => 'list'])),
            'kanbanUrl' => route('customers.index', array_merge(request()->query(), ['view' => 'kanban'])),
        ])
    </div>

    @if ($currentView === 'kanban')
        <div class="erp-kanban-grid">
            @forelse ($customers as $customer)
                @php
                    $isOverdue = (float) $customer->overdue_balance > 0;
                    $isOpen = (float) $customer->open_balance > 0;
                    $cardTone = $isOverdue ? 'danger' : ($isOpen ? 'warning' : 'success');
                @endphp
                <section class="card erp-kanban-card erp-kanban-card--{{ $cardTone }}">
                    <div class="erp-kanban-head">
                        <div class="erp-kanban-copy">
                            <div class="erp-kanban-code">{{ $customer->code }}</div>
                            <h3>{{ $customer->name }}</h3>
                            <p class="muted">{{ $customer->city ?: 'Ville non renseignee' }}</p>
                        </div>
                        <div style="display:grid; gap:8px; justify-items:end;">
                            @include('partials.erp-status-badge', [
                                'type' => 'activity',
                                'value' => $customer->is_active ? 'active' : 'inactive',
                            ])
                            @include('partials.erp-status-badge', [
                                'type' => 'portfolio',
                                'value' => $isOverdue ? 'overdue' : ($isOpen ? 'open' : 'clear'),
                            ])
                        </div>
                    </div>
                    <div class="erp-kanban-stats">
                        <div class="erp-kanban-stat">
                            <div class="label">Solde ouvert</div>
                            <div class="value">{{ number_format((float) $customer->open_balance, 0, ',', ' ') }}</div>
                        </div>
                        <div class="erp-kanban-stat">
                            <div class="label">Echu</div>
                            <div class="value">{{ number_format((float) $customer->overdue_balance, 0, ',', ' ') }}</div>
                        </div>
                    </div>
                    <div class="erp-kanban-copy">
                        <p class="muted">{{ $customer->phone ?: 'Telephone non renseigne' }}</p>
                        <p class="muted">{{ $customer->email ?: 'Email non renseigne' }}</p>
                        <p class="muted">0-30j {{ number_format((float) $customer->bucket_1_30, 0, ',', ' ') }} · 31-60j {{ number_format((float) $customer->bucket_31_60, 0, ',', ' ') }} · 60+j {{ number_format((float) $customer->bucket_61_plus, 0, ',', ' ') }}</p>
                    </div>
                    <div class="erp-kanban-actions">
                        <a href="{{ route('customers.show', $customer) }}" class="button button-secondary">Voir la fiche</a>
                        @allowed('customers.manage')
                            <a href="{{ route('customers.edit', $customer) }}" class="button button-secondary">Modifier</a>
                        @endallowed
                    </div>
                </section>
            @empty
                <section class="card empty-state" style="grid-column:1 / -1;">
                    <h3>Aucun client ne correspond aux filtres selectionnes.</h3>
                    <p class="muted">Ajuste la recherche, la ville, le statut ou le suivi solde.</p>
                </section>
            @endforelse
        </div>
    @else
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
                                @include('partials.erp-status-badge', [
                                    'type' => 'activity',
                                    'value' => $customer->is_active ? 'active' : 'inactive',
                                ])
                                @include('partials.erp-status-badge', [
                                    'type' => 'portfolio',
                                    'value' => $isOverdue ? 'overdue' : ($isOpen ? 'open' : 'clear'),
                                ])
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
        </div>
    @endif

    @if (method_exists($customers, 'links'))
        <div style="margin-top:18px;">{{ $customers->links() }}</div>
    @endif
@endsection
