@extends('layouts.app')

@section('title', 'Ventes - Nema ERP')
@section('page-title', 'Factures de vente')
@section('layout-mode', 'compact')

@push('page-styles')
    <style>
        .premium-page {
            display: grid;
            gap: 20px;
        }
        .sales-hero {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, rgba(255, 248, 241, 0.98) 0%, rgba(245, 251, 247, 0.96) 56%, rgba(255, 241, 226, 0.92) 100%);
            border-color: rgba(11, 79, 86, 0.12);
        }
        .sales-hero::after {
            content: "";
            position: absolute;
            width: 240px;
            height: 240px;
            right: -70px;
            top: -60px;
            border-radius: 999px;
            background: rgba(197, 106, 24, 0.14);
            filter: blur(8px);
            pointer-events: none;
        }
        .premium-hero__grid {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) minmax(280px, .95fr);
            gap: 20px;
            align-items: start;
        }
        .premium-copy {
            display: grid;
            gap: 12px;
        }
        .premium-copy h2 {
            margin: 0;
            font-size: clamp(28px, 4vw, 40px);
            line-height: 1.02;
            letter-spacing: -.04em;
        }
        .premium-copy p {
            margin: 0;
            max-width: 760px;
        }
        .premium-panel {
            border: 1px solid rgba(102, 82, 56, 0.1);
            border-radius: 20px;
            padding: 16px 18px;
            background: rgba(255, 255, 255, 0.74);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.88);
        }
        .premium-panel strong {
            display: block;
            margin-bottom: 8px;
            font-size: 16px;
        }
        .premium-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .metric-strip {
            display: grid;
            gap: 16px;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        }
        .metric-card {
            border: 1px solid rgba(102, 82, 56, 0.1);
            border-radius: 22px;
            padding: 18px;
            background: linear-gradient(180deg, rgba(255, 254, 251, 0.97) 0%, rgba(247, 239, 228, 0.94) 100%);
            box-shadow: var(--shadow-soft);
        }
        .metric-card .label {
            color: var(--muted);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: .12em;
            font-weight: 800;
        }
        .metric-card .value {
            margin-top: 10px;
            font-size: 34px;
            font-weight: 800;
            letter-spacing: -.04em;
        }
        .metric-card .hint {
            margin-top: 8px;
            color: var(--muted);
            font-size: 13px;
        }
        .premium-filter-card {
            background: linear-gradient(180deg, rgba(255, 252, 247, 0.96) 0%, rgba(244, 249, 246, 0.9) 100%);
        }
        .premium-section-head {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: flex-start;
            margin-bottom: 14px;
        }
        .premium-section-head h3 {
            margin: 0;
            font-size: 22px;
            letter-spacing: -.03em;
        }
        .premium-section-head p {
            margin: 6px 0 0;
        }
        .records-table {
            --table-cell-padding-y: 9px;
            --table-cell-padding-x: 8px;
            --row-note-size: 12px;
            --row-action-gap: 6px;
            --status-gap: 6px;
        }
        .records-table.is-detailed {
            --table-cell-padding-y: 12px;
            --table-cell-padding-x: 10px;
            --row-note-size: 13px;
            --row-action-gap: 10px;
            --status-gap: 8px;
        }
        .records-table table th,
        .records-table table td {
            padding: var(--table-cell-padding-y) var(--table-cell-padding-x);
            vertical-align: middle;
        }
        .records-table .row-note {
            margin-top: 4px;
            font-size: var(--row-note-size);
        }
        .records-table .row-actions {
            display: flex;
            gap: var(--row-action-gap);
            flex-wrap: wrap;
        }
        .records-table .row-actions form {
            display: inline;
        }
        .records-table .row-actions .button {
            padding: 7px 10px;
            border-radius: 10px;
            font-size: 13px;
        }
        .records-table .status-stack {
            display: grid;
            gap: var(--status-gap);
        }
        .records-table tbody tr:hover {
            background: rgba(15, 118, 110, 0.04);
        }
        .table-tools {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }
        .table-note {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
            color: var(--muted);
        }
        .table-note strong {
            color: var(--text);
        }
        .mode-switch {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px;
            border-radius: 16px;
            border: 1px solid rgba(102, 82, 56, 0.12);
            background: rgba(255, 255, 255, 0.82);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.92);
        }
        .mode-switch .button {
            padding: 7px 10px;
            border-radius: 10px;
            font-size: 13px;
        }
        .mode-switch .button.is-active {
            background: var(--brand);
            color: #fff;
        }
        @media (max-width: 1200px) {
            .records-table .col-optional-lg {
                display: none;
            }
        }
        @media (max-width: 980px) {
            .premium-hero__grid {
                grid-template-columns: 1fr;
            }
            .records-table .col-optional-md {
                display: none;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $currentView = $filters['view'] ?? 'list';
        $hasActiveFilters = collect($filters)->except('view')->filter(fn ($value) => filled($value))->isNotEmpty();
    @endphp

    <div class="premium-page erp-work-page">
        <section class="erp-work-toolbar">
            <div class="erp-work-toolbar__context">
                <span class="badge badge-muted">Ventes</span>
                <strong>{{ number_format($invoices->count(), 0, ',', ' ') }} facture(s) visibles</strong>
            </div>
            <div class="erp-work-toolbar__actions">
                @allowed('approvals.view')
                    <a href="{{ route('approvals.index', ['module' => 'sales']) }}" class="button button-secondary">Approbations</a>
                @endallowed
                <a href="{{ route('sales.export', request()->query()) }}" class="button button-secondary">Exporter</a>
                @allowed('sales.manage')
                    <a href="{{ route('sales.create') }}" class="button button-primary">Nouvelle facture</a>
                @endallowed
            </div>
        </section>

        <section class="metric-strip erp-kpi-strip">
            <article class="metric-card erp-kpi-card">
                <div class="label">Factures ouvertes</div>
                <div class="value">{{ number_format($summary['open_count'], 0, ',', ' ') }}</div>
                <div class="hint">Documents non totalement soldes.</div>
            </article>
            <article class="metric-card erp-kpi-card">
                <div class="label">Reste a encaisser</div>
                <div class="value">{{ number_format($summary['open_balance'], 0, ',', ' ') }}</div>
                <div class="hint">Montant encore attendu des clients.</div>
            </article>
            <article class="metric-card erp-kpi-card">
                <div class="label">En retard</div>
                <div class="value">{{ number_format($summary['overdue_balance'], 0, ',', ' ') }}</div>
                <div class="hint">Encours deja depasse en date d echeance.</div>
            </article>
            <article class="metric-card erp-kpi-card">
                <div class="label">Echeance proche</div>
                <div class="value">{{ number_format($summary['due_soon_balance'], 0, ',', ' ') }}</div>
                <div class="hint">Montants a suivre tres vite.</div>
            </article>
            <article class="metric-card erp-kpi-card">
                <div class="label">En attente d approbation</div>
                <div class="value">{{ number_format($summary['pending_approval_count'], 0, ',', ' ') }}</div>
                <div class="hint">Factures bloquees par le workflow.</div>
            </article>
        </section>

        <details class="card premium-filter-card erp-filter-panel" @if ($hasActiveFilters) open @endif>
            <summary>
                <span>Filtres ventes</span>
                <span class="muted">{{ $hasActiveFilters ? 'Filtres actifs' : 'Toutes les factures' }}</span>
            </summary>
            <div class="erp-filter-panel__body">
                <form method="GET" action="{{ route('sales.index') }}" class="form-grid" style="align-items:end; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));">
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
                    <label for="status">Workflow</label>
                    <select id="status" name="status">
                        <option value="">Tous</option>
                        <option value="validated" @selected(($filters['status'] ?? null) === 'validated')>Approuvees</option>
                        <option value="pending_approval" @selected(($filters['status'] ?? null) === 'pending_approval')>En attente</option>
                        <option value="rejected" @selected(($filters['status'] ?? null) === 'rejected')>Rejetees</option>
                        <option value="cancelled" @selected(($filters['status'] ?? null) === 'cancelled')>Annulees</option>
                    </select>
                </div>
                <div>
                    <label for="payment_status">Paiement</label>
                    <select id="payment_status" name="payment_status">
                        <option value="">Tous</option>
                        <option value="unpaid" @selected(($filters['payment_status'] ?? null) === 'unpaid')>Impayees</option>
                        <option value="partial" @selected(($filters['payment_status'] ?? null) === 'partial')>Partielles</option>
                        <option value="paid" @selected(($filters['payment_status'] ?? null) === 'paid')>Payees</option>
                    </select>
                </div>
                <div>
                    <label for="due_state">Suivi echeance</label>
                    <select id="due_state" name="due_state">
                        <option value="">Tous</option>
                        <option value="open" @selected(($filters['due_state'] ?? null) === 'open')>A encaisser</option>
                        <option value="overdue" @selected(($filters['due_state'] ?? null) === 'overdue')>En retard</option>
                        <option value="due_soon" @selected(($filters['due_state'] ?? null) === 'due_soon')>Echeance proche</option>
                        <option value="no_due" @selected(($filters['due_state'] ?? null) === 'no_due')>Sans echeance</option>
                    </select>
                </div>
                <div class="actions" style="margin-top:0; justify-content:flex-start; align-self:end;">
                    <button type="submit" class="button button-primary">Filtrer</button>
                    <a href="{{ route('sales.index', ['view' => $currentView]) }}" class="button button-secondary">Reinitialiser</a>
                </div>
                </form>
            </div>
        </details>

        <div class="table-tools">
            <div class="table-note">
                <strong>{{ number_format($invoices->count(), 0, ',', ' ') }}</strong>
                <span>facture(s) visibles sur cette page.</span>
                @if ($currentView === 'list')
                    <span>Mode d affichage memorise pour la liste des ventes.</span>
                @else
                    <span>Lecture par cartes pour prioriser les encaissements et validations.</span>
                @endif
            </div>
            <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                @include('partials.erp-view-switcher', [
                    'view' => $currentView,
                    'label' => 'Vue ventes',
                    'listUrl' => route('sales.index', array_merge(request()->query(), ['view' => 'list'])),
                    'kanbanUrl' => route('sales.index', array_merge(request()->query(), ['view' => 'kanban'])),
                ])
                @if ($currentView === 'list')
                    <div class="mode-switch" data-display-controls="sales">
                        <button type="button" class="button button-secondary is-active" data-mode="compact">Compact</button>
                        <button type="button" class="button button-secondary" data-mode="detailed">Detaille</button>
                    </div>
                @endif
            </div>
        </div>

        @if ($currentView === 'kanban')
            <div class="erp-kanban-grid">
                @forelse ($invoices as $invoice)
                    @php
                        $nextStep = $invoice->approvalSteps->firstWhere('status', 'pending');
                        $followUpLabel = 'Dans les delais';
                        $followUpTone = 'success';

                        if ($invoice->status === 'cancelled') {
                            $followUpLabel = 'Annulee';
                            $followUpTone = 'danger';
                        } elseif ($invoice->status === 'rejected') {
                            $followUpLabel = 'Rejetee';
                            $followUpTone = 'danger';
                        } elseif ($invoice->status !== 'validated') {
                            $followUpLabel = 'Workflow';
                            $followUpTone = 'warning';
                        } elseif ($invoice->payment_status === 'paid') {
                            $followUpLabel = 'A jour';
                            $followUpTone = 'success';
                        } elseif (! $invoice->due_date) {
                            $followUpLabel = 'Sans echeance';
                            $followUpTone = 'muted';
                        } elseif ($invoice->due_date->lt($today)) {
                            $followUpLabel = 'En retard';
                            $followUpTone = 'warning';
                        } elseif ($invoice->due_date->lte($soonDate)) {
                            $followUpLabel = 'Echeance proche';
                            $followUpTone = 'muted';
                        }

                        $cardTone = $followUpTone === 'danger'
                            ? 'danger'
                            : (($followUpTone === 'warning' || $followUpTone === 'muted' || $invoice->payment_status !== 'paid') ? 'warning' : 'success');
                    @endphp
                    <section class="card erp-kanban-card erp-kanban-card--{{ $cardTone }}">
                        <div class="erp-kanban-head">
                            <div class="erp-kanban-copy">
                                <div class="erp-kanban-code">{{ $invoice->invoice_number }}</div>
                                <h3>{{ $invoice->customer?->name ?? 'Client non renseigne' }}</h3>
                                <p class="muted">{{ $invoice->invoice_date?->format('d/m/Y') }} · {{ $invoice->branch?->name ?? 'Agence non renseignee' }}</p>
                            </div>
                            <div style="display:grid; gap:8px; justify-items:end;">
                                @include('partials.erp-status-badge', [
                                    'type' => 'workflow',
                                    'value' => $invoice->status,
                                ])
                                @include('partials.erp-status-badge', [
                                    'type' => 'payment',
                                    'value' => $invoice->payment_status,
                                ])
                                @include('partials.erp-status-badge', [
                                    'label' => $followUpLabel,
                                    'tone' => $followUpTone,
                                ])
                            </div>
                        </div>
                        <div class="erp-kanban-stats">
                            <div class="erp-kanban-stat">
                                <div class="label">Total</div>
                                <div class="value">{{ number_format((float) $invoice->total, 0, ',', ' ') }}</div>
                            </div>
                            <div class="erp-kanban-stat">
                                <div class="label">Reste</div>
                                <div class="value">{{ number_format((float) $invoice->balance_due, 0, ',', ' ') }}</div>
                            </div>
                            <div class="erp-kanban-stat">
                                <div class="label">Echeance</div>
                                <div class="value">{{ $invoice->due_date?->format('d/m') ?? 'N/A' }}</div>
                            </div>
                        </div>
                        <div class="erp-kanban-copy">
                            <p class="muted">{{ $invoice->warehouse?->name ?? 'Entrepot par defaut' }}</p>
                            @if ($invoice->status === 'pending_approval' && $nextStep)
                                <p class="muted">Etape suivante : {{ $nextStep->label }}</p>
                            @elseif ($invoice->status === 'rejected')
                                <p class="muted">Rejetee le {{ $invoice->rejected_at?->format('d/m/Y H:i') ?? 'N/A' }}</p>
                            @elseif ($invoice->status === 'cancelled')
                                <p class="muted">Annulee le {{ $invoice->cancelled_at?->format('d/m/Y H:i') ?? 'N/A' }}</p>
                            @elseif ($invoice->notes)
                                <p class="muted">{{ $invoice->notes }}</p>
                            @else
                                <p class="muted">{{ $followUpLabel }} · suivi commercial en cours.</p>
                            @endif
                        </div>
                        <div class="erp-kanban-actions">
                            <a href="{{ route('sales.show', $invoice) }}" class="button button-secondary">Voir la facture</a>
                            @if ($invoice->status === 'pending_approval')
                                @allowed('sales.approve')
                                    <form method="POST" action="{{ route('sales.approve', $invoice) }}">
                                        @csrf
                                        <button type="submit" class="button button-primary">Approuver</button>
                                    </form>
                                @endallowed
                                @allowed('sales.cancel')
                                    <form method="POST" action="{{ route('sales.cancel', $invoice) }}">
                                        @csrf
                                        <button type="submit" class="button button-secondary">Annuler</button>
                                    </form>
                                @endallowed
                            @elseif ($invoice->status === 'validated')
                                @if ($invoice->payment_status !== 'paid')
                                    @allowed('payments.manage')
                                        <a href="{{ route('payments.create', ['invoice' => $invoice->id]) }}" class="button button-primary">Encaisser</a>
                                    @endallowed
                                @endif
                                @allowed('credit_notes.issue')
                                    @if ((float) $invoice->balance_due > 0)
                                        <a href="{{ route('credit-notes.create', $invoice) }}" class="button button-secondary">Avoir</a>
                                    @endif
                                @endallowed
                            @endif
                        </div>
                    </section>
                @empty
                    <section class="card empty-state" style="grid-column:1 / -1;">
                        <h3>Aucune facture de vente ne correspond aux filtres selectionnes.</h3>
                        <p class="muted">Ajuste la recherche, le workflow, le paiement ou l echeance.</p>
                    </section>
                @endforelse
            </div>

            @if (method_exists($invoices, 'links'))
                <div style="margin-top:18px;">{{ $invoices->links() }}</div>
            @endif
        @else
            <section class="card table-wrap records-table is-compact" data-display-table="sales">
                <table>
                    <thead>
                    <tr>
                        <th>Numero</th>
                        <th>Date</th>
                        <th class="col-optional-md">Echeance</th>
                        <th>Client</th>
                        <th class="col-optional-lg">Agence</th>
                        <th>Workflow</th>
                        <th>Total</th>
                        <th>Reste</th>
                        <th>Suivi</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($invoices as $invoice)
                        @php
                            $nextStep = $invoice->approvalSteps->firstWhere('status', 'pending');
                            $followUpLabel = 'Dans les delais';
                            $followUpTone = 'success';

                            if ($invoice->status === 'cancelled') {
                                $followUpLabel = 'Annulee';
                                $followUpTone = 'danger';
                            } elseif ($invoice->status === 'rejected') {
                                $followUpLabel = 'Rejetee';
                                $followUpTone = 'danger';
                            } elseif ($invoice->status !== 'validated') {
                                $followUpLabel = 'Workflow';
                                $followUpTone = 'warning';
                            } elseif ($invoice->payment_status === 'paid') {
                                $followUpLabel = 'A jour';
                                $followUpTone = 'success';
                            } elseif (! $invoice->due_date) {
                                $followUpLabel = 'Sans echeance';
                                $followUpTone = 'muted';
                            } elseif ($invoice->due_date->lt($today)) {
                                $followUpLabel = 'En retard';
                                $followUpTone = 'warning';
                            } elseif ($invoice->due_date->lte($soonDate)) {
                                $followUpLabel = 'Echeance proche';
                                $followUpTone = 'muted';
                            }
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $invoice->invoice_number }}</strong>
                                @if ($invoice->notes)
                                    <div class="muted row-note">{{ $invoice->notes }}</div>
                                @endif
                            </td>
                            <td>{{ $invoice->invoice_date?->format('d/m/Y') }}</td>
                            <td class="col-optional-md">{{ $invoice->due_date?->format('d/m/Y') ?? 'Non renseignee' }}</td>
                            <td>{{ $invoice->customer?->name }}</td>
                            <td class="col-optional-lg">{{ $invoice->branch?->name }}</td>
                            <td>
                                @include('partials.erp-status-badge', [
                                    'type' => 'workflow',
                                    'value' => $invoice->status,
                                ])
                                @if ($invoice->status === 'pending_approval' && $nextStep)
                                    <div class="muted row-note">Etape : {{ $nextStep->label }}</div>
                                @elseif ($invoice->status === 'rejected')
                                    <div class="muted row-note">Rejetee le {{ $invoice->rejected_at?->format('d/m/Y H:i') ?? 'N/A' }}</div>
                                @elseif ($invoice->status === 'cancelled')
                                    <div class="muted row-note">Annulee le {{ $invoice->cancelled_at?->format('d/m/Y H:i') ?? 'N/A' }}</div>
                                @endif
                            </td>
                            <td>{{ number_format((float) $invoice->total, 0, ',', ' ') }} XOF</td>
                            <td>{{ number_format((float) $invoice->balance_due, 0, ',', ' ') }} XOF</td>
                            <td>
                                <div class="status-stack">
                                    @include('partials.erp-status-badge', [
                                        'type' => 'payment',
                                        'value' => $invoice->payment_status,
                                    ])
                                    @include('partials.erp-status-badge', [
                                        'label' => $followUpLabel,
                                        'tone' => $followUpTone,
                                    ])
                                </div>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a href="{{ route('sales.show', $invoice) }}" class="button button-secondary">Voir</a>
                                    @if ($invoice->status === 'pending_approval')
                                        @allowed('sales.approve')
                                            <form method="POST" action="{{ route('sales.approve', $invoice) }}">
                                                @csrf
                                                <button type="submit" class="button button-primary">Approuver</button>
                                            </form>
                                        @endallowed
                                        @allowed('sales.cancel')
                                            <form method="POST" action="{{ route('sales.cancel', $invoice) }}">
                                                @csrf
                                                <button type="submit" class="button button-secondary">Annuler</button>
                                            </form>
                                        @endallowed
                                    @elseif ($invoice->status === 'validated')
                                        @if ($invoice->payment_status !== 'paid')
                                            @allowed('payments.manage')
                                                <a href="{{ route('payments.create', ['invoice' => $invoice->id]) }}" class="button button-primary">Encaisser</a>
                                            @endallowed
                                        @endif
                                        @allowed('credit_notes.issue')
                                            @if ((float) $invoice->balance_due > 0)
                                                <a href="{{ route('credit-notes.create', $invoice) }}" class="button button-secondary">Avoir</a>
                                            @endif
                                        @endallowed
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="muted">Aucune facture de vente ne correspond aux filtres selectionnes.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>

                @if (method_exists($invoices, 'links'))
                    <div style="margin-top:18px;">{{ $invoices->links() }}</div>
                @endif
            </section>
        @endif
    </div>

    <script>
        (() => {
            const storageKey = 'nema.sales.display_mode';
            const table = document.querySelector('[data-display-table="sales"]');
            const controls = document.querySelector('[data-display-controls="sales"]');
            const buttons = controls ? Array.from(controls.querySelectorAll('[data-mode]')) : [];

            if (!table || !buttons.length) {
                return;
            }

            const applyMode = (mode) => {
                const nextMode = mode === 'detailed' ? 'detailed' : 'compact';
                table.classList.remove('is-compact', 'is-detailed');
                table.classList.add(nextMode === 'detailed' ? 'is-detailed' : 'is-compact');
                buttons.forEach((button) => {
                    button.classList.toggle('is-active', button.dataset.mode === nextMode);
                });
            };

            applyMode(localStorage.getItem(storageKey) || 'compact');

            buttons.forEach((button) => {
                button.addEventListener('click', () => {
                    const mode = button.dataset.mode === 'detailed' ? 'detailed' : 'compact';
                    localStorage.setItem(storageKey, mode);
                    applyMode(mode);
                });
            });
        })();
    </script>
@endsection
