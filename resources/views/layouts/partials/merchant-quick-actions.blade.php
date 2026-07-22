@if ($isMerchantMode)
    @php
        $merchantSalesLabel = $businessVocabulary['sales'] ?? 'Ventes';
        $merchantSaleLabel = $businessVocabulary['sale'] ?? 'Vente';
        $merchantStockLabel = $businessVocabulary['stock'] ?? 'Stock';
        $merchantProductLabel = $businessVocabulary['product'] ?? 'Produit';
        $merchantProductsLabel = $businessVocabulary['products'] ?? 'Produits';
    @endphp
    <section class="card" style="margin-bottom:20px; padding:18px 20px; background:linear-gradient(135deg, rgba(255,249,240,.98) 0%, rgba(240,248,246,.96) 58%, rgba(255,239,214,.94) 100%); border-color:rgba(15,118,110,.16);">
        <div style="display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap; align-items:flex-start;">
            <div>
                <div class="topbar-label" style="margin-bottom:8px;">Mode commercant</div>
                <h2 style="margin:0; font-size:28px; letter-spacing:-.03em;">Routine simple pour travailler vite</h2>
                <div class="muted" style="margin-top:8px; max-width:860px;">Connexion, caisse, {{ strtolower($merchantSaleLabel) }}, paiement, document et {{ strtolower($merchantStockLabel) }} mis a jour. Les modules techniques restent accessibles en repassant en mode complet.</div>
            </div>
            <div class="dashboard-chip-row">
                <span class="dashboard-chip">Priorite : encaisser vite</span>
                <span class="dashboard-chip">{{ $merchantStockLabel }} visible</span>
                <span class="dashboard-chip">Rapport du jour</span>
            </div>
        </div>
        <div class="grid" style="margin-top:18px; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:14px;">
            @allowed('dashboard.view')
                <a href="{{ route('merchant.routine') }}" class="dashboard-link-card">
                    <strong>Routine du jour</strong>
                    <p class="muted">Suivre les etapes commerce dans l ordre, sans se perdre.</p>
                </a>
            @endallowed
            @allowed('pos.view')
                <a href="{{ route('pos.index') }}" class="dashboard-link-card">
                    <strong>Ouvrir la caisse</strong>
                    <p class="muted">Commencer la session et encaisser un ticket.</p>
                </a>
            @endallowed
            @allowed('sales.view')
                <a href="{{ route('sales.index') }}" class="dashboard-link-card">
                    <strong>Voir les {{ strtolower($merchantSalesLabel) }}</strong>
                    <p class="muted">Retrouver les tickets et les {{ strtolower($merchantSalesLabel) }} du jour.</p>
                </a>
            @endallowed
            @allowed('payments.view')
                <a href="{{ route('payments.index') }}" class="dashboard-link-card">
                    <strong>Encaissements</strong>
                    <p class="muted">Verifier les paiements recus et les tickets lies.</p>
                </a>
            @endallowed
            @allowed('stock.view')
                <a href="{{ route('stock.index') }}" class="dashboard-link-card">
                    <strong>Voir le {{ strtolower($merchantStockLabel) }}</strong>
                    <p class="muted">Controler ce qui manque et les {{ strtolower($merchantProductsLabel) }} en rupture.</p>
                </a>
            @endallowed
            @allowed('purchase_requests.view')
                <a href="{{ route('replenishments.index') }}" class="dashboard-link-card">
                    <strong>{{ $merchantProductsLabel }} manquants</strong>
                    <p class="muted">Lancer rapidement le reassort sur ce qui tourne.</p>
                </a>
            @endallowed
            @allowed('pos.view')
                <a href="{{ route('pos.report') }}" class="dashboard-link-card">
                    <strong>Resume du jour</strong>
                    <p class="muted">Sortir les chiffres simples utiles au gerant.</p>
                </a>
            @endallowed
        </div>
    </section>
@endif
