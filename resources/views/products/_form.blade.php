<style>
    .product-media-layout {
        display: grid;
        gap: 18px;
        grid-template-columns: minmax(0, 1.15fr) minmax(280px, .85fr);
        margin-bottom: 20px;
        align-items: start;
    }
    .product-upload-card,
    .product-camera-card {
        border: 1px solid var(--line);
        border-radius: 24px;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        padding: 18px;
    }
    .product-dropzone {
        border: 2px dashed #bfd3ee;
        border-radius: 22px;
        padding: 16px;
        background: #f8fbff;
        transition: border-color .15s ease, background .15s ease, transform .15s ease;
        cursor: pointer;
    }
    .product-dropzone.is-dragover {
        border-color: #2e6cf6;
        background: #eef4ff;
        transform: translateY(-1px);
    }
    .product-dropzone input[type="file"] {
        position: absolute;
        width: 1px;
        height: 1px;
        opacity: 0;
        pointer-events: none;
    }
    .product-preview {
        width: 100%;
        aspect-ratio: 1 / 1;
        border-radius: 22px;
        overflow: hidden;
        background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
        border: 1px solid #dbe3ef;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #17304f;
    }
    .product-preview img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    .product-preview-placeholder {
        font-size: 72px;
        font-weight: 900;
        letter-spacing: .04em;
    }
    .product-upload-meta {
        display: grid;
        gap: 10px;
        margin-top: 16px;
    }
    .product-upload-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 4px;
    }
    .product-upload-note {
        padding: 12px 14px;
        border-radius: 16px;
        background: #fff;
        border: 1px solid #dbe3ef;
        color: #4b5e75;
        font-size: 14px;
    }
    .product-camera-card[hidden] {
        display: none !important;
    }
    .product-camera-frame {
        width: 100%;
        aspect-ratio: 4 / 3;
        background: #0f172a;
        border-radius: 22px;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #c9d5e4;
    }
    .product-camera-frame video,
    .product-camera-frame canvas {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    .product-camera-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 14px;
    }
    .product-current-photo {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 14px;
        border-radius: 18px;
        background: #fff;
        border: 1px solid #dbe3ef;
    }
    .product-current-photo img {
        width: 74px;
        height: 74px;
        object-fit: cover;
        border-radius: 18px;
        border: 1px solid #dbe3ef;
        background: #fff;
    }
    .product-section-grid {
        display: grid;
        gap: 18px;
        margin-top: 18px;
    }
    .product-toggle-card {
        display: grid;
        gap: 12px;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    }
    .product-toggle-box {
        border: 1px solid var(--line);
        border-radius: 18px;
        background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
        padding: 16px;
    }
    .product-toggle-box label {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        margin: 0;
    }
    .product-toggle-box input[type="checkbox"] {
        margin-top: 4px;
        width: 18px;
        height: 18px;
    }
    .product-toggle-box strong {
        display: block;
        margin-bottom: 4px;
    }
    .product-variant-grid {
        display: grid;
        gap: 14px;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        margin-top: 16px;
    }
    .product-variant-card {
        border: 1px solid var(--line);
        border-radius: 18px;
        background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
        padding: 14px 16px;
    }
    .product-variant-options {
        display: grid;
        gap: 10px;
        margin-top: 12px;
    }
    .product-variant-option {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 10px 12px;
        border-radius: 14px;
        border: 1px solid #dbe3ef;
        background: #fff;
        margin: 0;
        cursor: pointer;
    }
    .product-variant-option input[type="checkbox"] {
        margin-top: 2px;
    }
    .product-variant-option small {
        display: block;
        color: var(--muted);
        margin-top: 2px;
    }
    .product-variant-disabled {
        opacity: .56;
    }
    @media (max-width: 980px) {
        .product-media-layout {
            grid-template-columns: 1fr;
        }
    }
</style>

@php
    $canViewProductCosts = auth()->user()?->hasPermission('products.cost.view');
    $supplierRows = collect(old('supplier_infos', $product->supplierInfos->map(fn ($info) => [
        'supplier_id' => $info->supplier_id,
        'supplier_product_code' => $info->supplier_product_code,
        'supplier_product_name' => $info->supplier_product_name,
        'min_qty' => $info->min_qty,
        'unit_cost' => $info->unit_cost,
        'lead_time_days' => $info->lead_time_days,
        'is_preferred' => $info->is_preferred,
    ])->all()));

    if ($supplierRows->count() < 4) {
        $supplierRows = $supplierRows->merge(array_fill(0, 4 - $supplierRows->count(), [
            'supplier_id' => '',
            'supplier_product_code' => '',
            'supplier_product_name' => '',
            'min_qty' => '',
            'unit_cost' => '',
            'lead_time_days' => '',
            'is_preferred' => false,
        ]));
    }
