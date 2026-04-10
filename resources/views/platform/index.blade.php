@extends('layouts.app')

@section('title', 'Plateforme - Nema ERP')
@section('page-title', 'Plateforme')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Socle produit et ecosysteme</h2>
            <div class="muted">Lecture rapide des 4 axes d expansion: packaging, API, partenaires et nouveaux modules.</div>
        </div>
        <a href="{{ route('ops.index') }}" class="button button-secondary">Ouvrir Operations</a>
    </div>

    <div class="grid stats-grid" style="margin-bottom:20px;">
        <div class="card"><div class="muted">Edition</div><div class="stat-value">{{ $catalog['product']['edition'] }}</div></div>
        <div class="card"><div class="muted">Jetons API</div><div class="stat-value">{{ $catalog['metrics']['api_tokens'] }}</div></div>
        <div class="card"><div class="muted">Outbox pending</div><div class="stat-value">{{ $catalog['metrics']['outbox_pending'] }}</div></div>
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

    <div class="split">
        <section class="card">
            <h3 class="section-title">API et extensions</h3>
            <div class="help" style="margin-bottom:12px;">Authentification: {{ implode(' · ', $catalog['ecosystem']['api']['authentication']) }}</div>
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
            <h3 class="section-title">Partenaires et automatisation</h3>
            <div class="summary-box">
                <strong>Outbox et middleware</strong>
                @foreach ($catalog['ecosystem']['automation']['outbox_commands'] as $command)
                    <div class="muted" style="margin-top:8px;"><code>{{ $command }}</code></div>
                @endforeach
            </div>
            <div class="summary-box" style="margin-top:12px;">
                <strong>Canaux partenaires</strong>
                <ul class="summary-list">
                    @foreach ($catalog['ecosystem']['automation']['partner_channels'] as $channel)
                        <li>{{ $channel }}</li>
                    @endforeach
                </ul>
            </div>
        </section>
    </div>
@endsection
