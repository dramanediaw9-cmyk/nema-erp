@extends('layouts.app')

@section('title', 'Bons de livraison - Nema ERP')
@section('page-title', 'Bons de livraison')

@section('content')
    <style>
        .records-table {
            --table-cell-padding-y: 8px;
            --table-cell-padding-x: 8px;
            --row-note-size: 12px;
            --row-action-gap: 6px;
            --status-gap: 6px;
        }
        .records-table.is-detailed {
            --table-cell-padding-y: 11px;
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
        .records-table .row-actions .button {
            padding: 7px 10px;
            border-radius: 10px;
            font-size: 13px;
        }
        .records-table .status-stack {
            display: grid;
            gap: var(--status-gap);
        }
        .table-tools {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }
        .mode-switch {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px;
            border-radius: 14px;
            border: 1px solid var(--line);
            background: #fff;
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
            .records-table .col-optional-md {
                display: none;
            }
        }
    </style>

    <div class="page-head">
        <div>
            <h2 style="margin:0;">Suivi des livraisons</h2>
            <div class="muted">Gere les sorties de stock liees aux commandes confirmees et la preparation de la facturation.</div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="{{ route('delivery-notes.export', request()->query()) }}" class="button button-secondary">Exporter CSV</a>
            @allowed('delivery_notes.manage')
                <a href="{{ route('delivery-notes.create') }}" class="button button-primary">Nouveau bon de livraison</a>
            @endallowed
        </div>
    </div>

    <div class="grid stats-grid" style="margin-bottom:20px;">
        <div class="card"><div class="muted">Livraisons emises</div><div class="stat-value">{{ $summary['issued'] }}</div></div>
        <div class="card"><div class="muted">Livraisons facturees</div><div class="stat-value">{{ $summary['invoiced'] }}</div></div>
    </div>

    <section class="card" style="margin-bottom:18px;">
        <form method="GET" action="{{ route('delivery-notes.index') }}" class="form-grid" style="align-items:end; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));">
            <div style="grid-column:span 2; min-width:220px;">
                <label for="search">Recherche</label>
                <input type="text" id="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Numero, client, commande, agence...">
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
                    <option value="issued" @selected(($filters['status'] ?? null) === 'issued')>Emis</option>
                    <option value="invoiced" @selected(($filters['status'] ?? null) === 'invoiced')>Facture</option>
                </select>
            </div>
            <div class="actions" style="margin-top:0; justify-content:flex-start; align-self:end;">
                <button type="submit" class="button button-primary">Filtrer</button>
                <a href="{{ route('delivery-notes.index') }}" class="button button-secondary">Reinitialiser</a>
            </div>
        </form>
    </section>

    <div class="table-tools">
        <div class="muted">Mode d affichage memorise pour la liste des bons de livraison.</div>
        <div class="mode-switch" data-display-controls="delivery-notes">
            <button type="button" class="button button-secondary is-active" data-mode="compact">Compact</button>
            <button type="button" class="button button-secondary" data-mode="detailed">Detaille</button>
        </div>
    </div>

    <section class="card table-wrap records-table is-compact" data-display-table="delivery-notes">
        <table>
            <thead>
            <tr>
                <th>Numero</th>
                <th>Date</th>
                <th class="col-optional-md">Commande</th>
                <th>Client</th>
                <th class="col-optional-lg">Agence</th>
                <th>Statut</th>
                <th>Total</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($deliveryNotes as $deliveryNote)
                @php
                    $statusLabel = $deliveryNote->status === 'invoiced' ? 'Facture' : 'Emis';
                    $statusClass = $deliveryNote->status === 'invoiced' ? 'badge-success' : 'badge-muted';
                @endphp
                <tr>
                    <td>
                        <strong>{{ $deliveryNote->delivery_number }}</strong>
                        @if ($deliveryNote->notes)
                            <div class="muted row-note">{{ $deliveryNote->notes }}</div>
                        @endif
                    </td>
                    <td>{{ $deliveryNote->delivery_date?->format('d/m/Y') }}</td>
                    <td class="col-optional-md">
                        @if ($deliveryNote->salesOrder)
                            <a href="{{ route('orders.show', $deliveryNote->salesOrder) }}">{{ $deliveryNote->salesOrder->order_number }}</a>
                        @else
                            <span class="muted">Aucune commande</span>
                        @endif
                    </td>
                    <td>{{ $deliveryNote->customer?->name }}</td>
                    <td class="col-optional-lg">{{ $deliveryNote->branch?->name }}</td>
                    <td>
                        <div class="status-stack">
                            <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
                            @if ($deliveryNote->status === 'issued')
                                <span class="badge badge-success">Pret a facturer</span>
                            @endif
                        </div>
                    </td>
                    <td>{{ number_format((float) $deliveryNote->total, 0, ',', ' ') }} XOF</td>
                    <td>
                        <div class="row-actions">
                            <a href="{{ route('delivery-notes.show', $deliveryNote) }}" class="button button-secondary">Voir</a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">
                        <div class="empty-state">
                            <span class="badge badge-success">Aucun bon de livraison</span>
                            <h3>La base livraisons est vide</h3>
                            <div class="muted">Cree un bon de livraison a partir d une commande client confirmee.</div>
                            <div class="empty-actions">
                                @allowed('delivery_notes.manage')
                                    <a href="{{ route('delivery-notes.create') }}" class="button button-primary">Creer un bon de livraison</a>
                                @endallowed
                            </div>
                        </div>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>

        @if (method_exists($deliveryNotes, 'links'))
            <div style="margin-top:18px;">{{ $deliveryNotes->links() }}</div>
        @endif
    </section>

    <script>
        (() => {
            const storageKey = 'nema.delivery_notes.display_mode';
            const table = document.querySelector('[data-display-table="delivery-notes"]');
            const controls = document.querySelector('[data-display-controls="delivery-notes"]');
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
