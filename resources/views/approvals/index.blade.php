@extends('layouts.app')

@section('title', 'Approbations')
@section('page-title', 'Portail d approbation')
@section('layout-mode', 'compact')

@push('page-styles')
    <style>
        .approval-workbar {
            position: relative;
            display: grid;
            grid-template-columns: auto minmax(0, 1fr) minmax(300px, 430px);
            gap: 10px;
            align-items: center;
            margin-bottom: 10px;
            padding: 10px;
            overflow: hidden;
            border: 1px solid rgba(102, 82, 56, 0.14);
            border-radius: 12px;
            background: linear-gradient(180deg, rgba(255, 254, 251, 0.98) 0%, rgba(250, 245, 238, 0.96) 100%);
            box-shadow: 0 10px 24px rgba(42, 28, 18, 0.07), inset 0 1px 0 rgba(255, 255, 255, 0.9);
        }
        .approval-workbar::before {
            content: "";
            position: absolute;
            inset: 0 0 auto;
            height: 2px;
            background: linear-gradient(90deg, var(--brand) 0%, #3ec7c9 48%, var(--accent) 100%);
        }
        .approval-workbar__title {
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }
        .approval-workbar__title h2 {
            margin: 0;
            font-size: 16px;
            font-weight: 900;
        }
        .approval-filters {
            display: flex;
            gap: 6px;
            min-width: 0;
            overflow-x: auto;
            scrollbar-width: thin;
        }
        .approval-filter {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            min-height: 32px;
            padding: 6px 9px;
            border: 1px solid rgba(102, 82, 56, 0.12);
            border-radius: 9px;
            background: rgba(255, 255, 255, 0.88);
            color: var(--muted);
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
            transition: border-color .16s ease, background .16s ease, color .16s ease;
        }
        .approval-filter.is-active {
            border-color: rgba(15, 118, 110, 0.3);
            background: linear-gradient(180deg, rgba(15, 118, 110, 0.13) 0%, rgba(15, 118, 110, 0.08) 100%);
            color: var(--brand-deep);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.72);
        }
        .approval-filter:hover {
            border-color: rgba(15, 118, 110, 0.24);
            color: var(--brand-deep);
        }
        .approval-filter__count {
            display: inline-grid;
            min-width: 20px;
            height: 20px;
            place-items: center;
            padding: 0 5px;
            border-radius: 999px;
            background: rgba(15, 118, 110, 0.1);
            color: var(--brand-deep);
            font-size: 11px;
        }
        .approval-search {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto auto;
            gap: 6px;
        }
        .approval-search input,
        .approval-search .button {
            min-height: 34px;
            padding: 7px 9px;
            border-radius: 9px;
            font-size: 12px;
        }
        .approval-queue {
            display: grid;
            gap: 8px;
        }
        .approval-item {
            position: relative;
            margin: 0 !important;
            padding: 14px;
            overflow: hidden;
            border-radius: 12px;
            border-color: rgba(102, 82, 56, 0.13);
            background: linear-gradient(180deg, rgba(255, 254, 251, 0.98) 0%, rgba(251, 247, 241, 0.96) 100%);
            box-shadow: 0 8px 20px rgba(42, 28, 18, 0.06), inset 0 1px 0 rgba(255, 255, 255, 0.9);
        }
        .approval-item::before {
            content: "";
            position: absolute;
            inset: 0 auto 0 0;
            width: 3px;
            background: var(--brand);
        }
        .approval-item.is-overdue::before {
            background: var(--danger);
        }
        .approval-item__head {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 12px;
            align-items: start;
        }
        .approval-item__identity {
            display: flex;
            gap: 7px;
            align-items: center;
            flex-wrap: wrap;
        }
        .approval-item__number {
            color: var(--brand-deep);
            font-size: 14px;
            font-weight: 900;
        }
        .approval-item__counterpart {
            margin-top: 8px;
            font-size: 14px;
            font-weight: 800;
        }
        .approval-item__meta {
            display: flex;
            gap: 5px 12px;
            flex-wrap: wrap;
            margin-top: 6px;
            color: var(--muted);
            font-size: 12px;
        }
        .approval-item__actions {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        .approval-item__actions .button {
            min-height: 34px;
            padding: 7px 10px;
            border-radius: 9px;
            font-size: 12px;
        }
        .approval-item__form {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid rgba(102, 82, 56, 0.1);
        }
        @media (max-width: 1280px) {
            .approval-workbar {
                grid-template-columns: auto minmax(0, 1fr);
            }
            .approval-search {
                grid-column: 1 / -1;
            }
        }
        @media (max-width: 700px) {
            .approval-workbar {
                grid-template-columns: minmax(0, 1fr);
            }
            .approval-filters,
            .approval-search {
                grid-column: auto;
            }
            .approval-search {
                grid-template-columns: minmax(0, 1fr) auto;
            }
            .approval-search .button-secondary {
                display: none;
            }
            .approval-item__head {
                grid-template-columns: minmax(0, 1fr);
            }
            .approval-item__actions {
                justify-content: flex-start;
            }
        }
    </style>
@endpush

@section('content')
    <section class="approval-workbar" aria-label="Filtres des approbations">
        <div class="approval-workbar__title">
            <h2>Documents a traiter</h2>
            @if (($summary['overdue_count'] ?? 0) > 0)
                <span class="badge badge-danger">{{ $summary['overdue_count'] }} en retard</span>
            @endif
        </div>
        <nav class="approval-filters" aria-label="Type de document">
            <a href="{{ route('approvals.index', array_filter(['search' => $filters['search'] ?? null])) }}" class="approval-filter {{ ($filters['module'] ?? null) === null ? 'is-active' : '' }}">
                Tout <span class="approval-filter__count">{{ $summary['count'] }}</span>
            </a>
            <a href="{{ route('approvals.index', array_filter(['module' => 'sales', 'search' => $filters['search'] ?? null])) }}" class="approval-filter {{ ($filters['module'] ?? null) === 'sales' ? 'is-active' : '' }}">
                Ventes <span class="approval-filter__count">{{ $summary['by_module']['sales'] }}</span>
            </a>
            <a href="{{ route('approvals.index', array_filter(['module' => 'purchases', 'search' => $filters['search'] ?? null])) }}" class="approval-filter {{ ($filters['module'] ?? null) === 'purchases' ? 'is-active' : '' }}">
                Achats <span class="approval-filter__count">{{ $summary['by_module']['purchases'] }}</span>
            </a>
            <a href="{{ route('approvals.index', array_filter(['module' => 'expenses', 'search' => $filters['search'] ?? null])) }}" class="approval-filter {{ ($filters['module'] ?? null) === 'expenses' ? 'is-active' : '' }}">
                Depenses <span class="approval-filter__count">{{ $summary['by_module']['expenses'] }}</span>
            </a>
        </nav>
        <form method="GET" action="{{ route('approvals.index') }}" class="approval-search">
            <input type="hidden" name="module" value="{{ $filters['module'] ?? '' }}">
            <input type="search" id="search" name="search" aria-label="Rechercher une approbation" value="{{ $filters['search'] ?? '' }}" placeholder="Numero, tiers, agence...">
            <button type="submit" class="button button-primary">Filtrer</button>
            <a href="{{ route('approvals.index', array_filter(['module' => $filters['module'] ?? null])) }}" class="button button-secondary">Effacer</a>
        </form>
    </section>

    <div class="approval-queue">
        @forelse ($items as $item)
            @php
                $delegateCandidates = collect($item['delegate_candidates'] ?? collect())
                    ->reject(fn ($candidate) => $candidate->id === auth()->id())
                    ->values();
            @endphp
            <section class="card approval-item {{ ($item['is_overdue'] ?? false) ? 'is-overdue' : '' }}">
                <div class="approval-item__head">
                    <div>
                        <div class="approval-item__identity">
                            <strong class="approval-item__number">{{ $item['module_label'] }} {{ $item['number'] }}</strong>
                            <span class="badge badge-warning">{{ $item['pending_step']?->label }}</span>
                            <span class="badge badge-muted">{{ $item['branch_name'] }}</span>
                            @if ($item['is_overdue'] ?? false)
                                <span class="badge badge-danger">SLA depasse</span>
                            @endif
                            @if ($item['pending_step']?->escalated_at)
                                <span class="badge badge-danger">Escaladee</span>
                            @endif
                        </div>
                        <div class="approval-item__counterpart">{{ $item['counterpart'] }}</div>
                        <div class="approval-item__meta">
                            <span>{{ $item['document_date'] }}</span>
                            <span>{{ $item['creator_name'] }}</span>
                            <strong>{{ number_format($item['amount'], 0, ',', ' ') }} XOF</strong>
                        </div>
                        <div class="approval-item__meta">
                            @if ($item['assigned_approver_name'])
                                <span>Assigne a {{ $item['assigned_approver_name'] }}</span>
                            @else
                                <span>Etape non assignee explicitement</span>
                            @endif
                            @if ($item['due_at'])
                                <span>SLA {{ $item['due_at']->format('d/m/Y H:i') }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="approval-item__actions">
                        <a href="{{ $item['detail_url'] }}" class="button button-secondary">Ouvrir</a>
                        <form method="POST" action="{{ $item['approve_url'] }}">
                            @csrf
                            <button type="submit" class="button button-primary">Valider l etape</button>
                        </form>
                    </div>
                </div>
                @if ($item['reject_url'])
                    <form method="POST" action="{{ $item['reject_url'] }}" class="form-grid approval-item__form" style="grid-template-columns:minmax(220px, 2fr) auto; align-items:end;">
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
                    <form method="POST" action="{{ $item['delegate_url'] }}" class="form-grid approval-item__form" style="grid-template-columns:minmax(220px, 1.2fr) minmax(220px, 2fr) auto; align-items:end;">
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
