@php
    $customerLabel = $businessVocabulary['client'] ?? 'Client';
    $customersLabel = $businessVocabulary['clients'] ?? 'Clients';
    $productLabel = $businessVocabulary['product'] ?? 'Produit';
    $productsLabel = $businessVocabulary['products'] ?? 'Produits';
    $saleLabel = $businessVocabulary['sale'] ?? 'Vente';
    $salesLabel = $businessVocabulary['sales'] ?? 'Ventes';
    $groups = auth()->user()?->hasRole('cashier') ? [
        'Point de Vente' => [
            ['label' => 'Caisse', 'route' => 'pos.index'],
            ['label' => 'Commandes', 'route' => 'pos.orders.index'],
        ],
    ] : [
        'Point de Vente' => [
            ['label' => 'Tableau de bord', 'route' => 'pos.index'],
            ['label' => 'Commandes', 'route' => 'pos.orders.index'],
            ['label' => 'Sessions', 'route' => 'pos.sessions.index'],
            ['label' => 'Paiements', 'route' => 'pos.payments.index'],
            ['label' => $customersLabel, 'route' => 'pos.customers.index'],
            ['label' => $productsLabel, 'route' => 'pos.products.index'],
            ['label' => 'Preparation', 'route' => 'pos.preparation.index'],
        ],
        'Tarification' => [
            ['label' => 'Listes de prix', 'route' => 'pos.pricing.index', 'params' => ['focus' => 'pricelists']],
            ['label' => 'Remise & Fidelite', 'route' => 'pos.pricing.index', 'params' => ['focus' => 'loyalty']],
            ['label' => 'Cartes-cadeaux & e-wallet', 'route' => 'pos.pricing.index', 'params' => ['focus' => 'stored-value']],
        ],
        'Analyse' => [
            ['label' => 'Commandes', 'route' => 'pos.analytics.index', 'params' => ['focus' => 'orders']],
            ['label' => 'Details des '.$salesLabel, 'route' => 'pos.analytics.index', 'params' => ['focus' => 'sales']],
            ['label' => 'Rapport de session', 'route' => 'pos.analytics.index', 'params' => ['focus' => 'sessions']],
            ['label' => 'Temps de preparation', 'route' => 'pos.analytics.index', 'params' => ['focus' => 'prep']],
        ],
        'Configuration' => [
            ['label' => 'Parametres', 'route' => 'pos.settings.index', 'params' => ['focus' => 'profiles']],
            ['label' => 'Modes de paiement', 'route' => 'pos.settings.index', 'params' => ['focus' => 'payment-methods']],
            ['label' => 'Preraglages', 'route' => 'pos.settings.index', 'params' => ['focus' => 'profiles']],
            ['label' => 'Pieces/billets', 'route' => 'pos.settings.index', 'params' => ['focus' => 'denominations']],
            ['label' => 'Point de vente', 'route' => 'pos.settings.index', 'params' => ['focus' => 'profiles']],
            ['label' => 'Modeles de notes', 'route' => 'pos.settings.index', 'params' => ['focus' => 'note-templates']],
            ['label' => 'Categories '.$productsLabel.' du PdV', 'route' => 'pos.products.index', 'params' => ['focus' => 'menu-categories']],
            ['label' => 'Attributs', 'route' => 'pos.products.index', 'params' => ['focus' => 'attributes']],
            ['label' => 'Etiquettes '.$productLabel, 'route' => 'pos.products.index', 'params' => ['focus' => 'tags']],
            ['label' => 'Imprimantes de preparation', 'route' => 'pos.settings.index', 'params' => ['focus' => 'printers']],
            ['label' => 'Preparation Display', 'route' => 'pos.settings.index', 'params' => ['focus' => 'displays']],
            ['label' => 'Choix de combo', 'route' => 'pos.products.index', 'params' => ['focus' => 'combos']],
        ],
    ];
@endphp

<style>
    .pos-backoffice-nav {
        display: grid;
        gap: 14px;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        padding: 18px;
        border: 1px solid #d8e3f0;
        border-radius: 24px;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        box-shadow: 0 14px 30px rgba(15, 23, 42, 0.06);
    }
    .pos-backoffice-nav-head {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: center;
        flex-wrap: wrap;
        min-width: 0;
    }
    .pos-backoffice-nav-grid {
        display: grid;
        gap: 14px;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .pos-backoffice-nav-group {
        min-width: 0;
        padding: 14px;
        border-radius: 18px;
        border: 1px solid #e3ebf5;
        background: #fff;
    }
    .pos-backoffice-nav-group strong {
        display: block;
        margin-bottom: 10px;
        color: #15324d;
        font-size: 14px;
    }
    .pos-backoffice-nav-links {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    .pos-backoffice-nav-link {
        display: inline-flex;
        align-items: center;
        max-width: 100%;
        padding: 8px 12px;
        border-radius: 999px;
        border: 1px solid #dbe6f2;
        background: #f8fbff;
        color: #17304f;
        font-size: 12px;
        font-weight: 700;
        text-align: center;
        white-space: normal;
        overflow-wrap: anywhere;
    }
    .pos-backoffice-nav-link.active {
        background: #143a5a;
        border-color: #143a5a;
        color: #fff;
    }
    @media (min-width: 981px) {
        .erp-module-bar {
            flex-wrap: wrap;
            overflow-x: visible;
        }
    }
    @media (max-width: 760px) {
        .pos-backoffice-nav {
            padding: 14px;
        }
        .pos-backoffice-nav-grid {
            grid-template-columns: minmax(0, 1fr);
        }
    }
</style>

<section class="pos-backoffice-nav">
    <div class="pos-backoffice-nav-head">
        <div>
            <strong style="font-size:16px; color:#10233a;">Back-office POS</strong>
        </div>
        <a href="{{ route('pos.sales.create') }}" class="button button-primary">Ouvrir la caisse</a>
    </div>
    <div class="pos-backoffice-nav-grid">
        @foreach ($groups as $groupLabel => $links)
            <article class="pos-backoffice-nav-group">
                <strong>{{ $groupLabel }}</strong>
                <div class="pos-backoffice-nav-links">
                    @foreach ($links as $link)
                        @php
                            $url = route($link['route'], $link['params'] ?? []);
                        @endphp
                        <a href="{{ $url }}" class="pos-backoffice-nav-link {{ request()->fullUrlIs($url) || request()->routeIs($link['route']) ? 'active' : '' }}">
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                </div>
            </article>
        @endforeach
    </div>
</section>
