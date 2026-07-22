@extends('layouts.app')

@section('title', 'Imports Excel et CSV - Nema ERP')
@section('page-title', 'Centre d imports')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Chargement initial et migration historique</h2>
            <div class="muted">Importe rapidement les clients, fournisseurs, produits, stock initial et maintenant les achats ou ventes historiques a partir de fichiers Excel ou CSV simples.</div>
        </div>
    </div>

    <section class="card" style="margin-bottom:16px;padding:16px;display:flex;gap:14px;align-items:center;justify-content:space-between;flex-wrap:wrap;">
        <div>
            <h2 style="margin:0 0 4px;">Synchronisation directe depuis Odoo</h2>
            <div class="muted">Importe en lots les produits, variantes, images, fournisseurs, attributs et stocks par JSON-RPC ou XML-RPC, avec reprise et journal d'erreurs.</div>
        </div>
        <a href="{{ route('imports.odoo.index') }}" class="button button-primary">Configurer Odoo</a>
    </section>

    <div class="split" style="margin-bottom:20px;">
        <section class="card">
            <h2 style="margin-top:0;">Import clients</h2>
            <p class="muted">Colonnes acceptees : <strong>code</strong>, <strong>nom</strong>, <strong>telephone</strong>, <strong>email</strong>, <strong>ville</strong>, <strong>nif</strong>, <strong>adresse</strong>, <strong>solde_initial</strong>, <strong>condition_paiement</strong>, <strong>liste_prix</strong>, <strong>actif</strong>, <strong>notes</strong>.</p>
            <div style="margin-bottom:16px; display:flex; gap:10px; flex-wrap:wrap;">
                <a href="{{ route('imports.templates.download', 'customers-xlsx') }}" class="button button-primary">Modele Excel clients</a>
                <a href="{{ route('imports.templates.download', 'customers') }}" class="button button-secondary">Modele CSV clients</a>
            </div>
            <form method="POST" action="{{ route('imports.customers.store') }}" enctype="multipart/form-data">
                @csrf
                <label for="customers_file">Fichier clients</label>
                <input id="customers_file" type="file" name="file" accept=".xlsx,.csv,.txt">
                <div class="help" style="margin-top:10px;">Si un code, email ou nom existe deja, la fiche sera mise a jour. Les colonnes anglaises comme name, phone et address restent acceptees.</div>
                <div class="actions" style="justify-content:flex-start;">
                    <button type="submit" class="button button-primary">Importer les clients</button>
                </div>
            </form>
        </section>

        <section class="card">
            <h2 style="margin-top:0;">Import fournisseurs</h2>
            <p class="muted">Meme modele que les clients pour charger rapidement la base fournisseurs avant les achats, depenses et imports historiques.</p>
            <div style="margin-bottom:16px; display:flex; gap:10px; flex-wrap:wrap;">
                <a href="{{ route('imports.templates.download', 'suppliers-xlsx') }}" class="button button-primary">Modele Excel fournisseurs</a>
                <a href="{{ route('imports.templates.download', 'suppliers') }}" class="button button-secondary">Modele CSV fournisseurs</a>
            </div>
            <form method="POST" action="{{ route('imports.suppliers.store') }}" enctype="multipart/form-data">
                @csrf
                <label for="suppliers_file">Fichier fournisseurs</label>
                <input id="suppliers_file" type="file" name="file" accept=".xlsx,.csv,.txt">
                <div class="help" style="margin-top:10px;">Le systeme met a jour un fournisseur existant si le code, email ou nom correspond. Les colonnes francaises et anglaises sont acceptees.</div>
                <div class="actions" style="justify-content:flex-start;">
                    <button type="submit" class="button button-primary">Importer les fournisseurs</button>
                </div>
            </form>
        </section>
    </div>

    <div class="split" style="margin-bottom:20px;">
        <section class="card">
            <h2 style="margin-top:0;">Import produits boutique</h2>
            <p class="muted">Un seul fichier peut creer les produits, categories, codes-barres et stock initial.</p>
            <div style="margin-bottom:16px; display:flex; gap:10px; flex-wrap:wrap;">
                <a href="{{ route('imports.templates.download', 'products-xlsx') }}" class="button button-primary">Telecharger le modele Excel</a>
                <a href="{{ route('imports.templates.download', 'products') }}" class="button button-secondary">Modele CSV</a>
            </div>
            <form method="POST" action="{{ route('imports.products.store') }}" enctype="multipart/form-data">
                @csrf
                <label for="products_file">Fichier produits</label>
                <input id="products_file" type="file" name="file" accept=".xlsx,.csv,.txt">
                <div class="help" style="margin-top:10px;">Colonnes : sku, barcode, name, category, unit, type, sale_price, purchase_price, min_stock, opening_quantity, opening_unit_cost, description.</div>
                <div class="actions" style="justify-content:flex-start;">
                    <button type="submit" class="button button-primary">Importer les produits</button>
                </div>
            </form>
        </section>

        <section class="card">
            <h2 style="margin-top:0;">Import stock initial</h2>
            <p class="muted">Agence active : <strong>{{ $branch?->name ?? 'Non definie' }}</strong>. Utilise les <strong>SKU</strong> deja presents dans le catalogue pour charger le stock de depart en masse.</p>
            <div style="margin-bottom:16px;">
                <a href="{{ route('imports.templates.download', 'opening-stock') }}" class="button button-secondary">Telecharger le modele stock initial</a>
            </div>
            <form method="POST" action="{{ route('imports.opening-stock.store') }}" enctype="multipart/form-data">
                @csrf
                <label for="opening_stock_file">Fichier CSV stock initial</label>
                <input id="opening_stock_file" type="file" name="file" accept=".csv,.txt">
                <div class="help" style="margin-top:10px;">Colonnes attendues : <strong>sku</strong>, <strong>quantity</strong>, <strong>unit_cost</strong>, <strong>notes</strong>.</div>
                <div class="actions" style="justify-content:flex-start;">
                    <button type="submit" class="button button-primary">Importer le stock initial</button>
                </div>
            </form>
        </section>
    </div>

    <div class="split" style="margin-bottom:20px;">
        <section class="card">
            <h2 style="margin-top:0;">Import ventes historiques</h2>
            <p class="muted">Une ligne CSV represente une ligne de facture. Reprends le meme <strong>invoice_number</strong> sur chaque ligne d une meme facture. Si <strong>amount_paid</strong> est renseigne, indique aussi un <strong>cash_account</strong>.</p>
            <div style="margin-bottom:16px; display:flex; gap:10px; flex-wrap:wrap;">
                <a href="{{ route('imports.templates.download', 'historical-sales') }}" class="button button-secondary">Telecharger le modele ventes historiques</a>
            </div>
            <form method="POST" action="{{ route('imports.historical-sales.store') }}" enctype="multipart/form-data">
                @csrf
                <label for="historical_sales_file">Fichier CSV ventes historiques</label>
                <input id="historical_sales_file" type="file" name="file" accept=".csv,.txt">
                <div class="help" style="margin-top:10px;">Colonnes attendues : <strong>invoice_number</strong>, <strong>invoice_date</strong>, <strong>due_date</strong>, <strong>customer_code</strong>, <strong>sku</strong>, <strong>description</strong>, <strong>qty</strong>, <strong>unit_price</strong>, <strong>amount_paid</strong>, <strong>payment_date</strong>, <strong>cash_account</strong>, <strong>notes</strong>.</div>
                <div class="actions" style="justify-content:flex-start;">
                    <button type="submit" class="button button-primary">Importer les ventes historiques</button>
                </div>
            </form>
        </section>

        <section class="card">
            <h2 style="margin-top:0;">Import achats historiques</h2>
            <p class="muted">Utilise ce mode pour recréer des factures fournisseurs historiques avec lignes de produits, entree en stock et reglement partiel ou total si besoin.</p>
            <div style="margin-bottom:16px; display:flex; gap:10px; flex-wrap:wrap;">
                <a href="{{ route('imports.templates.download', 'historical-purchases') }}" class="button button-secondary">Telecharger le modele achats historiques</a>
            </div>
            <form method="POST" action="{{ route('imports.historical-purchases.store') }}" enctype="multipart/form-data">
                @csrf
                <label for="historical_purchases_file">Fichier CSV achats historiques</label>
                <input id="historical_purchases_file" type="file" name="file" accept=".csv,.txt">
                <div class="help" style="margin-top:10px;">Colonnes attendues : <strong>bill_number</strong>, <strong>bill_date</strong>, <strong>due_date</strong>, <strong>supplier_code</strong>, <strong>sku</strong>, <strong>description</strong>, <strong>qty</strong>, <strong>unit_cost</strong>, <strong>amount_paid</strong>, <strong>payment_date</strong>, <strong>cash_account</strong>, <strong>notes</strong>.</div>
                <div class="actions" style="justify-content:flex-start;">
                    <button type="submit" class="button button-primary">Importer les achats historiques</button>
                </div>
            </form>
        </section>
    </div>

    <section class="card" style="margin-top:20px;">
        <h2 style="margin-top:0;">Ordres conseilles</h2>
        <div class="muted" style="margin-bottom:12px;">Choisis le scenario qui correspond a ton demarrage.</div>
        <ol style="margin:0 0 0 18px; padding:0; color:var(--muted);">
            <li>Demarrage simple : clients, fournisseurs, produits, puis stock initial.</li>
            <li>Migration historique complete : clients, fournisseurs, produits, puis achats historiques avant les ventes historiques.</li>
            <li>Si tu importes des ventes historiques avec stock actif, assure-toi que le stock disponible couvre les quantites vendues.</li>
        </ol>
    </section>
@endsection
