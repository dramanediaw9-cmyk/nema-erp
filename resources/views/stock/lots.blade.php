@extends('layouts.app')

@section('title', 'Lots et peremption - Nema ERP')
@section('page-title', 'Lots et peremption')

@push('page-styles')
    <style>
        .lot-page {
            display: grid;
            gap: 20px;
        }
        .lot-hero {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, rgba(248, 252, 251, 0.98) 0%, rgba(233, 244, 242, 0.95) 54%, rgba(255, 245, 229, 0.92) 100%);
            border-color: rgba(15, 118, 110, 0.14);
        }
        .lot-hero::after {
            content: "";
            position: absolute;
            inset: auto -60px -50px auto;
            width: 220px;
            height: 220px;
            border-radius: 999px;
            background: rgba(15, 118, 110, 0.11);
            filter: blur(8px);
            pointer-events: none;
        }
        .lot-hero__grid {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: minmax(0, 1.4fr) minmax(280px, 0.95fr);
            gap: 18px;
            align-items: start;
        }
        .lot-hero__copy {
            display: grid;
            gap: 12px;
        }
        .lot-hero__copy h2 {
            margin: 0;
            font-size: clamp(28px, 4vw, 40px);
            line-height: 1.02;
            letter-spacing: -.04em;
        }
        .lot-hero__panel {
            border: 1px solid rgba(102, 82, 56, 0.1);
            border-radius: 20px;
            padding: 16px 18px;
            background: rgba(255, 255, 255, 0.76);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.9);
        }
        .lot-hero__panel strong {
            display: block;
            margin-bottom: 8px;
            font-size: 16px;
        }
        .lot-hero__actions {
            margin-top: 14px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .lot-metric-grid {
            display: grid;
            gap: 16px;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        }
        .lot-metric-card {
            border: 1px solid rgba(102, 82, 56, 0.1);
            border-radius: 22px;
            padding: 18px;
            background: linear-gradient(180deg, rgba(255, 254, 250, 0.96) 0%, rgba(239, 246, 243, 0.94) 100%);
            box-shadow: var(--shadow-soft);
        }
        .lot-metric-card .label {
            color: var(--muted);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: .12em;
            font-weight: 800;
        }
        .lot-metric-card .value {
            margin-top: 10px;
            font-size: 32px;
            font-weight: 800;
            letter-spacing: -.04em;
        }
        .lot-metric-card .hint {
            margin-top: 8px;
            color: var(--muted);
            font-size: 13px;
        }
        .lot-filter-card {
            background: linear-gradient(180deg, rgba(255, 252, 247, 0.96) 0%, rgba(241, 247, 245, 0.9) 100%);
        }
        .lot-section-head {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: flex-start;
            margin-bottom: 14px;
        }
        .lot-section-head h3 {
            margin: 0;
            font-size: 22px;
            letter-spacing: -.03em;
        }
        .lot-section-head p {
            margin: 6px 0 0;
        }
        .lot-table td,
        .lot-table th {
            vertical-align: middle;
        }
        .lot-table tbody tr:hover {
            background: rgba(15, 118, 110, 0.04);
        }
        .lot-status-note {
            margin-top: 6px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.4;
        }
        .lot-table-tools {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }
        .lot-table-tools .meta {
            color: var(--muted);
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }
        @media (max-width: 960px) {
            .lot-hero__grid {
                grid-template-columns: 1fr;
            }
            .lot-table .col-optional {
                display: none;
            }
        }
    </style>
@endpush

@section('content')
    <div class="lot-page">
        <section class="card lot-hero">
            <div class="lot-hero__grid">
                <div class="lot-hero__copy">
                    <div class="badge badge-muted">Traceabilite stock</div>
                    <h2>Lots, numeros de serie et peremption pour {{ $branch?->name ?? 'l agence active' }}.</h2>
                    <p class="muted">Cette vue met en avant les lots encore disponibles, les echeances critiques et les receptions a surveiller avant rupture, perte ou blocage terrain.</p>
                </div>
                <div class="lot-hero__panel">
                    <strong>Ce que tu vois ici</strong>
                    <div class="muted">Les lots filtrables par depot, type de suivi, disponibilite et horizon de peremption, avec acces rapide a la fiche produit et a la reception d origine.</div>
                    <div class="lot-hero__actions">
                        <a href="{{ route('stock.index') }}" class="button button-secondary">Retour stock</a>
                        <a href="{{ route('stock.movements') }}" class="button button-secondary">Mouvements</a>
                    </div>
                </div>
            </div>
        </section>

        <section class="lot-metric-grid">
            <div class="lot-metric-card">
                <div class="label">Lots visibles</div>
                <div class="value">{{ $stats['count'] }}</div>
                <div class="hint">Selon les filtres actifs.</div>
            </div>
            <div class="lot-metric-card">
                <div class="label">Produits suivis</div>
                <div class="value">{{ $stats['tracked_products'] }}</div>
                <div class="hint">Articles couverts par ces lots.</div>
            </div>
            <div class="lot-metric-card">
                <div class="label">Disponible</div>
                <div class="value">{{ number_format((float) $stats['available_qty'], 3, ',', ' ') }}</div>
                <div class="hint">Quantite encore exploitable.</div>
            </div>
            <div class="lot-metric-card">
                <div class="label">Expire</div>
                <div class="value">{{ $stats['expired_count'] }}</div>
                <div class="hint">Lots deja echus et encore disponibles.</div>
            </div>
            <div class="lot-metric-card">
                <div class="label">A surveiller</div>
                <div class="value">{{ $stats['expiring_count'] }}</div>
                <div class="hint">Lots qui expirent sous {{ $expiryHorizonDays }} jours.</div>
            </div>
        </section>

        <section class="card lot-filter-card">
            <div class="lot-section-head">
                <div>
                    <h3>Filtres de pilotage</h3>
                    <p class="muted">Concentre-toi sur les lots critiques, un depot precis ou une reference exacte.</p>
                </div>
            </div>

            <form method="GET" action="{{ route('stock.lots') }}" class="form-grid" style="grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); align-items:end;">
                <div>
                    <label for="search">Recherche</label>
                    <input id="search" type="text" name="search" value="{{ $filters['search'] }}" placeholder="Lot, serie, produit, BL, depot...">
                </div>
                <div>
                    <label for="warehouse_id">Depot</label>
                    <select id="warehouse_id" name="warehouse_id">
                        <option value="">Tous les depots</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected($filters['warehouse_id'] === $warehouse->id)>{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="tracking_type">Type de suivi</label>
                    <select id="tracking_type" name="tracking_type">
                        <option value="">Tous</option>
                        <option value="lot" @selected($filters['tracking_type'] === 'lot')>Par lot</option>
                        <option value="serial" @selected($filters['tracking_type'] === 'serial')>Par numero de serie</option>
                    </select>
                </div>
                <div>
                    <label for="status">Peremption</label>
                    <select id="status" name="status">
                        <option value="">Toutes</option>
                        <option value="expired" @selected($filters['status'] === 'expired')>Expire</option>
                        <option value="expiring" @selected($filters['status'] === 'expiring')>Expire bientot</option>
                        <option value="healthy" @selected($filters['status'] === 'healthy')>Stable</option>
                        <option value="no_expiry" @selected($filters['status'] === 'no_expiry')>Sans peremption</option>
                    </select>
                </div>
                <div>
                    <label for="expiry_window_days">Horizon</label>
                    <select id="expiry_window_days" name="expiry_window_days">
                        <option value="7" @selected(($filters['expiry_window_days'] ?? 30) === 7)>7 jours</option>
                        <option value="14" @selected(($filters['expiry_window_days'] ?? 30) === 14)>14 jours</option>
                        <option value="30" @selected(($filters['expiry_window_days'] ?? 30) === 30)>30 jours</option>
                    </select>
                </div>
                <div>
                    <label for="availability">Disponibilite</label>
                    <select id="availability" name="availability">
                        <option value="available" @selected($filters['availability'] === 'available')>Encore disponible</option>
                        <option value="all" @selected($filters['availability'] === 'all')>Tout voir</option>
                        <option value="consumed" @selected($filters['availability'] === 'consumed')>Consomme</option>
                    </select>
                </div>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <button type="submit" class="button button-primary">Appliquer</button>
                    <a href="{{ route('stock.lots') }}" class="button button-secondary">Reinitialiser</a>
                </div>
            </form>
        </section>

        <section class="card">
            <div class="lot-section-head">
                <div>
                    <h3>Lots et peremption</h3>
                    <p class="muted">{{ $selectedWarehouse?->name ? 'Lecture centree sur '.$selectedWarehouse->name : 'Vue de tous les depots de l agence active' }}.</p>
                </div>
            </div>

            @if ($lots->isEmpty())
                <div class="empty-state" style="padding:28px 0;">
                    <div class="muted">Aucun lot ne correspond aux filtres actuels. Essaie une recherche plus large ou retire le filtre de peremption.</div>
                </div>
            @else
                <div class="lot-table-tools" style="margin-bottom:14px;">
                    <div class="meta">
                        <strong>{{ $lots->total() }}</strong>
                        <span>lot(s) trouves</span>
                    </div>
                    <div class="meta">
                        <span>Horizon surveille : {{ $expiryHorizonDays }} jours</span>
                    </div>
                </div>

                <div class="table-wrap lot-table">
                    <table>
                        <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Produit</th>
                            <th class="col-optional">Depot</th>
                            <th>Quantites</th>
                            <th>Statut</th>
                            <th>Peremption</th>
                            <th class="col-optional">Reception</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($lots as $lot)
                            @php
                                $status = $lot->expiryStatus($expiryHorizonDays);
                                $statusClass = match ($status) {
                                    'expired' => 'badge-danger',
                                    'expiring' => 'badge-warning',
                                    'healthy' => 'badge-success',
                                    default => 'badge-muted',
                                };
                                $statusLabel = match ($status) {
                                    'expired' => 'Expire',
                                    'expiring' => 'A surveiller',
                                    'healthy' => 'Stable',
                                    default => 'Sans peremption',
                                };
                                $daysToExpiry = $lot->expires_at ? now()->startOfDay()->diffInDays($lot->expires_at, false) : null;
                                $statusHint = match ($status) {
                                    'expired' => 'Disponible alors que la date est depassee.',
                                    'expiring' => 'Echeance dans '.max(0, (int) $daysToExpiry).' jour(s).',
                                    'healthy' => 'Aucune tension immediate sur ce lot.',
                                    default => 'Pas de date de peremption renseignee.',
                                };
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $lot->displayCode() }}</strong>
                                    <div class="muted" style="margin-top:4px;">{{ $lot->tracking_type === 'serial' ? 'Numero de serie' : 'Lot produit' }}</div>
                                </td>
                                <td>
                                    @include('partials.product-inline', [
                                        'product' => $lot->product,
                                        'meta' => collect([
                                            $lot->product?->sku,
                                            $lot->product?->category?->name,
                                            $lot->tracking_type === 'serial' ? 'Trace unitaire' : 'Trace par lot',
                                        ])->filter()->implode(' | '),
                                        'size' => 42,
                                    ])
                                </td>
                                <td class="col-optional">{{ $lot->warehouse?->name ?? 'Depot non renseigne' }}</td>
                                <td>
                                    <strong>{{ number_format((float) $lot->quantity_available, 3, ',', ' ') }}</strong>
                                    <div class="muted" style="margin-top:4px;">Recu {{ number_format((float) $lot->quantity_received, 3, ',', ' ') }}</div>
                                </td>
                                <td>
                                    <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
                                    <div class="lot-status-note">{{ $statusHint }}</div>
                                </td>
                                <td>
                                    @if ($lot->expires_at)
                                        <strong>{{ $lot->expires_at->format('d/m/Y') }}</strong>
                                    @else
                                        <span class="muted">Aucune</span>
                                    @endif
                                </td>
                                <td class="col-optional">
                                    @if ($lot->goodsReceipt)
                                        <a href="{{ route('goods-receipts.show', $lot->goodsReceipt) }}">{{ $lot->goodsReceipt->receipt_number }}</a>
                                    @else
                                        <span class="muted">Sans reception liee</span>
                                    @endif
                                    <div class="muted" style="margin-top:4px;">{{ $lot->received_at?->format('d/m/Y') ?: '-' }}</div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div style="margin-top:16px;">
                    {{ $lots->links() }}
                </div>
            @endif
        </section>
    </div>
@endsection