@endphp

<div class="card">
    <div class="product-media-layout">
        <section class="product-upload-card">
            <label style="margin-bottom:12px;">Photo produit</label>
            <div class="product-dropzone" id="product-dropzone" tabindex="0">
                <input id="image" type="file" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" capture="environment">
                <div class="product-preview" id="product-preview">
                    @if ($product->image_url && ! old('remove_image'))
                        <img src="{{ $product->image_url }}" alt="{{ $product->name }}">
                    @else
                        <div class="product-preview-placeholder" id="product-preview-placeholder">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(old('name', $product->name ?: 'PR'), 0, 2)) }}</div>
                    @endif
                </div>
                <div class="product-upload-meta">
                    <strong id="product-upload-title">{{ $product->image_url && ! old('remove_image') ? 'Photo actuelle du produit' : 'Ajoute une photo produit' }}</strong>
                    <div class="muted" id="product-upload-filename">{{ $product->image_url && ! old('remove_image') ? 'Image deja enregistree sur ce produit.' : 'Glisse-depose une image ici ou utilise les boutons ci-dessous.' }}</div>
                    <div class="product-upload-actions">
                        <button type="button" class="button button-secondary" id="product-pick-image">Choisir une photo</button>
                        <button type="button" class="button button-secondary" id="product-open-camera">Prendre une photo</button>
                    </div>
                    <div class="product-upload-note">Formats acceptes : JPG, PNG, WEBP. La photo sera visible dans le catalogue, la fiche produit et le point de vente.</div>
                    @error('image')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>
        </section>

        <section class="product-camera-card" id="product-camera-card" hidden>
            <label style="margin-bottom:12px;">Capture camera</label>
            <div class="product-camera-frame" id="product-camera-frame">
                <video id="product-camera-video" autoplay playsinline muted></video>
                <canvas id="product-camera-canvas" hidden></canvas>
            </div>
            <div class="product-camera-actions">
                <button type="button" class="button button-primary" id="product-capture-image">Capturer</button>
                <button type="button" class="button button-secondary" id="product-stop-camera">Fermer la camera</button>
            </div>
            <div class="help" style="margin-top:10px;">Sur mobile, utilise la camera arriere quand elle est disponible.</div>
        </section>
    </div>

    @if ($product->image_url)
        <div class="product-current-photo" style="margin-bottom:20px;">
            <img src="{{ $product->image_url }}" alt="{{ $product->name }}">
            <div style="display:grid; gap:8px;">
                <strong>Photo actuellement enregistree</strong>
                <label style="display:flex; align-items:center; gap:10px; margin:0;">
                    <input type="checkbox" name="remove_image" id="remove_image" value="1" @checked(old('remove_image'))>
                    Supprimer la photo actuelle lors de l enregistrement
                </label>
            </div>
        </div>
    @else
        <input type="hidden" name="remove_image" value="0">
    @endif

    <div class="form-grid">
        <div>
            <label for="sku">Reference interne</label>
            <input id="sku" type="text" name="sku" value="{{ old('sku', $product->sku) }}">
            <div class="help">Laisser vide pour generation automatique.</div>
            @error('sku')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <div>
            <label for="barcode">Code-barres</label>
            <input id="barcode" type="text" name="barcode" value="{{ old('barcode', $product->barcode) }}" placeholder="Ex: 3700000000012">
            <div class="help">Utilise pour la recherche rapide et le scan au point de vente.</div>
            @error('barcode')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <div>
            <label for="name">Nom</label>
            <input id="name" type="text" name="name" value="{{ old('name', $product->name) }}" required>
            @error('name')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <div>
            <label for="category_id">Categorie</label>
            <select id="category_id" name="category_id">
                <option value="">Sans categorie</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) old('category_id', $product->category_id) === (string) $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            @error('category_id')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <div>
            <label for="type">Type</label>
            <select id="type" name="type" required>
                <option value="stockable" @selected(old('type', $product->type) === 'stockable')>Article stockable</option>
                <option value="service" @selected(old('type', $product->type) === 'service')>Service</option>
            </select>
            @error('type')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <div>
            <label for="unit">Unite de base</label>
            <input id="unit" type="text" name="unit" value="{{ old('unit', $product->unit ?: 'unite') }}" required>
            <div class="help">Unite de stock et de reference interne.</div>
            @error('unit')<div class="field-error">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="product-section-grid">
         <section class="card" style="padding:18px;">
            <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:flex-start;">
                <div>
                    <h3 class="section-title">Famille et variantes</h3>
                    <div class="muted">Modele proche d Odoo: un produit parent peut porter des variantes avec attributs et valeurs distinctes.</div>
                </div>
                <a href="{{ route('product-attributes.index') }}" class="button button-secondary">Gerer les attributs</a>
            </div>

            <div class="form-grid" style="margin-top:16px;">
                <div>
                    <label for="parent_product_id">Produit parent</label>
                    <select id="parent_product_id" name="parent_product_id">
                        <option value="">Produit autonome</option>
                        @foreach ($variantParents as $parentProduct)
                            <option value="{{ $parentProduct->id }}" @selected((string) old('parent_product_id', $product->parent_product_id) === (string) $parentProduct->id)>{{ $parentProduct->name }}</option>
                        @endforeach
                    </select>
                    <div class="help">Choisis une famille si ce produit represente une variante comme une taille, une couleur ou un conditionnement.</div>
                    @error('parent_product_id')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label>Situation actuelle</label>
                    <div class="summary-box" id="variant-status-box">
                        @if ($product->is_variant && $product->parent)
                            <strong>Variante de {{ $product->parent->name }}</strong>
                            <div class="help" style="margin-top:8px;">{{ $product->variant_label ?: $product->variantValuesSummary() }}</div>
                        @elseif ($product->variants()->exists())
                            <strong>Produit parent</strong>
                            <div class="help" style="margin-top:8px;">Ce produit possede deja des variantes enfants.</div>
                        @else
                            <strong>Produit simple</strong>
                            <div class="help" style="margin-top:8px;">Aucun rattachement de variante defini pour l instant.</div>
                        @endif
                    </div>
                </div>
            </div>

            @if ($variantAttributes->isEmpty())
                <div class="summary-box" style="margin-top:16px;">
                    <strong>Aucun attribut disponible</strong>
                    <div class="help" style="margin-top:8px;">Ajoute d abord des attributs comme Couleur, Taille ou Conditionnement pour creer de vraies variantes.</div>
                </div>
            @else
                <div class="product-variant-grid" id="variant-attributes-grid">
                    @foreach ($variantAttributes as $attribute)
                        <section class="product-variant-card" data-attribute-card>
                            <strong>{{ $attribute->name }}</strong>
                            <div class="muted">Une seule valeur maximum par attribut.</div>
                            <div class="product-variant-options" data-attribute-group>
                                @foreach ($attribute->values as $value)
                                    <label class="product-variant-option">
                                        <input type="checkbox" name="variant_value_ids[]" value="{{ $value->id }}" @checked(in_array((int) $value->id, $selectedVariantValueIds, true))>
                                        <span>
                                            <strong style="font-size:14px;">{{ $value->value }}</strong>
                                            @if ($value->code)
                                                <small>Code : {{ $value->code }}</small>
                                            @endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                </div>
                @error('variant_value_ids')<div class="field-error" style="margin-top:12px;">{{ $message }}</div>@enderror
                @error('variant_value_ids.*')<div class="field-error" style="margin-top:12px;">{{ $message }}</div>@enderror
                <div class="help" style="margin-top:12px;">Exemple: produit parent = Tee-shirt, puis variante = Couleur: Bleu et Taille: M.</div>
            @endif
        </section>
        <section class="card" style="padding:18px;">
            <h3 class="section-title">Canaux et comportement Odoo</h3>
            <div class="product-toggle-card">
                <div class="product-toggle-box">
                    <input type="hidden" name="sale_ok" value="0">
                    <label for="sale_ok">
                        <input id="sale_ok" type="checkbox" name="sale_ok" value="1" @checked((bool) old('sale_ok', $product->sale_ok ?? true))>
                        <span>
                            <strong>Peut etre vendu</strong>
                            <span class="muted">Le produit apparait dans devis, commandes clients, factures et POS.</span>
                        </span>
                    </label>
                </div>
                <div class="product-toggle-box">
                    <input type="hidden" name="sale_blocked" value="0">
                    <label for="sale_blocked">
                        <input id="sale_blocked" type="checkbox" name="sale_blocked" value="1" @checked((bool) old('sale_blocked', $product->sale_blocked ?? false))>
                        <span>
                            <strong>Vente bloquee</strong>
                            <span class="muted">Retire temporairement le produit des nouveaux flux de vente sans le desactiver.</span>
                        </span>
                    </label>
                </div>
                <div class="product-toggle-box">
                    <input type="hidden" name="purchase_ok" value="0">
                    <label for="purchase_ok">
                        <input id="purchase_ok" type="checkbox" name="purchase_ok" value="1" @checked((bool) old('purchase_ok', $product->purchase_ok ?? true))>
                        <span>
                            <strong>Peut etre achete</strong>
                            <span class="muted">Le produit apparait dans demandes d achat, commandes fournisseurs et factures fournisseur.</span>
                        </span>
                    </label>
                </div>
                <div class="product-toggle-box">
                    <input type="hidden" name="purchase_blocked" value="0">
                    <label for="purchase_blocked">
                        <input id="purchase_blocked" type="checkbox" name="purchase_blocked" value="1" @checked((bool) old('purchase_blocked', $product->purchase_blocked ?? false))>
                        <span>
                            <strong>Achat bloque</strong>
                            <span class="muted">Empeche temporairement demandes, commandes et factures fournisseur sur ce produit.</span>
                        </span>
                    </label>
                </div>
            </div>
            @error('sale_ok')<div class="field-error">{{ $message }}</div>@enderror
            @error('sale_blocked')<div class="field-error">{{ $message }}</div>@enderror
            @error('purchase_ok')<div class="field-error">{{ $message }}</div>@enderror
            @error('purchase_blocked')<div class="field-error">{{ $message }}</div>@enderror
        </section>


        <section class="card" style="padding:18px;">
            <h3 class="section-title">Vente</h3>
            <div class="form-grid">
                <div>
                    <label for="sale_price">Prix de vente</label>
                    <input id="sale_price" type="number" step="0.01" min="0" name="sale_price" value="{{ old('sale_price', $product->sale_price ?: 0) }}" required>
                    @error('sale_price')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="sale_tax_rule_id">Taxe vente</label>
                    <select id="sale_tax_rule_id" name="sale_tax_rule_id">
                        <option value="">Aucune taxe</option>
                        @foreach ($taxRules as $taxRule)
                            <option value="{{ $taxRule->id }}" @selected((string) old('sale_tax_rule_id', $product->sale_tax_rule_id) === (string) $taxRule->id)>{{ $taxRule->name }} · {{ number_format((float) $taxRule->rate, 2, ',', ' ') }}%</option>
                        @endforeach
                    </select>
                    @error('sale_tax_rule_id')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="sales_unit_name">Unite commerciale vente</label>
                    <input id="sales_unit_name" type="text" name="sales_unit_name" value="{{ old('sales_unit_name', $product->sales_unit_name) }}" placeholder="Ex: carton">
                    <div class="help">Exemple: 1 carton = 24 unites.</div>
                    @error('sales_unit_name')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="sales_unit_ratio">Ratio vente</label>
                    <input id="sales_unit_ratio" type="number" step="0.001" min="0.001" name="sales_unit_ratio" value="{{ old('sales_unit_ratio', $product->sales_unit_ratio ?: 1) }}">
                    @error('sales_unit_ratio')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="invoice_policy">Politique de facturation</label>
                    <select id="invoice_policy" name="invoice_policy" required>
                        <option value="ordered" @selected(old('invoice_policy', $product->invoice_policy ?? 'ordered') === 'ordered')>Quantites commandees</option>
                        <option value="delivered" @selected(old('invoice_policy', $product->invoice_policy ?? 'ordered') === 'delivered')>Quantites livrees</option>
                    </select>
                    <div class="help">Prepare les cas ou la facturation depend de la livraison effective.</div>
                    @error('invoice_policy')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="full">
                    <label for="sale_block_reason">Motif blocage vente</label>
                    <textarea id="sale_block_reason" name="sale_block_reason" placeholder="Ex: rupture qualite, produit reserve, fin de gamme temporaire">{{ old('sale_block_reason', $product->sale_block_reason) }}</textarea>
                    <div class="help">Visible pour l equipe et repris dans les messages de blocage metier.</div>
                    @error('sale_block_reason')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="full">
                    <label for="sales_description">Description vente</label>
                    <textarea id="sales_description" name="sales_description" placeholder="Texte repris automatiquement dans devis, commandes et factures client">{{ old('sales_description', $product->sales_description) }}</textarea>
                    @error('sales_description')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>
        </section>

        <section class="card" style="padding:18px;">
            <h3 class="section-title">Achat</h3>
            <div class="form-grid">
                @if ($canViewProductCosts)
                    <div>
                        <label for="purchase_price">Prix d'achat</label>
                        <input id="purchase_price" type="number" step="0.01" min="0" name="purchase_price" value="{{ old('purchase_price', $product->purchase_price ?: 0) }}" required>
                        @error('purchase_price')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                @else
                    <div>
                        <label>Cout achat</label>
                        <div class="summary-box">
                            <strong>Cout confidentiel</strong>
                            <div class="help" style="margin-top:8px;">Le cout d achat est reserve aux profils autorises. En creation, il sera initialise a 0. En mise a jour, la valeur actuelle sera conservee.</div>
                        </div>
                    </div>
                @endif
                <div>
                    <label for="purchase_unit_name">Unite achat</label>
                    <input id="purchase_unit_name" type="text" name="purchase_unit_name" value="{{ old('purchase_unit_name', $product->purchase_unit_name) }}" placeholder="Ex: carton fournisseur">
                    <div class="help">Affiche le conditionnement d achat prefere.</div>
                    @error('purchase_unit_name')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="purchase_unit_ratio">Ratio achat</label>
                    <input id="purchase_unit_ratio" type="number" step="0.001" min="0.001" name="purchase_unit_ratio" value="{{ old('purchase_unit_ratio', $product->purchase_unit_ratio ?: 1) }}">
                    @error('purchase_unit_ratio')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="purchase_tax_rule_id">Taxe achat</label>
                    <select id="purchase_tax_rule_id" name="purchase_tax_rule_id">
                        <option value="">Aucune taxe</option>
                        @foreach ($taxRules as $taxRule)
                            <option value="{{ $taxRule->id }}" @selected((string) old('purchase_tax_rule_id', $product->purchase_tax_rule_id) === (string) $taxRule->id)>{{ $taxRule->name }} · {{ number_format((float) $taxRule->rate, 2, ',', ' ') }}%</option>
                        @endforeach
                    </select>
                    @error('purchase_tax_rule_id')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="full">
                    <label for="purchase_block_reason">Motif blocage achat</label>
                    <textarea id="purchase_block_reason" name="purchase_block_reason" placeholder="Ex: fournisseur suspendu, produit obsolete, controle qualite en cours">{{ old('purchase_block_reason', $product->purchase_block_reason) }}</textarea>
                    <div class="help">Utile pour expliquer pourquoi le produit ne doit plus etre commande temporairement.</div>
                    @error('purchase_block_reason')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="full">
                    <label for="purchase_description">Description achat</label>
                    <textarea id="purchase_description" name="purchase_description" placeholder="Texte repris automatiquement dans les ecrans achat">{{ old('purchase_description', $product->purchase_description) }}</textarea>
                    @error('purchase_description')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>
        </section>

        <section class="card" style="padding:18px;">
            <h3 class="section-title">Fournisseurs produit</h3>
            <div class="muted">Approche Odoo: plusieurs fournisseurs possibles, avec un fournisseur prefere, son prix, son delai et sa reference propre.</div>

            <div style="display:grid; gap:12px; margin-top:16px;">
                @foreach ($supplierRows as $index => $supplierRow)
                    <div class="summary-box" style="padding:16px;">
                        <div class="form-grid">
                            <div>
                                <label for="supplier_infos_{{ $index }}_supplier_id">Fournisseur</label>
                                <select id="supplier_infos_{{ $index }}_supplier_id" name="supplier_infos[{{ $index }}][supplier_id]">
                                    <option value="">Aucun</option>
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}" @selected((string) ($supplierRow['supplier_id'] ?? '') === (string) $supplier->id)>{{ $supplier->code }} - {{ $supplier->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="supplier_infos_{{ $index }}_supplier_product_code">Reference fournisseur</label>
                                <input id="supplier_infos_{{ $index }}_supplier_product_code" type="text" name="supplier_infos[{{ $index }}][supplier_product_code]" value="{{ $supplierRow['supplier_product_code'] ?? '' }}" placeholder="Ex: REF-FOUR-001">
                            </div>
                            <div>
                                <label for="supplier_infos_{{ $index }}_supplier_product_name">Nom fournisseur</label>
                                <input id="supplier_infos_{{ $index }}_supplier_product_name" type="text" name="supplier_infos[{{ $index }}][supplier_product_name]" value="{{ $supplierRow['supplier_product_name'] ?? '' }}" placeholder="Libelle utilise chez le fournisseur">
                            </div>
                            <div>
                                <label for="supplier_infos_{{ $index }}_min_qty">Quantite mini</label>
                                <input id="supplier_infos_{{ $index }}_min_qty" type="number" step="0.001" min="0" name="supplier_infos[{{ $index }}][min_qty]" value="{{ $supplierRow['min_qty'] ?? '' }}" placeholder="Ex: 12">
                            </div>
                            @if ($canViewProductCosts)
                                <div>
                                    <label for="supplier_infos_{{ $index }}_unit_cost">Cout fournisseur</label>
                                    <input id="supplier_infos_{{ $index }}_unit_cost" type="number" step="0.01" min="0" name="supplier_infos[{{ $index }}][unit_cost]" value="{{ $supplierRow['unit_cost'] ?? '' }}" placeholder="Ex: 1750">
                                </div>
                            @endif
                            <div>
                                <label for="supplier_infos_{{ $index }}_lead_time_days">Delai achat</label>
                                <input id="supplier_infos_{{ $index }}_lead_time_days" type="number" min="0" max="365" name="supplier_infos[{{ $index }}][lead_time_days]" value="{{ $supplierRow['lead_time_days'] ?? '' }}" placeholder="Ex: 7">
                            </div>
                            <div class="full">
                                <input type="hidden" name="supplier_infos[{{ $index }}][is_preferred]" value="0">
                                <label style="display:flex; align-items:center; gap:10px; margin:0;">
                                    <input type="checkbox" name="supplier_infos[{{ $index }}][is_preferred]" value="1" @checked((bool) ($supplierRow['is_preferred'] ?? false))>
                                    Fournisseur prefere pour les achats et le reappro automatique
                                </label>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @unless ($canViewProductCosts)
                <div class="help" style="margin-top:12px;">Les couts fournisseurs sont masques ici et conserves automatiquement si des references existent deja.</div>
            @endunless
            <div class="help" style="margin-top:12px;">Si plusieurs lignes sont cochees comme preferees, l ERP gardera la premiere. Si aucune n est cochee, la premiere ligne renseignee deviendra la reference par defaut.</div>
            @error('supplier_infos')<div class="field-error">{{ $message }}</div>@enderror
            @error('supplier_infos.*.supplier_id')<div class="field-error">{{ $message }}</div>@enderror
            @error('supplier_infos.*.supplier_product_code')<div class="field-error">{{ $message }}</div>@enderror
            @error('supplier_infos.*.supplier_product_name')<div class="field-error">{{ $message }}</div>@enderror
            @error('supplier_infos.*.min_qty')<div class="field-error">{{ $message }}</div>@enderror
            @error('supplier_infos.*.unit_cost')<div class="field-error">{{ $message }}</div>@enderror
            @error('supplier_infos.*.lead_time_days')<div class="field-error">{{ $message }}</div>@enderror
        </section>

        <section class="card" style="padding:18px;">
            <h3 class="section-title">Stock et suivi</h3>
            <div class="form-grid">
                <div>
                    <label for="min_stock">Stock minimum</label>
                    <input id="min_stock" type="number" step="0.001" min="0" name="min_stock" value="{{ old('min_stock', $product->min_stock ?: 0) }}" required>
                    <div class="help">Seuil mini de vigilance sur le stock reel.</div>
                    @error('min_stock')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="auto_replenish">Reappro automatique</label>
                    <select id="auto_replenish" name="auto_replenish">
                        <option value="0" @selected(! old('auto_replenish', $product->auto_replenish ?? false))>Desactive</option>
                        <option value="1" @selected(old('auto_replenish', $product->auto_replenish ?? false))>Active</option>
                    </select>
                    <div class="help">Propose automatiquement ce produit en reappro quand le stock projete passe sous le seuil.</div>
                    @error('auto_replenish')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="reorder_max_qty">Stock cible</label>
                    <input id="reorder_max_qty" type="number" step="0.001" min="0.001" name="reorder_max_qty" value="{{ old('reorder_max_qty', $product->reorder_max_qty) }}" placeholder="Ex: 24">
                    <div class="help">Quantite visee apres reappro. Si vide, le seuil mini sert de base.</div>
                    @error('reorder_max_qty')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="reorder_multiple_qty">Multiple achat</label>
                    <input id="reorder_multiple_qty" type="number" step="0.001" min="0.001" name="reorder_multiple_qty" value="{{ old('reorder_multiple_qty', $product->reorder_multiple_qty) }}" placeholder="Ex: 6">
                    <div class="help">Arrondit les suggestions par multiple fournisseur.</div>
                    @error('reorder_multiple_qty')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="purchase_lead_time_days">Delai achat (jours)</label>
                    <input id="purchase_lead_time_days" type="number" min="0" max="365" name="purchase_lead_time_days" value="{{ old('purchase_lead_time_days', $product->purchase_lead_time_days) }}" placeholder="Ex: 7">
                    <div class="help">Alimente la date de besoin proposee dans la demande d achat automatique.</div>
                    @error('purchase_lead_time_days')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="tracking_type">Suivi logistique</label>
                    <select id="tracking_type" name="tracking_type" required>
                        <option value="none" @selected(old('tracking_type', $product->tracking_type ?? 'none') === 'none')>Aucun suivi</option>
                        <option value="lot" @selected(old('tracking_type', $product->tracking_type ?? 'none') === 'lot')>Par lot</option>
                        <option value="serial" @selected(old('tracking_type', $product->tracking_type ?? 'none') === 'serial')>Par numero de serie</option>
                    </select>
                    <div class="help">Prepare le terrain pour un suivi plus fin des mouvements et de la tracabilite.</div>
                    @error('tracking_type')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="is_active">Statut</label>
                    <select id="is_active" name="is_active">
                        <option value="1" @selected(old('is_active', $product->is_active ?? true))>Actif</option>
                        <option value="0" @selected((string) old('is_active', $product->is_active ?? true) === '0')>Inactif</option>
                    </select>
                    @error('is_active')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="full">
                    <label for="description">Description generale</label>
                    <textarea id="description" name="description" placeholder="Description interne ou technique du produit">{{ old('description', $product->description) }}</textarea>
                    @error('description')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="full">
                    <label for="internal_notes">Notes internes</label>
                    <textarea id="internal_notes" name="internal_notes" placeholder="Informations reservees a l equipe interne">{{ old('internal_notes', $product->internal_notes) }}</textarea>
                    @error('internal_notes')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>
        </section>
    </div>

    <div class="actions">
        <a href="{{ route('products.index') }}" class="button button-secondary">Annuler</a>
        <button type="submit" class="button button-primary">Enregistrer</button>
    </div>
