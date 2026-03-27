@extends('layouts.app')

@section('title', $product->name.' - Produit - Nema ERP')
@section('page-title', 'Fiche produit')

@section('content')
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
        @media (max-width: 960px) {
            .product-detail-layout {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="page-head">
        <div>
            <h2 style="margin:0;">{{ $product->name }}</h2>
            <div class="muted">{{ $product->sku }} · {{ $product->barcode ?: 'Code-barres non renseigne' }} · {{ $product->category?->name ?? 'Sans categorie' }}</div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="{{ route('products.index') }}" class="button button-secondary">Retour catalogue</a>
            @allowed('products.manage')
                <a href="{{ route('products.edit', $product) }}" class="button button-primary">Modifier</a>
            @endallowed
        </div>
    </div>

    <div class="product-detail-layout">
        <section class="product-photo-card">
            @if ($product->image_url)
                <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="product-photo-large">
            @else
                <div class="product-photo-placeholder">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($product->name, 0, 2)) }}</div>
            @endif
            <div style="margin-top:16px; display:grid; gap:10px;">
                <div class="summary-box">
                    <strong>Photo produit</strong>
                    <div class="help" style="margin-top:8px;">Cette photo est reprise dans le catalogue, au point de vente et sur les ecrans d echange.</div>
                </div>
                @if ($product->description)
                    <div class="summary-box">
                        <strong>Description</strong>
                        <div style="margin-top:8px; line-height:1.6;">{{ $product->description }}</div>
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
                <div class="product-meta-box">
                    <div class="label">Prix d achat</div>
                    <div class="value">{{ number_format((float) $product->purchase_price, 0, ',', ' ') }} XOF</div>
                </div>
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
                        <div class="label">Unite</div>
                        <div class="value" style="font-size:20px;">{{ $product->unit }}</div>
                    </div>
                    <div class="kpi">
                        <div class="label">Statut</div>
                        <div class="value" style="font-size:20px;">{{ $product->is_active ? 'Actif' : 'Inactif' }}</div>
                    </div>
                </div>
            </div>

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
@endsection
