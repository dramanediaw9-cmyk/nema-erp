@php
    $user = auth()->user();
    $metricCards = collect([
        [
            'permission' => 'stock.view',
            'label' => 'References disponibles',
            'value' => number_format((int) ($stats['references_disponibles'] ?? 0), 0, ',', ' '),
            'detail' => 'avec un solde positif',
            'icon' => 'stock',
            'tone' => 'success',
            'url' => route('stock.index', ['stock_state' => 'available']),
        ],
        [
            'permission' => 'stock.view',
            'label' => 'Ruptures et seuil mini',
            'value' => number_format((int) ($stats['alertes_stock'] ?? 0), 0, ',', ' '),
            'detail' => 'references a traiter',
            'icon' => 'alert',
            'tone' => ((int) ($stats['alertes_stock'] ?? 0)) > 0 ? 'danger' : 'success',
            'url' => route('stock.index', ['stock_state' => 'low']),
        ],
        [
            'permission' => 'stock.view',
            'label' => 'Mouvements aujourd hui',
            'value' => number_format((int) ($stats['mouvements_stock_jour'] ?? 0), 0, ',', ' '),
            'detail' => 'entrees et sorties',
            'icon' => 'pulse',
            'tone' => 'neutral',
            'url' => route('stock.movements'),
        ],
        [
            'permission' => 'stock_counts.view',
            'label' => 'Inventaires ouverts',
            'value' => number_format((int) ($stats['inventaires_ouverts'] ?? 0), 0, ',', ' '),
            'detail' => 'comptages a valider',
            'icon' => 'gauge',
            'tone' => ((int) ($stats['inventaires_ouverts'] ?? 0)) > 0 ? 'warning' : 'neutral',
            'url' => route('stock-counts.index'),
        ],
        [
            'permission' => 'transfers.view',
            'label' => 'Transferts du jour',
            'value' => number_format((int) ($stats['transferts_jour'] ?? 0), 0, ',', ' '),
            'detail' => 'entre depots',
            'icon' => 'truck',
            'tone' => 'neutral',
            'url' => route('transfers.index'),
        ],
        [
            'permission' => 'sales.view',
            'label' => 'Ventes du jour',
            'value' => number_format((float) ($stats['ventes_jour'] ?? 0), 0, ',', ' ').' FCFA',
            'detail' => number_format((int) ($stats['ventes_jour_count'] ?? 0), 0, ',', ' ').' facture(s)',
            'icon' => 'sell',
            'tone' => 'neutral',
            'url' => route('sales.index'),
        ],
        [
            'permission' => 'payments.view',
            'label' => 'Encaissements du jour',
            'value' => number_format((float) ($stats['encaissements_jour'] ?? 0), 0, ',', ' ').' FCFA',
            'detail' => number_format((int) ($stats['encaissements_jour_count'] ?? 0), 0, ',', ' ').' operation(s)',
            'icon' => 'wallet',
            'tone' => 'neutral',
            'url' => route('payments.index'),
        ],
    ])->filter(fn (array $item) => $user?->hasPermission($item['permission']));

    $quickActions = collect([
        ['permission' => 'pos.view', 'label' => 'Caisse', 'icon' => 'pos', 'url' => route('pos.index')],
        ['permission' => 'sales.manage', 'label' => 'Nouvelle vente', 'icon' => 'sell', 'url' => route('sales.create')],
        ['permission' => 'payments.validate', 'label' => 'Encaisser', 'icon' => 'wallet', 'url' => route('payments.create')],
        ['permission' => 'stock_counts.manage', 'label' => 'Inventaire rapide', 'icon' => 'gauge', 'url' => route('stock-counts.quick')],
        ['permission' => 'purchase_requests.view', 'label' => 'Reappro auto', 'icon' => 'flash', 'url' => route('replenishments.index')],
        ['permission' => 'transfers.manage', 'label' => 'Nouveau transfert', 'icon' => 'truck', 'url' => route('transfers.create')],
        ['permission' => 'pos.view', 'label' => 'Rapport caisse', 'icon' => 'report', 'url' => route('pos.report')],
        ['permission' => 'imports.manage', 'label' => 'Import Excel', 'icon' => 'import', 'url' => route('imports.index')],
        ['permission' => 'activity_logs.view', 'label' => 'Audit', 'icon' => 'pulse', 'url' => route('activity-logs.index')],
    ])->filter(fn (array $item) => $user?->hasPermission($item['permission']));

    $movementLabels = [
        'opening' => 'Stock initial',
        'purchase' => 'Reception',
        'sale' => 'Vente',
        'adjustment_in' => 'Ajustement +',
        'adjustment_out' => 'Ajustement -',
        'transfer_in' => 'Transfert entrant',
        'transfer_out' => 'Transfert sortant',
        'return_in' => 'Retour entrant',
        'return_out' => 'Retour sortant',
    ];
