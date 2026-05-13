@php
    $catalog = collect($appCatalog ?? []);
    $families = $catalog
        ->groupBy(fn (array $item) => $item['app_family'] ?? 'Applications')
        ->map(fn ($items, $label) => [
            'label' => $label,
            'count' => $items->count(),
            'accent' => $items->first()['app_accent'] ?? '#4fd1c5',
            'border' => $items->first()['app_border'] ?? 'rgba(255,255,255,.12)',
        ])
        ->values();

    $resolveAppUrl = static function (string $permission) use ($catalog): string {
        return $catalog->firstWhere('permission', $permission)['url'] ?? route('dashboard');
    };

    $counterCards = array_values(array_filter([
        [
            'label' => 'Alertes',
            'value' => (int) ($stats['alertes_non_lues'] ?? 0),
            'icon' => 'alert',
            'url' => $resolveAppUrl('notifications.view'),
        ],
        [
            'label' => 'Approbations',
            'value' => (int) ($stats['approbations_en_attente_total'] ?? 0),
            'icon' => 'approval',
            'url' => $resolveAppUrl('approvals.view'),
        ],
    ], static fn (array $item): bool => $item['value'] > 0));

    $statusCards = [
        [
            'label' => 'Modules visibles',
            'value' => number_format($catalog->count(), 0, ',', ' '),
            'meta' => number_format($families->count(), 0, ',', ' ').' univers metier',
            'url' => null,
        ],
        [
            'label' => 'Alertes actives',
            'value' => number_format((int) ($stats['alertes_non_lues'] ?? 0), 0, ',', ' '),
            'meta' => 'A traiter dans le centre d alertes',
            'url' => $resolveAppUrl('notifications.view'),
        ],
        [
            'label' => 'Ventes du jour',
            'value' => number_format((int) ($stats['ventes_jour_count'] ?? 0), 0, ',', ' '),
            'meta' => 'facture(s) deja validee(s)',
            'url' => $resolveAppUrl('sales.view'),
        ],
        [
            'label' => 'Stock a surveiller',
            'value' => number_format((int) ($stats['alertes_stock'] ?? 0), 0, ',', ' '),
            'meta' => 'produit(s) au mini ou en dessous',
            'url' => $resolveAppUrl('stock.view'),
        ],
    ];

    $launcherHighlights = collect($premiumActionCenter ?? [])->take(3);
    if ($launcherHighlights->isEmpty()) {
        $launcherHighlights = collect($roleSpotlight ?? [])->take(3);
    }

    $badgeForApp = static function (array $item) use ($stats): ?string {
        $count = match ((string) ($item['permission'] ?? '')) {
            'approvals.view' => (int) ($stats['approbations_en_attente_total'] ?? 0),
            'notifications.view' => (int) ($stats['alertes_non_lues'] ?? 0),
            'sales.view' => (int) ($stats['ventes_jour_count'] ?? 0),
            'payments.view' => (int) ($stats['encaissements_jour_count'] ?? 0),
            'stock.view' => (int) ($stats['alertes_stock'] ?? 0),
            'purchases.view', 'purchase_orders.view', 'purchase_requests.view' => (int) ($stats['achats_en_attente'] ?? 0),
            default => 0,
        };

        if ($count <= 0) {
            return null;
        }

        return $count > 99 ? '99+' : number_format($count, 0, ',', ' ');
    };

    $avatarInitial = mb_strtoupper(mb_substr(trim((string) (auth()->user()?->name ?? 'Nema')), 0, 1));
@endphp

