@extends('layouts.app')

@section('title', 'Mouvements de stock - Nema ERP')
@section('page-title', 'Historique des mouvements')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Traçabilite des mouvements</h2>
            <div class="muted">Chaque entree et sortie de stock est historisee avec sa source, son entrepot et son auteur.</div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="{{ route('stock.movements.print', request()->query()) }}" class="button button-primary" target="_blank">Imprimer</a>
            <a href="{{ route('stock.index') }}" class="button button-secondary">Retour au stock</a>
        </div>
    </div>

    <section class="card" style="margin-bottom:18px;">
        <form method="GET" action="{{ route('stock.movements') }}" class="form-grid" style="align-items:end; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));">
            <div style="grid-column:span 2; min-width:220px;">
                <label for="search">Recherche</label>
                <input type="text" id="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Produit, SKU, motif, agence...">
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
                <label for="warehouse_id">Entrepot</label>
                <select id="warehouse_id" name="warehouse_id">
                    <option value="">Tous les entrepots</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected((int) ($filters['warehouse_id'] ?? 0) === $warehouse->id)>{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="movement_type">Type</label>
                <select id="movement_type" name="movement_type">
                    <option value="">Tous les types</option>
                    @foreach ($movementTypes as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['movement_type'] ?? null) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="actions" style="margin-top:0; justify-content:flex-start; align-self:end;">
                <button type="submit" class="button button-primary">Filtrer</button>
                <a href="{{ route('stock.movements') }}" class="button button-secondary">Reinitialiser</a>
            </div>
        </form>
    </section>

    <section class="card table-wrap">
        <table>
            <thead>
            <tr>
                <th>Date</th>
                <th>Produit</th>
                <th>Agence</th>
                <th>Entrepot</th>
                <th>Type</th>
                <th>Source</th>
                <th>Entree</th>
                <th>Sortie</th>
                <th>Motif</th>
                <th>Auteur</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($movements as $movement)
                @php($sourceContext = $movementContexts[$movement->id] ?? null)
                <tr>
                    <td>{{ $movement->movement_date?->format('d/m/Y H:i') }}</td>
                    <td>
                        @include('partials.product-inline', ['product' => $movement->product, 'link' => route('stock.show', ['product' => $movement->product_id, 'warehouse_id' => $movement->warehouse_id]), 'meta' => $movement->product?->sku, 'size' => 42])
                    </td>
                    <td>{{ $movement->branch?->name }}</td>
                    <td>{{ $movement->warehouse?->name ?? 'N/A' }}</td>
                    <td>{{ $movementTypes[$movement->movement_type] ?? str($movement->movement_type)->replace('_', ' ')->title() }}</td>
                    <td>
                        @if ($sourceContext)
                            <a href="{{ $sourceContext['url'] }}">{{ $sourceContext['label'] }} {{ $sourceContext['number'] }}</a>
                        @else
                            <span class="muted">Operation interne</span>
                        @endif
                    </td>
                    <td>{{ number_format((float) $movement->quantity_in, 3, ',', ' ') }}</td>
                    <td>{{ number_format((float) $movement->quantity_out, 3, ',', ' ') }}</td>
                    <td>{{ $movement->reason ?: 'Non renseigne' }}</td>
                    <td>{{ $movement->creator?->name ?? 'Systeme' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="muted">Aucun mouvement ne correspond aux filtres selectionnes.</td>
                </tr>
            @endforelse
            </tbody>
        </table>

        @if (method_exists($movements, 'links'))
            <div style="margin-top:18px;">{{ $movements->links() }}</div>
        @endif
    </section>
@endsection
