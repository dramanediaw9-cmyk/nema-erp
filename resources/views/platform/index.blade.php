@extends('layouts.app')

@section('title', 'Plateforme - Nema ERP')
@section('page-title', 'Plateforme')

@section('content')
    @php
        $canManageIntegrations = auth()->user()?->hasPermission('settings.integrations.manage') ?? false;
        $connectionTypeBadge = [
            'api' => 'badge-muted',
            'webhook' => 'badge-warning',
            'payment_gateway' => 'badge-success',
            'marketplace' => 'badge-warning',
            'bi' => 'badge-muted',
            'logistics' => 'badge-warning',
        ];
        $statusBadge = [
            'draft' => 'badge-muted',
            'active' => 'badge-success',
            'paused' => 'badge-warning',
            'deprecated' => 'badge-danger',
        ];
        $healthBadge = [
            'healthy' => 'badge-success',
            'watch' => 'badge-warning',
            'critical' => 'badge-danger',
        ];
    @endphp

    <div class="page-head">
        <div>
            <h2 style="margin:0;">Socle produit et ecosysteme</h2>
            <div class="muted">Lecture rapide des axes packaging, API, partenaires et modules d expansion, avec un hub integrateur directement dans l ERP.</div>
        </div>
        <a href="{{ route('ops.index') }}" class="button button-secondary">Ouvrir Operations</a>
    </div>

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

    <div class="grid stats-grid" style="margin-bottom:20px;">
        <div class="card"><div class="muted">Edition</div><div class="stat-value">{{ $catalog['product']['edition'] }}</div></div>
        <div class="card"><div class="muted">Jetons API</div><div class="stat-value">{{ $catalog['metrics']['api_tokens'] }}</div></div>
        <div class="card"><div class="muted">Connexions</div><div class="stat-value">{{ $catalog['metrics']['integration_connections'] }}</div></div>
        <div class="card"><div class="muted">Outbox pending</div><div class="stat-value">{{ $catalog['metrics']['outbox_pending'] }}</div></div>
        <div class="card"><div class="muted">Inbound webhooks</div><div class="stat-value">{{ $catalog['metrics']['inbound_webhooks'] }}</div></div>
        <div class="card"><div class="muted">Nouveaux modules</div><div class="stat-value">{{ count($catalog['modules']) - 1 }}</div></div>
    </div>

    <div class="split" style="margin-bottom:18px;">
        <section class="card">
            <h3 class="section-title">Largeur fonctionnelle ouverte</h3>
            <div class="grid" style="gap:12px;">
                @foreach ($catalog['modules'] as $module)
                    <article class="summary-box">
                        <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start;">
                            <div>
                                <strong>{{ $module['label'] }}</strong>
                                <div class="muted" style="margin-top:8px;">{{ $module['description'] }}</div>
                            </div>
                            <span class="badge badge-muted">{{ $module['count'] }}</span>
                        </div>
                        @if (isset($module['route_name']))
                            <div style="margin-top:12px;">
                                <a href="{{ route($module['route_name']) }}" class="button button-secondary">Ouvrir</a>
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>

        <section class="card">
            <h3 class="section-title">Packaging et exploitation</h3>
            <div class="summary-stack">
                <div class="summary-box">
                    <strong>Promesse produit</strong>
                    <div class="muted" style="margin-top:8px;">{{ $catalog['packaging']['summary'] }}</div>
                    <div class="help" style="margin-top:12px;">Monnaie: {{ $catalog['product']['currency'] }} · Fuseau: {{ $catalog['product']['timezone'] }}</div>
                </div>
                <div class="summary-box">
                    <strong>Commandes cle</strong>
                    @foreach ($catalog['packaging']['quality_gates'] as $command)
                        <div class="muted" style="margin-top:8px;"><code>{{ $command }}</code></div>
                    @endforeach
                    <div class="muted" style="margin-top:8px;"><code>{{ $catalog['packaging']['start_command'] }}</code></div>
                    <div class="muted" style="margin-top:8px;"><code>{{ $catalog['packaging']['stop_command'] }}</code></div>
                </div>
                <div class="summary-box">
                    <strong>Runbooks</strong>
                    <ul class="summary-list">
                        @foreach ($catalog['packaging']['runbooks'] as $runbook)
                            <li><strong>{{ $runbook['label'] }}</strong> · {{ $runbook['path'] }} · {{ $runbook['purpose'] }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </section>
    </div>

    <div class="split" style="margin-bottom:18px;">
        <section class="card">
            <h3 class="section-title">API et extensions</h3>
            <div class="help" style="margin-bottom:12px;">Authentification: {{ implode(' · ', $catalog['ecosystem']['api']['authentication']) }}</div>
            <div class="summary-box" style="margin-bottom:12px;">
                <strong>Documentation integrateur</strong>
                <div class="muted" style="margin-top:8px;">Contrat OpenAPI, exemples cURL et point d entree pour les partenaires data, middleware, logistique et paiements.</div>
                <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:12px;">
                    <a href="{{ route('platform.openapi') }}" class="button button-secondary" target="_blank" rel="noreferrer">Ouvrir OpenAPI JSON</a>
                    <span class="badge badge-muted"><code>{{ $catalog['ecosystem']['api']['documentation']['api_openapi_path'] }}</code></span>
                </div>
                <ul class="summary-list" style="margin-top:12px;">
                    @foreach ($catalog['ecosystem']['api']['documentation']['curl_examples'] as $curlExample)
                        <li><code>{{ $curlExample }}</code></li>
                    @endforeach
                </ul>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Ressource</th>
                        <th>Path</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($catalog['ecosystem']['api']['resources'] as $resource)
                        <tr>
                            <td>{{ $resource['name'] }}</td>
                            <td><code>{{ $resource['path'] }}</code></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <h3 class="section-title">Hygiène API et webhooks</h3>
            <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:10px; margin-bottom:12px;">
                <div class="summary-box"><strong>Jetons actifs</strong><div class="muted" style="margin-top:8px;">{{ $catalog['ecosystem']['token_hygiene']['active'] }}</div></div>
                <div class="summary-box"><strong>Expiration proche</strong><div class="muted" style="margin-top:8px;">{{ $catalog['ecosystem']['token_hygiene']['expiring_soon'] }}</div></div>
                <div class="summary-box"><strong>Jetons inactifs</strong><div class="muted" style="margin-top:8px;">{{ $catalog['ecosystem']['token_hygiene']['stale'] }}</div></div>
                <div class="summary-box"><strong>Outbound failed</strong><div class="muted" style="margin-top:8px;">{{ $catalog['ecosystem']['monitoring']['outbox_failed'] }}</div></div>
                <div class="summary-box"><strong>Inbound rejected</strong><div class="muted" style="margin-top:8px;">{{ $catalog['ecosystem']['monitoring']['inbound_rejected'] }}</div></div>
            </div>

            <div class="summary-box">
                <strong>Jetons recents</strong>
                <ul class="summary-list">
                    @forelse ($catalog['ecosystem']['token_hygiene']['recent_tokens'] as $token)
                        <li>{{ $token['name'] }} · cree par {{ $token['created_by'] ?? 'n/a' }} · dernier usage {{ optional($token['last_used_at'])->format('d/m H:i') ?? 'jamais' }} · expire {{ optional($token['expires_at'])->format('d/m/Y') ?? 'sans limite' }}</li>
                    @empty
                        <li>Aucun jeton API enregistre.</li>
                    @endforelse
                </ul>
            </div>
        </section>
    </div>

    @if ($canManageIntegrations)
        <form method="POST" action="{{ route('platform.connections.store') }}" class="card form-grid" style="margin-bottom:18px;">
            @csrf
            <div class="full">
                <h3 class="section-title">Nouvelle connexion partenaire</h3>
            </div>
            <div>
                <label for="code">Code</label>
                <input id="code" name="code" value="{{ old('code') }}" placeholder="INT-0001">
            </div>
            <div>
                <label for="name">Nom interne</label>
                <input id="name" name="name" value="{{ old('name') }}" required placeholder="Sync commandes WhatsApp">
            </div>
            <div>
                <label for="partner_name">Partenaire</label>
                <input id="partner_name" name="partner_name" value="{{ old('partner_name') }}" required placeholder="Wave, Power BI, middleware SI, marketplace">
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
                <label for="owner_id">Responsable</label>
                <select id="owner_id" name="owner_id">
                    <option value="">Non affecte</option>
                    @foreach ($owners as $owner)
                        <option value="{{ $owner->id }}" @selected(old('owner_id') == $owner->id)>{{ $owner->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="connection_type">Type</label>
                <select id="connection_type" name="connection_type" required>
                    @foreach ($connectionTypeOptions as $value => $label)
                        <option value="{{ $value }}" @selected(old('connection_type', 'api') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="sync_mode">Mode</label>
                <select id="sync_mode" name="sync_mode" required>
                    @foreach ($syncModeOptions as $value => $label)
                        <option value="{{ $value }}" @selected(old('sync_mode', 'bidirectional') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="status">Statut</label>
                <select id="status" name="status" required>
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', 'draft') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="health_status">Sante</label>
                <select id="health_status" name="health_status" required>
                    @foreach ($healthOptions as $value => $label)
                        <option value="{{ $value }}" @selected(old('health_status', 'watch') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="external_reference">Reference externe</label>
                <input id="external_reference" name="external_reference" value="{{ old('external_reference') }}" placeholder="workspace, tenant, app id ou endpoint">
            </div>
            <div>
                <label for="last_sync_at">Derniere synchro</label>
                <input id="last_sync_at" name="last_sync_at" type="date" value="{{ old('last_sync_at') }}">
            </div>
            <div>
                <label for="last_health_at">Dernier controle</label>
                <input id="last_health_at" name="last_health_at" type="date" value="{{ old('last_health_at') }}">
            </div>
            <div class="full">
                <label for="scope_summary">Perimetre synchronise</label>
                <textarea id="scope_summary" name="scope_summary" rows="2" placeholder="Ex: commandes, paiements, statuts de livraison, dashboard exec">{{ old('scope_summary') }}</textarea>
            </div>
            <div class="full">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" rows="2">{{ old('notes') }}</textarea>
            </div>
            <div class="full actions">
                <button type="submit" class="button button-primary">Enregistrer la connexion</button>
            </div>
        </form>
    @endif

    <div class="split" style="margin-bottom:18px;">
        <section class="card">
            <h3 class="section-title">Registre des connexions partenaires</h3>
            <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:10px; margin-bottom:12px;">
                <div class="summary-box"><strong>Total</strong><div class="muted" style="margin-top:8px;">{{ $catalog['ecosystem']['connections']['summary']['total'] }}</div></div>
                <div class="summary-box"><strong>Actives</strong><div class="muted" style="margin-top:8px;">{{ $catalog['ecosystem']['connections']['summary']['active'] }}</div></div>
                <div class="summary-box"><strong>Critiques</strong><div class="muted" style="margin-top:8px;">{{ $catalog['ecosystem']['connections']['summary']['critical'] }}</div></div>
                <div class="summary-box"><strong>Bidirectionnelles</strong><div class="muted" style="margin-top:8px;">{{ $catalog['ecosystem']['connections']['summary']['bidirectional'] }}</div></div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Connexion</th>
                        <th>Perimetre</th>
                        <th>Derniere synchro</th>
                        <th>Sante</th>
                        @if ($canManageIntegrations)
                            <th>Actions</th>
                        @endif
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($catalog['ecosystem']['connections']['items'] as $connection)
                        <tr>
                            <td>
                                <strong>{{ $connection['name'] }}</strong>
                                <div class="muted" style="font-size:12px;">{{ $connection['code'] }} · {{ $connection['partner_name'] }} · {{ $connection['owner_name'] ?? 'Sans responsable' }}</div>
                                <div style="display:flex; gap:6px; flex-wrap:wrap; margin-top:6px;">
                                    <span class="badge {{ $connectionTypeBadge[$connection['connection_type']] ?? 'badge-muted' }}">{{ $connectionTypeOptions[$connection['connection_type']] ?? $connection['connection_type'] }}</span>
                                    <span class="badge {{ $statusBadge[$connection['status']] ?? 'badge-muted' }}">{{ $statusOptions[$connection['status']] ?? $connection['status'] }}</span>
                                </div>
                            </td>
                            <td>
                                <div>{{ $syncModeOptions[$connection['sync_mode']] ?? $connection['sync_mode'] }}</div>
                                <div class="muted" style="font-size:12px;">{{ $connection['scope_summary'] ?: 'Perimetre non precise' }}</div>
                                @if ($connection['external_reference'])
                                    <div class="muted" style="font-size:12px;">Ref. {{ $connection['external_reference'] }}</div>
                                @endif
                            </td>
                            <td>
                                <div>{{ optional($connection['last_sync_at'])->format('d/m/Y H:i') ?: 'Jamais' }}</div>
                                <div class="muted" style="font-size:12px;">Controle {{ optional($connection['last_health_at'])->format('d/m/Y H:i') ?: 'n/a' }}</div>
                            </td>
                            <td>
                                <span class="badge {{ $healthBadge[$connection['health_status']] ?? 'badge-muted' }}">{{ $healthOptions[$connection['health_status']] ?? $connection['health_status'] }}</span>
                                @if ($connection['notes'])
                                    <div class="muted" style="font-size:12px; margin-top:6px;">{{ $connection['notes'] }}</div>
                                @endif
                            </td>
                            @if ($canManageIntegrations)
                                <td>
                                    <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                        <form method="POST" action="{{ route('platform.connections.status', $connection['id']) }}">
                                            @csrf
                                            <input type="hidden" name="status" value="active">
                                            <input type="hidden" name="health_status" value="healthy">
                                            <button type="submit" class="button button-secondary">Passer sain</button>
                                        </form>
                                        <form method="POST" action="{{ route('platform.connections.status', $connection['id']) }}">
                                            @csrf
                                            <input type="hidden" name="status" value="paused">
                                            <input type="hidden" name="health_status" value="watch">
                                            <button type="submit" class="button button-secondary">Surveiller</button>
                                        </form>
                                        <form method="POST" action="{{ route('platform.connections.status', $connection['id']) }}">
                                            @csrf
                                            <input type="hidden" name="status" value="deprecated">
                                            <input type="hidden" name="health_status" value="critical">
                                            <button type="submit" class="button button-primary">Escalader</button>
                                        </form>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $canManageIntegrations ? 5 : 4 }}" class="muted">Aucune connexion partenaire declaree pour le moment.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <h3 class="section-title">Santé des flux d integration</h3>
            <div class="summary-box">
                <strong>Outbox recent</strong>
                <ul class="summary-list">
                    @forelse ($catalog['ecosystem']['monitoring']['recent_outbox'] as $event)
                        <li>{{ $event['event_name'] }} · {{ $event['status'] }} · tentatives {{ $event['attempts'] }} · {{ $event['latest_delivery_target'] ?? 'sans cible' }}</li>
                    @empty
                        <li>Aucun evenement outbox recent.</li>
                    @endforelse
                </ul>
            </div>
            <div class="summary-box" style="margin-top:12px;">
                <strong>Inbound recent</strong>
                <ul class="summary-list">
                    @forelse ($catalog['ecosystem']['monitoring']['recent_inbound'] as $webhook)
                        <li>{{ $webhook['source'] ?? 'source n/a' }} · {{ $webhook['event_name'] ?? 'event n/a' }} · {{ $webhook['status'] }} · {{ optional($webhook['processed_at'])->format('d/m H:i') ?? 'n/a' }}</li>
                    @empty
                        <li>Aucun webhook entrant recent.</li>
                    @endforelse
                </ul>
            </div>
            <div class="summary-box" style="margin-top:12px;">
                <strong>Partenaires et automatisation</strong>
                @foreach ($catalog['ecosystem']['automation']['outbox_commands'] as $command)
                    <div class="muted" style="margin-top:8px;"><code>{{ $command }}</code></div>
                @endforeach
                <ul class="summary-list" style="margin-top:12px;">
                    @foreach ($catalog['ecosystem']['automation']['partner_channels'] as $channel)
                        <li>{{ $channel }}</li>
                    @endforeach
                </ul>
            </div>
        </section>
    </div>
@endsection