@endphp

<section class="dashboard-workspace" aria-labelledby="dashboard-workspace-title">
    <header class="dashboard-workspace__header">
        <div>
            <p class="dashboard-workspace__eyebrow">Travail du jour</p>
            <h2 id="dashboard-workspace-title">Situation operationnelle</h2>
        </div>
        <div class="dashboard-workspace__tools">
            <a href="{{ route('search.index') }}" class="dashboard-workspace__search">
                @include('dashboard.partials.icon', ['name' => 'search', 'size' => 17])
                <span>Recherche globale</span>
            </a>
            @foreach ($quickActions as $action)
                <a href="{{ $action['url'] }}" class="dashboard-workspace__action">
                    @include('dashboard.partials.icon', ['name' => $action['icon'], 'size' => 17])
                    <span>{{ $action['label'] }}</span>
                </a>
            @endforeach
        </div>
    </header>

    <div class="dashboard-workspace__metrics">
        @foreach ($metricCards as $metric)
            <a href="{{ $metric['url'] }}" class="dashboard-workspace__metric dashboard-workspace__metric--{{ $metric['tone'] }}">
                <span class="dashboard-workspace__metric-icon">
                    @include('dashboard.partials.icon', ['name' => $metric['icon'], 'size' => 18])
                </span>
                <span class="dashboard-workspace__metric-copy">
                    <small>{{ $metric['label'] }}</small>
                    <strong>{{ $metric['value'] }}</strong>
                    <span>{{ $metric['detail'] }}</span>
                </span>
            </a>
        @endforeach
    </div>

    <div class="dashboard-workspace__feed-grid">
        @if ($user?->hasPermission('stock.view'))
            <section class="dashboard-workspace__feed" aria-labelledby="stock-movements-title">
                <div class="dashboard-workspace__section-head">
                    <div>
                        <h3 id="stock-movements-title">Derniers mouvements de stock</h3>
                        <span>Agence active</span>
                    </div>
                    <a href="{{ route('stock.movements') }}">Voir tout</a>
                </div>
                @if ($recentStockMovements->isEmpty())
                    <p class="dashboard-workspace__empty">Aucun mouvement de stock enregistre.</p>
                @else
                    <div class="dashboard-workspace__movement-list">
                        @foreach ($recentStockMovements as $movement)
                            @php
                                $isEntry = (float) $movement->quantity_in > 0;
                                $quantity = $isEntry ? (float) $movement->quantity_in : (float) $movement->quantity_out;
                                $formattedQuantity = rtrim(rtrim(number_format($quantity, 3, ',', ' '), '0'), ',');
                            @endphp
                            <article class="dashboard-workspace__movement">
                                <span class="dashboard-workspace__direction dashboard-workspace__direction--{{ $isEntry ? 'in' : 'out' }}">{{ $isEntry ? '+' : '-' }}</span>
                                <div class="dashboard-workspace__movement-main">
                                    <strong>{{ $movement->product?->name ?? 'Produit archive' }}</strong>
                                    <span>{{ $movementLabels[$movement->movement_type] ?? ucfirst(str_replace('_', ' ', $movement->movement_type)) }} · {{ $movement->warehouse?->name ?? 'Depot' }}</span>
                                </div>
                                <strong class="dashboard-workspace__quantity">{{ $isEntry ? '+' : '-' }}{{ $formattedQuantity }}</strong>
                                <time datetime="{{ $movement->movement_date?->toIso8601String() }}">{{ $movement->movement_date?->format('d/m H:i') }}</time>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        @endif

        <section class="dashboard-workspace__feed" aria-labelledby="recent-activity-title">
            <div class="dashboard-workspace__section-head">
                <div>
                    <h3 id="recent-activity-title">Activite recente</h3>
                    <span>Dernieres operations ERP</span>
                </div>
            </div>
            @if ($recentActivities->isEmpty())
                <p class="dashboard-workspace__empty">Aucune activite enregistree.</p>
            @else
                <div class="dashboard-workspace__activity-list">
                    @foreach ($recentActivities->take(5) as $activity)
                        <article class="dashboard-workspace__activity">
                            <span class="dashboard-workspace__activity-dot"></span>
                            <div>
                                <strong>{{ $activity->description }}</strong>
                                <span>{{ $activity->user?->name ?? 'Systeme' }} · {{ $activity->created_at?->format('d/m/Y H:i') }}</span>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</section>
