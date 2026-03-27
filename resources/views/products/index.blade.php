@extends('layouts.app')

@section('title', 'Produits - Nema ERP')
@section('page-title', 'Catalogue produits')

@section('content')
    <style>
        .product-cell {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 10px 12px;
            border-radius: 22px;
            background: linear-gradient(180deg, #ffffff 0%, #f7fbff 100%);
            border: 1px solid #dbe4f0;
            box-shadow: 0 14px 26px rgba(15, 23, 42, 0.05);
            text-decoration: none;
        }
        .product-thumb {
            width: 76px;
            height: 76px;
            flex: 0 0 76px;
            border-radius: 22px;
            object-fit: cover;
            border: 1px solid #d7deea;
            background: #fff;
            box-shadow: 0 12px 22px rgba(15, 23, 42, 0.08);
        }
        .product-thumb-fallback {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 76px;
            height: 76px;
            flex: 0 0 76px;
            border-radius: 22px;
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            color: #17304f;
            font-weight: 900;
            letter-spacing: .04em;
            border: 1px solid #d7deea;
            box-shadow: 0 12px 22px rgba(15, 23, 42, 0.08);
        }
        .product-card-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 6px;
        }
        .product-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            background: #eef4ff;
            border: 1px solid #d9e5f5;
            color: #33527b;
        }
        .product-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
    </style>

    <div class="page-head">
        <div>
            <h2 style="margin:0;">Produits et services</h2>
            <div class="muted">Le catalogue sert directement au stock, aux ventes, aux achats et au point de vente.</div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            @allowed('imports.manage')
                <a href="{{ route('imports.index') }}" class="button button-secondary">Importer CSV</a>
            @endallowed
            @allowed('products.manage')
                <a href="{{ route('products.create') }}" class="button button-primary">Nouveau produit</a>
            @endallowed
        </div>
    </div>

    <section class="card table-wrap">
        <table>
            <thead>
            <tr>
                <th>SKU</th>
                <th>Code-barres</th>
                <th>Produit</th>
                <th>Type</th>
                <th>Categorie</th>
                <th>PU vente</th>
                <th>PU achat</th>
                <th>Stock mini</th>
                <th>Statut</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($products as $product)
                <tr>
                    <td><strong>{{ $product->sku }}</strong></td>
                    <td>{{ $product->barcode ?: 'Non renseigne' }}</td>
                    <td>
                        <a href="{{ route('products.show', $product) }}" class="product-cell">
                            @if ($product->image_url)
                                <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="product-thumb">
                            @else
                                <span class="product-thumb-fallback">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($product->name, 0, 2)) }}</span>
                            @endif
                            <div style="min-width:0;">
                                <div style="font-weight:800; color:#15263d; line-height:1.32;">{{ $product->name }}</div>
                                <div class="product-card-meta">
                                    <span class="product-pill">{{ $product->unit }}</span>
                                    @if ($product->category?->name)
                                        <span class="product-pill">{{ $product->category->name }}</span>
                                    @endif
                                </div>
                            </div>
                        </a>
                    </td>
                    <td>{{ $product->type === 'service' ? 'Service' : 'Stockable' }}</td>
                    <td>{{ $product->category?->name ?? 'Sans categorie' }}</td>
                    <td>{{ number_format((float) $product->sale_price, 0, ',', ' ') }} XOF</td>
                    <td>{{ number_format((float) $product->purchase_price, 0, ',', ' ') }} XOF</td>
                    <td>{{ number_format((float) $product->min_stock, 3, ',', ' ') }}</td>
                    <td><span class="badge {{ $product->is_active ? 'badge-success' : 'badge-muted' }}">{{ $product->is_active ? 'Actif' : 'Inactif' }}</span></td>
                    <td>
                        <div class="product-actions">
                            <a href="{{ route('products.show', $product) }}" class="button button-secondary">Voir</a>
                            @allowed('products.manage')
                                <a href="{{ route('products.edit', $product) }}" class="button button-secondary">Modifier</a>
                            @endallowed
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="muted">Aucun produit disponible.</td>
                </tr>
            @endforelse
            </tbody>
        </table>

        @if (method_exists($products, 'links'))
            <div style="margin-top:18px;">{{ $products->links() }}</div>
        @endif
    </section>
@endsection
