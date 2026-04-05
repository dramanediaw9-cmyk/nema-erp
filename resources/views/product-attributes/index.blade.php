@extends('layouts.app')

@section('title', 'Attributs produit - Nema ERP')
@section('page-title', 'Attributs produit')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Variantes et attributs produit</h2>
            <div class="muted">Definis les attributs comme couleur, taille, format ou conditionnement, puis leurs valeurs avant de creer des variantes.</div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="{{ route('products.index') }}" class="button button-secondary">Retour catalogue</a>
            <a href="{{ route('products.create') }}" class="button button-primary">Nouveau produit</a>
        </div>
    </div>

    <div class="split">
        <section class="card">
            <h3 class="section-title">Nouvel attribut</h3>
            <form method="POST" action="{{ route('product-attributes.store') }}" class="form-grid">
                @csrf
                <div>
                    <label for="attribute_name">Nom</label>
                    <input id="attribute_name" type="text" name="name" value="{{ old('name') }}" placeholder="Ex: Couleur" required>
                    @error('name')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="attribute_code">Code</label>
                    <input id="attribute_code" type="text" name="code" value="{{ old('code') }}" placeholder="Ex: COLOR">
                    <div class="help">Laisser vide pour une generation simple.</div>
                    @error('code')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="attribute_active">Statut</label>
                    <select id="attribute_active" name="is_active">
                        <option value="1" @selected(old('is_active', '1') === '1')>Actif</option>
                        <option value="0" @selected(old('is_active') === '0')>Inactif</option>
                    </select>
                </div>
                <div class="full">
                    <button type="submit" class="button button-primary">Ajouter l attribut</button>
                </div>
            </form>
        </section>

        <aside class="card">
            <h3 class="section-title">Bon usage</h3>
            <div class="summary-box">
                <strong>Exemples adaptes</strong>
                <div class="help" style="margin-top:8px;">Format, Couleur, Taille, Pack, Conditionnement, Parfum, Volume.</div>
            </div>
            <div class="summary-box" style="margin-top:12px;">
                <strong>Conseil</strong>
                <div class="help" style="margin-top:8px;">Garde un produit parent simple, puis cree les variantes vendables avec une combinaison unique de valeurs.</div>
            </div>
        </aside>
    </div>

    <section class="card" style="margin-top:18px;">
        <div class="section-title-row" style="display:flex; justify-content:space-between; gap:16px; align-items:flex-start; flex-wrap:wrap;">
            <div>
                <h3 class="section-title" style="margin-bottom:6px;">Attributs existants</h3>
                <div class="muted">Ajoute ici les valeurs disponibles pour chaque attribut.</div>
            </div>
            <div class="badge badge-muted">{{ $attributes->count() }} attribut(s)</div>
        </div>

        @forelse ($attributes as $attribute)
            <div class="card" style="margin-top:14px; padding:16px; background:linear-gradient(180deg, rgba(255,255,255,.96) 0%, rgba(250,245,238,.92) 100%);">
                <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:center;">
                    <div>
                        <strong style="font-size:18px;">{{ $attribute->name }}</strong>
                        <div class="muted">Code {{ $attribute->code }} · {{ $attribute->is_active ? 'Actif' : 'Inactif' }}</div>
                    </div>
                    <div class="chip-row">
                        @forelse ($attribute->values as $value)
                            <span class="badge badge-muted">{{ $value->value }}</span>
                        @empty
                            <span class="badge badge-warning">Aucune valeur</span>
                        @endforelse
                    </div>
                </div>

                <form method="POST" action="{{ route('product-attributes.values.store', $attribute) }}" class="form-grid" style="margin-top:14px;">
                    @csrf
                    <div>
                        <label for="value_{{ $attribute->id }}">Nouvelle valeur</label>
                        <input id="value_{{ $attribute->id }}" type="text" name="value" placeholder="Ex: Rouge" required>
                    </div>
                    <div>
                        <label for="value_code_{{ $attribute->id }}">Code</label>
                        <input id="value_code_{{ $attribute->id }}" type="text" name="code" placeholder="Ex: RED">
                    </div>
                    <div>
                        <label for="value_active_{{ $attribute->id }}">Statut</label>
                        <select id="value_active_{{ $attribute->id }}" name="is_active">
                            <option value="1">Actif</option>
                            <option value="0">Inactif</option>
                        </select>
                    </div>
                    <div style="align-self:end;">
                        <button type="submit" class="button button-secondary">Ajouter une valeur</button>
                    </div>
                </form>
            </div>
        @empty
            <div class="empty-state" style="padding:32px 0 12px;">
                <div class="muted">Aucun attribut produit pour le moment. Cree d abord Couleur, Taille, Format ou Pack.</div>
            </div>
        @endforelse
    </section>
@endsection
