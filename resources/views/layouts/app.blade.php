<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <style>
            :root {
                --bg: #f6f1e8;
                --paper: #fffdf9;
                --line: #e4d8c4;
                --text: #291f16;
                --muted: #746556;
                --brand: #005f73;
                --accent: #ca6702;
                --success: #176b4d;
                --danger: #b42318;
            }
            * { box-sizing: border-box; }
            body { margin: 0; font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; background: radial-gradient(circle at top left, #fff8ea 0, var(--bg) 40%, #efe5d7 100%); color: var(--text); }
            a { color: inherit; text-decoration: none; }
            .shell { display: grid; grid-template-columns: 280px 1fr; min-height: 100vh; }
            .sidebar { background: linear-gradient(180deg, #183c47 0%, #0f2c35 100%); color: #f5efe6; padding: 26px 18px; }
            .brand { margin-bottom: 28px; }
            .brand small { color: #c9d8dc; display: block; margin-top: 6px; }
            .nav-title { margin: 18px 10px 10px; font-size: 12px; text-transform: uppercase; letter-spacing: .08em; color: #a9c2c8; }
            .nav-link { display: block; padding: 12px 14px; margin-bottom: 8px; border-radius: 12px; color: #deebe9; }
            .nav-link.active, .nav-link:hover { background: rgba(255,255,255,.09); }
            .main { padding: 24px 28px 40px; }
            .topbar { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 24px; padding: 18px 22px; background: rgba(255,255,255,.75); border: 1px solid rgba(228,216,196,.85); border-radius: 20px; backdrop-filter: blur(10px); }
            .workspace { color: var(--muted); font-size: 14px; }
            .button, button { border: 0; border-radius: 12px; padding: 11px 16px; cursor: pointer; font-weight: 600; }
            .button-primary { background: var(--brand); color: white; }
            .button-secondary { background: #efe6d8; color: var(--text); }
            .button-danger { background: #fee4e2; color: var(--danger); }
            .page-head { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 20px; }
            .card { background: var(--paper); border: 1px solid var(--line); border-radius: 20px; padding: 20px; box-shadow: 0 10px 30px rgba(41,31,22,.04); }
            .grid { display: grid; gap: 18px; }
            .stats-grid { grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); }
            .stat-value { font-size: 34px; font-weight: 700; margin-top: 12px; }
            .muted { color: var(--muted); }
            .table-wrap { overflow-x: auto; }
            table { width: 100%; border-collapse: collapse; }
            th, td { padding: 14px 12px; border-bottom: 1px solid #efe4d3; text-align: left; vertical-align: top; }
            th { color: var(--muted); font-size: 13px; text-transform: uppercase; letter-spacing: .04em; }
            .badge { display: inline-flex; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; }
            .badge-success { background: #dcfae6; color: var(--success); }
            .badge-muted { background: #efe6d8; color: #705e4f; }
            .badge-warning { background: #fff1db; color: #9a5b00; }
            .form-grid { display: grid; gap: 16px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .form-grid .full { grid-column: 1 / -1; }
            label { display: block; font-size: 14px; font-weight: 600; margin-bottom: 8px; }
            input, textarea, select { width: 100%; padding: 12px 14px; border-radius: 12px; border: 1px solid #d9cdbd; background: #fff; font: inherit; }
            textarea { min-height: 110px; resize: vertical; }
            .help { color: var(--muted); font-size: 13px; }
            .actions { display: flex; gap: 12px; justify-content: flex-end; margin-top: 22px; }
            .alert { padding: 14px 16px; border-radius: 14px; margin-bottom: 16px; border: 1px solid transparent; }
            .alert-success { background: #e7f6ec; border-color: #bfe3ca; color: #185b41; }
            .alert-error { background: #feeceb; border-color: #f4c7c3; color: #9a2c22; }
            .checkbox-grid { display: grid; gap: 10px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
            .checkbox-card { border: 1px solid var(--line); background: #fff; border-radius: 16px; padding: 14px; }
            .checkbox-row { display: flex; gap: 10px; align-items: flex-start; }
            .inline-form { display: inline; }
            .split { display:grid; gap:18px; grid-template-columns: 1fr 1fr; }
            .progress { width: 100%; height: 10px; background: #efe4d3; border-radius: 999px; overflow: hidden; }
            .progress-bar { height: 100%; background: linear-gradient(90deg, #0f766e 0%, #0ea5a4 100%); border-radius: 999px; }
            .notification-pill { display:inline-flex; align-items:center; gap:8px; padding:10px 14px; border-radius:12px; background:#efe6d8; color:var(--text); font-weight:600; }
            .notification-count { display:inline-flex; min-width:24px; height:24px; border-radius:999px; align-items:center; justify-content:center; padding:0 8px; background:#b42318; color:#fff; font-size:12px; }
            .tip-grid { display:grid; gap:16px; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); }
            .tip-card { border:1px solid var(--line); background:#fff; border-radius:16px; padding:14px 16px; }
            .tip-card strong { display:block; margin-bottom:6px; }
            .summary-stack { display:grid; gap:12px; }
            .summary-box { border:1px solid var(--line); background:#fff; border-radius:16px; padding:14px 16px; }
            .summary-box .value { font-size:28px; font-weight:700; margin-top:6px; }
            .summary-list { margin:0; padding-left:18px; display:grid; gap:6px; color:var(--muted); }
            .chip-row { display:flex; gap:8px; flex-wrap:wrap; margin-top:10px; }
            .chip { border:1px solid #d9cdbd; background:#fff; color:var(--text); border-radius:999px; padding:8px 12px; font-size:13px; cursor:pointer; }
            .chip:hover { background:#f9f3ea; }
            .table-foot-note { display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-top:16px; }
            .kpi-row { display:grid; gap:12px; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); }
            .kpi { border:1px solid var(--line); border-radius:16px; padding:14px 16px; background:#fff; }
            .kpi .label { color:var(--muted); font-size:13px; }
            .kpi .value { font-size:24px; font-weight:700; margin-top:8px; }
            .section-title { margin:0 0 8px; font-size:20px; }
            .empty-state { text-align:center; padding:34px 24px; }
            .empty-state h3 { margin:12px 0 8px; font-size:24px; }
            .empty-actions { display:flex; justify-content:center; gap:12px; flex-wrap:wrap; margin-top:18px; }
            .filter-pills { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:16px; }
            .field-error { color:var(--danger); font-size:13px; margin-top:6px; }
            input:disabled, textarea:disabled, select:disabled { background:#f6efe4; color:#7b6c5c; }
            @media (max-width: 980px) {
                .shell { grid-template-columns: 1fr; }
                .sidebar { padding: 18px; }
                .main { padding: 18px; }
                .form-grid, .split { grid-template-columns: 1fr; }
                .topbar, .page-head { flex-direction: column; align-items: flex-start; }
            }
        </style>
    @endif
    <style>
        body {
            overflow: hidden;
        }
        .shell {
            height: 100vh;
            min-height: 100vh;
        }
        .sidebar {
            max-height: 100vh;
            overflow-y: auto;
            overflow-x: hidden;
            scrollbar-width: thin;
            scrollbar-color: #3ec7c9 #10242c;
        }
        .main {
            max-height: 100vh;
            overflow-y: auto;
            overflow-x: hidden;
            scrollbar-width: thin;
            scrollbar-color: #ca6702 #efe3d3;
        }
        .sidebar::-webkit-scrollbar {
            width: 11px;
        }
        .sidebar::-webkit-scrollbar-track {
            background: #10242c;
        }
        .sidebar::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, #2a9d8f 0%, #0ea5a4 100%);
            border-radius: 999px;
            border: 2px solid #10242c;
        }
        .sidebar::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(180deg, #35b6a7 0%, #22c1c3 100%);
        }
        .main::-webkit-scrollbar {
            width: 13px;
        }
        .main::-webkit-scrollbar-track {
            background: #efe3d3;
            border-left: 1px solid #decdb8;
        }
        .main::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, #ca6702 0%, #ee9b00 100%);
            border-radius: 999px;
            border: 3px solid #efe3d3;
        }
        .main::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(180deg, #d97706 0%, #f59e0b 100%);
        }
        @media (max-width: 980px) {
            body {
                overflow: auto;
            }
            .shell {
                height: auto;
                min-height: 100vh;
            }
            .sidebar,
            .main {
                max-height: none;
                overflow: visible;
            }
        }
    </style>
</head>
<body>
<div class="shell">
    <aside class="sidebar">
        <div class="brand">
            <h1 style="margin:0;font-size:24px;">Nema ERP</h1>
            <small>Socle ERP PME maliennes</small>
        </div>
        <nav>
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
            @allowed('notifications.outbound.view')
                <a class="nav-link {{ request()->routeIs('notifications.outbound.*') ? 'active' : '' }}" href="{{ route('notifications.outbound.index') }}">Notifications sortantes</a>
            @endallowed
            @allowed('imports.manage')
                <a class="nav-link {{ request()->routeIs('imports.*') ? 'active' : '' }}" href="{{ route('imports.index') }}">Imports CSV</a>
            @endallowed
            @allowed('ops.view')
                <a class="nav-link {{ request()->routeIs('ops.*') ? 'active' : '' }}" href="{{ route('ops.index') }}">Operations</a>
            @endallowed

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

            <div class="nav-title">Tiers</div>
            @allowed('customers.view')
                <a class="nav-link {{ request()->routeIs('customers.*') ? 'active' : '' }}" href="{{ route('customers.index') }}">Clients</a>
            @endallowed
            @allowed('suppliers.view')
                <a class="nav-link {{ request()->routeIs('suppliers.*') ? 'active' : '' }}" href="{{ route('suppliers.index') }}">Fournisseurs</a>
            @endallowed

            <div class="nav-title">Catalogue</div>
            @allowed('categories.view')
                <a class="nav-link {{ request()->routeIs('categories.*') ? 'active' : '' }}" href="{{ route('categories.index') }}">Categories</a>
            @endallowed
            @allowed('products.view')
                <a class="nav-link {{ request()->routeIs('products.*') ? 'active' : '' }}" href="{{ route('products.index') }}">Produits</a>
            @endallowed
            @allowed('stock.view')
                <a class="nav-link {{ request()->routeIs('stock.*') ? 'active' : '' }}" href="{{ route('stock.index') }}">Stock</a>
            @endallowed
            @allowed('stock_counts.view')
                <a class="nav-link {{ request()->routeIs('stock-counts.*') ? 'active' : '' }}" href="{{ route('stock-counts.index') }}">Inventaires</a>
            @endallowed

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
                @allowed('fixed_assets.view')
                    <a class="nav-link {{ request()->routeIs('fixed-assets.*') ? 'active' : '' }}" href="{{ route('fixed-assets.index') }}">Immobilisations</a>
                @endallowed
            @endallowed

            <div class="nav-title">Audit</div>
            @allowed('activity_logs.view')
                <a class="nav-link {{ request()->routeIs('activity-logs.*') ? 'active' : '' }}" href="{{ route('activity-logs.index') }}">Journaux d'activite</a>
            @endallowed
        </nav>
    </aside>
    <main class="main">
        <div class="topbar">
            <div>
                <div style="font-size: 24px; font-weight: 700;">@yield('page-title', 'Tableau de bord')</div>
                <div class="workspace">
                    Entreprise active : <strong>{{ $workspace->company()?->name ?? 'Non definie' }}</strong>
                    @if ($workspace->branch())
                        Â· Agence active : <strong>{{ $workspace->branch()?->name }}</strong>
                    @endif
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;justify-content:flex-end;">
                @allowed('approvals.view')
                    <a href="{{ route('approvals.index') }}" class="notification-pill">
                        <span>Approbations</span>
                        @if (($approvalSummary['count'] ?? 0) > 0)
                            <span class="notification-count">{{ $approvalSummary['count'] }}</span>
                        @else
                            <span class="badge badge-success">0</span>
                        @endif
                    </a>
                @endallowed
                @allowed('notifications.view')
                    <a href="{{ route('notifications.index') }}" class="notification-pill">
                        <span>Alertes</span>
                        @if (($notificationSummary['count'] ?? 0) > 0)
                            <span class="notification-count">{{ $notificationSummary['count'] }}</span>
                        @else
                            <span class="badge badge-success">0</span>
                        @endif
                    </a>
                @endallowed
                <div class="muted" style="text-align:right;">
                    <div><strong>{{ auth()->user()?->name }}</strong></div>
                    <div>{{ auth()->user()?->email }}</div>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="button button-secondary" type="submit">Deconnexion</button>
                </form>
            </div>
        </div>

        @include('partials.flash')

        @yield('content')
    </main>
</div>
</body>
</html>

















