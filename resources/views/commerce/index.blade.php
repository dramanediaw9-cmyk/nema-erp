@extends('layouts.app')

@section('title', 'Commerce unifie - Nema ERP')
@section('page-title', 'Commerce unifie')

@section('content')
    @php
        $canManageCommerce = auth()->user()?->hasPermission('commerce.manage') ?? false;
        $channelBadge = [
            'pipeline' => 'badge-muted',
            'active' => 'badge-success',
            'paused' => 'badge-warning',
        ];
        $healthBadge = [
            'stable' => ['label' => 'Sous controle', 'class' => 'badge-success'],
            'watch' => ['label' => 'A surveiller', 'class' => 'badge-warning'],
            'risk' => ['label' => 'Sous tension', 'class' => 'badge-danger'],
            'growth' => ['label' => 'Canal en traction', 'class' => 'badge-muted'],
        ];
        $actionBadge = [
            'todo' => 'badge-muted',
            'in_progress' => 'badge-success',
            'blocked' => 'badge-danger',
            'done' => 'badge-warning',
        ];
        $impactBadge = [
            'low' => 'badge-muted',
            'normal' => 'badge-warning',
            'high' => 'badge-danger',
            'critical' => 'badge-danger',
        ];
    @endphp

    <div class="erp-work-page">
        <section class="erp-work-toolbar">
            <div class="erp-work-toolbar__context">
                <div>
                    <strong>Commerce unifie</strong>
                    <div class="muted">Traction et incidents web, retail, marketplace et mobile money.</div>
                </div>
            </div>
        </section>

    @if ($errors->any())
        <div class="card" style="margin-bottom:18px; border-color:#9c3d2f;">
            <strong>Des validations sont a corriger</strong>
            <ul class="summary-list" style="margin-top:10px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

        <div class="erp-kpi-strip">
            <div class="card erp-kpi-card"><div class="label">Canaux</div><div class="value">{{ $summary['channels'] }}</div></div>
            <div class="card erp-kpi-card"><div class="label">Actifs</div><div class="value">{{ $summary['active'] }}</div></div>
            <div class="card erp-kpi-card"><div class="label">Digitaux</div><div class="value">{{ $summary['digital'] }}</div></div>
            <div class="card erp-kpi-card"><div class="label">Objectif mensuel</div><div class="value">{{ number_format($summary['target_revenue'], 0, ',', ' ') }} XOF</div></div>
            <div class="card erp-kpi-card"><div class="label">CA suivi</div><div class="value">{{ number_format($summary['current_revenue'], 0, ',', ' ') }} XOF</div></div>
            <div class="card erp-kpi-card"><div class="label">Sous tension</div><div class="value">{{ $summary['at_risk'] }}</div></div>
            <div class="card erp-kpi-card"><div class="label">Actions ouvertes</div><div class="value">{{ $summary['open_actions'] }}</div></div>
        </div>

    @allowed('commerce.manage')
        <details class="card erp-filter-panel" @if($errors->any()) open @endif>
            <summary>Ajouter un canal</summary>
            <div class="erp-filter-panel__body">
        <form method="POST" action="{{ route('commerce.store') }}" class="card form-grid" style="margin-bottom:18px;">
            @csrf
            <div class="full">
                <h3 class="section-title">Nouveau canal</h3>
            </div>
            <div>
                <label for="code">Code</label>
                <input id="code" name="code" value="{{ old('code') }}" placeholder="CH-0001">
            </div>
            <div>
                <label for="name">Nom</label>
                <input id="name" name="name" value="{{ old('name') }}" required>
            </div>
            <div>
                <label for="branch_id">Agence</label>
                <select id="branch_id" name="branch_id">
                    <option value="">Perimetre global</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="channel_type">Type</label>
                <select id="channel_type" name="channel_type" required>
                    @foreach ($typeOptions as $value => $label)
                        <option value="{{ $value }}" @selected(old('channel_type', 'b2b') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="status">Statut</label>
                <select id="status" name="status" required>
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', 'pipeline') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="connector_name">Connecteur</label>
                <input id="connector_name" name="connector_name" value="{{ old('connector_name') }}" placeholder="Shopify, WooCommerce, Wave API...">
            </div>
            <div>
                <label for="settlement_mode">Reglement</label>
                <select id="settlement_mode" name="settlement_mode" required>
                    @foreach ($settlementOptions as $value => $label)
                        <option value="{{ $value }}" @selected(old('settlement_mode', 'mixed') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="target_monthly_revenue">Objectif mensuel</label>
                <input id="target_monthly_revenue" name="target_monthly_revenue" type="number" min="0" step="0.01" value="{{ old('target_monthly_revenue', 0) }}">
            </div>
            <div class="full">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes">{{ old('notes') }}</textarea>
            </div>
            <div class="full actions">
                <button type="submit" class="button button-primary">Enregistrer le canal</button>
            </div>
        </form>
            </div>
        </details>
    @endallowed

    <section class="card">
        <h3 class="section-title">Vue portefeuille</h3>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Canal</th>
                    <th>Traction</th>
                    <th>Experience</th>
                    <th>Execution</th>
                    <th>Statut</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($channels as $channel)
                    @php($snapshot = $channel->execution_snapshot)
                    <tr>
                        <td>
                            <strong>{{ $channel->name }}</strong>
                            <div class="muted" style="font-size:12px;">{{ $typeOptions[$channel->channel_type] ?? $channel->channel_type }} · {{ $channel->connector_name ?: 'Sans connecteur' }}</div>
                        </td>
                        <td>
                            <div>{{ number_format($snapshot['gross_revenue'], 0, ',', ' ') }} XOF</div>
                            <div class="muted" style="font-size:12px;">{{ $snapshot['orders_count'] }} commandes · {{ $snapshot['revenue_ratio'] !== null ? number_format($snapshot['revenue_ratio'], 1, ',', ' ') . '%' : 'Sans objectif' }}</div>
                        </td>
                        <td>
                            <div>Conv. {{ number_format($snapshot['conversion_rate'], 1, ',', ' ') }}%</div>
                            <div class="muted" style="font-size:12px;">Service {{ number_format($snapshot['service_level'], 1, ',', ' ') }}% · incidents {{ $snapshot['failed_orders_count'] + $snapshot['failed_payments_count'] }}</div>
                        </td>
                        <td>
                            <div>{{ $snapshot['open_actions'] }} action(s) ouverte(s)</div>
                            <div class="muted" style="font-size:12px;">{{ $snapshot['overdue_actions'] }} en retard</div>
                        </td>
                        <td>
                            <span class="badge {{ $channelBadge[$channel->status] ?? 'badge-muted' }}">{{ $statusOptions[$channel->status] ?? $channel->status }}</span>
                            <span class="badge {{ $healthBadge[$snapshot['health']]['class'] ?? 'badge-muted' }}">{{ $healthBadge[$snapshot['health']]['label'] ?? $snapshot['health_label'] }}</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">Aucun canal commerce enregistre pour le moment.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="grid" style="gap:18px;">
        @forelse ($channels as $channel)
            @php($snapshot = $channel->execution_snapshot)
            <article class="card" style="border-color: {{ $snapshot['health'] === 'risk' ? '#9c3d2f' : '#d6dde8' }};">
                <div class="page-head" style="margin-bottom:14px;">
                    <div>
                        <h3 style="margin:0 0 4px;">{{ $channel->name }}</h3>
                        <div class="muted">{{ $channel->code }} · {{ $typeOptions[$channel->channel_type] ?? $channel->channel_type }} · {{ $channel->branch?->name ?? 'Perimetre global' }}</div>
                    </div>
                    <div style="display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end;">
                        <span class="badge {{ $channelBadge[$channel->status] ?? 'badge-muted' }}">{{ $statusOptions[$channel->status] ?? $channel->status }}</span>
                        <span class="badge {{ $healthBadge[$snapshot['health']]['class'] ?? 'badge-muted' }}">{{ $healthBadge[$snapshot['health']]['label'] ?? $snapshot['health_label'] }}</span>
                    </div>
                </div>

                <div class="summary-list" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:10px; margin-bottom:14px;">
                    <div><strong>Objectif</strong><div class="muted">{{ number_format((float) $channel->target_monthly_revenue, 0, ',', ' ') }} XOF</div></div>
                    <div><strong>CA suivi</strong><div class="muted">{{ number_format($snapshot['gross_revenue'], 0, ',', ' ') }} XOF</div></div>
                    <div><strong>Commandes</strong><div class="muted">{{ $snapshot['orders_count'] }}</div></div>
                    <div><strong>Panier moyen</strong><div class="muted">{{ number_format($snapshot['average_order_value'], 0, ',', ' ') }} XOF</div></div>
                    <div><strong>Conversion</strong><div class="muted">{{ number_format($snapshot['conversion_rate'], 1, ',', ' ') }}%</div></div>
                    <div><strong>Service</strong><div class="muted">{{ number_format($snapshot['service_level'], 1, ',', ' ') }}%</div></div>
                    <div><strong>Incidents</strong><div class="muted">{{ $snapshot['failed_orders_count'] }} commande(s) · {{ $snapshot['failed_payments_count'] }} paiement(s)</div></div>
                    <div><strong>Snapshot</strong><div class="muted">{{ optional($snapshot['snapshot_date'])->format('d/m/Y') ?: 'Aucune mesure' }}</div></div>
                </div>

                @if ($channel->notes)
                    <div class="muted" style="margin-bottom:14px;">{{ $channel->notes }}</div>
                @endif

                @allowed('commerce.manage')
                    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:14px; margin-bottom:14px;">
                        <form method="POST" action="{{ route('commerce.snapshots.store', $channel) }}" class="card form-grid" style="background:#f8fafc;">
                            @csrf
                            <div class="full">
                                <h4 class="section-title" style="margin-bottom:0;">Mettre a jour les KPI</h4>
                            </div>
                            <div>
                                <label for="snapshot-date-{{ $channel->id }}">Date</label>
                                <input id="snapshot-date-{{ $channel->id }}" name="snapshot_date" type="date" value="{{ old('snapshot_date', now()->toDateString()) }}" required>
                            </div>
                            <div>
                                <label for="gross-revenue-{{ $channel->id }}">CA</label>
                                <input id="gross-revenue-{{ $channel->id }}" name="gross_revenue" type="number" min="0" step="0.01" value="{{ old('gross_revenue', $snapshot['gross_revenue']) }}">
                            </div>
                            <div>
                                <label for="orders-count-{{ $channel->id }}">Commandes</label>
                                <input id="orders-count-{{ $channel->id }}" name="orders_count" type="number" min="0" value="{{ old('orders_count', $snapshot['orders_count']) }}">
                            </div>
                            <div>
                                <label for="average-order-value-{{ $channel->id }}">Panier moyen</label>
                                <input id="average-order-value-{{ $channel->id }}" name="average_order_value" type="number" min="0" step="0.01" value="{{ old('average_order_value', $snapshot['average_order_value']) }}">
                            </div>
                            <div>
                                <label for="conversion-rate-{{ $channel->id }}">Conversion %</label>
                                <input id="conversion-rate-{{ $channel->id }}" name="conversion_rate" type="number" min="0" max="100" step="0.01" value="{{ old('conversion_rate', $snapshot['conversion_rate']) }}">
                            </div>
                            <div>
                                <label for="service-level-{{ $channel->id }}">Service %</label>
                                <input id="service-level-{{ $channel->id }}" name="service_level" type="number" min="0" max="100" step="0.01" value="{{ old('service_level', $snapshot['service_level']) }}">
                            </div>
                            <div>
                                <label for="failed-orders-{{ $channel->id }}">Commandes en echec</label>
                                <input id="failed-orders-{{ $channel->id }}" name="failed_orders_count" type="number" min="0" value="{{ old('failed_orders_count', $snapshot['failed_orders_count']) }}">
                            </div>
                            <div>
                                <label for="failed-payments-{{ $channel->id }}">Paiements en echec</label>
                                <input id="failed-payments-{{ $channel->id }}" name="failed_payments_count" type="number" min="0" value="{{ old('failed_payments_count', $snapshot['failed_payments_count']) }}">
                            </div>
                            <div class="full">
                                <label for="snapshot-notes-{{ $channel->id }}">Notes KPI</label>
                                <textarea id="snapshot-notes-{{ $channel->id }}" name="notes" rows="2">{{ old('notes') }}</textarea>
                            </div>
                            <div class="full actions">
                                <button type="submit" class="button button-primary">Enregistrer le snapshot</button>
                            </div>
                        </form>

                        <form method="POST" action="{{ route('commerce.actions.store', $channel) }}" class="card form-grid" style="background:#f8fafc;">
                            @csrf
                            <div class="full">
                                <h4 class="section-title" style="margin-bottom:0;">Ajouter une action</h4>
                            </div>
                            <div>
                                <label for="action-title-{{ $channel->id }}">Intitule</label>
                                <input id="action-title-{{ $channel->id }}" name="title" value="{{ old('title') }}" required placeholder="Ex: corriger taux d echec Wave, relancer campagne, nettoyer catalogue">
                            </div>
                            <div>
                                <label for="action-owner-{{ $channel->id }}">Responsable</label>
                                <select id="action-owner-{{ $channel->id }}" name="owner_id">
                                    <option value="">Non affecte</option>
                                    @foreach ($owners as $owner)
                                        <option value="{{ $owner->id }}" @selected(old('owner_id') == $owner->id)>{{ $owner->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="action-type-{{ $channel->id }}">Type</label>
                                <select id="action-type-{{ $channel->id }}" name="action_type">
                                    @foreach ($actionTypeOptions as $value => $label)
                                        <option value="{{ $value }}" @selected(old('action_type', 'campaign') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="action-impact-{{ $channel->id }}">Impact</label>
                                <select id="action-impact-{{ $channel->id }}" name="impact_level">
                                    @foreach ($impactOptions as $value => $label)
                                        <option value="{{ $value }}" @selected(old('impact_level', 'normal') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="action-status-{{ $channel->id }}">Statut</label>
                                <select id="action-status-{{ $channel->id }}" name="status">
                                    @foreach ($actionStatusOptions as $value => $label)
                                        <option value="{{ $value }}" @selected(old('status', 'todo') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="action-due-{{ $channel->id }}">Echeance</label>
                                <input id="action-due-{{ $channel->id }}" name="due_date" type="date" value="{{ old('due_date', now()->addDays(5)->toDateString()) }}">
                            </div>
                            <div class="full">
                                <label for="action-notes-{{ $channel->id }}">Notes action</label>
                                <textarea id="action-notes-{{ $channel->id }}" name="notes" rows="2">{{ old('notes') }}</textarea>
                            </div>
                            <div class="full actions">
                                <button type="submit" class="button button-primary">Ajouter au backlog</button>
                            </div>
                        </form>
                    </div>
                @endallowed

                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>Action</th>
                            <th>Responsable</th>
                            <th>Echeance</th>
                            <th>Impact</th>
                            <th>Statut</th>
                            @if ($canManageCommerce)
                                <th>Actions rapides</th>
                            @endif
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($channel->actions as $action)
                            <tr>
                                <td>
                                    <strong>{{ $action->title }}</strong>
                                    <div style="display:flex; gap:6px; flex-wrap:wrap; margin-top:4px;">
                                        <span class="badge badge-muted">{{ $actionTypeOptions[$action->action_type] ?? $action->action_type }}</span>
                                        <span class="badge {{ $impactBadge[$action->impact_level] ?? 'badge-muted' }}">{{ $impactOptions[$action->impact_level] ?? $action->impact_level }}</span>
                                    </div>
                                    @if ($action->notes)
                                        <div class="muted" style="font-size:12px; margin-top:6px;">{{ $action->notes }}</div>
                                    @endif
                                </td>
                                <td>{{ $action->owner?->name ?? '-' }}</td>
                                <td>
                                    {{ optional($action->due_date)->format('d/m/Y') ?: '-' }}
                                    @if ($action->isOverdue())
                                        <div class="muted" style="font-size:12px; color:#9c3d2f;">Retard actif</div>
                                    @endif
                                </td>
                                <td>{{ $impactOptions[$action->impact_level] ?? $action->impact_level }}</td>
                                <td><span class="badge {{ $actionBadge[$action->status] ?? 'badge-muted' }}">{{ $actionStatusOptions[$action->status] ?? $action->status }}</span></td>
                                @if ($canManageCommerce)
                                    <td>
                                        <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                            @if ($action->status !== 'in_progress')
                                                <form method="POST" action="{{ route('commerce.actions.status', $action) }}">
                                                    @csrf
                                                    <input type="hidden" name="status" value="in_progress">
                                                    <button type="submit" class="button button-secondary">Demarrer</button>
                                                </form>
                                            @endif
                                            @if ($action->status !== 'blocked' && $action->status !== 'done')
                                                <form method="POST" action="{{ route('commerce.actions.status', $action) }}">
                                                    @csrf
                                                    <input type="hidden" name="status" value="blocked">
                                                    <button type="submit" class="button button-secondary">Bloquer</button>
                                                </form>
                                            @endif
                                            @if ($action->status !== 'done')
                                                <form method="POST" action="{{ route('commerce.actions.status', $action) }}">
                                                    @csrf
                                                    <input type="hidden" name="status" value="done">
                                                    <button type="submit" class="button button-primary">Terminer</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canManageCommerce ? 6 : 5 }}" class="muted">Aucune action canal pour le moment.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </article>
        @empty
            <div class="card">
                <div class="muted">Ajoute un premier canal pour lancer le pilotage omnicanal.</div>
            </div>
        @endforelse
    </section>
    </div>
@endsection
