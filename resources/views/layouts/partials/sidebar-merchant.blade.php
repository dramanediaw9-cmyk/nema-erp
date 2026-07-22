@php
    $merchantSalesLabel = $businessVocabulary['sales'] ?? 'Ventes';
    $merchantCustomersLabel = $businessVocabulary['clients'] ?? 'Clients';
    $merchantProductsLabel = $businessVocabulary['products'] ?? 'Produits';
@endphp

<nav class="sidebar-nav">
    <section class="nav-section nav-section--spotlight">
        <div class="nav-title">Quotidien</div>
        @allowed('dashboard.view')
            <a class="nav-link {{ request()->routeIs('merchant.routine') ? 'active' : '' }}" href="{{ route('merchant.routine') }}">Routine du jour</a>
        @endallowed
        @allowed('pos.view')
            <a class="nav-link {{ request()->routeIs('pos.*') ? 'active' : '' }}" href="{{ route('pos.index') }}">Vendre et encaisser</a>
            <a class="nav-link {{ request()->routeIs('pos.orders.*') ? 'active' : '' }}" href="{{ route('pos.orders.index') }}">Commandes POS</a>
            <a class="nav-link {{ request()->routeIs('pos.analytics.*') ? 'active' : '' }}" href="{{ route('pos.analytics.index') }}">Analyse POS</a>
            <a class="nav-link {{ request()->routeIs('pos.settings.*') ? 'active' : '' }}" href="{{ route('pos.settings.index') }}">Config POS</a>
        @endallowed
        @allowed('sales.view')
            <a class="nav-link {{ request()->routeIs('sales.*') ? 'active' : '' }}" href="{{ route('sales.index') }}">{{ $merchantSalesLabel }}</a>
        @endallowed
        @allowed('payments.view')
            <a class="nav-link {{ request()->routeIs('payments.*') ? 'active' : '' }}" href="{{ route('payments.index') }}">Paiements</a>
        @endallowed
        @allowed('stock.view')
            <a class="nav-link {{ request()->routeIs('stock.*') && ! request()->routeIs('stock.lots') ? 'active' : '' }}" href="{{ route('stock.index') }}">Voir le stock</a>
            <a class="nav-link {{ request()->routeIs('stock.lots') ? 'active' : '' }}" href="{{ route('stock.lots') }}">Peremption</a>
        @endallowed
        @allowed('pos.view')
            <a class="nav-link {{ request()->routeIs('pos.report') ? 'active' : '' }}" href="{{ route('pos.report') }}">Rapport simple</a>
        @endallowed
    </section>

    <section class="nav-section">
        <div class="nav-title">Suivi commerce</div>
        @allowed('customers.view')
            <a class="nav-link {{ request()->routeIs('customers.*') ? 'active' : '' }}" href="{{ route('customers.index') }}">{{ $merchantCustomersLabel }}</a>
        @endallowed
        @allowed('suppliers.view')
            <a class="nav-link {{ request()->routeIs('suppliers.*') ? 'active' : '' }}" href="{{ route('suppliers.index') }}">Fournisseurs</a>
        @endallowed
        @allowed('products.view')
            <a class="nav-link {{ request()->routeIs('products.*') ? 'active' : '' }}" href="{{ route('products.index') }}">Catalogue {{ $merchantProductsLabel }}</a>
        @endallowed
        @allowed('purchase_requests.view')
            <a class="nav-link {{ request()->routeIs('replenishments.*') ? 'active' : '' }}" href="{{ route('replenishments.index') }}">{{ $merchantProductsLabel }} manquants</a>
        @endallowed
        @allowed('collections.view')
            <a class="nav-link {{ request()->routeIs('collections.*') ? 'active' : '' }}" href="{{ route('collections.index') }}">Recouvrements</a>
        @endallowed
    </section>

    <section class="nav-section">
        <div class="nav-title">Supervision</div>
        @allowed('approvals.view')
            <a class="nav-link {{ request()->routeIs('approvals.*') ? 'active' : '' }}" href="{{ route('approvals.index') }}">Approbations</a>
        @endallowed
        @allowed('notifications.view')
            <a class="nav-link {{ request()->routeIs('notifications.index') ? 'active' : '' }}" href="{{ route('notifications.index') }}">Alertes</a>
        @endallowed
        @allowed('automation.view')
            <a class="nav-link {{ request()->routeIs('automation.*') ? 'active' : '' }}" href="{{ route('automation.index') }}">Automatisations</a>
        @endallowed
        @allowed('settings.view')
            <a class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}" href="{{ route('settings.index') }}">Reglages</a>
        @endallowed
    </section>

    <section class="nav-section">
        <div class="nav-title">Compte</div>
        <a class="nav-link {{ request()->routeIs('account.profile.*') ? 'active' : '' }}" href="{{ route('account.profile.edit') }}">Mon compte</a>
    </section>
</nav>
