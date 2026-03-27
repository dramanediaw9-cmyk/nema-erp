@extends('layouts.app')

@section('title', 'Bons de livraison - Nema ERP')
@section('page-title', 'Bons de livraison')

@section('content')
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

    <section class="card table-wrap">
        <table>
            <thead>
            <tr>
                <th>Numero</th>
                <th>Date</th>
                <th>Commande</th>
                <th>Client</th>
                <th>Agence</th>
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
                            <div class="muted" style="font-size:13px;">{{ $deliveryNote->notes }}</div>
                        @endif
                    </td>
                    <td>{{ $deliveryNote->delivery_date?->format('d/m/Y') }}</td>
                    <td>
                        @if ($deliveryNote->salesOrder)
                            <a href="{{ route('orders.show', $deliveryNote->salesOrder) }}">{{ $deliveryNote->salesOrder->order_number }}</a>
                        @else
                            <span class="muted">Aucune commande</span>
                        @endif
                    </td>
                    <td>{{ $deliveryNote->customer?->name }}</td>
                    <td>{{ $deliveryNote->branch?->name }}</td>
                    <td><span class="badge {{ $statusClass }}">{{ $statusLabel }}</span></td>
                    <td>{{ number_format((float) $deliveryNote->total, 0, ',', ' ') }} XOF</td>
                    <td>
                        <div style="display:flex; gap:10px; flex-wrap:wrap;">
                            <a href="{{ route('delivery-notes.show', $deliveryNote) }}" class="button button-secondary">Voir</a>
                            @if ($deliveryNote->status === 'issued')
                                <span class="badge badge-success">Pret a facturer</span>
                            @endif
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
@endsection
