<nav class="sidebar-nav">
    <section class="nav-section">
    <div class="nav-title">Pilotage</div>
    @allowed('dashboard.view')
        <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">Dashboard</a>
        <a class="nav-link {{ request()->routeIs('onboarding.*') ? 'active' : '' }}" href="{{ route('onboarding.index') }}">Demarrage</a>
    @endallowed
    @allowed('approvals.view')
        <a class="nav-link {{ request()->routeIs('approvals.*') ? 'active' : '' }}" href="{{ route('approvals.index') }}">Approbations</a>
    @endallowed
    @allowed('reports.view')
        <a class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}" href="{{ route('reports.index') }}">Rapports</a>
    @endallowed
    @allowed('budgets.view')
        <a class="nav-link {{ request()->routeIs('budgets.*') ? 'active' : '' }}" href="{{ route('budgets.index') }}">Budgets</a>
    @endallowed
    @allowed('notifications.view')
        <a class="nav-link {{ request()->routeIs('notifications.index') ? 'active' : '' }}" href="{{ route('notifications.index') }}">Alertes internes</a>
    @endallowed
    @allowed('automation.view')
        <a class="nav-link {{ request()->routeIs('automation.*') ? 'active' : '' }}" href="{{ route('automation.index') }}">Automatisations</a>
    @endallowed
    @allowed('notifications.outbound.view')
        <a class="nav-link {{ request()->routeIs('notifications.outbound.*') ? 'active' : '' }}" href="{{ route('notifications.outbound.index') }}">Notifications sortantes</a>
    @endallowed
    @allowed('imports.manage')
        <a class="nav-link {{ request()->routeIs('imports.*') ? 'active' : '' }}" href="{{ route('imports.index') }}">Imports CSV</a>
    @endallowed
    @allowed('ops.view')
        <a class="nav-link {{ request()->routeIs('ops.*') ? 'active' : '' }}" href="{{ route('ops.index') }}">Operations</a>
    @endallowed
    @allowed('platform.view')
        <a class="nav-link {{ request()->routeIs('platform.*') ? 'active' : '' }}" href="{{ route('platform.index') }}">Plateforme</a>
    @endallowed
    </section>

    <section class="nav-section">
    <div class="nav-title">Structure</div>
    @allowed('companies.view')
        <a class="nav-link {{ request()->routeIs('companies.*') ? 'active' : '' }}" href="{{ route('companies.index') }}">Entreprises</a>
    @endallowed
    @allowed('branches.view')
        <a class="nav-link {{ request()->routeIs('branches.*') ? 'active' : '' }}" href="{{ route('branches.index') }}">Agences</a>
    @endallowed
    @allowed('users.view')
        <a class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}">Utilisateurs</a>
    @endallowed
    @allowed('roles.view')
        <a class="nav-link {{ request()->routeIs('roles.*') ? 'active' : '' }}" href="{{ route('roles.index') }}">Roles et permissions</a>
    @endallowed
    @allowed('settings.view')
        <a class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}" href="{{ route('settings.index') }}">Parametres</a>
    @endallowed
    </section>

    <section class="nav-section">
    <div class="nav-title">Tiers</div>
    @allowed('customers.view')
        <a class="nav-link {{ request()->routeIs('customers.*') ? 'active' : '' }}" href="{{ route('customers.index') }}">Clients</a>
    @endallowed
    @allowed('suppliers.view')
        <a class="nav-link {{ request()->routeIs('suppliers.*') ? 'active' : '' }}" href="{{ route('suppliers.index') }}">Fournisseurs</a>
    @endallowed
    </section>

    <section class="nav-section">
    <div class="nav-title">Catalogue</div>
    @allowed('categories.view')
        <a class="nav-link {{ request()->routeIs('categories.*') ? 'active' : '' }}" href="{{ route('categories.index') }}">Categories</a>
    @endallowed
    @allowed('products.view')
        <a class="nav-link {{ request()->routeIs('products.*') ? 'active' : '' }}" href="{{ route('products.index') }}">Produits</a>
    @endallowed
    @allowed('stock.view')
        <a class="nav-link {{ request()->routeIs('stock.*') && ! request()->routeIs('stock.lots') ? 'active' : '' }}" href="{{ route('stock.index') }}">Stock</a>
        <a class="nav-link {{ request()->routeIs('stock.lots') ? 'active' : '' }}" href="{{ route('stock.lots') }}">Lots et peremption</a>
    @endallowed
    @allowed('stock_counts.view')
        <a class="nav-link {{ request()->routeIs('stock-counts.*') ? 'active' : '' }}" href="{{ route('stock-counts.index') }}">Inventaires</a>
    @endallowed
    </section>

    <section class="nav-section nav-section--spotlight">
    <div class="nav-title">Commercial</div>
    @allowed('crm.view')
        <a class="nav-link {{ request()->routeIs('crm.*') ? 'active' : '' }}" href="{{ route('crm.index') }}">CRM</a>
    @endallowed
    @allowed('quotes.view')
        <a class="nav-link {{ request()->routeIs('quotes.*') ? 'active' : '' }}" href="{{ route('quotes.index') }}">Devis</a>
    @endallowed
    @allowed('orders.view')
        <a class="nav-link {{ request()->routeIs('orders.*') ? 'active' : '' }}" href="{{ route('orders.index') }}">Commandes clients</a>
    @endallowed
    @allowed('delivery_notes.view')
        <a class="nav-link {{ request()->routeIs('delivery-notes.*') ? 'active' : '' }}" href="{{ route('delivery-notes.index') }}">Bons de livraison</a>
    @endallowed
    @allowed('pos.view')
        <a class="nav-link {{ request()->routeIs('pos.*') ? 'active' : '' }}" href="{{ route('pos.index') }}">Point de vente</a>
    @endallowed
    @allowed('sales.view')
        <a class="nav-link {{ request()->routeIs('sales.*') ? 'active' : '' }}" href="{{ route('sales.index') }}">Ventes</a>
    @endallowed
    @allowed('credit_notes.view')
        <a class="nav-link {{ request()->routeIs('credit-notes.*') ? 'active' : '' }}" href="{{ route('credit-notes.index') }}">Avoirs clients</a>
    @endallowed
    @allowed('collections.view')
        <a class="nav-link {{ request()->routeIs('collections.*') ? 'active' : '' }}" href="{{ route('collections.index') }}">Recouvrement</a>
    @endallowed
    @allowed('purchase_requests.view')
        <a class="nav-link {{ request()->routeIs('purchase-requests.*') ? 'active' : '' }}" href="{{ route('purchase-requests.index') }}">Demandes d achat</a>
        <a class="nav-link {{ request()->routeIs('replenishments.*') ? 'active' : '' }}" href="{{ route('replenishments.index') }}">Reappro automatique</a>
    @endallowed
    @allowed('purchases.view')
        <a class="nav-link {{ request()->routeIs('purchases.*') ? 'active' : '' }}" href="{{ route('purchases.index') }}">Achats</a>
    @endallowed
    @allowed('purchase_orders.view')
        <a class="nav-link {{ request()->routeIs('purchase-orders.*') ? 'active' : '' }}" href="{{ route('purchase-orders.index') }}">Commandes fournisseurs</a>
    @endallowed
    @allowed('goods_receipts.view')
        <a class="nav-link {{ request()->routeIs('goods-receipts.*') ? 'active' : '' }}" href="{{ route('goods-receipts.index') }}">Receptions fournisseurs</a>
    @endallowed
    </section>

    <section class="nav-section">
    <div class="nav-title">Tresorerie</div>
    @allowed('cash_accounts.view')
        <a class="nav-link {{ request()->routeIs('cash-accounts.*') ? 'active' : '' }}" href="{{ route('cash-accounts.index') }}">Comptes</a>
    @endallowed
    @allowed('payments.view')
        <a class="nav-link {{ request()->routeIs('payments.*') ? 'active' : '' }}" href="{{ route('payments.index') }}">Paiements</a>
    @endallowed
    @allowed('reconciliations.view')
        <a class="nav-link {{ request()->routeIs('treasury-reconciliations.*') ? 'active' : '' }}" href="{{ route('treasury-reconciliations.index') }}">Rapprochements</a>
    @endallowed
    @allowed('expenses.view')
        <a class="nav-link {{ request()->routeIs('expenses.*') ? 'active' : '' }}" href="{{ route('expenses.index') }}">Depenses</a>
    @endallowed
    @allowed('expense_categories.view')
        <a class="nav-link {{ request()->routeIs('expense-categories.*') ? 'active' : '' }}" href="{{ route('expense-categories.index') }}">Categories depenses</a>
    @endallowed
    </section>

    <section class="nav-section">
    <div class="nav-title">Comptabilite</div>
    @allowed('accounting.view')
        <a class="nav-link {{ request()->routeIs('accounting.accounts.*') ? 'active' : '' }}" href="{{ route('accounting.accounts.index') }}">Plan comptable</a>
        @allowed('accounting.manage_periods')
            <a class="nav-link {{ request()->routeIs('accounting.periods.*') ? 'active' : '' }}" href="{{ route('accounting.periods.index') }}">Periodes</a>
        @endallowed
        <a class="nav-link {{ request()->routeIs('accounting.journal-entries.*') ? 'active' : '' }}" href="{{ route('accounting.journal-entries.index') }}">Journaux</a>
        <a class="nav-link {{ request()->routeIs('accounting.general-ledger.*') ? 'active' : '' }}" href="{{ route('accounting.general-ledger.index') }}">Grand livre</a>
        <a class="nav-link {{ request()->routeIs('accounting.balance.*') ? 'active' : '' }}" href="{{ route('accounting.balance.index') }}">Balance</a>
        <a class="nav-link {{ request()->routeIs('accounting.profit-loss.*') ? 'active' : '' }}" href="{{ route('accounting.profit-loss.index') }}">Resultat</a>
        <a class="nav-link {{ request()->routeIs('accounting.balance-sheet.*') ? 'active' : '' }}" href="{{ route('accounting.balance-sheet.index') }}">Bilan</a>
        <a class="nav-link {{ request()->routeIs('accounting.tax-report.*') ? 'active' : '' }}" href="{{ route('accounting.tax-report.index') }}">Fiscalite</a>
        <a class="nav-link {{ request()->routeIs('accounting.ohada.*') ? 'active' : '' }}" href="{{ route('accounting.ohada.index') }}">Localisation OHADA</a>
        @allowed('fixed_assets.view')
            <a class="nav-link {{ request()->routeIs('fixed-assets.*') ? 'active' : '' }}" href="{{ route('fixed-assets.index') }}">Immobilisations</a>
        @endallowed
    @endallowed
    </section>

    <section class="nav-section">
    <div class="nav-title">Expansion</div>
    @allowed('hr.view')
        <a class="nav-link {{ request()->routeIs('hr.*') ? 'active' : '' }}" href="{{ route('hr.index') }}">Capital humain</a>
    @endallowed
    @allowed('payroll.view')
        <a class="nav-link {{ request()->routeIs('payroll.*') ? 'active' : '' }}" href="{{ route('payroll.index') }}">Paie</a>
    @endallowed
    @allowed('projects.view')
        <a class="nav-link {{ request()->routeIs('projects.*') ? 'active' : '' }}" href="{{ route('projects.index') }}">Projets</a>
    @endallowed
    @allowed('manufacturing.view')
        <a class="nav-link {{ request()->routeIs('manufacturing.*') ? 'active' : '' }}" href="{{ route('manufacturing.index') }}">Production</a>
    @endallowed
    @allowed('commerce.view')
        <a class="nav-link {{ request()->routeIs('commerce.*') ? 'active' : '' }}" href="{{ route('commerce.index') }}">Commerce unifie</a>
    @endallowed
    </section>

    <section class="nav-section">
    <div class="nav-title">Audit</div>
    @allowed('activity_logs.view')
        <a class="nav-link {{ request()->routeIs('activity-logs.*') ? 'active' : '' }}" href="{{ route('activity-logs.index') }}">Journaux d'activite</a>
    @endallowed
    </section>
</nav>
