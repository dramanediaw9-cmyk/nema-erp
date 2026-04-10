@extends('layouts.app')

@section('title', 'Commerce unifie - Nema ERP')
@section('page-title', 'Commerce unifie')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Commerce unifie</h2>
            <div class="muted">Socle e-commerce / omnicanal pour piloter retail, web, marketplace et mobile money.</div>
        </div>
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
        <div class="card"><div class="muted">Canaux</div><div class="stat-value">{{ $summary['channels'] }}</div></div>
        <div class="card"><div class="muted">Actifs</div><div class="stat-value">{{ $summary['active'] }}</div></div>
        <div class="card"><div class="muted">Digitaux</div><div class="stat-value">{{ $summary['digital'] }}</div></div>
        <div class="card"><div class="muted">Objectif mensuel</div><div class="stat-value">{{ number_format($summary['target_revenue'], 0, ',', ' ') }} XOF</div></div>
    </div>

    @allowed('commerce.manage')
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
    @endallowed

    <section class="card">
        <h3 class="section-title">Canaux suivis</h3>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Code</th>
                    <th>Nom</th>
                    <th>Type</th>
                    <th>Connecteur</th>
                    <th>Reglement</th>
                    <th>Objectif</th>
                    <th>Statut</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($channels as $channel)
                    <tr>
                        <td>{{ $channel->code }}</td>
                        <td>{{ $channel->name }}</td>
                        <td>{{ $typeOptions[$channel->channel_type] ?? $channel->channel_type }}</td>
                        <td>{{ $channel->connector_name ?: '-' }}</td>
                        <td>{{ $settlementOptions[$channel->settlement_mode] ?? $channel->settlement_mode }}</td>
                        <td>{{ number_format((float) $channel->target_monthly_revenue, 0, ',', ' ') }} XOF</td>
                        <td><span class="badge badge-muted">{{ $statusOptions[$channel->status] ?? $channel->status }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">Aucun canal commerce enregistre pour le moment.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
