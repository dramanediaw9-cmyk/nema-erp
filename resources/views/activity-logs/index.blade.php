@extends('layouts.app')

@section('title', 'Journal audit - Nema ERP')
@section('page-title', 'Journal audit')

@section('content')
    @php
        $formatAuditValue = function ($value): string {
            if (is_bool($value)) {
                return $value ? 'Oui' : 'Non';
            }

            if ($value === null || $value === '') {
                return '-';
            }

            if (is_array($value)) {
                return \Illuminate\Support\Str::limit(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 80);
            }

            return \Illuminate\Support\Str::limit((string) $value, 80);
        };

        $fieldLabels = [
            'name' => 'Nom',
            'sku' => 'Reference',
            'barcode' => 'Code-barres',
            'sale_price' => 'Prix vente',
            'purchase_price' => 'Prix achat',
            'min_stock' => 'Stock mini',
            'sale_ok' => 'Vente active',
            'purchase_ok' => 'Achat actif',
            'sale_blocked' => 'Vente bloquee',
            'purchase_blocked' => 'Achat bloque',
            'auto_replenish' => 'Reappro auto',
            'reorder_max_qty' => 'Stock cible',
            'reorder_multiple_qty' => 'Multiple achat',
            'purchase_lead_time_days' => 'Delai achat',
            'is_active' => 'Actif',
            'tracking_type' => 'Suivi',
            'invoice_policy' => 'Facturation',
        ];

        $sensitiveActions = [
            'products.update',
            'products.delete',
            'products.archive',
            'products.restore',
            'pos.session.open',
            'pos.session.close',
            'pos.session.unlock',
            'pos.sale.return',
            'stock.adjustment',
            'stock.opening',
            'stock_counts.post',
            'transfers.create',
            'replenishments.generate',
            'replenishments.activate_products',
        ];
    @endphp

    @include('partials.erp-page-head', [
        'eyebrow' => 'Controle',
        'title' => 'Journal audit',
        'description' => 'Controle rapide des changements sensibles : prix, stock, caisse, utilisateurs, droits et parametres societe.',
    ])

    <div class="grid stats-grid" style="margin-bottom:16px;">
        <div class="card"><div class="muted">Evenements filtres</div><div class="stat-value">{{ number_format($auditSummary['total'] ?? 0, 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Actions sensibles</div><div class="stat-value">{{ number_format($auditSummary['sensitive'] ?? 0, 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Utilisateurs</div><div class="stat-value">{{ number_format($auditSummary['users'] ?? 0, 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Derniere trace</div><div class="stat-value" style="font-size:22px;">{{ ! empty($auditSummary['latest']) ? \Illuminate\Support\Carbon::parse($auditSummary['latest'])->format('d/m H:i') : '-' }}</div></div>
    </div>

    <div class="card" style="margin-bottom:16px;">
        <form method="GET" class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); align-items: end;">
            <div style="min-width: 220px;">
                <label for="search">Recherche</label>
                <input type="text" id="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Produit, utilisateur, action, IP...">
            </div>
            <div>
                <label for="category">Categorie</label>
                <select id="category" name="category">
                    <option value="">Toutes</option>
                    @foreach ($categories as $key => $category)
                        <option value="{{ $key }}" @selected(($filters['category'] ?? null) === $key)>{{ $category['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="action">Action precise</label>
                <select id="action" name="action">
                    <option value="">Toutes les actions</option>
                    @foreach ($actions as $action)
                        <option value="{{ $action }}" @selected(($filters['action'] ?? null) === $action)>{{ $action }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="branch_id">Agence</label>
                <select id="branch_id" name="branch_id">
                    <option value="">Toutes les agences</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((int) ($filters['branch_id'] ?? 0) === $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="date_from">Du</label>
                <input type="date" id="date_from" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
            </div>
            <div>
                <label for="date_to">Au</label>
                <input type="date" id="date_to" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
            </div>
            <div class="actions" style="margin-top:0; justify-content:flex-start; align-self:end;">
                <button type="submit" class="button button-primary">Filtrer</button>
                <a href="{{ route('activity-logs.index') }}" class="button button-secondary">Reinitialiser</a>
            </div>
        </form>
    </div>

    <div class="card table-wrap">
        <table>
            <thead>
            <tr>
                <th>Date</th>
                <th>Zone</th>
                <th>Action</th>
                <th>Details controle</th>
                <th>Utilisateur</th>
                <th>Agence</th>
                <th>IP</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($logs as $log)
                @php
                    $properties = is_array($log->properties) ? $log->properties : [];
                    $changes = collect($properties['changes'] ?? $properties['sensitive_changes'] ?? []);
                    $shortProperties = collect($properties)
                        ->except(['changes', 'sensitive_changes'])
                        ->filter(fn ($value) => ! is_array($value) || $value !== [])
                        ->take(6);
                    $isSensitive = in_array($log->action, $sensitiveActions, true)
                        || \Illuminate\Support\Str::startsWith($log->action, ['users.', 'roles.', 'permissions.', 'settings.', 'companies.', 'branches.', 'warehouses.', 'cash_registers.', 'taxes.']);
                    $zone = collect($categories)->first(fn ($category) => collect($category['prefixes'] ?? [])->contains(fn ($prefix) => \Illuminate\Support\Str::startsWith($log->action, $prefix)));
                    $subjectName = class_basename($log->subject_type ?: '');
                @endphp
                <tr>
                    <td style="white-space:nowrap;">{{ $log->created_at?->format('d/m/Y H:i') }}</td>
                    <td>
                        <div>{{ $zone['label'] ?? ($isSensitive ? 'Sensible' : 'General') }}</div>
                        @if ($subjectName)
                            <div class="muted" style="font-size:12px; margin-top:4px;">{{ $subjectName }} #{{ $log->subject_id }}</div>
                        @endif
                    </td>
                    <td>
                        @include('partials.erp-status-badge', [
                            'label' => $log->action,
                            'tone' => $isSensitive ? 'warning' : 'muted',
                        ])
                    </td>
                    <td>
                        <div style="font-weight:700;">{{ $log->description }}</div>

                        @if ($changes->isNotEmpty())
                            <div style="display:grid; gap:6px; margin-top:8px;">
                                @foreach ($changes->take(6) as $field => $change)
                                    <div style="display:flex; flex-wrap:wrap; gap:6px; align-items:center; font-size:13px;">
                                        <strong>{{ $fieldLabels[$field] ?? $field }}</strong>
                                        <span class="muted">{{ $formatAuditValue($change['old'] ?? null) }}</span>
                                        <span>-></span>
                                        <span>{{ $formatAuditValue($change['new'] ?? null) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @elseif ($shortProperties->isNotEmpty())
                            <div style="display:flex; flex-wrap:wrap; gap:6px; margin-top:8px;">
                                @foreach ($shortProperties as $key => $value)
                                    <span style="border:1px solid rgba(15, 23, 42, .12); border-radius:999px; padding:3px 8px; font-size:12px;">
                                        {{ $fieldLabels[$key] ?? $key }}: {{ $formatAuditValue($value) }}
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </td>
                    <td>
                        <div>{{ $log->user?->name ?? 'Systeme' }}</div>
                        @if ($log->user?->email)
                            <div class="muted" style="font-size:12px; margin-top:4px;">{{ $log->user->email }}</div>
                        @endif
                    </td>
                    <td>{{ $log->branch?->name ?? 'Non renseignee' }}</td>
                    <td title="{{ $log->user_agent ?: 'Non renseigne' }}">{{ $log->ip_address ?: '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7"><span class="muted">Aucune activite enregistree.</span></td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:16px;">{{ $logs->links() }}</div>
@endsection
