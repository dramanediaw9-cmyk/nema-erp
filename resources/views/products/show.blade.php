@extends('layouts.app')

@section('title', $product->display_name.' - Produit - Nema ERP')
@section('page-title', 'Fiche produit')

@section('content')
    @php
        $canViewProductCosts = auth()->user()?->hasPermission('products.cost.view');
        $isStockable = $product->type === 'stockable';
        $stockStatusLabel = ! $isStockable
            ? 'Service'
            : ($currentStock <= 0 ? 'Rupture' : ($currentStock <= (float) $product->min_stock ? 'A surveiller' : 'En stock'));
        $stockStatusTone = ! $isStockable
            ? 'muted'
            : ($currentStock <= 0 ? 'danger' : ($currentStock <= (float) $product->min_stock ? 'warning' : 'success'));
    @endphp
    <style>
        .product-detail-layout {
            display: grid;
            gap: 18px;
            grid-template-columns: minmax(320px, 420px) minmax(0, 1fr);
            align-items: start;
        }
        .product-photo-card {
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            border: 1px solid #d7deea;
            border-radius: 26px;
            padding: 18px;
            box-shadow: 0 18px 36px rgba(15, 23, 42, 0.06);
        }
        .product-photo-large {
            width: 100%;
            aspect-ratio: 1 / 1;
            object-fit: cover;
            border-radius: 22px;
            border: 1px solid #dbe3ef;
            background: #fff;
        }
        .product-photo-placeholder {
            width: 100%;
            aspect-ratio: 1 / 1;
            border-radius: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 72px;
            font-weight: 900;
            color: #17304f;
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            border: 1px solid #dbe3ef;
        }
        .product-meta-grid {
            display: grid;
            gap: 14px;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        }
        .product-meta-box {
            border: 1px solid var(--line);
            border-radius: 18px;
            background: #fff;
            padding: 14px 16px;
        }
        .product-meta-box .label {
            color: var(--muted);
            font-size: 13px;
        }
        .product-meta-box .value {
            margin-top: 8px;
            font-size: 24px;
            font-weight: 800;
        }
        .stock-balance-grid {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        }
        .stock-balance-card {
            border: 1px solid var(--line);
            border-radius: 18px;
            background: #fff;
            padding: 14px 16px;
        }
        .lifecycle-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 14px;
        }
        @media (max-width: 960px) {
            .product-detail-layout {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="page-head">
        <div>
            <h2 style="margin:0;">{{ $product->display_name }}</h2>
            <div class="muted">
                {{ $product->sku }} · {{ $product->barcode ?: 'Code-barres non renseigne' }} · {{ $product->category?->name ?? 'Sans categorie' }}
                @if ($product->is_variant && $product->parent)
                    · Variante de {{ $product->parent->name }}
                @elseif ($product->variants->isNotEmpty())
                    · Famille avec {{ $product->variants->count() }} variantes
                @endif
            </div>
            <div class="chip-row" style="margin-top:12px;">
                @include('partials.erp-status-badge', [
                    'type' => 'activity',
                    'value' => $product->is_active ? 'active' : 'inactive',
                ])
                @include('partials.erp-status-badge', [
                    'label' => $stockStatusLabel,
                    'tone' => $stockStatusTone,
                ])
                @include('partials.erp-status-badge', [
                    'label' => $product->sale_ok ? 'Vendable' : 'Non vendable',
                    'tone' => $product->sale_ok ? 'success' : 'muted',
                ])
                @include('partials.erp-status-badge', [
                    'label' => $product->purchase_ok ? 'Achetable' : 'Non achetable',
                    'tone' => $product->purchase_ok ? 'success' : 'muted',
                ])
                @include('partials.erp-status-badge', [
                    'label' => match ($product->tracking_type) {
                        'lot' => 'Suivi par lot',
                        'serial' => 'Suivi par serie',
                        default => 'Sans suivi',
                    },
                    'tone' => 'muted',
                ])
            </div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
            <a href="{{ route('products.index') }}" class="button button-secondary">Retour catalogue</a>
            @allowed('products.manage')
                <a href="{{ route('products.edit', $product) }}" class="button button-primary">Modifier</a>
                @if ($product->is_active)
                    <form method="POST" action="{{ route('products.archive', $product) }}" class="inline-form" onsubmit="return confirm('Archiver ce produit ? Il ne sera plus propose dans les nouveaux documents.');">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="button button-secondary">Archiver</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('products.restore', $product) }}" class="inline-form">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="button button-primary">Reactiver</button>
                    </form>
                @endif
                <form method="POST" action="{{ route('products.destroy', $product) }}" class="inline-form" onsubmit="return confirm('Supprimer definitivement ce produit ? Cette action est irreversible.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="button button-danger" @disabled(! $deletionGuard['can_delete'])>Supprimer</button>
                </form>
            @endallowed
        </div>
    </div>

    <div class="product-detail-layout">
        <section class="product-photo-card">
            @if ($product->image_url)
                <img src="{{ $product->image_url }}" alt="{{ $product->display_name }}" class="product-photo-large">
            @else
                <div class="product-photo-placeholder">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($product->display_name, 0, 2)) }}</div>
            @endif
            <div style="margin-top:16px; display:grid; gap:10px;">
                <div class="summary-box">
                    <strong>Photo produit</strong>
                    <div class="help" style="margin-top:8px;">Cette photo est reprise dans le catalogue, au point de vente et sur les ecrans d echange.</div>
                </div>
                @if ($product->description)
                    <div class="summary-box">
                        <strong>Description generale</strong>
                        <div style="margin-top:8px; line-height:1.6;">{{ $product->description }}</div>
                    </div>
                @endif
                @if ($product->internal_notes)
                    <div class="summary-box">
                        <strong>Notes internes</strong>
                        <div style="margin-top:8px; line-height:1.6;">{{ $product->internal_notes }}</div>
                    </div>
                @endif
            </div>
        </section>

        <section class="grid">
            <div class="product-meta-grid">
                <div class="product-meta-box">
                    <div class="label">Prix de vente</div>
                    <div class="value">{{ number_format((float) $product->sale_price, 0, ',', ' ') }} XOF</div>
                </div>
                @if ($canViewProductCosts)
                    <div class="product-meta-box">
                        <div class="label">Prix d achat</div>
                        <div class="value">{{ number_format((float) $product->purchase_price, 0, ',', ' ') }} XOF</div>
                    </div>
                @else
                    <div class="product-meta-box">
                        <div class="label">Couts confidentiels</div>
                        <div class="value" style="font-size:20px;">Masques</div>
                    </div>
                @endif
                <div class="product-meta-box">
                    <div class="label">Stock actuel</div>
                    <div class="value">{{ number_format($currentStock, 3, ',', ' ') }}</div>
                </div>
                <div class="product-meta-box">
                    <div class="label">Stock minimum</div>
                    <div class="value">{{ number_format((float) $product->min_stock, 3, ',', ' ') }}</div>
                </div>
            </div>

            <div class="card">
                <h3 class="section-title">Informations produit</h3>
                <div class="kpi-row">
                    <div class="kpi">
                        <div class="label">Type</div>
                        <div class="value" style="font-size:20px;">{{ $product->type === 'service' ? 'Service' : 'Article stockable' }}</div>
                    </div>
                    <div class="kpi">
                        <div class="label">Unite de base</div>
                        <div class="value" style="font-size:20px;">{{ $product->unit }}</div>
                    </div>
                    <div class="kpi">
                        <div class="label">Statut</div>
                        <div class="value" style="font-size:20px;">{{ $product->is_active ? 'Actif' : 'Archive' }}</div>
                    </div>
                </div>
                <div class="grid" style="grid-template-columns: repeat(2, minmax(0, 1fr)); margin-top:14px;">
                    <div class="summary-box">
                        <strong>Unite commerciale vente</strong>
                        <div style="margin-top:8px; line-height:1.6;">{{ $product->salesUnitSummary() ?: 'Non renseignee' }}</div>
                    </div>
                    <div class="summary-box">
                        <strong>Unite achat</strong>
                        <div style="margin-top:8px; line-height:1.6;">{{ $product->purchaseUnitSummary() ?: 'Non renseignee' }}</div>
                    </div>
                </div>
                <div class="grid" style="grid-template-columns: repeat(2, minmax(0, 1fr)); margin-top:14px;">
                    <div class="summary-box">
                        <strong>Motif blocage vente</strong>
                        <div style="margin-top:8px; line-height:1.6;">{{ $product->saleBlockSummary() ?: 'Aucun blocage de vente actif.' }}</div>
                    </div>
                    <div class="summary-box">
                        <strong>Motif blocage achat</strong>
                        <div style="margin-top:8px; line-height:1.6;">{{ $product->purchaseBlockSummary() ?: 'Aucun blocage d achat actif.' }}</div>
                    </div>
                </div>
                <div class="chip-row" style="margin-top:14px;">
                    @include('partials.erp-status-badge', [
                        'label' => $product->sale_ok ? 'Vendable' : 'Non vendable',
                        'tone' => $product->sale_ok ? 'success' : 'muted',
                    ])
                    @include('partials.erp-status-badge', [
                        'label' => $product->sale_blocked ? 'Vente bloquee' : 'Vente ouverte',
                        'tone' => $product->sale_blocked ? 'danger' : 'success',
                    ])
                    @include('partials.erp-status-badge', [
                        'label' => $product->purchase_ok ? 'Achetable' : 'Non achetable',
                        'tone' => $product->purchase_ok ? 'success' : 'muted',
                    ])
                    @include('partials.erp-status-badge', [
                        'label' => $product->purchase_blocked ? 'Achat bloque' : 'Achat ouvert',
                        'tone' => $product->purchase_blocked ? 'danger' : 'success',
                    ])
                    @include('partials.erp-status-badge', [
                        'label' => 'Facturation : '.($product->invoice_policy === 'delivered' ? 'quantites livrees' : 'quantites commandees'),
                        'tone' => 'muted',
                    ])
                    @include('partials.erp-status-badge', [
                        'label' => 'Suivi : '.match ($product->tracking_type) {
                            'lot' => 'par lot',
                            'serial' => 'numero de serie',
                            default => 'aucun',
                        },
                        'tone' => 'muted',
                    ])
                    @include('partials.erp-status-badge', [
                        'label' => $product->auto_replenish ? 'Reappro auto active' : 'Reappro auto desactivee',
                        'tone' => $product->auto_replenish ? 'success' : 'muted',
                    ])
                </div>
                <div class="grid" style="grid-template-columns: repeat(3, minmax(0, 1fr)); margin-top:14px;">
                    <div class="summary-box">
                        <strong>Stock cible</strong>
                        <div style="margin-top:8px; line-height:1.6;">{{ $product->reorder_max_qty ? number_format((float) $product->reorder_max_qty, 3, ',', ' ') : 'Non defini' }}</div>
                    </div>
                    <div class="summary-box">
                        <strong>Multiple achat</strong>
                        <div style="margin-top:8px; line-height:1.6;">{{ $product->reorder_multiple_qty ? number_format((float) $product->reorder_multiple_qty, 3, ',', ' ') : 'Libre' }}</div>
                    </div>
                    <div class="summary-box">
                        <strong>Delai achat</strong>
                        <div style="margin-top:8px; line-height:1.6;">{{ $product->purchase_lead_time_days !== null ? $product->purchase_lead_time_days.' j' : 'Non defini' }}</div>
                    </div>
                </div>
            </div>

            <div class="card">
                <h3 class="section-title">Famille et variantes</h3>
                @if ($product->is_variant || $product->variants->isNotEmpty())
                    <div class="grid" style="grid-template-columns: repeat(2, minmax(0, 1fr)); gap:14px;">
                        @if ($product->is_variant)
                            <div class="summary-box">
                                <strong>Produit parent</strong>
                                <div style="margin-top:8px; line-height:1.6;">
                                    @if ($product->parent)
                                        <a href="{{ route('products.show', $product->parent) }}">{{ $product->parent->name }}</a>
                                    @else
                                        -
                                    @endif
                                </div>
                            </div>
                            <div class="summary-box">
                                <strong>Configuration de la variante</strong>
                                <div style="margin-top:8px; line-height:1.6;">{{ $product->variant_label ?: ($product->variantValuesSummary() ?: 'Aucune valeur rattachee.') }}</div>
                            </div>
                        @else
                            <div class="summary-box">
                                <strong>Produit parent</strong>
                                <div style="margin-top:8px; line-height:1.6;">Cette fiche sert de famille pour les variantes ci-dessous.</div>
                            </div>
                            <div class="summary-box">
                                <strong>Nombre de variantes</strong>
                                <div style="margin-top:8px; line-height:1.6;">{{ $product->variants->count() }}</div>
                            </div>
                        @endif
                    </div>

                    @if ($product->variants->isNotEmpty())
                        <div class="summary-box" style="margin-top:14px;">
                            <strong>Variantes disponibles</strong>
                            <div class="chip-row" style="margin-top:10px;">
                                @foreach ($product->variants as $variant)
                                    <a href="{{ route('products.show', $variant) }}" class="badge badge-muted">{{ $variant->display_name }}</a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @else
                    <div class="summary-box">
                        <strong>Produit simple</strong>
                        <div class="help" style="margin-top:8px;">Ce produit n appartient pas encore a une famille de variantes.</div>
                    </div>
                @endif
            </div>

            <div class="card">
                <h3 class="section-title">Descriptions metier</h3>
                <div class="grid" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
                    <div class="summary-box">
                        <strong>Description vente</strong>
                        <div style="margin-top:8px; line-height:1.6;">{{ $product->sales_description ?: 'Aucune description commerciale specifique.' }}</div>
                    </div>
                    <div class="summary-box">
                        <strong>Description achat</strong>
                        <div style="margin-top:8px; line-height:1.6;">{{ $product->purchase_description ?: 'Aucune description achat specifique.' }}</div>
                    </div>
                </div>
                <div class="grid" style="grid-template-columns: repeat(2, minmax(0, 1fr)); margin-top:14px;">
                    <div class="summary-box">
                        <strong>Unite commerciale vente</strong>
                        <div style="margin-top:8px; line-height:1.6;">{{ $product->salesUnitSummary() ?: 'Non renseignee' }}</div>
                    </div>
                    <div class="summary-box">
                        <strong>Unite achat</strong>
                        <div style="margin-top:8px; line-height:1.6;">{{ $product->purchaseUnitSummary() ?: 'Non renseignee' }}</div>
                    </div>
                </div>
                <div class="grid" style="grid-template-columns: repeat(2, minmax(0, 1fr)); margin-top:14px;">
                    <div class="summary-box">
                        <strong>Motif blocage vente</strong>
                        <div style="margin-top:8px; line-height:1.6;">{{ $product->saleBlockSummary() ?: 'Aucun blocage de vente actif.' }}</div>
                    </div>
                    <div class="summary-box">
                        <strong>Motif blocage achat</strong>
                        <div style="margin-top:8px; line-height:1.6;">{{ $product->purchaseBlockSummary() ?: 'Aucun blocage d achat actif.' }}</div>
                    </div>
                </div>
                <div class="chip-row" style="margin-top:14px;">
                    @if ($product->saleTaxRule)
                        @include('partials.erp-status-badge', ['label' => 'Taxe vente : '.$product->saleTaxRule->name, 'tone' => 'muted'])
                    @endif
                    @if ($product->purchaseTaxRule)
                        @include('partials.erp-status-badge', ['label' => 'Taxe achat : '.$product->purchaseTaxRule->name, 'tone' => 'muted'])
                    @endif
                </div>
            </div>

            <div class="card">
                <h3 class="section-title">Fournisseurs produit</h3>
                @unless ($canViewProductCosts)
                    <div class="summary-box" style="margin-bottom:14px;">
                        <strong>Couts fournisseurs masques</strong>
                        <div class="help" style="margin-top:8px;">Les montants achat et couts fournisseurs sont reserves aux profils autorises.</div>
                    </div>
                @endunless
                @if ($product->supplierInfos->isEmpty())
                    <div class="summary-box">
                        <strong>Aucun fournisseur specifique</strong>
                        <div class="help" style="margin-top:8px;">Le produit utilisera son cout achat standard et ses regles generales tant qu aucun fournisseur prefere n est renseigne.</div>
                    </div>
                @else
                    <div class="table-wrap">
                        <table>
                            <thead>
                            <tr>
                                <th>Fournisseur</th>
                                <th>Statut</th>
                                <th>Reference</th>
                                <th>Nom fournisseur</th>
                                <th>Mini</th>
                                @if ($canViewProductCosts)
                                    <th>Cout</th>
                                @endif
                                <th>Delai</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($product->supplierInfos as $supplierInfo)
                                <tr>
                                    <td>{{ $supplierInfo->supplier?->name ?? '-' }}</td>
                                    <td>
                                        @include('partials.erp-status-badge', [
                                            'label' => $supplierInfo->is_preferred ? 'Prefere' : 'Secondaire',
                                            'tone' => $supplierInfo->is_preferred ? 'success' : 'muted',
                                        ])
                                    </td>
                                    <td>{{ $supplierInfo->supplier_product_code ?: '-' }}</td>
                                    <td>{{ $supplierInfo->supplier_product_name ?: '-' }}</td>
                                    <td>{{ $supplierInfo->min_qty !== null ? number_format((float) $supplierInfo->min_qty, 3, ',', ' ') : '-' }}</td>
                                    @if ($canViewProductCosts)
                                        <td>{{ $supplierInfo->unit_cost !== null ? number_format((float) $supplierInfo->unit_cost, 0, ',', ' ') . ' XOF' : '-' }}</td>
                                    @endif
                                    <td>{{ $supplierInfo->lead_time_days !== null ? $supplierInfo->lead_time_days . ' j' : '-' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="card">
                <h3 class="section-title">Archivage et suppression</h3>
                @if ($deletionGuard['can_delete'])
                    <div class="summary-box">
                        <strong>Suppression autorisee</strong>
                        <div class="help" style="margin-top:8px;">Ce produit ne porte encore aucune reference metier. Il peut etre supprime definitivement si tu n en as plus besoin.</div>
                    </div>
                @else
                    <div class="summary-box">
                        <strong>Suppression bloquee</strong>
                        <div class="help" style="margin-top:8px;">Ce produit est deja utilise dans l historique. Pour conserver les ventes, achats et mouvements de stock, archive-le au lieu de le supprimer.</div>
                        <div class="chip-row">
                            @foreach ($deletionGuard['usage'] as $usage)
                                <span class="badge badge-muted">{{ $usage['label'] }} : {{ $usage['count'] }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif
                <div class="lifecycle-actions">
                    @if ($product->is_active)
                        @include('partials.erp-status-badge', ['label' => 'Produit visible dans les nouveaux documents', 'tone' => 'success'])
                    @else
                        @include('partials.erp-status-badge', ['label' => 'Produit retire des nouveaux documents', 'tone' => 'warning'])
                    @endif
                </div>
            </div>

            @if ($product->tracking_type !== 'none')
                <div class="card">
                    <h3 class="section-title">Lots et suivi</h3>
                    @if ($trackedLots->isEmpty())
                        <div class="summary-box">
                            <strong>Aucun lot ou numero de serie</strong>
                            <div class="help" style="margin-top:8px;">Aucune reception tracee n a encore cree de lot pour ce produit.</div>
                        </div>
                    @else
                        <div class="table-wrap">
                            <table>
                                <thead>
                                <tr>
                                    <th>Reference</th>
                                    <th>Type</th>
                                    <th>Depot</th>
                                    <th>Quantite</th>
                                    <th>Disponible</th>
                                    <th>Peremption</th>
                                    <th>Reception</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach ($trackedLots as $lot)
                                    <tr>
                                        <td>{{ $lot->displayCode() }}</td>
                                        <td>{{ $lot->tracking_type === 'serial' ? 'Numero de serie' : 'Lot' }}</td>
                                        <td>{{ $lot->warehouse?->name ?? '-' }}</td>
                                        <td>{{ number_format((float) $lot->quantity_received, 3, ',', ' ') }}</td>
                                        <td>{{ number_format((float) $lot->quantity_available, 3, ',', ' ') }}</td>
                                        <td>
                                            @if ($lot->expires_at)
                                                @include('partials.erp-status-badge', [
                                                    'label' => $lot->expires_at->format('d/m/Y'),
                                                    'tone' => $lot->isExpired() ? 'danger' : 'warning',
                                                ])
                                            @else
                                                <span class="muted">-</span>
                                            @endif
                                        </td>
                                        <td>{{ $lot->goodsReceipt?->receipt_number ?? '-' }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endif

            <div class="card">
                <h3 class="section-title">Stock par magasin</h3>
                @if ($stockByWarehouse->isEmpty())
                    <div class="empty-state" style="padding:20px 0 4px;">
                        <div class="muted">Aucun stock disponible pour le moment sur ce produit.</div>
                    </div>
                @else
                    <div class="stock-balance-grid">
                        @foreach ($stockByWarehouse as $balance)
                            <div class="stock-balance-card">
                                <strong>{{ $balance->warehouse?->name ?? 'Magasin inconnu' }}</strong>
                                <div class="muted" style="margin-top:4px;">{{ $balance->branch?->name ?? 'Agence inconnue' }}</div>
                                <div class="value" style="font-size:24px; margin-top:10px; font-weight:800;">{{ number_format((float) $balance->balance, 3, ',', ' ') }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="card table-wrap">
                <h3 class="section-title">Derniers mouvements de stock</h3>
                <table>
                    <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Magasin</th>
                        <th>Entree</th>
                        <th>Sortie</th>
                        <th>Motif</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($recentMovements as $movement)
                        <tr>
                            <td>{{ $movement->movement_date?->format('d/m/Y') }}</td>
                            <td>{{ ucfirst(str_replace('_', ' ', $movement->movement_type)) }}</td>
                            <td>{{ $movement->warehouse?->name ?? '-' }}</td>
                            <td>{{ number_format((float) $movement->quantity_in, 3, ',', ' ') }}</td>
                            <td>{{ number_format((float) $movement->quantity_out, 3, ',', ' ') }}</td>
                            <td>{{ $movement->reason ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="muted">Aucun mouvement de stock pour ce produit.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    @include('partials.activity-history', [
        'activities' => $recentActivities,
        'title' => 'Historique des actions',
        'description' => 'Retrouve les creations, mises a jour, archivages et autres actions recentes sur cette fiche produit.',
        'sectionId' => 'activity-history',
    ])
@endsection