@if ($catalog->isNotEmpty())
    <section class="card dashboard-launcher">
        <div class="dashboard-launcher__top">
            <a href="{{ route('search.index') }}" class="dashboard-launcher__search">
                <span class="dashboard-launcher__search-icon">
                    @include('dashboard.partials.icon', ['name' => 'search', 'size' => 18])
                </span>
                <span>Rechercher un module, un client ou une facture</span>
            </a>
            <div class="dashboard-launcher__actions">
                @foreach ($counterCards as $counter)
                    <a href="{{ $counter['url'] }}" class="dashboard-launcher__counter" aria-label="{{ $counter['label'] }} : {{ $counter['value'] }}">
                        @include('dashboard.partials.icon', ['name' => $counter['icon'], 'size' => 16])
                        <strong>{{ $counter['value'] > 99 ? '99+' : number_format($counter['value'], 0, ',', ' ') }}</strong>
                    </a>
                @endforeach
                <span class="dashboard-launcher__avatar" title="{{ auth()->user()?->name }}">{{ $avatarInitial }}</span>
            </div>
        </div>

        <div class="dashboard-launcher__hero">
            <div class="dashboard-launcher__copy">
                <div class="badge badge-muted">Modules ERP</div>
                <h2 class="dashboard-launcher__title">Applications</h2>
                <p class="dashboard-launcher__body">{{ $dashboardProfile['focus_title'] }}. Ouvre vite le bon espace metier depuis une grille compacte, comme un vrai launcher ERP mobile.</p>
                <div class="dashboard-launcher__family-strip">
                    @foreach ($families as $family)
                        <span class="dashboard-launcher__family" style="--launcher-accent: {{ $family['accent'] }}; --launcher-border: {{ $family['border'] }};">
                            {{ $family['label'] }} <strong>{{ $family['count'] }}</strong>
                        </span>
                    @endforeach
                </div>
            </div>

            <div class="dashboard-launcher__status-grid">
                @foreach ($statusCards as $card)
                    @if ($card['url'])
                        <a href="{{ $card['url'] }}" class="dashboard-launcher__status-card">
                            <span>{{ $card['label'] }}</span>
                            <strong>{{ $card['value'] }}</strong>
                            <small>{{ $card['meta'] }}</small>
                        </a>
                    @else
                        <article class="dashboard-launcher__status-card">
                            <span>{{ $card['label'] }}</span>
                            <strong>{{ $card['value'] }}</strong>
                            <small>{{ $card['meta'] }}</small>
                        </article>
                    @endif
                @endforeach
            </div>
        </div>

        <div class="dashboard-app-grid">
            @foreach ($catalog as $item)
                @php
                    $tileStyle = implode('; ', [
                        '--app-accent: '.$item['app_accent'],
                        '--app-surface: '.$item['app_surface'],
                        '--app-soft: '.$item['app_soft'],
                        '--app-border: '.$item['app_border'],
                        '--app-ink: '.$item['app_ink'],
                        '--app-muted: '.$item['app_muted'],
                        '--app-shadow: '.$item['app_shadow'],
                        '--app-badge-start: '.$item['app_badge_start'],
                        '--app-badge-end: '.$item['app_badge_end'],
                    ]);
                    $badge = $badgeForApp($item);
                @endphp
                <a href="{{ $item['url'] }}" class="dashboard-app-card" aria-label="Ouvrir {{ $item['label'] }}" style="{{ $tileStyle }}">
                    @if ($badge)
                        <span class="dashboard-app-card__badge">{{ $badge }}</span>
                    @endif
                    <span class="dashboard-icon-badge dashboard-icon-badge--app">
                        @include('dashboard.partials.icon', ['name' => $item['icon'] ?? 'grid', 'size' => 30])
                    </span>
                    <strong class="dashboard-app-card__label">{{ $item['short_label'] ?? $item['label'] }}</strong>
                    <span class="dashboard-app-card__family">{{ $item['app_family'] ?? 'Application' }}</span>
                </a>
            @endforeach
        </div>

        @if ($launcherHighlights->isNotEmpty())
            <div class="dashboard-launcher__focus-grid">
                @foreach ($launcherHighlights as $item)
                    <a href="{{ $item['url'] }}" class="dashboard-launcher__focus-card">
                        <div class="dashboard-card-lead">
                            <span class="dashboard-icon-badge dashboard-icon-badge--premium">
                                @include('dashboard.partials.icon', ['name' => $item['icon'] ?? 'flash', 'size' => 18])
                            </span>
                            <div>
                                <p class="dashboard-card-label">{{ $item['label'] }}</p>
                                <div class="dashboard-card-caption">{{ $item['eyebrow'] ?? 'Focus' }}</div>
                            </div>
                        </div>
                        <div class="stat-value">{{ $item['metric'] ?? $item['value'] ?? '0' }}</div>
                        <p class="muted">{{ $item['description'] }}</p>
                    </a>
                @endforeach
            </div>
        @endif
    </section>
@endif
