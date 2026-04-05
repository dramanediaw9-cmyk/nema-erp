@extends('layouts.app')

@section('title', 'Stock produit - Nema ERP')
@section('page-title', 'Fiche stock produit')

@section('content')
    <div class="page-head">
        <div style="display:flex; gap:14px; align-items:center; flex-wrap:wrap;">
            @if ($product->image_url)
                <img src="{{ $product->image_url }}" alt="{{ $product->name }}" style="width:72px; height:72px; object-fit:cover; border-radius:20px; border:1px solid #d7deea; background:#fff;">
            @else
                <span style="display:inline-flex; width:72px; height:72px; align-items:center; justify-content:center; border-radius:20px; background:linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); color:#17304f; font-weight:900; font-size:24px; letter-spacing:.04em; border:1px solid #d7deea;">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($product->name, 0, 2)) }}</span>
            @endif
            <div>
                <h2 style="margin:0;">{{ $product->name }}</h2>
                <div class="muted">{{ $product->sku }} · {{ $branch?->name }}@if($selectedWarehouse) · {{ $selectedWarehouse->name }} @endif</div>
            </div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="{{ route('stock.index', ['warehouse_id' => $selectedWarehouse?->id]) }}" class="button button-secondary">Retour stock</a>
            <a href="{{ route('stock.movements', ['warehouse_id' => $selectedWarehouse?->id, 'search' => $product->sku]) }}" class="button button-secondary">Voir mouvements</a>
        </div>
    </div>

    <section class="card" style="margin-bottom:20px;">
        <form method="GET" action="{{ route('stock.show', $product) }}" class="form-grid" style="grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); align-items:end;">
            <div>
                <label for="warehouse_id">Entrepot</label>
                <select id="warehouse_id" name="warehouse_id">
                    <option value="">Vue agence complete</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected($selectedWarehouse?->id === $warehouse->id)>{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="actions" style="margin-top:0; justify-content:flex-start; align-self:end;">
                <button type="submit" class="button button-primary">Appliquer</button>
                <a href="{{ route('stock.show', $product) }}" class="button button-secondary">Vue globale</a>
            </div>
        </form>
    </section>

    <div class="grid stats-grid" style="margin-bottom:20px;">
        <div class="card"><div class="muted">Stock physique</div><div class="stat-value">{{ number_format($currentStock, 3, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Stock vendable</div><div class="stat-value">{{ number_format($saleableStock, 3, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Reserve</div><div class="stat-value">{{ number_format($reservedStock, 3, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Disponible a promettre</div><div class="stat-value">{{ number_format($availableToPromise, 3, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Stock minimum</div><div class="stat-value">{{ number_format((float) $product->min_stock, 3, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Entrees cumulees</div><div class="stat-value">{{ number_format($totalIn, 3, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Sorties cumulees</div><div class="stat-value">{{ number_format($totalOut, 3, ',', ' ') }}</div></div>
    </div>

    <div class="split">
        <section class="card">
            <h2 style="margin-top:0;">Reservations ouvertes</h2>
            @forelse ($reservationOrders as $reservation)
                @php($reservedOrder = $reservation['order'])
                <div style="padding-bottom:14px; border-bottom:1px solid #efe4d3; margin-bottom:14px; display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                    <div>
                        <strong>{{ $reservedOrder->order_number }}</strong>
                        <div class="muted" style="margin-top:6px;">{{ $reservedOrder->customer?->name }} · {{ $reservedOrder->warehouse?->name ?? 'Depot principal' }}</div>
                        <div class="muted" style="margin-top:6px;">Reserve : {{ number_format((float) $reservation['reserved_qty'], 3, ',', ' ') }} · Livraison souhaitee : {{ $reservedOrder->requested_delivery_date?->format('d/m/Y') ?? 'Non renseignee' }}</div>
                    </div>
                    <a href="{{ route('orders.show', $reservedOrder) }}" class="button button-secondary">Ouvrir</a>
                </div>
            @empty
                <p class="muted">Aucune reservation ouverte sur ce produit pour ce perimetre.</p>
            @endforelse
        </section>

        <section class="card">
            <h2 style="margin-top:0;">Documents lies</h2>
            @forelse ($relatedDocuments as $document)
                <div style="padding-bottom:14px; border-bottom:1px solid #efe4d3; margin-bottom:14px; display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                    <div>
                        <strong>{{ $document['label'] }} {{ $document['number'] }}</strong>
                        <div class="muted" style="margin-top:6px;">{{ $document['date']?->format('d/m/Y') }} · {{ $document['status'] }}</div>
                    </div>
                    <a href="{{ $document['url'] }}" class="button button-secondary">Ouvrir</a>
                </div>
            @empty
                <p class="muted">Aucun document n'est rattache a ce produit pour ce perimetre.</p>
            @endforelse
        </section>
    </div>

    <div class="split" style="margin-top:20px;">
        <section class="card">
            <h2 style="margin-top:0;">Ecritures comptables liees</h2>
            @forelse ($journalEntries as $entry)
                <div style="padding-bottom:14px; border-bottom:1px solid #efe4d3; margin-bottom:14px; display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                    <div>
                        <strong>{{ $entry->journal_number }}</strong>
                        <div class="muted" style="margin-top:6px;">{{ $entry->entry_date?->format('d/m/Y') }} · {{ $entry->description }}</div>
                    </div>
                    <a href="{{ route('accounting.journal-entries.show', $entry) }}" class="button button-secondary">Voir</a>
                </div>
            @empty
                <p class="muted">Aucune ecriture comptable rattachee a ce produit.</p>
            @endforelse
        </section>

        <section class="card">
            <h2 style="margin-top:0;">Lecture rapide</h2>
            <div class="summary-stack">
                <div class="tip-card">
                    <strong>Stock physique</strong>
                    <div class="muted">Ce qui est reellement present selon les mouvements deja postes.</div>
                </div>
                <div class="tip-card">
                    <strong>Stock vendable</strong>
                    <div class="muted">Pour les produits suivis par lot ou serie, les lots expires sont exclus du vendable.</div>
                </div>
                <div class="tip-card">
                    <strong>Disponible a promettre</strong>
                    <div class="muted">Ce qui reste encore vendable apres deduction des commandes confirmees non encore livrees.</div>
                </div>
            </div>
        </section>
    </div>

    <section class="card" style="margin-top:20px;">
        <h2 style="margin-top:0;">Mouvements recents</h2>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Date</th>
                    <th>Entrepot</th>
                    <th>Type</th>
                    <th>Source</th>
                    <th>Entree</th>
                    <th>Sortie</th>
                    <th>Auteur</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($recentMovements as $movement)
                    @php($context = $movementContexts[$movement->id] ?? null)
                    <tr>
                        <td>{{ $movement->movement_date?->format('d/m/Y H:i') }}</td>
                        <td>{{ $movement->warehouse?->name ?? 'N/A' }}</td>
                        <td>{{ $movement->reason ?: str($movement->movement_type)->replace('_', ' ')->title() }}</td>
                        <td>
                            @if ($context)
                                <a href="{{ $context['url'] }}">{{ $context['label'] }} {{ $context['number'] }}</a>
                            @else
                                <span class="muted">Operation interne</span>
                            @endif
                        </td>
                        <td>{{ number_format((float) $movement->quantity_in, 3, ',', ' ') }}</td>
                        <td>{{ number_format((float) $movement->quantity_out, 3, ',', ' ') }}</td>
                        <td>{{ $movement->creator?->name ?? 'Systeme' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="muted">Aucun mouvement recent pour ce produit.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
