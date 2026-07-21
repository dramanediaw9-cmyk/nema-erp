@extends('layouts.app')

@php
    $productLabel = $businessVocabulary['product'] ?? 'Produit';
    $productsLabel = $businessVocabulary['products'] ?? 'Produits';
@endphp

@section('title', $productsLabel.' POS - Nema ERP')
@section('page-title', $productsLabel.' POS')

@section('content')
    <div class="grid" style="gap:18px;">
        @include('pos.partials.backoffice-nav')

        <div class="page-head">
            <div>
                <h2 style="margin:0;">Catalogue PdV, variantes et combos</h2>
                <div class="muted">Pont direct avec les {{ strtolower($productsLabel) }}, categories, attributs et enrichissements POS comme categories menu, etiquettes et choix de combo.</div>
            </div>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <a href="{{ route('products.index') }}" class="button button-secondary">{{ $productsLabel }}</a>
                <a href="{{ route('categories.index') }}" class="button button-secondary">Categories</a>
                <a href="{{ route('product-attributes.index') }}" class="button button-secondary">Attributs</a>
            </div>
        </div>

        <div class="grid stats-grid">
            <div class="card"><div class="muted">{{ $productsLabel }}</div><div class="stat-value">{{ $data['summary']['products'] }}</div></div>
            <div class="card"><div class="muted">Variantes</div><div class="stat-value">{{ $data['summary']['variants'] }}</div></div>
            <div class="card"><div class="muted">Categories</div><div class="stat-value">{{ $data['summary']['categories'] }}</div></div>
            <div class="card"><div class="muted">Attributs</div><div class="stat-value">{{ $data['summary']['attributes'] }}</div></div>
            <div class="card"><div class="muted">Combos</div><div class="stat-value">{{ $data['summary']['combos'] }}</div></div>
            <div class="card"><div class="muted">Categories PdV</div><div class="stat-value">{{ $data['summary']['menu_categories'] }}</div></div>
            <div class="card"><div class="muted">Etiquettes</div><div class="stat-value">{{ $data['summary']['tags'] }}</div></div>
        </div>

        <form method="GET" class="card" style="display:grid; grid-template-columns:minmax(0,1fr) auto; gap:10px; align-items:end;">
            <div>
                <label for="product_search">Rechercher les produits a configurer</label>
                <input id="product_search" name="product_search" value="{{ $productOptionsSearch }}" placeholder="Nom, SKU ou code-barres">
                <div class="help">{{ $productOptions->count() }} resultat(s) affiche(s), sur {{ number_format($data['summary']['products'], 0, ',', ' ') }} produits. Affine la recherche pour retrouver n importe quel article.</div>
            </div>
            <button type="submit" class="button button-primary">Rechercher</button>
        </form>

        @allowed('pos.manage')
            <div class="split">
                <form method="POST" action="{{ route('pos.combo-choices.store') }}" class="card form-grid">
                    @csrf
                    <div class="full"><h3 class="section-title">Nouveau choix de combo</h3></div>
                    <div>
                        <label for="combo_name">Nom</label>
                        <input id="combo_name" name="name" value="{{ old('name') }}" required>
                    </div>
                    <div>
                        <label for="combo_parent_product_id">{{ $productLabel }} parent</label>
                        <select id="combo_parent_product_id" name="parent_product_id">
                            <option value="">Aucun</option>
                            @foreach ($productOptions as $product)
                                <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->sku }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="combo_pricing_mode">Tarification</label>
                        <select id="combo_pricing_mode" name="pricing_mode" required>
                            <option value="sum">Somme composants</option>
                            <option value="fixed">Prix fixe</option>
                            <option value="free_choice">Choix libre</option>
                        </select>
                    </div>
                    <div>
                        <label for="combo_price_override">Prix fixe</label>
                        <input id="combo_price_override" name="price_override" type="number" min="0" step="0.01" value="{{ old('price_override') }}">
                    </div>
                    <div>
                        <label for="combo_max_selectable">Max selections</label>
                        <input id="combo_max_selectable" name="max_selectable" type="number" min="1" max="20" value="{{ old('max_selectable', 2) }}">
                    </div>
                    <div class="full">
                        <label for="combo_component_product_ids">Composants</label>
                        <select id="combo_component_product_ids" name="component_product_ids[]" multiple size="6">
                            @foreach ($productOptions as $product)
                                <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->sku }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="full actions">
                        <button type="submit" class="button button-primary">Enregistrer le combo</button>
                    </div>
                </form>

                <form method="POST" action="{{ route('pos.menu-categories.store') }}" class="card form-grid">
                    @csrf
                    <div class="full"><h3 class="section-title">Categorie {{ strtolower($productsLabel) }} du PdV</h3></div>
                    <div>
                        <label for="menu_category_name">Nom</label>
                        <input id="menu_category_name" name="name" value="{{ old('name') }}" required>
                    </div>
                    <div>
                        <label for="menu_category_color">Couleur</label>
                        <input id="menu_category_color" name="color" value="{{ old('color', '#1f8ef1') }}">
                    </div>
                    <div>
                        <label for="menu_category_sort_order">Ordre</label>
                        <input id="menu_category_sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', 0) }}">
                    </div>
                    <div class="full">
                        <label for="menu_category_product_ids">{{ $productsLabel }} visibles</label>
                        <select id="menu_category_product_ids" name="product_ids[]" multiple size="6">
                            @foreach ($productOptions as $product)
                                <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->sku }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="full actions">
                        <button type="submit" class="button button-primary">Enregistrer la categorie</button>
                    </div>
                </form>
            </div>

            <form method="POST" action="{{ route('pos.product-tags.store') }}" class="card form-grid">
                @csrf
                <div class="full"><h3 class="section-title">Nouvelle etiquette {{ strtolower($productLabel) }} POS</h3></div>
                <div>
                    <label for="tag_name">Nom</label>
                    <input id="tag_name" name="name" value="{{ old('name') }}" required>
                </div>
                <div>
                    <label for="tag_color">Couleur</label>
                    <input id="tag_color" name="color" value="{{ old('color', '#16a34a') }}">
                </div>
                <div class="full">
                    <label for="tag_product_ids">{{ $productsLabel }} lies</label>
                    <select id="tag_product_ids" name="product_ids[]" multiple size="6">
                        @foreach ($productOptions as $product)
                            <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->sku }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="full actions">
                    <button type="submit" class="button button-primary">Enregistrer l etiquette</button>
                </div>
            </form>
        @endallowed

        <div class="split">
            <section class="card">
                <h3 class="section-title">Combos</h3>
                <div class="summary-stack">
                    @forelse ($data['combos'] as $combo)
                        <div class="summary-box">
                            <strong>{{ $combo->name }}</strong>
                            <div class="muted" style="margin-top:8px;">{{ $combo->parentProduct?->name ?? 'Sans '.strtolower($productLabel).' parent' }} · {{ strtoupper($combo->pricing_mode) }}</div>
                            <div class="help" style="margin-top:8px;">{{ count($combo->component_product_ids ?? []) }} composant(s)</div>
                        </div>
                    @empty
                        <div class="muted">Aucun combo configure.</div>
                    @endforelse
                </div>
            </section>

            <section class="card">
                <h3 class="section-title">Categories PdV et etiquettes</h3>
                <div class="summary-stack">
                    @foreach ($data['menu_categories'] as $category)
                        <div class="summary-box">
                            <strong>{{ $category->name }}</strong>
                            <div class="help" style="margin-top:8px;">{{ count($category->product_ids ?? []) }} {{ strtolower($productLabel) }}(s) · ordre {{ $category->sort_order }}</div>
                        </div>
                    @endforeach
                    @foreach ($data['product_tags'] as $tag)
                        <div class="summary-box">
                            <strong>{{ $tag->name }}</strong>
                            <div class="help" style="margin-top:8px;">{{ count($tag->product_ids ?? []) }} {{ strtolower($productLabel) }}(s) lies</div>
                        </div>
                    @endforeach
                    @if ($data['menu_categories']->isEmpty() && $data['product_tags']->isEmpty())
                        <div class="muted">Aucune categorie PdV ni etiquette configuree.</div>
                    @endif
                </div>
            </section>
        </div>

        <div class="split">
            <section class="card">
                <h3 class="section-title">Categories catalogue</h3>
                <div class="summary-stack">
                    @forelse ($data['categories'] as $category)
                        <div class="summary-box">
                            <strong>{{ $category->name }}</strong>
                            <div class="help" style="margin-top:8px;">{{ $category->products_count }} {{ strtolower($productLabel) }}(s)</div>
                        </div>
                    @empty
                        <div class="muted">Aucune categorie catalogue.</div>
                    @endforelse
                </div>
            </section>

            <section class="card">
                <h3 class="section-title">Attributs et variantes</h3>
                <div class="summary-stack">
                    @forelse ($data['attributes'] as $attribute)
                        <div class="summary-box">
                            <strong>{{ $attribute->name }}</strong>
                            <div class="help" style="margin-top:8px;">{{ $attribute->values_count }} valeur(s)</div>
                        </div>
                    @empty
                        <div class="muted">Aucun attribut {{ strtolower($productLabel) }}.</div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
@endsection
