@extends('layouts.app')

@section('title', 'Session POS - Nema ERP')
@section('page-title', 'Session de caisse')
@section('hide-global-shortcuts', '1')
@section('layout-mode', 'focus')

@section('content')
    @php
        $openingCashBreakdown = is_array($session->opening_cash_breakdown) ? $session->opening_cash_breakdown : [];
        $openingHasBreakdown = collect(array_keys($cashDenominations))->sum(fn ($denomination) => (int) ($openingCashBreakdown[$denomination] ?? 0)) > 0;
        $closingCashBreakdown = is_array($session->closing_cash_breakdown) ? $session->closing_cash_breakdown : [];
        $closingHasBreakdown = collect(array_keys($cashDenominations))->sum(fn ($denomination) => (int) ($closingCashBreakdown[$denomination] ?? 0)) > 0;
        $ticketRows = collect($ticketRows ?? []);
        $sessionLockLabel = $session->isOpen() ? 'Ouverte' : 'Fermee / verrouillee';
        $customerLabel = $businessVocabulary['client'] ?? 'Client';
        $productLabel = $businessVocabulary['product'] ?? 'Produit';
        $productsLabel = $businessVocabulary['products'] ?? 'Produits';
        $saleLabel = $businessVocabulary['sale'] ?? 'Vente';
        $salesLabel = $businessVocabulary['sales'] ?? 'Ventes';
        $stockLabel = $businessVocabulary['stock'] ?? 'Stock';
        $cashierLabel = $businessVocabulary['cashier'] ?? 'Caissier';
    @endphp

    <style>
        .pos-session {
            display: grid;
            gap: 22px;
        }
        .pos-session-hero {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            flex-wrap: wrap;
            align-items: flex-start;
            padding: 24px 26px;
            border-radius: 24px;
            color: #fff;
            background: linear-gradient(135deg, #102a43 0%, #1d4f73 52%, #3478ad 100%);
            box-shadow: 0 20px 42px rgba(16, 42, 67, 0.18);
        }
        .pos-session-hero h2 { margin: 0; font-size: 30px; letter-spacing: -0.03em; }
        .pos-session-hero .muted { color: rgba(255,255,255,.78); }
        .pos-session-hero .button-secondary {
            background: rgba(255,255,255,.14);
            color: #fff;
            border: 1px solid rgba(255,255,255,.22);
        }
        .pos-view-tabs {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 16px;
        }
        .pos-view-tab {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 999px;
            border: 1px solid rgba(255,255,255,.24);
            background: rgba(255,255,255,.10);
            color: #fff;
            font-size: 13px;
            font-weight: 700;
        }
        .pos-view-tab.is-active {
            background: rgba(255,255,255,.18);
            border-color: rgba(255,255,255,.34);
        }
        .pos-status-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,.16);
            border: 1px solid rgba(255,255,255,.22);
            font-weight: 700;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: .08em;
            margin-top: 12px;
        }
        .pos-session-grid {
            display: grid;
            gap: 20px;
            grid-template-columns: minmax(0, 1fr) minmax(360px, .92fr);
        }
        .pos-session-panel {
            border: 1px solid #dbe5f0;
            border-radius: 24px;
            background: linear-gradient(180deg, #ffffff 0%, #f9fbfe 100%);
            box-shadow: 0 18px 35px rgba(17, 24, 39, 0.05);
        }
        .pos-session-head {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            align-items: flex-start;
            padding: 22px 22px 14px;
            border-bottom: 1px solid #e8eef5;
        }
        .pos-session-head h3 { margin: 0; font-size: 22px; letter-spacing: -0.02em; }
        .pos-session-body { padding: 20px 22px 22px; }
        .pos-stat-grid {
            display: grid;
            gap: 14px;
            grid-template-columns: repeat(auto-fit, minmax(155px, 1fr));
        }
        .pos-stat-card {
            padding: 16px 18px;
            border-radius: 18px;
            border: 1px solid #dde7f2;
            background: #fff;
        }
        .pos-stat-card .label {
            color: #6c8097;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .pos-stat-card .value {
            margin-top: 10px;
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.03em;
            color: #13263e;
        }
        .pos-detail-list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: grid;
            gap: 10px;
        }
        .pos-detail-list li {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            padding: 12px 14px;
            border-radius: 16px;
            background: #fff;
            border: 1px solid #e7edf4;
        }
        .pos-detail-list span:last-child { font-weight: 700; color: #16293f; text-align: right; }
        .pos-close-grid {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            margin-top: 14px;
        }
        .pos-count-card {
            padding: 12px;
            border: 1px solid #e5d9c7;
            border-radius: 14px;
            background: #fcfaf6;
        }
        .pos-kpi-row {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
        }
        .pos-kpi {
            padding: 14px 16px;
            border-radius: 18px;
            border: 1px solid #dce6f2;
            background: #fff;
        }
        .pos-kpi .label { color: #6d8197; font-size: 13px; }
        .pos-kpi .value { margin-top: 8px; font-size: 24px; font-weight: 800; color: #16293f; }
        .pos-history-list {
            display: grid;
            gap: 12px;
        }
        .pos-history-list.is-compact {
            gap: 8px;
        }
        .pos-history-item {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            align-items: flex-start;
            padding: 14px 16px;
            border: 1px solid #e5ebf3;
            border-radius: 18px;
            background: #fff;
        }
        .pos-history-item.is-compact {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 16px;
        }
        .pos-history-item strong { display: block; margin-bottom: 5px; }
        .pos-history-meta { color: #6f8094; font-size: 13px; }
        .pos-history-main {
            min-width: 0;
        }
        .pos-history-title-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }
        .pos-history-title-row strong {
            margin: 0;
            font-size: 15px;
        }
        .pos-history-title-row .pos-history-meta {
            white-space: nowrap;
        }
        .pos-history-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 8px;
        }
        .pos-mini-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 999px;
            border: 1px solid #dbe5f0;
            background: #f7fafc;
            color: #304256;
            font-size: 12px;
            font-weight: 700;
            line-height: 1;
        }
        .pos-amount {
            font-size: 16px;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: #13263e;
            white-space: nowrap;
        }
        .pos-inline-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 8px;
        }
        .pos-inline-actions .button {
            min-height: auto;
            padding: 8px 12px;
            border-radius: 12px;
            font-size: 12px;
        }
        .pos-breakdown-grid {
            display: grid;
            gap: 20px;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        }
        .pos-session-quick-grid {
            display: grid;
            gap: 14px;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        }
        .pos-session-quick-card {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            align-items: center;
            border-radius: 20px;
            border: 1px solid #dde7f2;
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            padding: 16px 18px;
        }
        .pos-session-quick-card .label {
            color: #64748b;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .08em;
            font-weight: 700;
        }
        .pos-session-quick-card .value {
            margin-top: 8px;
            color: #13263e;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -.03em;
        }
        .pos-command-shell {
            overflow: hidden;
            border-radius: 18px;
            border: 1px solid #263753;
            background: #111827;
            color: #eef4ff;
            box-shadow: 0 22px 46px rgba(15, 23, 42, .22);
        }
        .pos-command-topbar {
            display: flex;
            align-items: center;
            gap: 8px;
            min-height: 48px;
            padding: 7px 10px;
            background: linear-gradient(90deg, #20283e 0%, #2c3150 72%, #d437a2 100%);
        }
        .pos-command-tab,
        .pos-command-session,
        .pos-command-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 34px;
            padding: 0 14px;
            border-radius: 8px;
            color: #fff;
            font-weight: 800;
            text-decoration: none;
            border: 1px solid rgba(255,255,255,.12);
            background: rgba(255,255,255,.08);
        }
        .pos-command-session {
            color: #e0f2fe;
            background: rgba(14, 116, 144, .42);
            border-color: rgba(125, 211, 252, .28);
        }
        .pos-command-tab.is-active {
            background: #0f172a;
            border-color: rgba(255,255,255,.2);
        }
        .pos-command-count {
            background: #0f766e;
            border-color: #14b8a6;
        }
        .pos-command-lock {
            margin-left: auto;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 30px;
            padding: 0 10px;
            border-radius: 999px;
            color: #fff;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .06em;
            background: rgba(15, 23, 42, .5);
            border: 1px solid rgba(255,255,255,.18);
        }
        .pos-command-search {
            display: grid;
            grid-template-columns: 44px minmax(0, 1fr) auto;
            align-items: center;
            min-height: 44px;
            border-top: 1px solid rgba(255,255,255,.08);
            border-bottom: 1px solid rgba(255,255,255,.08);
            background: #182239;
        }
        .pos-command-search-icon {
            display: grid;
            place-items: center;
            height: 44px;
            color: #cbd5e1;
            border-right: 1px solid rgba(255,255,255,.08);
            font-weight: 900;
        }
        .pos-command-search input {
            width: 100%;
            height: 44px;
            border: 0;
            outline: 0;
            color: #f8fafc;
            background: transparent;
            padding: 0 13px;
            font-size: 14px;
        }
        .pos-command-search input::placeholder { color: #94a3b8; }
        .pos-command-search-hint {
            padding: 0 12px;
            color: #cbd5e1;
            font-size: 12px;
            white-space: nowrap;
        }
        .pos-draft-strip {
            display: flex;
            gap: 8px;
            align-items: center;
            overflow-x: auto;
            padding: 8px 10px;
            border-bottom: 1px solid rgba(255,255,255,.08);
            background: #141c30;
        }
        .pos-draft-strip-title {
            flex: 0 0 auto;
            color: #fbbf24;
            font-size: 12px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .08em;
        }
        .pos-draft-pill {
            flex: 0 0 auto;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 32px;
            padding: 0 10px;
            border-radius: 8px;
            color: #f8fafc;
            background: rgba(251, 191, 36, .12);
            border: 1px solid rgba(251, 191, 36, .28);
            text-decoration: none;
            font-size: 12px;
            font-weight: 800;
        }
        .pos-draft-pill span {
            color: #fde68a;
            font-weight: 900;
        }
        .pos-command-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 330px;
            min-height: 640px;
        }
        .pos-command-list {
            min-width: 0;
            overflow: auto;
            max-height: 74vh;
            border-right: 1px solid rgba(255,255,255,.10);
        }
        .pos-command-table {
            width: 100%;
            border-collapse: collapse;
            color: #eef4ff;
            table-layout: fixed;
        }
        .pos-command-table thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            height: 31px;
            padding: 0 9px;
            color: #cbd5e1;
            background: #111827;
            border-bottom: 1px solid rgba(255,255,255,.14);
            font-size: 11px;
            text-align: left;
            text-transform: uppercase;
            letter-spacing: .06em;
        }
        .pos-command-table tbody tr {
            height: 42px;
            cursor: pointer;
            border-bottom: 1px solid rgba(148, 163, 184, .22);
            background: #202a44;
        }
        .pos-command-table tbody tr:nth-child(even) { background: #24304f; }
        .pos-command-table tbody tr:hover { background: #33446d; }
        .pos-command-table tbody tr.is-selected {
            background: #7dd3fc;
            color: #073344;
        }
        .pos-command-table td {
            padding: 5px 9px;
            vertical-align: middle;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 12px;
            font-weight: 700;
        }
        .pos-command-table .sub {
            display: block;
            margin-top: 2px;
            opacity: .78;
            font-size: 11px;
            font-weight: 600;
        }
        .pos-command-amount {
            text-align: right;
            font-weight: 900;
        }
        .pos-command-actions {
            display: flex;
            justify-content: flex-end;
            gap: 4px;
            flex-wrap: nowrap;
        }
        .pos-command-actions a,
        .pos-command-actions button {
            min-width: 24px;
            height: 24px;
            border-radius: 7px;
            border: 1px solid rgba(255,255,255,.14);
            color: #fff;
            background: rgba(255,255,255,.10);
            font-size: 10px;
            font-weight: 800;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 4px;
        }
        .pos-command-actions .is-disabled {
            opacity: .42;
            pointer-events: none;
        }
        .pos-status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 72px;
            height: 22px;
            padding: 0 8px;
            border-radius: 6px;
            color: #fff;
            font-size: 11px;
            font-weight: 900;
        }
        .pos-status-badge.is-success { background: #16a34a; }
        .pos-status-badge.is-warning { background: #f59e0b; color: #111827; }
        .pos-status-badge.is-purple { background: #7c3aed; }
        .pos-status-badge.is-danger { background: #dc2626; }
        .pos-ticket-detail {
            display: grid;
            grid-template-rows: auto minmax(0, 1fr);
            min-height: 100%;
            background: #172033;
        }
        .pos-ticket-detail-head {
            padding: 14px 16px;
            border-bottom: 1px solid rgba(255,255,255,.10);
            background: #1f2940;
        }
        .pos-ticket-detail-head h3 {
            margin: 0;
            color: #fff;
            font-size: 18px;
        }
        .pos-ticket-detail-head .muted { color: #b8c2d6; }
        .pos-ticket-detail-body {
            overflow: auto;
            max-height: calc(74vh - 58px);
            padding: 12px;
            display: grid;
            gap: 12px;
            align-content: start;
        }
        .pos-detail-card {
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,.10);
            background: rgba(255,255,255,.055);
            padding: 12px;
        }
        .pos-detail-card h4 {
            margin: 0 0 9px;
            color: #f8fafc;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .08em;
        }
        .pos-detail-lines {
            display: grid;
            gap: 7px;
        }
        .pos-detail-line {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 10px;
            color: #e5edf8;
            font-size: 12px;
        }
        .pos-detail-line strong {
            display: block;
            color: #fff;
            font-size: 13px;
        }
        .pos-detail-line span:last-child {
            font-weight: 900;
            text-align: right;
            white-space: nowrap;
        }
        .pos-detail-total {
            display: grid;
            gap: 8px;
        }
        .pos-detail-total div {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            color: #dbeafe;
            font-size: 13px;
        }
        .pos-detail-total strong { color: #fff; }
        .pos-detail-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        .pos-detail-actions a {
            min-height: 38px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            text-decoration: none;
            font-weight: 900;
            background: #334155;
            border: 1px solid rgba(255,255,255,.12);
        }
        .pos-detail-actions a.primary { background: #0f766e; }
        .pos-detail-actions a.danger { background: #be185d; }
        .pos-detail-actions a.is-disabled {
            opacity: .42;
            pointer-events: none;
        }
        .pos-command-empty {
            padding: 24px;
            color: #cbd5e1;
            text-align: center;
        }
        @media (max-width: 1080px) {
            .pos-session-grid { grid-template-columns: 1fr; }
            .pos-command-layout { grid-template-columns: 1fr; }
            .pos-command-list { border-right: 0; }
            .pos-ticket-detail-body { max-height: none; }
        }
        @media (max-width: 760px) {
            .pos-session-hero { padding: 20px 18px; }
            .pos-session-hero h2 { font-size: 26px; }
            .pos-history-item.is-compact {
                grid-template-columns: 1fr;
            }
            .pos-inline-actions {
                justify-content: flex-start;
            }
        }
    </style>

    <div class="pos-session">
        <section id="tickets-session" class="pos-command-shell" data-pos-command-shell>
            <div class="pos-command-topbar">
                @if ($session->status === 'open')
                    <a href="{{ route('pos.sales.create', ['session' => $session->id]) }}" class="pos-command-tab">Caisse</a>
                @else
                    <a href="{{ route('pos.index') }}" class="pos-command-tab">Caisse</a>
                @endif
                <span class="pos-command-tab is-active">{{ $salesLabel }}</span>
                <span class="pos-command-session">{{ $session->session_number }}</span>
                <span class="pos-command-count">{{ $summary['sales_count'] }}</span>
                <a href="{{ route('pos.preparation.index') }}" class="pos-command-tab">Prep</a>
                <a href="{{ route('pos.report', ['date' => $session->opened_at?->toDateString(), 'warehouse_id' => $session->warehouse_id, 'cash_account_id' => $session->cash_account_id]) }}" class="pos-command-tab">Rapport</a>
                <a href="{{ route('pos.count-sheet', $session) }}" class="pos-command-tab">Comptage</a>
                <a href="{{ route('pos.session.print', $session) }}" class="pos-command-tab">Imprimer</a>
                @if ($session->status === 'open')
                        <a href="{{ route('pos.sales.create', ['session' => $session->id]) }}" class="pos-command-tab">+ {{ $saleLabel }}</a>
                @endif
                <span class="pos-command-lock">{{ $sessionLockLabel }}</span>
            </div>
            <div class="pos-command-search">
                <div class="pos-command-search-icon">F2</div>
                <input id="pos-command-search" type="search" autocomplete="off" placeholder="Rechercher..." data-pos-command-search>
                <div class="pos-command-search-hint">F2 recherche · F4 paiement · ENTER ouvrir · ESC annuler</div>
            </div>
            @if ($pendingDrafts->isNotEmpty())
                <div class="pos-draft-strip">
                    <div class="pos-draft-strip-title">{{ $salesLabel }} en attente</div>
                    @foreach ($pendingDrafts as $draft)
                        <a href="{{ route('pos.sales.create', ['session' => $session->id, 'draft' => $draft->id]) }}" class="pos-draft-pill">
                            {{ $draft->label }}
                            <span>{{ number_format((float) $draft->total, 0, ',', ' ') }} XOF</span>
                            Reprendre
                        </a>
                    @endforeach
                </div>
            @endif
            <div class="pos-command-layout">
                <div class="pos-command-list">
                    <table class="pos-command-table">
                        <thead>
                        <tr>
                            <th style="width:96px;">Date / heure</th>
                            <th style="width:142px;">Facture / ticket</th>
                            <th style="width:170px;">{{ $customerLabel }}</th>
                            <th style="width:104px; text-align:right;">Montant</th>
                            <th style="width:88px;">Statut</th>
                            <th style="width:126px; text-align:right;">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($ticketRows as $row)
                            <tr data-ticket-row data-ticket-id="{{ $row['id'] }}" data-search="{{ $row['search'] }}">
                                <td>
                                    {{ $row['date'] }}
                                    <span class="sub">{{ $row['time'] }}</span>
                                </td>
                                <td>
                                    {{ $row['invoice_number'] }}
                                    <span class="sub">{{ $row['ticket_reference'] }}</span>
                                </td>
                                <td>{{ $row['customer'] }}</td>
                                <td class="pos-command-amount">{{ number_format($row['amount'], 0, ',', ' ') }} XOF</td>
                                <td><span class="pos-status-badge is-{{ $row['status']['tone'] }}">{{ $row['status']['label'] }}</span></td>
                                <td>
                                    <div class="pos-command-actions">
                                        <a href="{{ $row['receipt_url'] }}" title="Ticket">Recu</a>
                                        <a href="{{ $row['thermal_url'] }}" title="Ticket thermique">Imp.</a>
                                        <a href="{{ $row['payment_url'] }}" class="{{ $row['can_pay'] ? '' : 'is-disabled' }}" title="{{ $row['can_pay'] ? 'Paiement' : 'Aucun solde a encaisser' }}">Payer</a>
                                        <a href="{{ $row['return_url'] }}" class="{{ $row['can_refund'] ? '' : 'is-disabled' }}" title="{{ $row['can_refund'] ? 'Rembourser' : 'Caisse verrouillee ou ticket non remboursable' }}">Ret.</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="pos-command-empty">Aucune operation sur cette session.</div>
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <aside class="pos-ticket-detail" data-pos-ticket-detail>
                    <div class="pos-ticket-detail-head">
                        <h3>Session {{ $session->session_number }}</h3>
                        <div class="muted">{{ $sessionLockLabel }} · {{ $summary['sales_count'] }} operation(s)</div>
                    </div>
                    <div class="pos-ticket-detail-body">
                        <div class="pos-detail-card">
                            <h4>Resume caisse</h4>
                            <div class="pos-detail-total">
                                <div><span>Ouverte par</span><strong>{{ $session->opener?->name ?? 'Operateur' }}</strong></div>
                                <div><span>Montant initial</span><strong>{{ number_format((float) $session->opening_amount, 0, ',', ' ') }} XOF</strong></div>
                                <div><span>Total net</span><strong>{{ number_format($summary['sales_total'], 0, ',', ' ') }} XOF</strong></div>
                                <div><span>Retours</span><strong>{{ number_format($summary['return_total'], 0, ',', ' ') }} XOF</strong></div>
                                <div><span>Flux net</span><strong>{{ number_format($summary['net_cash'], 0, ',', ' ') }} XOF</strong></div>
                            </div>
                        </div>
                        <div class="pos-detail-card">
                            <h4>Audit recent</h4>
                            <div class="pos-detail-lines">
                                @forelse ($auditLogs as $log)
                                    <div class="pos-detail-line">
                                        <div>
                                            <strong>{{ $log->description }}</strong>
                                            <div class="muted">{{ $log->user?->name ?? 'Systeme' }} · {{ $log->created_at?->format('d/m/Y H:i') }}</div>
                                            @if (($log->properties['reason'] ?? null) || ($log->properties['unlock_reason'] ?? null))
                                                <div class="muted">Raison : {{ $log->properties['reason'] ?? $log->properties['unlock_reason'] }}</div>
                                            @endif
                                            @if (($log->properties['old_values'] ?? null) || ($log->properties['new_values'] ?? null))
                                                <div class="muted">Ancienne valeur / nouvelle valeur enregistrees</div>
                                            @endif
                                        </div>
                                        <span>{{ $log->action }}</span>
                                    </div>
                                @empty
                                    <div class="muted">Aucun audit recent pour cette session.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </section>

        <div class="pos-stat-grid">
            <div class="pos-stat-card"><div class="label">Montant initial</div><div class="value">{{ number_format((float) $session->opening_amount, 0, ',', ' ') }}</div></div>
            <div class="pos-stat-card"><div class="label">Brut {{ strtolower($productsLabel) }}</div><div class="value">{{ number_format($summary['gross_sales_total'], 0, ',', ' ') }}</div></div>
            <div class="pos-stat-card"><div class="label">Remises</div><div class="value">{{ number_format($summary['discount_total'], 0, ',', ' ') }}</div></div>
            <div class="pos-stat-card"><div class="label">Total net</div><div class="value">{{ number_format($summary['sales_total'], 0, ',', ' ') }}</div></div>
            <div class="pos-stat-card"><div class="label">Retours</div><div class="value">{{ number_format($summary['return_total'], 0, ',', ' ') }}</div></div>
            <div class="pos-stat-card"><div class="label">Flux net caisse</div><div class="value">{{ number_format($summary['net_cash'], 0, ',', ' ') }}</div></div>
            <div class="pos-stat-card"><div class="label">Ecart</div><div class="value">{{ number_format((float) ($session->variance_amount ?? 0), 0, ',', ' ') }}</div></div>
        </div>

        <section class="pos-session-quick-grid">
            <article class="pos-session-quick-card">
                <div>
                    <div class="label">{{ $salesLabel }}</div>
                    <div class="value">{{ $summary['sales_count'] }}</div>
                </div>
                <a href="#tickets-session" class="button button-secondary">Voir</a>
            </article>
            <article class="pos-session-quick-card">
                <div>
                    <div class="label">Paiements</div>
                    <div class="value">{{ number_format($summary['paid_total'], 0, ',', ' ') }}</div>
                </div>
                <a href="#cloture-session" class="button button-secondary">Controler</a>
            </article>
            <article class="pos-session-quick-card">
                <div>
                    <div class="label">Retours</div>
                    <div class="value">{{ $summary['return_count'] }}</div>
                </div>
                <a href="#tickets-session" class="button button-secondary">Suivre</a>
            </article>
            <article class="pos-session-quick-card">
                <div>
                    <div class="label">Comptage</div>
                    <div class="value">{{ number_format((float) $summary['expected_amount'], 0, ',', ' ') }}</div>
                </div>
                <a href="{{ route('pos.count-sheet', $session) }}" class="button button-secondary">Imprimer</a>
            </article>
            <article class="pos-session-quick-card">
                <div>
                    <div class="label">Fiche session</div>
                    <div class="value">{{ $session->status === 'closed' ? 'Archive' : 'Controle' }}</div>
                </div>
                <a href="{{ route('pos.session.print', $session) }}" class="button button-secondary">Imprimer</a>
            </article>
            <article class="pos-session-quick-card">
                <div>
                    <div class="label">Rapport</div>
                    <div class="value">{{ number_format($summary['net_cash'], 0, ',', ' ') }}</div>
                </div>
                <a href="{{ route('pos.report', ['date' => $session->opened_at?->toDateString(), 'warehouse_id' => $session->warehouse_id, 'cash_account_id' => $session->cash_account_id]) }}" class="button button-secondary">Ouvrir</a>
            </article>
        </section>

        <div class="pos-session-grid">
            <section class="pos-session-panel">
                <div class="pos-session-head">
                    <div>
                        <h3>Informations session</h3>
                        <div class="muted">Lecture rapide de l activite et des chiffres clefs de la caisse.</div>
                    </div>
                </div>
                <div class="pos-session-body">
                    <div class="pos-kpi-row" style="margin-bottom:18px;">
                        <div class="pos-kpi"><div class="label">Ouverte le</div><div class="value">{{ $session->opened_at?->format('d/m H:i') }}</div></div>
                        <div class="pos-kpi"><div class="label">Ouverte par</div><div class="value">{{ $session->opener?->name }}</div></div>
                        <div class="pos-kpi"><div class="label">Documents</div><div class="value">{{ $summary['sales_count'] }}</div></div>
                        <div class="pos-kpi"><div class="label">Retours</div><div class="value">{{ $summary['return_count'] }}</div></div>
                    </div>

                    <ul class="pos-detail-list">
                        <li><span>Statut</span><span>{{ $session->status === 'open' ? 'OPEN' : 'CLOSED' }}</span></li>
                        <li><span>Montant initial</span><span>{{ number_format((float) $session->opening_amount, 0, ',', ' ') }} XOF</span></li>
                        <li><span>{{ $productsLabel }} vendus</span><span>{{ $summary['items_count'] }}</span></li>
                        <li><span>{{ $productsLabel }} retournes</span><span>{{ $summary['returned_items_count'] }}</span></li>
                        <li><span>Remises accordees</span><span>{{ number_format($summary['discount_total'], 0, ',', ' ') }} XOF</span></li>
                        <li><span>Fond de caisse detaille</span><span>{{ $openingHasBreakdown ? 'Oui' : 'Non' }}</span></li>
                        @if ($session->closed_at)
                            <li><span>Cloturee le</span><span>{{ $session->closed_at?->format('d/m/Y H:i') }}</span></li>
                            <li><span>Compte physique</span><span>{{ number_format((float) $session->closing_amount, 0, ',', ' ') }} XOF</span></li>
                            <li><span>Comptage especes detaille</span><span>{{ $closingHasBreakdown ? 'Oui' : 'Non' }}</span></li>
                        @endif
                        @if ($session->unlocked_at)
                            <li><span>Dernier deverrouillage</span><span>{{ $session->unlocked_at?->format('d/m/Y H:i') }}</span></li>
                            <li><span>Deverrouille par</span><span>{{ $session->unlocker?->name ?? 'Utilisateur inconnu' }}</span></li>
                            <li><span>Motif deverrouillage</span><span>{{ $session->unlock_reason }}</span></li>
                        @endif
                    </ul>
                </div>
            </section>

            <section id="cloture-session" class="pos-session-panel">
                <div class="pos-session-head">
                    <div>
                        <h3>Cloture detaillee par mode</h3>
                        <div class="muted">Controle le compte physique, les ecarts et la justification avant fermeture.</div>
                    </div>
                    @if ($session->status === 'open')
                        <span class="badge badge-warning">A cloturer</span>
                    @else
                        <span class="badge badge-success">Session arretee</span>
                    @endif
                </div>
                <div class="pos-session-body">
                    @if ($session->status === 'open')
                        @php
                            $closingBreakdown = old('closing_cash_breakdown', []);
                            $closingBreakdownTotal = collect(array_keys($cashDenominations))->sum(fn ($denomination) => ((int) ($closingBreakdown[$denomination] ?? 0)) * (int) $denomination);
                        @endphp
                        <form method="POST" action="{{ route('pos.close', $session) }}" class="form-grid">
                            @csrf
                            <div class="full" style="padding:16px; border:1px solid #eadfcd; border-radius:16px; background:#fcfaf6;">
                                <label style="margin-bottom:8px;">Comptage especes par coupures</label>
                                <div class="help">Renseigne les quantites d especes. Le montant cash se met a jour automatiquement.</div>
                                <div class="pos-close-grid">
                                    @foreach ($cashDenominations as $denomination => $label)
                                        <div class="pos-count-card">
                                            <label for="closing_cash_breakdown_{{ $denomination }}">{{ $label }}</label>
                                            <div class="help" style="margin-bottom:8px;">{{ number_format((int) $denomination, 0, ',', ' ') }} XOF</div>
                                            <input
                                                id="closing_cash_breakdown_{{ $denomination }}"
                                                name="closing_cash_breakdown[{{ $denomination }}]"
                                                type="number"
                                                min="0"
                                                step="1"
                                                value="{{ old('closing_cash_breakdown.'.$denomination, 0) }}"
                                                data-closing-cash-count
                                                data-denomination="{{ $denomination }}"
                                            >
                                        </div>
                                    @endforeach
                                </div>
                                @error('closing_cash_breakdown')<div class="field-error">{{ $message }}</div>@enderror
                                <div class="help" style="margin-top:10px;">Total especes comptees : <strong id="closing-breakdown-total">{{ number_format($closingBreakdownTotal, 0, ',', ' ') }} XOF</strong></div>
                            </div>
                            @foreach ($methodOptions as $method => $label)
                                @php
                                    $isCashMethod = $method === 'cash';
                                    $defaultCountedValue = $isCashMethod && $closingBreakdownTotal > 0
                                        ? number_format($closingBreakdownTotal, 2, '.', '')
                                        : old('counted_methods.'.$method, number_format($summary['expected_breakdown'][$method] ?? 0, 2, '.', ''));
                                @endphp
                                <div>
                                    <label for="counted_methods_{{ $method }}">{{ $label }}</label>
                                    @if ($isCashMethod)
                                        <input id="counted_methods_{{ $method }}" name="counted_methods[{{ $method }}]" type="number" min="0" step="0.01" value="{{ $defaultCountedValue }}" data-closing-cash-total-target>
                                    @else
                                        <input id="counted_methods_{{ $method }}" name="counted_methods[{{ $method }}]" type="number" min="0" step="0.01" value="{{ $defaultCountedValue }}">
                                    @endif
                                    <div class="help">Attendu : {{ number_format($summary['expected_breakdown'][$method] ?? 0, 0, ',', ' ') }} XOF</div>
                                    @if ($isCashMethod)
                                        <div class="help">Ce champ peut rester manuel, mais il sera aligne automatiquement si tu comptes les coupures.</div>
                                    @endif
                                    @error('counted_methods.'.$method)
                                        <div class="field-error">{{ $message }}</div>
                                    @enderror
                                    <label for="variance_notes_{{ $method }}" style="margin-top:10px;">Justification ecart {{ strtolower($label) }}</label>
                                    <input id="variance_notes_{{ $method }}" name="variance_notes[{{ $method }}]" value="{{ old('variance_notes.'.$method) }}" placeholder="Obligatoire si le compte differe de l attendu">
                                    @error('variance_notes.'.$method)
                                        <div class="field-error">{{ $message }}</div>
                                    @enderror
                                </div>
                            @endforeach
                            <div class="full">
                                <label for="closing_notes">Observations generales</label>
                                <textarea id="closing_notes" name="closing_notes">{{ old('closing_notes') }}</textarea>
                                @error('closing_notes')<div class="field-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="full actions">
                                <button type="submit" class="button button-primary">Cloturer la session</button>
                            </div>
                        </form>
                    @else
                        <div class="table-wrap">
                            <table>
                                <thead>
                                <tr>
                                    <th>Mode</th>
                                    <th>Attendu</th>
                                    <th>Compte</th>
                                    <th>Ecart</th>
                                    <th>Justification</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach ($methodOptions as $method => $label)
                                    <tr>
                                        <td>{{ $label }}</td>
                                        <td>{{ number_format($summary['expected_breakdown'][$method] ?? 0, 0, ',', ' ') }} XOF</td>
                                        <td>{{ number_format($summary['counted_breakdown'][$method] ?? 0, 0, ',', ' ') }} XOF</td>
                                        <td>{{ number_format($summary['variance_breakdown'][$method] ?? 0, 0, ',', ' ') }} XOF</td>
                                        <td>{{ $summary['variance_notes'][$method] ?: 'Aucun ecart ou non renseigne' }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if (auth()->user()?->hasPermission('pos.sessions.unlock'))
                            <form method="POST" action="{{ route('pos.unlock', $session) }}" class="form-grid" style="margin-top:18px; padding:16px; border:1px solid #eadfcd; border-radius:16px; background:#fcfaf6;">
                                @csrf
                                <div class="full">
                                    <label for="unlock_reason">Motif du deverrouillage</label>
                                    <textarea id="unlock_reason" name="unlock_reason" required placeholder="Ex: correction controlee apres erreur de saisie, validee par le superviseur.">{{ old('unlock_reason') }}</textarea>
                                    <div class="help">Le nom du responsable, l heure et ce motif seront enregistres dans l audit de caisse.</div>
                                    @error('unlock_reason')<div class="field-error">{{ $message }}</div>@enderror
                                </div>
                                <div class="full actions">
                                    <button type="submit" class="button button-primary">Deverrouiller la caisse</button>
                                </div>
                            </form>
                        @endif
                    @endif
                </div>
            </section>
        </div>

        @if ($openingHasBreakdown || $closingHasBreakdown)
            <div class="pos-breakdown-grid">
                @if ($openingHasBreakdown)
                    <section class="pos-session-panel">
                        <div class="pos-session-head">
                            <div>
                                <h3>Detail du fond de caisse</h3>
                                <div class="muted">Comptage a l ouverture de session.</div>
                            </div>
                        </div>
                        <div class="pos-session-body">
                            @include('pos.partials.cash-breakdown-table', ['cashDenominations' => $cashDenominations, 'breakdown' => $openingCashBreakdown])
                        </div>
                    </section>
                @endif
                @if ($closingHasBreakdown)
                    <section class="pos-session-panel">
                        <div class="pos-session-head">
                            <div>
                                <h3>Detail du comptage final</h3>
                                <div class="muted">Photographie de la caisse au moment de la cloture.</div>
                            </div>
                        </div>
                        <div class="pos-session-body">
                            @include('pos.partials.cash-breakdown-table', ['cashDenominations' => $cashDenominations, 'breakdown' => $closingCashBreakdown])
                        </div>
                    </section>
                @endif
            </div>
        @endif

    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const tickets = @json($ticketRows->values());
        const rows = Array.from(document.querySelectorAll('[data-ticket-row]'));
        const searchInput = document.querySelector('[data-pos-command-search]');
        const detail = document.querySelector('[data-pos-ticket-detail]');
        const cashierLabel = @json($cashierLabel);
        let selectedId = rows[0]?.dataset.ticketId || null;

        const ticketById = Object.fromEntries(tickets.map((ticket) => [String(ticket.id), ticket]));
        const money = (value) => new Intl.NumberFormat('fr-FR').format(Math.round(Number(value) || 0)) + ' XOF';
        const esc = (value) => String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
        const normalize = (value) => String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase();

        function badge(ticket) {
            return `<span class="pos-status-badge is-${esc(ticket.status.tone)}">${esc(ticket.status.label)}</span>`;
        }

        function renderLines(title, rowsHtml, emptyText) {
            return `
                <div class="pos-detail-card">
                    <h4>${esc(title)}</h4>
                    <div class="pos-detail-lines">
                        ${rowsHtml || `<div class="muted">${esc(emptyText)}</div>`}
                    </div>
                </div>
            `;
        }

        function renderDetail(ticket) {
            if (! detail || ! ticket) {
                return;
            }

            const items = ticket.items.map((item) => `
                <div class="pos-detail-line">
                    <div>
                        <strong>${esc(item.description)}</strong>
                        <div class="muted">${esc(item.code || '')} · ${Number(item.qty).toLocaleString('fr-FR')} x ${money(item.unit_price)}</div>
                        ${Number(item.discount_total || 0) > 0 ? `<div class="muted">Remise ${money(item.discount_total)}</div>` : ''}
                    </div>
                    <span>${money(item.line_total)}</span>
                </div>
            `).join('');

            const payments = ticket.payments.map((payment) => `
                <div class="pos-detail-line">
                    <div>
                        <strong>${esc(payment.method)}</strong>
                        <div class="muted">${esc(payment.date || '')} · ${esc(payment.reference || 'Sans reference')} · ${esc(payment.cash_account || '')}</div>
                    </div>
                    <span>${payment.direction === 'out' ? '-' : ''}${money(payment.amount)}</span>
                </div>
            `).join('');

            const returns = ticket.returns.map((itemReturn) => `
                <div class="pos-detail-line">
                    <div>
                        <strong>${esc(itemReturn.number)}</strong>
                        <div class="muted">${esc(itemReturn.date || '')} · ${esc(itemReturn.reason || 'Retour article')}</div>
                    </div>
                    <span>-${money(itemReturn.amount)}</span>
                </div>
            `).join('');

            const history = ticket.history.map((event) => `
                <div class="pos-detail-line">
                    <div>
                        <strong>${esc(event.label)}</strong>
                        <div class="muted">${esc(event.detail || '')}</div>
                    </div>
                    <span>${esc(event.date || '')}</span>
                </div>
            `).join('');

            detail.innerHTML = `
                <div class="pos-ticket-detail-head">
                    <h3>${esc(ticket.invoice_number)} ${badge(ticket)}</h3>
                    <div class="muted">${esc(ticket.ticket_reference)} · ${esc(ticket.customer)} · ${esc(ticket.date)} ${esc(ticket.time || '')}</div>
                </div>
                <div class="pos-ticket-detail-body">
                    <div class="pos-detail-card">
                        <h4>Total</h4>
                        <div class="pos-detail-total">
                            <div><span>Total ticket</span><strong>${money(ticket.amount)}</strong></div>
                            <div><span>Remises</span><strong>${money(ticket.discount_total)}</strong></div>
                            <div><span>Paye</span><strong>${money(ticket.amount_paid)}</strong></div>
                            <div><span>Reste</span><strong>${money(ticket.balance_due)}</strong></div>
                            <div><span>Rembourse</span><strong>${money(ticket.returned_amount)}</strong></div>
                            <div><span>${esc(cashierLabel)}</span><strong>${esc(ticket.cashier)}</strong></div>
                        </div>
                    </div>
                    ${renderLines(@json($productsLabel), items, 'Aucune ligne trouvee.')}
                    ${renderLines('Paiements', payments, 'Aucun paiement enregistre.')}
                    ${renderLines('Remboursements', returns, 'Aucun remboursement sur ce ticket.')}
                    ${renderLines('Historique', history, 'Aucun historique disponible.')}
                    <div class="pos-detail-actions">
                        <a href="${esc(ticket.receipt_url)}" class="primary">Ticket</a>
                        <a href="${esc(ticket.thermal_url)}">Thermique</a>
                        <a href="${esc(ticket.payment_url)}" class="${ticket.can_pay ? 'primary' : 'is-disabled'}">Paiement</a>
                        <a href="${esc(ticket.return_url)}" class="danger ${ticket.can_refund ? '' : 'is-disabled'}">Remboursement</a>
                    </div>
                </div>
            `;
        }

        function selectRow(row) {
            if (! row) {
                return;
            }

            rows.forEach((candidate) => candidate.classList.toggle('is-selected', candidate === row));
            selectedId = row.dataset.ticketId;
            renderDetail(ticketById[String(selectedId)]);
        }

        function visibleRows() {
            return rows.filter((row) => row.style.display !== 'none');
        }

        function filterRows() {
            const term = normalize(searchInput?.value || '');
            let firstVisible = null;

            rows.forEach((row) => {
                const matches = ! term || normalize(row.dataset.search || '').includes(term);
                row.style.display = matches ? '' : 'none';
                if (matches && ! firstVisible) {
                    firstVisible = row;
                }
            });

            if (firstVisible && (! selectedId || ! visibleRows().some((row) => row.dataset.ticketId === selectedId))) {
                selectRow(firstVisible);
            }
        }

        rows.forEach((row) => {
            row.addEventListener('click', () => selectRow(row));
        });

        searchInput?.addEventListener('input', filterRows);

        document.addEventListener('keydown', (event) => {
            const selected = ticketById[String(selectedId)];

            if (event.key === 'F2') {
                event.preventDefault();
                searchInput?.focus();
                searchInput?.select();
            }

            if (event.key === 'F4' && selected) {
                event.preventDefault();
                if (selected.can_pay) {
                    window.location.href = selected.payment_url;
                }
            }

            if (event.key === 'Escape') {
                if (searchInput && searchInput.value) {
                    event.preventDefault();
                    searchInput.value = '';
                    filterRows();
                }
            }

            if (event.key === 'Enter' && selected && document.activeElement === searchInput) {
                event.preventDefault();
                window.location.href = selected.receipt_url;
            }
        });

        if (rows.length > 0) {
            selectRow(rows[0]);
        }
    });
    </script>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const totalOutput = document.getElementById('closing-breakdown-total');
        const cashTarget = document.querySelector('[data-closing-cash-total-target]');
        const breakdownInputs = Array.from(document.querySelectorAll('[data-closing-cash-count]'));

        if (! totalOutput || ! cashTarget || breakdownInputs.length === 0) {
            return;
        }

        const formatter = new Intl.NumberFormat('fr-FR');

        const updateClosingBreakdownTotal = () => {
            const total = breakdownInputs.reduce((sum, input) => {
                const count = parseInt(input.value || '0', 10) || 0;
                const denomination = parseInt(input.dataset.denomination || '0', 10) || 0;

                return sum + (count * denomination);
            }, 0);

            totalOutput.textContent = formatter.format(total) + ' XOF';

            if (total > 0) {
                cashTarget.value = total.toFixed(2);
            }
        };

        breakdownInputs.forEach((input) => input.addEventListener('input', updateClosingBreakdownTotal));
        updateClosingBreakdownTotal();
    });
    </script>
@endsection
