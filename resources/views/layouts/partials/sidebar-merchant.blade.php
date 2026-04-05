<nav class="sidebar-nav">
    <section class="nav-section nav-section--spotlight">
        <div class="nav-title">Quotidien</div>
        @allowed('dashboard.view')
            <a class="nav-link {{ request()->routeIs('merchant.routine') ? 'active' : '' }}" href="{{ route('merchant.routine') }}">Routine du jour</a>
        @endallowed
        @allowed('pos.view')
            <a class="nav-link {{ request()->routeIs('pos.*') ? 'active' : '' }}" href="{{ route('pos.index') }}">Vendre et encaisser</a>
        @endallowed
        @allowed('sales.view')
            <a class="nav-link {{ request()->routeIs('sales.*') ? 'active' : '' }}" href="{{ route('sales.index') }}">Ventes</a>
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
            <a class="nav-link {{ request()->routeIs('customers.*') ? 'active' : '' }}" href="{{ route('customers.index') }}">Clients</a>
        @endallowed
        @allowed('suppliers.view')
            <a class="nav-link {{ request()->routeIs('suppliers.*') ? 'active' : '' }}" href="{{ route('suppliers.index') }}">Fournisseurs</a>
        @endallowed
        @allowed('products.view')
            <a class="nav-link {{ request()->routeIs('products.*') ? 'active' : '' }}" href="{{ route('products.index') }}">Catalogue</a>
        @endallowed
        @allowed('purchase_requests.view')
            <a class="nav-link {{ request()->routeIs('replenishments.*') ? 'active' : '' }}" href="{{ route('replenishments.index') }}">Produits manquants</a>
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
        @allowed('settings.view')
            <a class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}" href="{{ route('settings.index') }}">Reglages</a>
        @endallowed
    </section>
</nav>