</div>

<script>
    (() => {
        const fileInput = document.getElementById('image');
        const dropzone = document.getElementById('product-dropzone');
        const preview = document.getElementById('product-preview');
        const title = document.getElementById('product-upload-title');
        const fileName = document.getElementById('product-upload-filename');
        const pickButton = document.getElementById('product-pick-image');
        const cameraButton = document.getElementById('product-open-camera');
        const removeCheckbox = document.getElementById('remove_image');
        const nameInput = document.getElementById('name');
        const cameraCard = document.getElementById('product-camera-card');
        const cameraVideo = document.getElementById('product-camera-video');
        const cameraCanvas = document.getElementById('product-camera-canvas');
        const captureButton = document.getElementById('product-capture-image');
        const stopCameraButton = document.getElementById('product-stop-camera');
        const existingImageUrl = @json(old('remove_image') ? null : $product->image_url);
        let cameraStream = null;

        if (!fileInput || !dropzone || !preview || !title || !fileName || !pickButton || !cameraButton) {
            return;
        }

        const initials = (value) => {
            const parts = String(value || '').trim().split(/\s+/).filter(Boolean).slice(0, 2);
            return (parts.map((part) => part.charAt(0).toUpperCase()).join('') || 'PR');
        };

        const humanSize = (bytes) => {
            if (!bytes) {
                return '0 Ko';
            }
            const kb = bytes / 1024;
            if (kb < 1024) {
                return `${kb.toFixed(0)} Ko`;
            }
            return `${(kb / 1024).toFixed(1)} Mo`;
        };

        const setPlaceholder = (message) => {
            preview.innerHTML = `<div class="product-preview-placeholder">${initials(nameInput?.value)}</div>`;
            title.textContent = 'Ajoute une photo produit';
            fileName.textContent = message || 'Glisse-depose une image ici ou utilise les boutons ci-dessous.';
        };

        const setImagePreview = (url, label, detail) => {
            preview.innerHTML = `<img src="${url}" alt="Apercu produit">`;
            title.textContent = label;
            fileName.textContent = detail;
        };

        const assignFile = (file) => {
            if (!file) {
                return;
            }
            const transfer = new DataTransfer();
            transfer.items.add(file);
            fileInput.files = transfer.files;
            if (removeCheckbox) {
                removeCheckbox.checked = false;
            }
            const reader = new FileReader();
            reader.onload = (event) => {
                setImagePreview(event.target?.result || '', 'Nouvelle photo selectionnee', `${file.name} · ${humanSize(file.size)}`);
            };
            reader.readAsDataURL(file);
        };

        const stopCamera = () => {
            if (cameraStream) {
                cameraStream.getTracks().forEach((track) => track.stop());
                cameraStream = null;
            }
            if (cameraCard) {
                cameraCard.hidden = true;
            }
        };

        const openCamera = async () => {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || !cameraCard || !cameraVideo) {
                fileInput.click();
                return;
            }
            try {
                cameraStream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: { ideal: 'environment' } },
                    audio: false,
                });
                cameraVideo.srcObject = cameraStream;
                cameraCard.hidden = false;
            } catch (error) {
                fileName.textContent = 'Camera non accessible dans ce navigateur. Utilise le choix de fichier classique.';
                fileInput.click();
            }
        };

        pickButton.addEventListener('click', () => fileInput.click());
        cameraButton.addEventListener('click', () => {
            openCamera();
        });
        stopCameraButton?.addEventListener('click', stopCamera);

        captureButton?.addEventListener('click', () => {
            if (!cameraVideo || !cameraCanvas) {
                return;
            }
            const width = cameraVideo.videoWidth || 1280;
            const height = cameraVideo.videoHeight || 960;
            cameraCanvas.width = width;
            cameraCanvas.height = height;
            const context = cameraCanvas.getContext('2d');
            if (!context) {
                return;
            }
            context.drawImage(cameraVideo, 0, 0, width, height);
            cameraCanvas.toBlob((blob) => {
                if (!blob) {
                    return;
                }
                const capturedFile = new File([blob], `produit-${Date.now()}.jpg`, { type: 'image/jpeg' });
                assignFile(capturedFile);
                stopCamera();
            }, 'image/jpeg', 0.92);
        });

        fileInput.addEventListener('change', () => {
            const file = fileInput.files?.[0];
            if (file) {
                assignFile(file);
            }
        });

        ['dragenter', 'dragover'].forEach((eventName) => {
            dropzone.addEventListener(eventName, (event) => {
                event.preventDefault();
                dropzone.classList.add('is-dragover');
            });
        });
        ['dragleave', 'dragend', 'drop'].forEach((eventName) => {
            dropzone.addEventListener(eventName, (event) => {
                event.preventDefault();
                dropzone.classList.remove('is-dragover');
            });
        });
        dropzone.addEventListener('drop', (event) => {
            const file = event.dataTransfer?.files?.[0];
            if (file && file.type.startsWith('image/')) {
                assignFile(file);
            }
        });
        dropzone.addEventListener('click', (event) => {
            if (event.target.closest('button') || event.target.closest('input[type="checkbox"]')) {
                return;
            }
            fileInput.click();
        });
        dropzone.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                fileInput.click();
            }
        });

        removeCheckbox?.addEventListener('change', () => {
            if (removeCheckbox.checked) {
                fileInput.value = '';
                setPlaceholder('La photo actuelle sera supprimee a l enregistrement.');
                stopCamera();
                return;
            }
            if (fileInput.files?.[0]) {
                assignFile(fileInput.files[0]);
                return;
            }
            if (existingImageUrl) {
                setImagePreview(existingImageUrl, 'Photo actuelle du produit', 'Image deja enregistree sur ce produit.');
                return;
            }
            setPlaceholder();
        });

        nameInput?.addEventListener('input', () => {
            if (!preview.querySelector('img')) {
                setPlaceholder(fileName.textContent);
            }
        });

        window.addEventListener('beforeunload', stopCamera);

        if (existingImageUrl && !removeCheckbox?.checked) {
            setImagePreview(existingImageUrl, 'Photo actuelle du produit', 'Image deja enregistree sur ce produit.');
        } else {
            setPlaceholder();
        }
    })();
</script>
<script>
    (() => {
        const parentSelect = document.getElementById('parent_product_id');
        const attributeCards = Array.from(document.querySelectorAll('[data-attribute-card]'));
        if (!parentSelect || attributeCards.length === 0) {
            return;
        }
        const syncVariantState = () => {
            const enabled = parentSelect.value !== '';
            attributeCards.forEach((card) => {
                card.classList.toggle('product-variant-disabled', !enabled);
                card.querySelectorAll('input[type="checkbox"]').forEach((input) => {
                    input.disabled = !enabled;
                    if (!enabled) {
                        input.checked = false;
                    }
                });
            });
        };
        attributeCards.forEach((card) => {
            card.querySelectorAll('input[type="checkbox"]').forEach((input) => {
                input.addEventListener('change', () => {
                    if (!input.checked) {
                        return;
                    }
                    card.querySelectorAll('input[type="checkbox"]').forEach((other) => {
                        if (other !== input) {
                            other.checked = false;
                        }
                    });
                });
            });
        });
        parentSelect.addEventListener('change', syncVariantState);
        syncVariantState();
    })();
</script>



