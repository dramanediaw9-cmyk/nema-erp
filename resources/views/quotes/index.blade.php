@extends('layouts.app')

@section('title', 'Devis - Nema ERP')
@section('page-title', 'Devis clients')

@section('content')
    @php
        $currentView = $filters['view'] ?? 'list';
    @endphp

    <div class="page-head">
        <div>
            <h2 style="margin:0;">Propositions commerciales</h2>
            <div class="muted">Gere les devis, leur validite commerciale et leur conversion en commande ou facture de vente.</div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="{{ route('quotes.export', request()->query()) }}" class="button button-secondary">Exporter CSV</a>
            @allowed('quotes.manage')
                <a href="{{ route('quotes.create') }}" class="button button-primary">Nouveau devis</a>
            @endallowed
        </div>
    </div>

    <div class="grid stats-grid" style="margin-bottom:20px;">
        <div class="card"><div class="muted">Brouillons</div><div class="stat-value">{{ $summary['draft'] }}</div></div>
        <div class="card"><div class="muted">Envoyes</div><div class="stat-value">{{ $summary['sent'] }}</div></div>
        <div class="card"><div class="muted">Acceptes</div><div class="stat-value">{{ $summary['accepted'] }}</div></div>
        <div class="card"><div class="muted">Convertis</div><div class="stat-value">{{ $summary['converted'] }}</div></div>
    </div>

    <section class="card" style="margin-bottom:18px;">
        <form method="GET" action="{{ route('quotes.index') }}" class="form-grid" style="align-items:end; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));">
            <input type="hidden" name="view" value="{{ $currentView }}">
            <div style="grid-column:span 2; min-width:220px;">
                <label for="search">Recherche</label>
                <input type="text" id="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Numero, client, agence, note...">
            </div>
            <div>
                <label for="date_from">Date debut</label>
                <input type="date" id="date_from" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
            </div>
            <div>
                <label for="date_to">Date fin</label>
                <input type="date" id="date_to" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
            </div>
            <div>
                <label for="branch_id">Agence</label>
                <select id="branch_id" name="branch_id">
                    <option value="">Toutes les agences</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((int) ($filters['branch_id'] ?? 0) === $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="status">Statut</label>
                <select id="status" name="status">
                    <option value="">Tous</option>
                    <option value="draft" @selected(($filters['status'] ?? null) === 'draft')>Brouillon</option>
                    <option value="sent" @selected(($filters['status'] ?? null) === 'sent')>Envoye</option>
                    <option value="accepted" @selected(($filters['status'] ?? null) === 'accepted')>Accepte</option>
                    <option value="converted" @selected(($filters['status'] ?? null) === 'converted')>Converti</option>
                    <option value="cancelled" @selected(($filters['status'] ?? null) === 'cancelled')>Annule</option>
                </select>
                </div>
            <div class="actions" style="margin-top:0; justify-content:flex-start; align-self:end;">
                <button type="submit" class="button button-primary">Filtrer</button>
                <a href="{{ route('quotes.index', ['view' => $currentView]) }}" class="button button-secondary">Reinitialiser</a>
            </div>
        </form>
    </section>

    <div style="display:flex; justify-content:space-between; gap:16px; align-items:center; flex-wrap:wrap; margin-bottom:18px;">
        <div class="muted">Choisis une lecture tabulaire ou par cartes selon ton niveau de suivi.</div>
        @include('partials.erp-view-switcher', [
            'view' => $currentView,
            'label' => 'Vue devis',
            'listUrl' => route('quotes.index', array_merge(request()->query(), ['view' => 'list'])),
            'kanbanUrl' => route('quotes.index', array_merge(request()->query(), ['view' => 'kanban'])),
        ])
    </div>

    @if ($currentView === 'kanban')
        <div class="erp-kanban-grid">
            @forelse ($quotes as $quote)
                @php
                    $statusLabel = match ($quote->status) {
                        'draft' => 'Brouillon',
                        'sent' => 'Envoye',
                        'accepted' => 'Accepte',
                        'converted' => 'Converti',
                        'cancelled' => 'Annule',
                        default => $quote->status,
                    };
                    $statusTone = in_array($quote->status, ['accepted', 'converted'], true) ? 'success' : ($quote->status === 'cancelled' ? 'danger' : 'muted');
                    $validityLabel = 'Sans limite';
                    $validityTone = 'muted';

                    if ($quote->status === 'converted') {
                        $validityLabel = 'Traite';
                        $validityTone = 'success';
                    } elseif ($quote->valid_until && $quote->valid_until->lt($today)) {
                        $validityLabel = 'Expire';
                        $validityTone = 'danger';
                    } elseif ($quote->valid_until) {
                        $validityLabel = 'Valide';
                        $validityTone = 'success';
                    }

                    $cardTone = $validityTone === 'danger'
                        ? 'danger'
                        : (in_array($quote->status, ['accepted', 'converted'], true) ? 'success' : 'warning');
                @endphp
                <section class="card erp-kanban-card erp-kanban-card--{{ $cardTone }}">
                    <div class="erp-kanban-head">
                        <div class="erp-kanban-copy">
                            <div class="erp-kanban-code">{{ $quote->quote_number }}</div>
                            <h3>{{ $quote->customer?->name ?? 'Client non renseigne' }}</h3>
                            <p class="muted">{{ $quote->quote_date?->format('d/m/Y') }} · {{ $quote->branch?->name ?? 'Agence non renseignee' }}</p>
                        </div>
                        <div style="display:grid; gap:8px; justify-items:end;">
                            @include('partials.erp-status-badge', ['label' => $statusLabel, 'tone' => $statusTone])
                            @include('partials.erp-status-badge', ['label' => $validityLabel, 'tone' => $validityTone])
                        </div>
                    </div>
                    <div class="erp-kanban-stats">
                        <div class="erp-kanban-stat">
                            <div class="label">Total</div>
                            <div class="value">{{ number_format((float) $quote->total, 0, ',', ' ') }}</div>
                        </div>
                        <div class="erp-kanban-stat">
                            <div class="label">Validite</div>
                            <div class="value">{{ $quote->valid_until?->format('d/m') ?? 'Libre' }}</div>
                        </div>
                    </div>
                    <div class="erp-kanban-copy">
                        @if ($quote->convertedOrder)
                            <p class="muted">Commande liee : {{ $quote->convertedOrder->order_number }}</p>
                        @elseif ($quote->convertedInvoice)
                            <p class="muted">Facture liee : {{ $quote->convertedInvoice->invoice_number }}</p>
                        @elseif ($quote->notes)
                            <p class="muted">{{ $quote->notes }}</p>
                        @else
                            <p class="muted">Proposition prete pour suivi commercial et conversion.</p>
                        @endif
                    </div>
                    <div class="erp-kanban-actions">
                        <a href="{{ route('quotes.show', $quote) }}" class="button button-secondary">Voir le devis</a>
                        @if ($quote->status === 'accepted')
                            <span class="badge badge-success">Pret a convertir</span>
                        @endif
                    </div>
                </section>
            @empty
                <section class="card empty-state" style="grid-column:1 / -1;">
                    <span class="badge badge-success">Aucun devis</span>
                    <h3>La base devis est vide</h3>
                    <div class="muted">Commence par creer un devis pour alimenter la chaine commerciale amont.</div>
                    <div class="empty-actions">
                        @allowed('quotes.manage')
                            <a href="{{ route('quotes.create') }}" class="button button-primary">Creer un devis</a>
                        @endallowed
                    </div>
                </section>
            @endforelse
        </div>
    @else
        <section class="card table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Numero</th>
                    <th>Date</th>
                    <th>Validite</th>
                    <th>Client</th>
                    <th>Agence</th>
                    <th>Statut</th>
                    <th>Total</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($quotes as $quote)
                    @php
                        $statusLabel = match ($quote->status) {
                            'draft' => 'Brouillon',
                            'sent' => 'Envoye',
                            'accepted' => 'Accepte',
                            'converted' => 'Converti',
                            'cancelled' => 'Annule',
                            default => $quote->status,
                        };
                        $statusClass = in_array($quote->status, ['accepted', 'converted'], true) ? 'badge-success' : ($quote->status === 'cancelled' ? 'badge-warning' : 'badge-muted');
                        $validityLabel = 'Sans limite';

                        if ($quote->status === 'converted') {
                            $validityLabel = 'Traite';
                        } elseif ($quote->valid_until && $quote->valid_until->lt($today)) {
                            $validityLabel = 'Expire';
                        } elseif ($quote->valid_until) {
                            $validityLabel = 'Valide';
                        }
                    @endphp
                    <tr>
                        <td>
                            <strong>{{ $quote->quote_number }}</strong>
                            @if ($quote->notes)
                                <div class="muted" style="font-size:13px;">{{ $quote->notes }}</div>
                            @endif
                        </td>
                        <td>{{ $quote->quote_date?->format('d/m/Y') }}</td>
                        <td>
                            {{ $quote->valid_until?->format('d/m/Y') ?? 'Non renseignee' }}
                            <div class="muted" style="margin-top:6px;">{{ $validityLabel }}</div>
                        </td>
                        <td>{{ $quote->customer?->name }}</td>
                        <td>{{ $quote->branch?->name }}</td>
                        <td><span class="badge {{ $statusClass }}">{{ $statusLabel }}</span></td>
                        <td>{{ number_format((float) $quote->total, 0, ',', ' ') }} XOF</td>
                        <td>
                            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                                <a href="{{ route('quotes.show', $quote) }}" class="button button-secondary">Voir</a>
                                @if ($quote->status === 'accepted')
                                    <span class="badge badge-success">Pret a convertir</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">
                            <div class="empty-state">
                                <span class="badge badge-success">Aucun devis</span>
                                <h3>La base devis est vide</h3>
                                <div class="muted">Commence par creer un devis pour alimenter la chaine commerciale amont.</div>
                                <div class="empty-actions">
                                    @allowed('quotes.manage')
                                        <a href="{{ route('quotes.create') }}" class="button button-primary">Creer un devis</a>
                                    @endallowed
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </section>
    @endif

    @if (method_exists($quotes, 'links'))
        <div style="margin-top:18px;">{{ $quotes->links() }}</div>
    @endif
@endsection
