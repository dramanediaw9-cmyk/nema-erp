@extends('layouts.app')

@section('title', 'Commandes clients - Nema ERP')
@section('page-title', 'Commandes clients')

@section('content')
    @php
        $currentView = $filters['view'] ?? 'list';
    @endphp

    <div class="page-head">
        <div>
            <h2 style="margin:0;">Engagements clients</h2>
            <div class="muted">Gere les commandes confirmees avant facturation et prepare la suite du cycle commercial.</div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="{{ route('orders.export', request()->query()) }}" class="button button-secondary">Exporter CSV</a>
            @allowed('orders.manage')
                <a href="{{ route('orders.create') }}" class="button button-primary">Nouvelle commande</a>
            @endallowed
        </div>
    </div>

    <div class="grid stats-grid" style="margin-bottom:20px;">
        <div class="card"><div class="muted">Brouillons</div><div class="stat-value">{{ $summary['draft'] }}</div></div>
        <div class="card"><div class="muted">Confirmees</div><div class="stat-value">{{ $summary['confirmed'] }}</div></div>
        <div class="card"><div class="muted">Partielles</div><div class="stat-value">{{ $summary['partial_delivered'] }}</div></div>
        <div class="card"><div class="muted">Livrees</div><div class="stat-value">{{ $summary['delivered'] }}</div></div>
        <div class="card"><div class="muted">Converties</div><div class="stat-value">{{ $summary['converted'] }}</div></div>
        <div class="card"><div class="muted">Annulees</div><div class="stat-value">{{ $summary['cancelled'] }}</div></div>
    </div>

    <section class="card" style="margin-bottom:18px;">
        <form method="GET" action="{{ route('orders.index') }}" class="form-grid" style="align-items:end; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));">
            <input type="hidden" name="view" value="{{ $currentView }}">
            <div style="grid-column:span 2; min-width:220px;">
                <label for="search">Recherche</label>
                <input type="text" id="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Numero, client, ref client, source, commercial...">
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
                    <option value="confirmed" @selected(($filters['status'] ?? null) === 'confirmed')>Confirmee</option>
                    <option value="partial_delivered" @selected(($filters['status'] ?? null) === 'partial_delivered')>Partiellement livree</option>
                    <option value="delivered" @selected(($filters['status'] ?? null) === 'delivered')>Livree</option>
                    <option value="converted" @selected(($filters['status'] ?? null) === 'converted')>Convertie</option>
                    <option value="cancelled" @selected(($filters['status'] ?? null) === 'cancelled')>Annulee</option>
                </select>
            </div>
            <div>
                <label for="coverage_state">Promesse stock</label>
                <select id="coverage_state" name="coverage_state">
                    <option value="">Toutes</option>
                    <option value="at_risk" @selected(($filters['coverage_state'] ?? null) === 'at_risk')>A risque</option>
                    <option value="incoming" @selected(($filters['coverage_state'] ?? null) === 'incoming')>Couverte par appro</option>
                    <option value="covered_now" @selected(($filters['coverage_state'] ?? null) === 'covered_now')>Couverte maintenant</option>
                </select>
            </div>
            <div>
                <label for="delivery_focus">Reliquat</label>
                <select id="delivery_focus" name="delivery_focus">
                    <option value="">Tous</option>
                    <option value="remaining" @selected(($filters['delivery_focus'] ?? null) === 'remaining')>Reste a servir</option>
                    <option value="overdue" @selected(($filters['delivery_focus'] ?? null) === 'overdue')>En retard</option>
                </select>
            </div>
            <div class="actions" style="margin-top:0; justify-content:flex-start; align-self:end;">
                <button type="submit" class="button button-primary">Filtrer</button>
                <a href="{{ route('orders.index', ['view' => $currentView]) }}" class="button button-secondary">Reinitialiser</a>
            </div>
        </form>
    </section>

    <div style="display:flex; justify-content:space-between; gap:16px; align-items:center; flex-wrap:wrap; margin-bottom:18px;">
        <div class="muted">Passe d une lecture analytique a une lecture visuelle selon le niveau de pilotage souhaite.</div>
        @include('partials.erp-view-switcher', [
            'view' => $currentView,
            'label' => 'Vue commandes',
            'listUrl' => route('orders.index', array_merge(request()->query(), ['view' => 'list'])),
            'kanbanUrl' => route('orders.index', array_merge(request()->query(), ['view' => 'kanban'])),
        ])
    </div>

    @if ($currentView === 'kanban')
        <div class="erp-kanban-grid">
            @forelse ($orders as $order)
                @php
                    $coverageSnapshot = $orderCoverageSnapshots[$order->id] ?? null;
                    $statusLabel = match ($order->status) {
                        'draft' => 'Brouillon',
                        'confirmed' => 'Confirmee',
                        'partial_delivered' => 'Partiellement livree',
                        'delivered' => 'Livree',
                        'converted' => 'Convertie',
                        'cancelled' => 'Annulee',
                        default => $order->status,
                    };
                    $statusTone = match ($order->status) {
                        'confirmed', 'delivered', 'converted' => 'success',
                        'partial_delivered' => 'warning',
                        'cancelled' => 'muted',
                        default => 'muted',
                    };
                    $deliveryLabel = 'Sans date';
                    $deliveryTone = 'muted';
                    if ($order->status === 'converted') {
                        $deliveryLabel = 'Facturee';
                        $deliveryTone = 'success';
                    } elseif ($order->requested_delivery_date && $order->requested_delivery_date->lt($today)) {
                        $deliveryLabel = 'A traiter';
                        $deliveryTone = 'danger';
                    } elseif ($order->requested_delivery_date && $order->requested_delivery_date->lte($soonDate)) {
                        $deliveryLabel = 'Echeance proche';
                        $deliveryTone = 'warning';
                    } elseif ($order->requested_delivery_date) {
                        $deliveryLabel = 'Planifiee';
                        $deliveryTone = 'success';
                    }
                    $orderedQty = (float) $order->items->sum('qty');
                    $deliveredQty = (float) $order->items->sum('delivered_qty');
                    $reservedQty = in_array($order->status, ['confirmed', 'partial_delivered'], true)
                        ? (float) $order->items->sum(fn ($item) => $item->remainingQty())
                        : 0;
                    $progress = $orderedQty > 0 ? round(($deliveredQty / $orderedQty) * 100, 1) : 0;
                    $cardTone = $coverageSnapshot
                        ? ($coverageSnapshot['is_overdue'] ? 'danger' : (($coverageSnapshot['summary']['at_risk'] ?? 0) > 0 ? 'warning' : 'success'))
                        : ($deliveryTone === 'danger' ? 'danger' : 'warning');
                @endphp
                <section class="card erp-kanban-card erp-kanban-card--{{ $cardTone }}">
                    <div class="erp-kanban-head">
                        <div class="erp-kanban-copy">
                            <div class="erp-kanban-code">{{ $order->order_number }}</div>
                            <h3>{{ $order->customer?->name ?? 'Client non renseigne' }}</h3>
                            <p class="muted">{{ $order->order_date?->format('d/m/Y') }} · {{ $order->branch?->name ?? 'Agence non renseignee' }}</p>
                        </div>
                        <div style="display:grid; gap:8px; justify-items:end;">
                            @include('partials.erp-status-badge', ['label' => $statusLabel, 'tone' => $statusTone])
                            @include('partials.erp-status-badge', ['label' => $deliveryLabel, 'tone' => $deliveryTone])
                        </div>
                    </div>
                    <div class="erp-kanban-stats">
                        <div class="erp-kanban-stat">
                            <div class="label">Progression</div>
                            <div class="value">{{ number_format($progress, 1, ',', ' ') }}%</div>
                        </div>
                        <div class="erp-kanban-stat">
                            <div class="label">Reserve</div>
                            <div class="value">{{ number_format($reservedQty, 0, ',', ' ') }}</div>
                        </div>
                        <div class="erp-kanban-stat">
                            <div class="label">Total</div>
                            <div class="value">{{ number_format((float) $order->total, 0, ',', ' ') }}</div>
                        </div>
                    </div>
                    <div class="erp-kanban-copy">
                        <p class="muted">{{ $order->warehouse?->name ?? 'Depot principal' }}</p>
                        @if ($order->salesperson_name)
                            <p class="muted">Commercial : {{ $order->salesperson_name }}</p>
                        @endif
                        @if ($coverageSnapshot)
                            <p class="muted">
                                @if (($coverageSnapshot['summary']['at_risk'] ?? 0) > 0)
                                    {{ $coverageSnapshot['summary']['at_risk'] }} ligne(s) a risque
                                @elseif (($coverageSnapshot['summary']['covered_incoming'] ?? 0) > 0)
                                    {{ $coverageSnapshot['summary']['covered_incoming'] }} ligne(s) attendent un appro
                                @else
                                    {{ $coverageSnapshot['promise_label'] }}
                                @endif
                            </p>
                            @if ($coverageSnapshot['is_overdue'] && $coverageSnapshot['target_date'])
                                <p class="muted">Engagement depasse depuis le {{ $coverageSnapshot['target_date']->format('d/m/Y') }}</p>
                            @endif
                        @elseif ($order->customer_reference)
                            <p class="muted">Ref client : {{ $order->customer_reference }}</p>
                        @elseif ($order->notes)
                            <p class="muted">{{ $order->notes }}</p>
                        @endif
                    </div>
                    <div class="erp-kanban-actions">
                        <a href="{{ route('orders.show', $order) }}" class="button button-secondary">Voir la commande</a>
                        @if (in_array($order->status, ['confirmed', 'partial_delivered'], true) && $deliveredQty < $orderedQty)
                            <span class="badge badge-warning">Livraison en cours</span>
                        @elseif ($order->status === 'delivered')
                            <span class="badge badge-success">Tout livre</span>
                        @endif
                    </div>
                </section>
            @empty
                <section class="card empty-state" style="grid-column:1 / -1;">
                    <span class="badge badge-success">Aucune commande</span>
                    <h3>La base commandes est vide</h3>
                    <div class="muted">Commence par creer une commande client pour preparer la facturation.</div>
                    <div class="empty-actions">
                        @allowed('orders.manage')
                            <a href="{{ route('orders.create') }}" class="button button-primary">Creer une commande</a>
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
                    <th>Livraison</th>
                    <th>Client</th>
                    <th>Agence</th>
                    <th>Depot</th>
                    <th>Statut</th>
                    <th>Progression</th>
                    <th>Reserve</th>
                    <th>Promesse</th>
                    <th>Total</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($orders as $order)
                    @php
                        $coverageSnapshot = $orderCoverageSnapshots[$order->id] ?? null;
                        $statusLabel = match ($order->status) {
                            'draft' => 'Brouillon',
                            'confirmed' => 'Confirmee',
                            'partial_delivered' => 'Partiellement livree',
                            'delivered' => 'Livree',
                            'converted' => 'Convertie',
                            'cancelled' => 'Annulee',
                            default => $order->status,
                        };
                        $statusClass = match ($order->status) {
                            'confirmed', 'delivered', 'converted' => 'badge-success',
                            'partial_delivered' => 'badge-warning',
                            'cancelled' => 'badge-muted',
                            default => 'badge-muted',
                        };
                        $deliveryLabel = 'Sans date';
                        if ($order->status === 'converted') {
                            $deliveryLabel = 'Facturee';
                        } elseif ($order->requested_delivery_date && $order->requested_delivery_date->lt($today)) {
                            $deliveryLabel = 'A traiter';
                        } elseif ($order->requested_delivery_date && $order->requested_delivery_date->lte($soonDate)) {
                            $deliveryLabel = 'Echeance proche';
                        } elseif ($order->requested_delivery_date) {
                            $deliveryLabel = 'Planifiee';
                        }
                        $orderedQty = (float) $order->items->sum('qty');
                        $deliveredQty = (float) $order->items->sum('delivered_qty');
                        $reservedQty = in_array($order->status, ['confirmed', 'partial_delivered'], true)
                            ? (float) $order->items->sum(fn ($item) => $item->remainingQty())
                            : 0;
                        $progress = $orderedQty > 0 ? round(($deliveredQty / $orderedQty) * 100, 1) : 0;
                    @endphp
                    <tr>
                        <td>
                            <strong>{{ $order->order_number }}</strong>
                            @if ($order->customer_reference)
                                <div class="muted" style="font-size:13px;">Ref client : {{ $order->customer_reference }}</div>
                            @endif
                            @if ($order->source_document)
                                <div class="muted" style="font-size:13px;">Source : {{ $order->source_document }}</div>
                            @endif
                            @if ($order->notes)
                                <div class="muted" style="font-size:13px;">{{ $order->notes }}</div>
                            @endif
                        </td>
                        <td>
                            {{ $order->order_date?->format('d/m/Y') }}
                            @if ($order->commitment_date)
                                <div class="muted" style="margin-top:6px;">Engagement : {{ $order->commitment_date->format('d/m/Y') }}</div>
                            @endif
                        </td>
                        <td>
                            {{ $order->requested_delivery_date?->format('d/m/Y') ?? 'Non renseignee' }}
                            <div class="muted" style="margin-top:6px;">{{ $deliveryLabel }}</div>
                        </td>
                        <td>
                            {{ $order->customer?->name }}
                            @if ($order->salesperson_name)
                                <div class="muted" style="margin-top:6px;">Commercial : {{ $order->salesperson_name }}</div>
                            @endif
                        </td>
                        <td>{{ $order->branch?->name }}</td>
                        <td>{{ $order->warehouse?->name ?? 'Depot principal' }}</td>
                        <td><span class="badge {{ $statusClass }}">{{ $statusLabel }}</span></td>
                        <td>
                            <div>{{ number_format($progress, 1, ',', ' ') }} %</div>
                            <div class="muted" style="margin-top:6px;">{{ number_format($deliveredQty, 3, ',', ' ') }} / {{ number_format($orderedQty, 3, ',', ' ') }}</div>
                        </td>
                        <td>
                            {{ number_format($reservedQty, 3, ',', ' ') }}
                            <div class="muted" style="margin-top:6px;">Stock promis</div>
                        </td>
                        <td>
                            @if ($coverageSnapshot)
                                <span class="badge {{ $coverageSnapshot['promise_tone'] }}">{{ $coverageSnapshot['promise_label'] }}</span>
                                <div class="muted" style="margin-top:6px;">
                                    @if (($coverageSnapshot['summary']['at_risk'] ?? 0) > 0)
                                        {{ $coverageSnapshot['summary']['at_risk'] }} ligne(s) a risque
                                    @elseif (($coverageSnapshot['summary']['covered_incoming'] ?? 0) > 0)
                                        {{ $coverageSnapshot['summary']['covered_incoming'] }} ligne(s) attendent un appro
                                    @else
                                        Reste {{ number_format((float) ($coverageSnapshot['remaining_qty'] ?? 0), 3, ',', ' ') }}
                                    @endif
                                </div>
                                @if ($coverageSnapshot['is_overdue'] && $coverageSnapshot['target_date'])
                                    <div class="muted" style="margin-top:6px;">Engagement depasse depuis le {{ $coverageSnapshot['target_date']->format('d/m/Y') }}</div>
                                @endif
                            @else
                                <span class="badge badge-muted">Lecture indisponible</span>
                            @endif
                        </td>
                        <td>{{ number_format((float) $order->total, 0, ',', ' ') }} XOF</td>
                        <td>
                            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                                <a href="{{ route('orders.show', $order) }}" class="button button-secondary">Voir</a>
                                @if (in_array($order->status, ['confirmed', 'partial_delivered'], true) && $deliveredQty < $orderedQty)
                                    <span class="badge badge-warning">Livraison en cours</span>
                                @elseif ($order->status === 'delivered')
                                    <span class="badge badge-success">Tout livre</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="12">
                            <div class="empty-state">
                                <span class="badge badge-success">Aucune commande</span>
                                <h3>La base commandes est vide</h3>
                                <div class="muted">Commence par creer une commande client pour preparer la facturation.</div>
                                <div class="empty-actions">
                                    @allowed('orders.manage')
                                        <a href="{{ route('orders.create') }}" class="button button-primary">Creer une commande</a>
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

    @if (method_exists($orders, 'links'))
        <div style="margin-top:18px;">{{ $orders->links() }}</div>
    @endif
@endsection
