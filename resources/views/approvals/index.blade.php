@extends('layouts.app')

@section('title', 'Approbations')
@section('page-title', 'Portail d approbation')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Documents a traiter</h2>
            <div class="muted">Toutes les demandes qui attendent une action de ta part, centralisees dans un seul ecran.</div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="{{ route('approvals.index', array_filter(['search' => $filters['search'] ?? null])) }}" class="button {{ ($filters['module'] ?? null) === null ? 'button-primary' : 'button-secondary' }}">Tout</a>
            <a href="{{ route('approvals.index', array_filter(['module' => 'sales', 'search' => $filters['search'] ?? null])) }}" class="button {{ ($filters['module'] ?? null) === 'sales' ? 'button-primary' : 'button-secondary' }}">Ventes ({{ $summary['by_module']['sales'] }})</a>
            <a href="{{ route('approvals.index', array_filter(['module' => 'purchases', 'search' => $filters['search'] ?? null])) }}" class="button {{ ($filters['module'] ?? null) === 'purchases' ? 'button-primary' : 'button-secondary' }}">Achats ({{ $summary['by_module']['purchases'] }})</a>
            <a href="{{ route('approvals.index', array_filter(['module' => 'expenses', 'search' => $filters['search'] ?? null])) }}" class="button {{ ($filters['module'] ?? null) === 'expenses' ? 'button-primary' : 'button-secondary' }}">Depenses ({{ $summary['by_module']['expenses'] }})</a>
        </div>
    </div>

    <div class="grid stats-grid" style="margin-bottom:20px;">
        <div class="card"><div class="muted">A traiter maintenant</div><div class="stat-value">{{ $summary['count'] }}</div></div>
        <div class="card"><div class="muted">Ventes</div><div class="stat-value">{{ $summary['by_module']['sales'] }}</div></div>
        <div class="card"><div class="muted">Achats</div><div class="stat-value">{{ $summary['by_module']['purchases'] }}</div></div>
        <div class="card"><div class="muted">Depenses</div><div class="stat-value">{{ $summary['by_module']['expenses'] }}</div></div>
        <div class="card"><div class="muted">En retard SLA</div><div class="stat-value">{{ $summary['overdue_count'] ?? 0 }}</div></div>
    </div>

    <section class="card" style="margin-bottom:18px;">
        <form method="GET" action="{{ route('approvals.index') }}" class="form-grid" style="align-items:end; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));">
            <input type="hidden" name="module" value="{{ $filters['module'] ?? '' }}">
            <div style="grid-column:span 2; min-width:220px;">
                <label for="search">Recherche</label>
                <input type="text" id="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Numero, tiers, agence, createur...">
            </div>
            <div class="actions" style="margin-top:0; justify-content:flex-start; align-self:end;">
                <button type="submit" class="button button-primary">Filtrer</button>
                <a href="{{ route('approvals.index', array_filter(['module' => $filters['module'] ?? null])) }}" class="button button-secondary">Reinitialiser</a>
            </div>
        </form>
    </section>

    <div class="grid">
        @forelse ($items as $item)
            @php
                $delegateCandidates = collect($item['delegate_candidates'] ?? collect())
                    ->reject(fn ($candidate) => $candidate->id === auth()->id())
                    ->values();
            @endphp
            <section class="card">
                <div style="display:flex; justify-content:space-between; gap:18px; align-items:flex-start; flex-wrap:wrap;">
                    <div style="max-width:820px;">
                        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                            <strong>{{ $item['module_label'] }} {{ $item['number'] }}</strong>
                            <span class="badge badge-warning">{{ $item['pending_step']?->label }}</span>
                            <span class="badge badge-muted">{{ $item['branch_name'] }}</span>
                            @if ($item['is_overdue'] ?? false)
                                <span class="badge badge-danger">SLA depasse</span>
                            @endif
                            @if ($item['pending_step']?->escalated_at)
                                <span class="badge badge-danger">Escaladee</span>
                            @endif
                        </div>
                        <div style="margin-top:12px;">{{ $item['counterpart'] }}</div>
                        <div class="muted" style="margin-top:8px;">
                            Date document : {{ $item['document_date'] }} · Cree par {{ $item['creator_name'] }} · Montant {{ number_format($item['amount'], 0, ',', ' ') }} XOF
                        </div>
                        <div class="muted" style="margin-top:8px;">
                            @if ($item['assigned_approver_name'])
                                Assigne a {{ $item['assigned_approver_name'] }}
                            @else
                                Etape non assignee explicitement
                            @endif
                            @if ($item['due_at'])
                                · SLA {{ $item['due_at']->format('d/m/Y H:i') }}
                            @endif
                        </div>
                    </div>
                    <div style="display:flex; gap:10px; flex-wrap:wrap; justify-content:flex-end;">
                        <a href="{{ $item['detail_url'] }}" class="button button-secondary">Ouvrir</a>
                        <form method="POST" action="{{ $item['approve_url'] }}">
                            @csrf
                            <button type="submit" class="button button-primary">Valider l etape</button>
                        </form>
                    </div>
                </div>
                @if ($item['reject_url'])
                    <form method="POST" action="{{ $item['reject_url'] }}" class="form-grid" style="margin-top:16px; grid-template-columns:minmax(220px, 2fr) auto; align-items:end;">
                        @csrf
                        <div>
                            <label>Motif du rejet</label>
                            <input type="text" name="rejection_reason" maxlength="1000" required placeholder="Blocage, correction demandee, piece manquante...">
                        </div>
                        <div class="actions" style="margin-top:0;">
                            <button type="submit" class="button button-secondary">Rejeter</button>
                        </div>
                    </form>
                @endif
                @if ($item['delegate_url'] && $delegateCandidates->isNotEmpty())
                    <form method="POST" action="{{ $item['delegate_url'] }}" class="form-grid" style="margin-top:16px; grid-template-columns:minmax(220px, 1.2fr) minmax(220px, 2fr) auto; align-items:end;">
                        @csrf
                        <div>
                            <label>Deleguer a</label>
                            <select name="delegate_to" required>
                                <option value="">Choisir un valideur</option>
                                @foreach ($delegateCandidates as $candidate)
                                    <option value="{{ $candidate->id }}">{{ $candidate->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label>Note de delegation</label>
                            <input type="text" name="note" maxlength="500" placeholder="Motif, contexte, urgence...">
                        </div>
                        <div class="actions" style="margin-top:0;">
                            <button type="submit" class="button button-secondary">Deleguer</button>
                        </div>
                    </form>
                @endif
            </section>
        @empty
            <section class="card empty-state">
                <span class="badge badge-success">Boite vide</span>
                <h3>Aucune approbation en attente</h3>
                <div class="muted">Les documents en attente ont ete traites ou aucun workflow n attend ton intervention pour le moment.</div>
                <div class="empty-actions">
                    <a href="{{ route('dashboard') }}" class="button button-primary">Retour dashboard</a>
                    @allowed('sales.view')
                        <a href="{{ route('sales.index') }}" class="button button-secondary">Voir les ventes</a>
                    @endallowed
                    @allowed('purchases.view')
                        <a href="{{ route('purchases.index') }}" class="button button-secondary">Voir les achats</a>
                    @endallowed
                    @allowed('expenses.view')
                        <a href="{{ route('expenses.index') }}" class="button button-secondary">Voir les depenses</a>
                    @endallowed
                </div>
            </section>
        @endforelse
    </div>
@endsection
