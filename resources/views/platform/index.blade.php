@extends('layouts.app')

@section('title', 'Plateforme - Nema ERP')
@section('page-title', 'Plateforme')

@section('content')
    @php
        $canManageIntegrations = auth()->user()?->hasPermission('settings.integrations.manage') ?? false;
        $canManageDeployment = auth()->user()?->hasPermission('settings.manage') ?? false;
        $deploymentProfile = $catalog['packaging']['deployment_profile'];
        $readiness = $catalog['packaging']['readiness'];
        $tenantLandscape = $catalog['packaging']['tenant_landscape'];
        $tenantReadiness = $catalog['packaging']['tenant_readiness'] ?? null;
        $secretGovernance = $catalog['ecosystem']['connections']['secret_governance'] ?? ['items' => []];
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
        $readinessBadge = [
            'ready' => 'badge-success',
            'progressing' => 'badge-warning',
            'foundation' => 'badge-muted',
            'at_risk' => 'badge-danger',
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
        <div class="card"><div class="muted">Offre</div><div class="stat-value">{{ $catalog['product']['commercial_offer'] ?: 'n/a' }}</div></div>
        <div class="card"><div class="muted">Readiness</div><div class="stat-value">{{ $catalog['metrics']['readiness_score'] }}</div></div>
        <div class="card"><div class="muted">Societes actives</div><div class="stat-value">{{ $catalog['metrics']['tenant_active_companies'] ?: ($tenantLandscape['active_companies'] ?? 0) }}</div></div>
        <div class="card"><div class="muted">Jetons API</div><div class="stat-value">{{ $catalog['metrics']['api_tokens'] }}</div></div>
        <div class="card"><div class="muted">Connexions</div><div class="stat-value">{{ $catalog['metrics']['integration_connections'] }}</div></div>
        <div class="card"><div class="muted">Secrets critiques</div><div class="stat-value">{{ $catalog['metrics']['connection_secrets_critical'] }}</div></div>
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
                @if ($deploymentProfile)
                    <div class="summary-box">
                        <strong>Profil de deploiement</strong>
                        <div class="chip-row" style="margin-top:8px;">
                            <span class="badge badge-muted">{{ $deploymentProfile['commercial_offer_label'] }}</span>
                            <span class="badge badge-muted">{{ $deploymentProfile['deployment_mode_label'] }}</span>
                            <span class="badge badge-muted">{{ $deploymentProfile['lifecycle_stage_label'] }}</span>
                        </div>
                        <div class="help" style="margin-top:10px;">Hebergement: {{ $deploymentProfile['hosting_target_label'] }} · Support: {{ $deploymentProfile['support_tier_label'] }}</div>
                        <div class="help" style="margin-top:8px;">Monitoring: {{ $deploymentProfile['monitoring_level_label'] }} · Sauvegarde: {{ $deploymentProfile['backup_strategy_label'] }} · Updates: {{ $deploymentProfile['update_channel_label'] }}</div>
                        <div class="help" style="margin-top:8px;">Cible: {{ $deploymentProfile['target_users'] ?: 'n/a' }} utilisateur(s) · {{ $deploymentProfile['target_branches'] ?: 'n/a' }} agence(s)</div>
                    </div>
                @endif
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

    @if ($deploymentProfile && $readiness)
        <div class="split" style="margin-bottom:18px;">
            <section class="card">
                <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start; flex-wrap:wrap; margin-bottom:16px;">
                    <div>
                        <h3 class="section-title">Readiness deploiement</h3>
                        <div class="muted">Score de preparation par societe pour savoir si on reste en local, si on ouvre un pilote ou si on peut industrialiser davantage.</div>
                    </div>
                    <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                        <span class="badge {{ $readinessBadge[$readiness['status']] ?? 'badge-muted' }}">{{ strtoupper($readiness['status']) }}</span>
                        <span class="badge badge-muted">Score {{ $readiness['score'] }}/100</span>
                    </div>
                </div>

                <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:10px; margin-bottom:12px;">
                    <div class="summary-box"><strong>Stage</strong><div class="muted" style="margin-top:8px;">{{ $deploymentProfile['lifecycle_stage_label'] }}</div></div>
                    <div class="summary-box"><strong>Mode</strong><div class="muted" style="margin-top:8px;">{{ $deploymentProfile['deployment_mode_label'] }}</div></div>
                    <div class="summary-box"><strong>Support</strong><div class="muted" style="margin-top:8px;">{{ $deploymentProfile['support_tier_label'] }}</div></div>
                    <div class="summary-box"><strong>Tenant</strong><div class="muted" style="margin-top:8px;">{{ $tenantLandscape['tenant_name'] ?? 'n/a' }}</div></div>
                </div>

                <div class="summary-box" style="margin-bottom:12px;">
                    <strong>Paysage multi-client</strong>
                    <div class="help" style="margin-top:8px;">{{ $tenantLandscape['active_companies'] ?? 0 }} societe(s) active(s) · {{ $tenantLandscape['active_users'] ?? 0 }} utilisateur(s) actifs · {{ $tenantLandscape['tenant_active_branches'] ?? ($tenantLandscape['active_branches'] ?? 0) }} agence(s) actives sur le tenant</div>
                    <div class="help" style="margin-top:8px;">Portefeuille {{ $tenantLandscape['portfolio_status_label'] ?? 'n/a' }} · score moyen {{ $tenantLandscape['average_score'] ?? 0 }}/100 · {{ $tenantLandscape['active_branches'] ?? 0 }} agence(s) sur la societe courante</div>
                    <div class="help" style="margin-top:8px;">Owner de deploiement: {{ $deploymentProfile['owner_name'] ?: 'Non affecte' }}</div>
                </div>

                <div class="grid" style="gap:12px;">
                    @foreach ($readiness['items'] as $item)
                        <div class="summary-box">
                            <div style="display:flex; justify-content:space-between; gap:12px; align-items:center;">
                                <strong>{{ $item['label'] }}</strong>
                                <span class="badge {{ $item['status'] === 'ok' ? 'badge-success' : ($item['status'] === 'warning' ? 'badge-warning' : 'badge-danger') }}">{{ strtoupper($item['status']) }}</span>
                            </div>
                            <div class="muted" style="margin-top:8px;">{{ $item['message'] }}</div>
                            <div class="help" style="margin-top:8px;">{{ $item['action'] }}</div>
                        </div>
                    @endforeach
                </div>

                @if (! empty($readiness['next_actions']))
                    <div class="summary-box" style="margin-top:12px;">
                        <strong>Actions recommandees</strong>
                        <ul class="summary-list">
                            @foreach ($readiness['next_actions'] as $action)
                                <li>{{ $action }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($tenantReadiness)
                    <div class="summary-box" style="margin-top:12px;">
                        <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start; flex-wrap:wrap;">
                            <div>
                                <strong>Readiness inter-societes</strong>
                                <div class="help" style="margin-top:8px;">Vue portefeuille pour savoir quelles societes peuvent monter en charge et lesquelles demandent encore de la stabilisation.</div>
                            </div>
                            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                <span class="badge {{ $readinessBadge[$tenantReadiness['portfolio_status']] ?? 'badge-muted' }}">{{ strtoupper($tenantReadiness['portfolio_status']) }}</span>
                                <span class="badge badge-muted">Moyenne {{ $tenantReadiness['average_score'] }}/100</span>
                            </div>
                        </div>

                        <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:10px; margin-top:12px;">
                            <div class="summary-box"><strong>Societes</strong><div class="muted" style="margin-top:8px;">{{ $tenantReadiness['active_companies'] }}</div></div>
                            <div class="summary-box"><strong>Utilisateurs</strong><div class="muted" style="margin-top:8px;">{{ $tenantReadiness['active_users'] }}</div></div>
                            <div class="summary-box"><strong>Agences</strong><div class="muted" style="margin-top:8px;">{{ $tenantReadiness['active_branches'] }}</div></div>
                            <div class="summary-box"><strong>Fourchette</strong><div class="muted" style="margin-top:8px;">{{ $tenantReadiness['lowest_score'] }} - {{ $tenantReadiness['highest_score'] }}</div></div>
                        </div>

                        <div class="chip-row" style="margin-top:12px;">
                            @foreach ($tenantReadiness['status_breakdown'] as $status)
                                @if ($status['count'] > 0)
                                    <span class="badge {{ $readinessBadge[$status['key']] ?? 'badge-muted' }}">{{ $status['label'] }} · {{ $status['count'] }}</span>
                                @endif
                            @endforeach
                        </div>

                        <div class="table-wrap" style="margin-top:12px;">
                            <table>
                                <thead>
                                <tr>
                                    <th>Societe</th>
                                    <th>Offre et mode</th>
                                    <th>Empreinte</th>
                                    <th>Readiness</th>
                                    <th>Priorites</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach ($tenantReadiness['companies'] as $tenantCompany)
                                    <tr>
                                        <td>
                                            <strong>{{ $tenantCompany['company_name'] }}</strong>
                                            <div class="muted" style="font-size:12px;">{{ $tenantCompany['is_current'] ? 'Societe courante' : 'Autre societe du tenant' }}</div>
                                        </td>
                                        <td>
                                            <div>{{ $tenantCompany['commercial_offer_label'] }} · {{ $tenantCompany['deployment_mode_label'] }}</div>
                                            <div class="muted" style="font-size:12px;">{{ $tenantCompany['lifecycle_stage_label'] }} · {{ $tenantCompany['support_tier_label'] }}</div>
                                        </td>
                                        <td>
                                            <div>{{ $tenantCompany['active_users'] }} utilisateur(s) · {{ $tenantCompany['active_branches'] }} agence(s)</div>
                                            <div class="muted" style="font-size:12px;">Go-live {{ optional($tenantCompany['go_live_target_at'])->format('d/m/Y') ?? 'n/a' }} · owner {{ $tenantCompany['owner_name'] ?: 'non affecte' }}</div>
                                        </td>
                                        <td>
                                            <span class="badge {{ $readinessBadge[$tenantCompany['readiness_status']] ?? 'badge-muted' }}">{{ strtoupper($tenantCompany['readiness_status']) }}</span>
                                            <div class="muted" style="font-size:12px; margin-top:6px;">Score {{ $tenantCompany['readiness_score'] }}/100</div>
                                        </td>
                                        <td>
                                            <div class="muted" style="font-size:12px;">{{ $tenantCompany['top_blockers'][0] ?? $tenantCompany['top_warnings'][0] ?? 'Aucune alerte prioritaire.' }}</div>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if (! empty($tenantReadiness['next_actions']))
                            <div class="summary-box" style="margin-top:12px;">
                                <strong>Actions portfolio</strong>
                                <ul class="summary-list">
                                    @foreach ($tenantReadiness['next_actions'] as $action)
                                        <li>{{ $action }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                @endif
            </section>

            <section class="card">
                <h3 class="section-title">Profil d industrialisation</h3>
                <div class="help" style="margin-bottom:16px;">Ce profil transforme l ERP en offre exploitable: mode de deploiement, niveau de support, monitoring et discipline de release.</div>

                @if ($canManageDeployment)
                    <form method="POST" action="{{ route('platform.deployment-profile.update') }}" class="form-grid">
                        @csrf
                        @method('PUT')
                        <div>
                            <label for="deployment-owner-id">Responsable</label>
                            <select id="deployment-owner-id" name="owner_id">
                                <option value="">Non affecte</option>
                                @foreach ($owners as $owner)
                                    <option value="{{ $owner->id }}" @selected((string) old('owner_id', $deploymentProfile['owner_id'] ?? '') === (string) $owner->id)>{{ $owner->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="commercial-offer">Offre</label>
                            <select id="commercial-offer" name="commercial_offer" required>
                                @foreach ($deploymentOfferOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('commercial_offer', $deploymentProfile['commercial_offer']) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="deployment-mode">Mode</label>
                            <select id="deployment-mode" name="deployment_mode" required>
                                @foreach ($deploymentModeOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('deployment_mode', $deploymentProfile['deployment_mode']) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="lifecycle-stage">Cycle</label>
                            <select id="lifecycle-stage" name="lifecycle_stage" required>
                                @foreach ($lifecycleStageOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('lifecycle_stage', $deploymentProfile['lifecycle_stage']) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="hosting-target">Hebergement</label>
                            <select id="hosting-target" name="hosting_target" required>
                                @foreach ($hostingTargetOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('hosting_target', $deploymentProfile['hosting_target']) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="support-tier">Support</label>
                            <select id="support-tier" name="support_tier" required>
                                @foreach ($supportTierOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('support_tier', $deploymentProfile['support_tier']) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="monitoring-level">Monitoring</label>
                            <select id="monitoring-level" name="monitoring_level" required>
                                @foreach ($monitoringLevelOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('monitoring_level', $deploymentProfile['monitoring_level']) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="backup-strategy">Sauvegarde</label>
                            <select id="backup-strategy" name="backup_strategy" required>
                                @foreach ($backupStrategyOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('backup_strategy', $deploymentProfile['backup_strategy']) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="update-channel">Updates</label>
                            <select id="update-channel" name="update_channel" required>
                                @foreach ($updateChannelOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('update_channel', $deploymentProfile['update_channel']) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="target-users">Utilisateurs cibles</label>
                            <input id="target-users" type="number" min="1" max="10000" name="target_users" value="{{ old('target_users', $deploymentProfile['target_users']) }}">
                        </div>
                        <div>
                            <label for="target-branches">Agences cibles</label>
                            <input id="target-branches" type="number" min="1" max="1000" name="target_branches" value="{{ old('target_branches', $deploymentProfile['target_branches']) }}">
                        </div>
                        <div>
                            <label for="go-live-target-at">Go-live cible</label>
                            <input id="go-live-target-at" type="date" name="go_live_target_at" value="{{ old('go_live_target_at', optional($deploymentProfile['go_live_target_at'])->toDateString()) }}">
                        </div>
                        <div>
                            <label for="last-release-at">Derniere release</label>
                            <input id="last-release-at" type="date" name="last_release_at" value="{{ old('last_release_at', optional($deploymentProfile['last_release_at'])->toDateString()) }}">
                        </div>
                        <div>
                            <label for="last-backup-verified-at">Derniere sauvegarde verifiee</label>
                            <input id="last-backup-verified-at" type="date" name="last_backup_verified_at" value="{{ old('last_backup_verified_at', optional($deploymentProfile['last_backup_verified_at'])->toDateString()) }}">
                        </div>
                        <div>
                            <label for="last-restore-drill-at">Dernier exercice restauration</label>
                            <input id="last-restore-drill-at" type="date" name="last_restore_drill_at" value="{{ old('last_restore_drill_at', optional($deploymentProfile['last_restore_drill_at'])->toDateString()) }}">
                        </div>
                        <div class="full">
                            <label for="deployment-notes">Notes</label>
                            <textarea id="deployment-notes" name="notes" rows="4">{{ old('notes', $deploymentProfile['notes']) }}</textarea>
                        </div>
                        <div class="full actions">
                            <button type="submit" class="button button-primary">Mettre a jour le profil</button>
                        </div>
                    </form>
                @else
                    <div class="summary-box">
                        <strong>Profil actuel</strong>
                        <div class="help" style="margin-top:8px;">Offre {{ $deploymentProfile['commercial_offer_label'] }} · Stage {{ $deploymentProfile['lifecycle_stage_label'] }} · Support {{ $deploymentProfile['support_tier_label'] }}</div>
                        <div class="help" style="margin-top:8px;">Hebergement {{ $deploymentProfile['hosting_target_label'] }} · Monitoring {{ $deploymentProfile['monitoring_level_label'] }} · Sauvegarde {{ $deploymentProfile['backup_strategy_label'] }}</div>
                    </div>
                @endif
            </section>
        </div>
    @endif

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

            <div class="summary-box" style="margin-bottom:12px;">
                <strong>Gouvernance secrets connecteurs</strong>
                <div class="help" style="margin-top:8px;">{{ $secretGovernance['healthy'] ?? 0 }} sain(s) · {{ $secretGovernance['watch'] ?? 0 }} a surveiller · {{ $secretGovernance['critical'] ?? 0 }} critique(s)</div>
                <div class="help" style="margin-top:8px;">{{ $secretGovernance['rotation_due_soon'] ?? 0 }} rotation(s) a planifier sous 7 jours · {{ $secretGovernance['rotation_overdue'] ?? 0 }} en retard · {{ $secretGovernance['expiring_soon'] ?? 0 }} expiration(s) proches</div>
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
                <label for="authentication_mode">Auth</label>
                <select id="authentication_mode" name="authentication_mode">
                    @foreach ($authenticationModeOptions as $value => $label)
                        <option value="{{ $value }}" @selected(old('authentication_mode', 'api_key') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="secret_health_status">Etat secret</label>
                <select id="secret_health_status" name="secret_health_status">
                    @foreach ($secretHealthOptions as $value => $label)
                        <option value="{{ $value }}" @selected(old('secret_health_status', 'watch') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="secret_owner_id">Responsable secret</label>
                <select id="secret_owner_id" name="secret_owner_id">
                    <option value="">Non affecte</option>
                    @foreach ($secretOwners as $secretOwner)
                        <option value="{{ $secretOwner->id }}" @selected(old('secret_owner_id') == $secretOwner->id)>{{ $secretOwner->name }}</option>
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
            <div>
                <label for="secret_last_rotated_at">Derniere rotation</label>
                <input id="secret_last_rotated_at" name="secret_last_rotated_at" type="date" value="{{ old('secret_last_rotated_at') }}">
            </div>
            <div>
                <label for="secret_rotation_due_at">Rotation cible</label>
                <input id="secret_rotation_due_at" name="secret_rotation_due_at" type="date" value="{{ old('secret_rotation_due_at') }}">
            </div>
            <div>
                <label for="secret_expires_at">Expiration secret</label>
                <input id="secret_expires_at" name="secret_expires_at" type="date" value="{{ old('secret_expires_at') }}">
            </div>
            <div class="full">
                <label for="scope_summary">Perimetre synchronise</label>
                <textarea id="scope_summary" name="scope_summary" rows="2" placeholder="Ex: commandes, paiements, statuts de livraison, dashboard exec">{{ old('scope_summary') }}</textarea>
            </div>
            <div class="full">
                <label for="secret_notes">Notes secret</label>
                <textarea id="secret_notes" name="secret_notes" rows="2" placeholder="Politique de rotation, coffre-fort, contact partenaire">{{ old('secret_notes') }}</textarea>
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
                        <th>Secrets</th>
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
                                @php($secretProfile = $connection['secret_profile'] ?? null)
                                @if ($secretProfile)
                                    <span class="badge {{ $healthBadge[$secretProfile['computed_status']] ?? 'badge-muted' }}">{{ $secretProfile['computed_status_label'] }}</span>
                                    <div class="muted" style="font-size:12px; margin-top:6px;">{{ $secretProfile['authentication_mode_label'] }}</div>
                                    <div class="muted" style="font-size:12px;">{{ $secretProfile['message'] }}</div>
                                    <div class="muted" style="font-size:12px;">Owner {{ $secretProfile['secret_owner_name'] ?: 'non affecte' }}</div>
                                @else
                                    <span class="muted">n/a</span>
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
                        <tr><td colspan="{{ $canManageIntegrations ? 6 : 5 }}" class="muted">Aucune connexion partenaire declaree pour le moment.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @if ($canManageIntegrations && ! empty($secretGovernance['items']))
                <div class="summary-stack" style="margin-top:16px;">
                    <h4 style="margin:0;">Gouvernance des secrets</h4>
                    @foreach ($catalog['ecosystem']['connections']['items'] as $connection)
                        @php($secretProfile = $connection['secret_profile'] ?? null)
                        <form method="POST" action="{{ route('platform.connections.secrets.update', $connection['id']) }}" class="summary-box" style="display:grid; gap:10px;">
                            @csrf
                            @method('PUT')
                            <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start; flex-wrap:wrap;">
                                <div>
                                    <strong>{{ $connection['name'] }}</strong>
                                    <div class="muted" style="margin-top:6px;">{{ $connection['partner_name'] }} · {{ $connection['code'] }}</div>
                                </div>
                                @if ($secretProfile)
                                    <span class="badge {{ $healthBadge[$secretProfile['computed_status']] ?? 'badge-muted' }}">{{ strtoupper($secretProfile['computed_status']) }}</span>
                                @endif
                            </div>
                            <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:10px;">
                                <div>
                                    <label for="authentication-mode-{{ $connection['id'] }}">Auth</label>
                                    <select id="authentication-mode-{{ $connection['id'] }}" name="authentication_mode" required>
                                        @foreach ($authenticationModeOptions as $value => $label)
                                            <option value="{{ $value }}" @selected(old('authentication_mode', $connection['authentication_mode'] ?? ($secretProfile['authentication_mode'] ?? 'api_key')) === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label for="secret-health-{{ $connection['id'] }}">Etat secret</label>
                                    <select id="secret-health-{{ $connection['id'] }}" name="secret_health_status" required>
                                        @foreach ($secretHealthOptions as $value => $label)
                                            <option value="{{ $value }}" @selected(old('secret_health_status', $connection['secret_health_status'] ?? 'watch') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label for="secret-owner-{{ $connection['id'] }}">Responsable secret</label>
                                    <select id="secret-owner-{{ $connection['id'] }}" name="secret_owner_id">
                                        <option value="">Non affecte</option>
                                        @foreach ($secretOwners as $secretOwner)
                                            <option value="{{ $secretOwner->id }}" @selected((string) old('secret_owner_id', data_get($secretProfile, 'secret_owner_id', $connection['secret_owner_id'] ?? '')) === (string) $secretOwner->id)>{{ $secretOwner->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label for="secret-rotated-{{ $connection['id'] }}">Derniere rotation</label>
                                    <input id="secret-rotated-{{ $connection['id'] }}" type="date" name="secret_last_rotated_at" value="{{ old('secret_last_rotated_at', optional(data_get($secretProfile, 'secret_last_rotated_at'))->toDateString()) }}">
                                </div>
                                <div>
                                    <label for="secret-due-{{ $connection['id'] }}">Rotation cible</label>
                                    <input id="secret-due-{{ $connection['id'] }}" type="date" name="secret_rotation_due_at" value="{{ old('secret_rotation_due_at', optional(data_get($secretProfile, 'secret_rotation_due_at'))->toDateString()) }}">
                                </div>
                                <div>
                                    <label for="secret-expiry-{{ $connection['id'] }}">Expiration</label>
                                    <input id="secret-expiry-{{ $connection['id'] }}" type="date" name="secret_expires_at" value="{{ old('secret_expires_at', optional(data_get($secretProfile, 'secret_expires_at'))->toDateString()) }}">
                                </div>
                            </div>
                            <div>
                                <label for="secret-notes-{{ $connection['id'] }}">Notes secret</label>
                                <textarea id="secret-notes-{{ $connection['id'] }}" name="secret_notes" rows="2">{{ old('secret_notes', data_get($secretProfile, 'notes')) }}</textarea>
                            </div>
                            @if ($secretProfile && ! empty($secretProfile['alerts']))
                                <div class="help">{{ implode(' ', $secretProfile['alerts']) }}</div>
                            @endif
                            <div class="actions">
                                <button type="submit" class="button button-secondary">Mettre a jour les secrets</button>
                            </div>
                        </form>
                    @endforeach
                </div>
            @endif
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
