@extends('layouts.app')

@section('title', 'Achats - Nema ERP')
@section('page-title', 'Factures fournisseurs')

@push('page-styles')
    <style>
        .premium-page {
            display: grid;
            gap: 20px;
        }
        .purchases-hero {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, rgba(248, 251, 248, 0.98) 0%, rgba(240, 248, 246, 0.96) 55%, rgba(255, 245, 230, 0.9) 100%);
            border-color: rgba(15, 118, 110, 0.14);
        }
        .purchases-hero::after {
            content: "";
            position: absolute;
            width: 240px;
            height: 240px;
            right: -70px;
            top: -60px;
            border-radius: 999px;
            background: rgba(15, 118, 110, 0.12);
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
            background: linear-gradient(180deg, rgba(255, 254, 251, 0.97) 0%, rgba(239, 246, 243, 0.94) 100%);
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
            background: linear-gradient(180deg, rgba(255, 252, 247, 0.96) 0%, rgba(241, 247, 245, 0.9) 100%);
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
    @endphp

    <div class="premium-page">
        <section class="card purchases-hero">
            <div class="premium-hero__grid">
                <div class="premium-copy">
                    <div class="badge badge-muted">Approvisionnements</div>
                    <h2>Les achats et dettes fournisseurs gagnent en clarte et en priorisation.</h2>
                    <p class="muted">Une facture fournisseur approuvee augmente le stock et alimente le suivi des montants restant a regler. L ecran met maintenant davantage en avant les urgences et les prochaines actions.</p>
                    <div class="premium-actions">
                        @allowed('approvals.view')
                            <a href="{{ route('approvals.index', ['module' => 'purchases']) }}" class="button button-secondary">Approvals achats</a>
                        @endallowed
                        <a href="{{ route('purchases.export', request()->query()) }}" class="button button-secondary">Exporter CSV</a>
                        @allowed('purchases.manage')
                            <a href="{{ route('purchases.create') }}" class="button button-primary">Nouvel achat</a>
                        @endallowed
                    </div>
                </div>
                <div class="premium-panel">
                    <strong>Lecture acheteur</strong>
                    <p class="muted">Voir tout de suite ce qui doit etre regle, ce qui est en retard et ce qui est encore bloque par validation avant de descendre dans les pieces une par une.</p>
                </div>
            </div>
        </section>

        <section class="metric-strip">
            <article class="metric-card">
                <div class="label">Factures ouvertes</div>
                <div class="value">{{ number_format($summary['open_count'], 0, ',', ' ') }}</div>
                <div class="hint">Documents non totalement regles.</div>
            </article>
            <article class="metric-card">
                <div class="label">Reste a regler</div>
                <div class="value">{{ number_format($summary['open_balance'], 0, ',', ' ') }}</div>
                <div class="hint">Montant encore attendu par les fournisseurs.</div>
            </article>
            <article class="metric-card">
                <div class="label">En retard</div>
                <div class="value">{{ number_format($summary['overdue_balance'], 0, ',', ' ') }}</div>
                <div class="hint">Dettes deja depassees en echeance.</div>
            </article>
            <article class="metric-card">
                <div class="label">Echeance proche</div>
                <div class="value">{{ number_format($summary['due_soon_balance'], 0, ',', ' ') }}</div>
                <div class="hint">Montants a suivre rapidement.</div>
            </article>
            <article class="metric-card">
                <div class="label">En attente d approbation</div>
                <div class="value">{{ number_format($summary['pending_approval_count'], 0, ',', ' ') }}</div>
                <div class="hint">Achats encore bloques par le workflow.</div>
            </article>
        </section>

        <section class="card premium-filter-card">
            <div class="premium-section-head">
                <div>
                    <h3>Filtres achats</h3>
                    <p class="muted">Recherche rapide par numero, fournisseur, agence, workflow, paiement ou echeance.</p>
                </div>
            </div>
            <form method="GET" action="{{ route('purchases.index') }}" class="form-grid" style="align-items:end; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));">
                <input type="hidden" name="view" value="{{ $currentView }}">
                <div style="grid-column:span 2; min-width:220px;">
                    <label for="search">Recherche</label>
                    <input type="text" id="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Numero, fournisseur, agence, note...">
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
                        <option value="open" @selected(($filters['due_state'] ?? null) === 'open')>A regler</option>
                        <option value="overdue" @selected(($filters['due_state'] ?? null) === 'overdue')>En retard</option>
                        <option value="due_soon" @selected(($filters['due_state'] ?? null) === 'due_soon')>Echeance proche</option>
                        <option value="no_due" @selected(($filters['due_state'] ?? null) === 'no_due')>Sans echeance</option>
                    </select>
                </div>
                <div class="actions" style="margin-top:0; justify-content:flex-start; align-self:end;">
                    <button type="submit" class="button button-primary">Filtrer</button>
                    <a href="{{ route('purchases.index', ['view' => $currentView]) }}" class="button button-secondary">Reinitialiser</a>
                </div>
            </form>
        </section>

        <div class="table-tools">
            <div class="table-note">
                <strong>{{ number_format($bills->count(), 0, ',', ' ') }}</strong>
                <span>facture(s) visibles sur cette page.</span>
                @if ($currentView === 'list')
                    <span>Mode d affichage memorise pour la liste des achats.</span>
                @else
                    <span>Lecture par cartes pour prioriser reglements et validations.</span>
                @endif
            </div>
            <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                @include('partials.erp-view-switcher', [
                    'view' => $currentView,
                    'label' => 'Vue achats',
                    'listUrl' => route('purchases.index', array_merge(request()->query(), ['view' => 'list'])),
                    'kanbanUrl' => route('purchases.index', array_merge(request()->query(), ['view' => 'kanban'])),
                ])
                @if ($currentView === 'list')
                    <div class="mode-switch" data-display-controls="purchases">
                        <button type="button" class="button button-secondary is-active" data-mode="compact">Compact</button>
                        <button type="button" class="button button-secondary" data-mode="detailed">Detaille</button>
                    </div>
                @endif
            </div>
        </div>

        @if ($currentView === 'kanban')
            <div class="erp-kanban-grid">
                @forelse ($bills as $bill)
                    @php
                        $nextStep = $bill->approvalSteps->firstWhere('status', 'pending');
                        $followUpLabel = 'Dans les delais';
                        $followUpTone = 'success';

                        if ($bill->status === 'rejected') {
                            $followUpLabel = 'Rejetee';
                            $followUpTone = 'danger';
                        } elseif ($bill->status !== 'validated') {
                            $followUpLabel = 'Workflow';
                            $followUpTone = 'warning';
                        } elseif ($bill->payment_status === 'paid') {
                            $followUpLabel = 'A jour';
                            $followUpTone = 'success';
                        } elseif (! $bill->due_date) {
                            $followUpLabel = 'Sans echeance';
                            $followUpTone = 'muted';
                        } elseif ($bill->due_date->lt($today)) {
                            $followUpLabel = 'En retard';
                            $followUpTone = 'warning';
                        } elseif ($bill->due_date->lte($soonDate)) {
                            $followUpLabel = 'Echeance proche';
                            $followUpTone = 'muted';
                        }

                        $cardTone = $followUpTone === 'danger'
                            ? 'danger'
                            : (($followUpTone === 'warning' || $followUpTone === 'muted' || $bill->payment_status !== 'paid') ? 'warning' : 'success');
                    @endphp
                    <section class="card erp-kanban-card erp-kanban-card--{{ $cardTone }}">
                        <div class="erp-kanban-head">
                            <div class="erp-kanban-copy">
                                <div class="erp-kanban-code">{{ $bill->bill_number }}</div>
                                <h3>{{ $bill->supplier?->name ?? 'Fournisseur non renseigne' }}</h3>
                                <p class="muted">{{ $bill->bill_date?->format('d/m/Y') }} · {{ $bill->branch?->name ?? 'Agence non renseignee' }}</p>
                            </div>
                            <div style="display:grid; gap:8px; justify-items:end;">
                                @include('partials.erp-status-badge', [
                                    'type' => 'workflow',
                                    'value' => $bill->status,
                                ])
                                @include('partials.erp-status-badge', [
                                    'type' => 'payment',
                                    'value' => $bill->payment_status,
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
                                <div class="value">{{ number_format((float) $bill->total, 0, ',', ' ') }}</div>
                            </div>
                            <div class="erp-kanban-stat">
                                <div class="label">Reste</div>
                                <div class="value">{{ number_format((float) $bill->balance_due, 0, ',', ' ') }}</div>
                            </div>
                            <div class="erp-kanban-stat">
                                <div class="label">Echeance</div>
                                <div class="value">{{ $bill->due_date?->format('d/m') ?? 'N/A' }}</div>
                            </div>
                        </div>
                        <div class="erp-kanban-copy">
                            <p class="muted">{{ $bill->warehouse?->name ?? 'Depot non renseigne' }}</p>
                            @if ($bill->purchaseOrder || $bill->goodsReceipt)
                                <p class="muted">{{ collect([$bill->purchaseOrder?->order_number, $bill->goodsReceipt?->receipt_number])->filter()->implode(' · ') }}</p>
                            @endif
                            @if ($bill->status === 'pending_approval' && $nextStep)
                                <p class="muted">Etape suivante : {{ $nextStep->label }}</p>
                            @elseif ($bill->status === 'rejected')
                                <p class="muted">Rejetee le {{ $bill->rejected_at?->format('d/m/Y H:i') ?? 'N/A' }}</p>
                            @elseif ($bill->notes)
                                <p class="muted">{{ $bill->notes }}</p>
                            @else
                                <p class="muted">{{ $followUpLabel }} · suivi fournisseur en cours.</p>
                            @endif
                        </div>
                        <div class="erp-kanban-actions">
                            <a href="{{ route('purchases.show', $bill) }}" class="button button-secondary">Voir la facture</a>
                            @if ($bill->status === 'pending_approval')
                                @allowed('purchases.approve')
                                    <form method="POST" action="{{ route('purchases.approve', $bill) }}">
                                        @csrf
                                        <button type="submit" class="button button-primary">Approuver</button>
                                    </form>
                                @endallowed
                            @elseif ($bill->status === 'validated' && $bill->payment_status !== 'paid')
                                @allowed('payments.manage')
                                    <a href="{{ route('payments.create', ['type' => 'supplier_payment', 'purchase_bill' => $bill->id]) }}" class="button button-primary">Regler</a>
                                @endallowed
                                @allowed('supplier_credit_notes.issue')
                                    <a href="{{ route('purchase-credit-notes.create', $bill) }}" class="button button-secondary">Avoir</a>
                                @endallowed
                            @endif
                        </div>
                    </section>
                @empty
                    <section class="card empty-state" style="grid-column:1 / -1;">
                        <h3>Aucune facture fournisseur ne correspond aux filtres selectionnes.</h3>
                        <p class="muted">Ajuste la recherche, le workflow, le paiement ou l echeance.</p>
                    </section>
                @endforelse
            </div>

            @if (method_exists($bills, 'links'))
                <div style="margin-top:18px;">{{ $bills->links() }}</div>
            @endif
        @else
            <section class="card table-wrap records-table is-compact" data-display-table="purchases">
                <table>
                    <thead>
                    <tr>
                        <th>Numero</th>
                        <th>Date</th>
                        <th class="col-optional-md">Echeance</th>
                        <th>Fournisseur</th>
                        <th class="col-optional-lg">Agence</th>
                        <th>Workflow</th>
                        <th>Total</th>
                        <th>Reste</th>
                        <th>Suivi</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($bills as $bill)
                        @php
                            $nextStep = $bill->approvalSteps->firstWhere('status', 'pending');
                            $followUpLabel = 'Dans les delais';
                            $followUpTone = 'success';

                            if ($bill->status === 'rejected') {
                                $followUpLabel = 'Rejetee';
                                $followUpTone = 'danger';
                            } elseif ($bill->status !== 'validated') {
                                $followUpLabel = 'Workflow';
                                $followUpTone = 'warning';
                            } elseif ($bill->payment_status === 'paid') {
                                $followUpLabel = 'A jour';
                                $followUpTone = 'success';
                            } elseif (! $bill->due_date) {
                                $followUpLabel = 'Sans echeance';
                                $followUpTone = 'muted';
                            } elseif ($bill->due_date->lt($today)) {
                                $followUpLabel = 'En retard';
                                $followUpTone = 'warning';
                            } elseif ($bill->due_date->lte($soonDate)) {
                                $followUpLabel = 'Echeance proche';
                                $followUpTone = 'muted';
                            }
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $bill->bill_number }}</strong>
                                @if ($bill->notes)
                                    <div class="muted row-note">{{ $bill->notes }}</div>
                                @endif
                            </td>
                            <td>{{ $bill->bill_date?->format('d/m/Y') }}</td>
                            <td class="col-optional-md">{{ $bill->due_date?->format('d/m/Y') ?? 'Non renseignee' }}</td>
                            <td>{{ $bill->supplier?->name }}</td>
                            <td class="col-optional-lg">{{ $bill->branch?->name }}</td>
                            <td>
                                @include('partials.erp-status-badge', [
                                    'type' => 'workflow',
                                    'value' => $bill->status,
                                ])
                                @if ($bill->status === 'pending_approval' && $nextStep)
                                    <div class="muted row-note">Etape : {{ $nextStep->label }}</div>
                                @elseif ($bill->status === 'rejected')
                                    <div class="muted row-note">Rejetee le {{ $bill->rejected_at?->format('d/m/Y H:i') ?? 'N/A' }}</div>
                                @endif
                            </td>
                            <td>{{ number_format((float) $bill->total, 0, ',', ' ') }} XOF</td>
                            <td>{{ number_format((float) $bill->balance_due, 0, ',', ' ') }} XOF</td>
                            <td>
                                <div class="status-stack">
                                    @include('partials.erp-status-badge', [
                                        'type' => 'payment',
                                        'value' => $bill->payment_status,
                                    ])
                                    @include('partials.erp-status-badge', [
                                        'label' => $followUpLabel,
                                        'tone' => $followUpTone,
                                    ])
                                </div>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a href="{{ route('purchases.show', $bill) }}" class="button button-secondary">Voir</a>
                                    @if ($bill->status === 'pending_approval')
                                        @allowed('purchases.approve')
                                            <form method="POST" action="{{ route('purchases.approve', $bill) }}">
                                                @csrf
                                                <button type="submit" class="button button-primary">Approuver</button>
                                            </form>
                                        @endallowed
                                    @elseif ($bill->status === 'validated' && $bill->payment_status !== 'paid')
                                        @allowed('payments.manage')
                                            <a href="{{ route('payments.create', ['type' => 'supplier_payment', 'purchase_bill' => $bill->id]) }}" class="button button-primary">Regler</a>
                                        @endallowed
                                        @allowed('supplier_credit_notes.issue')
                                            <a href="{{ route('purchase-credit-notes.create', $bill) }}" class="button button-secondary">Avoir</a>
                                        @endallowed
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="muted">Aucune facture fournisseur ne correspond aux filtres selectionnes.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>

                @if (method_exists($bills, 'links'))
                    <div style="margin-top:18px;">{{ $bills->links() }}</div>
                @endif
            </section>
        @endif
    </div>

    <script>
        (() => {
            const storageKey = 'nema.purchases.display_mode';
            const table = document.querySelector('[data-display-table="purchases"]');
            const controls = document.querySelector('[data-display-controls="purchases"]');
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
