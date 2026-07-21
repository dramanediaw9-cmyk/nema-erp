@extends('layouts.app')

@section('title', 'Point de vente - Nema ERP')
@section('page-title', 'Point de vente')

@section('content')
    @php
        $isCashier = auth()->user()?->hasRole('cashier');
        $customerLabel = $businessVocabulary['client'] ?? 'Client';
        $productLabel = $businessVocabulary['product'] ?? 'Produit';
        $productsLabel = $businessVocabulary['products'] ?? 'Produits';
        $saleLabel = $businessVocabulary['sale'] ?? 'Vente';
        $salesLabel = $businessVocabulary['sales'] ?? 'Ventes';
        $stockLabel = $businessVocabulary['stock'] ?? 'Stock';
        $cashierLabel = $businessVocabulary['cashier'] ?? 'Caissier';
    @endphp
    <style>
        .pos-home {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 12px;
            width: 100%;
            max-width: 100%;
            min-width: 0;
        }
        .pos-home-hero {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            flex-wrap: wrap;
            align-items: flex-start;
            padding: 14px 18px;
            border-radius: 8px;
            color: #fff;
            background: linear-gradient(135deg, #16324f 0%, #24517a 55%, #3370a8 100%);
            box-shadow: 0 20px 40px rgba(22, 50, 79, 0.18);
        }
        .pos-home-hero h2 { margin: 0; font-size: 24px; letter-spacing: -0.03em; }
        .pos-home-hero .muted { color: rgba(255,255,255,.78); max-width: 760px; }
        .pos-home-hero .button-secondary {
            background: rgba(255,255,255,.14);
            color: #fff;
            border: 1px solid rgba(255,255,255,.22);
        }
        .pos-home-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.2fr) minmax(320px, .8fr);
            gap: 20px;
        }
        .pos-panel {
            border: 1px solid #d9e3ef;
            border-radius: 8px;
            background: linear-gradient(180deg, #ffffff 0%, #f9fbfd 100%);
            box-shadow: 0 18px 35px rgba(17, 24, 39, 0.05);
        }
        .pos-panel-head {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            align-items: flex-start;
            padding: 14px 16px 10px;
            border-bottom: 1px solid #e7edf4;
        }
        .pos-panel-head h3 { margin: 0; font-size: 18px; letter-spacing: -0.02em; }
        .pos-panel-body { padding: 14px 16px 16px; }
        .pos-mode-strip {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 14px;
        }
        .pos-mode-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 12px;
            border-radius: 999px;
            background: #eff5ff;
            color: #2555a6;
            font-size: 12px;
            font-weight: 700;
        }
        .pos-opening-grid {
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
        .pos-count-card label { margin-bottom: 6px; }
        .pos-summary-cards {
            display: grid;
            gap: 14px;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
        }
        .pos-stat-card {
            padding: 16px 18px;
            border: 1px solid #dce6f2;
            border-radius: 8px;
            background: #fff;
        }
        .pos-stat-card .label {
            color: #6c7f95;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .pos-stat-card .value {
            margin-top: 10px;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.03em;
            color: #10233a;
        }
        .pos-mini-grid {
            display: grid;
            gap: 12px;
        }
        .pos-mini-card {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            align-items: flex-start;
            padding: 14px 16px;
            border: 1px solid #e4ebf3;
            border-radius: 18px;
            background: #fff;
        }
        .pos-mini-card strong { display: block; margin-bottom: 4px; }
        .pos-mini-meta { color: #6f7f92; font-size: 12px; }
        .pos-side-list {
            display: grid;
            gap: 12px;
        }
        .pos-side-item {
            padding: 14px 16px;
            border-radius: 18px;
            border: 1px solid #e5ebf3;
            background: linear-gradient(180deg, #fff 0%, #f8fbff 100%);
        }
        .pos-side-item strong { display: block; margin-bottom: 5px; }
        .pos-kpi-row {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        }
        .pos-kpi {
            padding: 14px 16px;
            border-radius: 18px;
            border: 1px solid #dbe5f1;
            background: #fff;
        }
        .pos-kpi .label { color: #6d8096; font-size: 12px; }
        .pos-kpi .value { margin-top: 8px; font-size: 20px; font-weight: 800; color: #13253b; }
        .pos-control-strip {
            display: grid;
            gap: 10px;
            grid-template-columns: repeat(auto-fit, minmax(155px, 1fr));
            margin-top: 12px;
        }
        .pos-control-chip {
            padding: 10px 12px;
            border: 1px solid #dbe6f2;
            border-radius: 8px;
            background: #fff;
        }
        .pos-control-chip .label {
            color: #65778d;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .pos-control-chip .value {
            margin-top: 6px;
            color: #10233a;
            font-size: 18px;
            font-weight: 900;
            letter-spacing: -.03em;
        }
        .pos-table-actions {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
            flex-wrap: wrap;
            white-space: nowrap;
        }
        .pos-table-actions .button {
            min-height: 34px;
            padding: 7px 10px;
            border-radius: 8px;
            font-size: 12px;
        }
        .pos-stale-alert {
            margin-top: 14px;
            padding: 14px;
            border: 1px solid #f1c27d;
            border-left: 5px solid #d97706;
            border-radius: 8px;
            background: #fff8ed;
        }
        .pos-stale-alert h4 {
            margin: 0 0 8px;
            color: #7c3f06;
            font-size: 15px;
        }
        .pos-stale-list {
            display: grid;
            gap: 8px;
            margin-top: 10px;
        }
        .pos-stale-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 12px;
            align-items: center;
            padding: 10px 12px;
            border-radius: 8px;
            background: #fff;
            border: 1px solid #f1dfc3;
        }
        .pos-shortcuts-grid {
            display: grid;
            gap: 14px;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            margin-top: 14px;
        }
        .pos-shortcut-card {
            display: block;
            padding: 16px 18px;
            border-radius: 8px;
            border: 1px solid #dbe6f2;
            background: linear-gradient(180deg, #fff 0%, #f8fbff 100%);
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.04);
        }
        .pos-shortcut-card strong {
            display: block;
            margin-bottom: 6px;
            color: #10233a;
        }
        .pos-shortcut-card .muted {
            color: #6f7f92;
        }
        @media (max-width: 1080px) {
            .pos-home-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 760px) {
            .pos-home-hero { padding: 20px 18px; }
            .pos-home-hero h2 { font-size: 22px; }
        }
    </style>

    <div class="pos-home">
        @include('pos.partials.backoffice-nav')

        <section class="pos-home-hero">
            <div>
                <h2>Caisse comptoir</h2>
                <div class="muted">{{ $currentSession ? $currentSession->session_number.' · '.$currentSession->cashAccount?->name.' · '.$currentSession->warehouse?->name : 'Choisis un poste disponible et ouvre ta session.' }}</div>
            </div>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                @unless ($isCashier)
                    <a href="{{ route('pos.report') }}" class="button button-secondary">Rapport journalier</a>
                    <a href="{{ route('pos.preparation.index') }}" class="button button-secondary">Board preparation</a>
                @endunless
                @if ($currentSession)
                    <a href="{{ route('pos.sales.create', ['session' => $currentSession->id]) }}" class="button button-primary">Nouvelle {{ strtolower($saleLabel) }} comptoir</a>
                    <a href="{{ route('pos.show', $currentSession) }}" class="button button-secondary">Voir la session</a>
                @endif
            </div>
        </section>

        <section class="pos-panel">
            <div class="pos-panel-head">
                <div>
                    <h3>Operations caisse</h3>
                    <div class="muted">{{ $isCashier ? 'Ton espace est limite a tes '.strtolower($salesLabel).' et tes sessions.' : 'Vue rapide des postes, clotures et ecarts de caisse.' }}</div>
                </div>
            </div>
            <div class="pos-panel-body">
                <div class="pos-control-strip">
                    <div class="pos-control-chip">
                        <div class="label">Sessions ouvertes</div>
                        <div class="value">{{ $sessionControl['open'] ?? 0 }}</div>
                    </div>
                    <div class="pos-control-chip">
                        <div class="label">Sessions du jour</div>
                        <div class="value">{{ $sessionControl['today'] ?? 0 }}</div>
                    </div>
                    <div class="pos-control-chip">
                        <div class="label">Cloturees jour</div>
                        <div class="value">{{ $sessionControl['closed_today'] ?? 0 }}</div>
                    </div>
                    <div class="pos-control-chip">
                        <div class="label">Attendu jour</div>
                        <div class="value">{{ number_format($sessionControl['expected_today'] ?? 0, 0, ',', ' ') }} XOF</div>
                    </div>
                    <div class="pos-control-chip">
                        <div class="label">Ecart jour</div>
                        <div class="value">{{ number_format($sessionControl['variance_today'] ?? 0, 0, ',', ' ') }} XOF</div>
                    </div>
                </div>
                <div class="pos-shortcuts-grid">
                    <a href="{{ $currentSession ? route('pos.sales.create', ['session' => $currentSession->id]) : route('pos.index') }}" class="pos-shortcut-card">
                        <strong>{{ $currentSession ? 'Nouvelle '.strtolower($saleLabel).' comptoir' : 'Ouvrir une session' }}</strong>
                        <div class="muted">{{ $currentSession ? 'Lancer rapidement un ticket dans la session ouverte.' : 'Demarrer la caisse avec le bon compte et le bon entrepot.' }}</div>
                    </a>
                    <a href="{{ $currentSession ? route('pos.show', $currentSession) : ($isCashier ? route('pos.index') : route('pos.sessions.index')) }}" class="pos-shortcut-card">
                        <strong>{{ $currentSession ? 'Voir la session active' : 'Voir les sessions' }}</strong>
                        <div class="muted">{{ $currentSession ? 'Suivre les tickets, retours et la cloture du poste courant.' : ($isCashier ? 'Aucune session ouverte sur ton compte pour le moment.' : 'Retrouver les ouvertures et clotures recentes de caisse.') }}</div>
                    </a>
                    @unless ($isCashier)
                        <a href="{{ route('pos.payments.index') }}" class="pos-shortcut-card">
                            <strong>Paiements POS</strong>
                            <div class="muted">Controler les encaissements comptoir et les modes de paiement utilises.</div>
                        </a>
                        <a href="{{ route('pos.report') }}" class="pos-shortcut-card">
                            <strong>Rapport journalier</strong>
                            <div class="muted">Comparer les {{ strtolower($salesLabel) }}, les retours, la caisse attendue et les ecarts du jour.</div>
                        </a>
                    @endunless
                </div>
                @if (($staleOpenSessions ?? collect())->isNotEmpty())
                    <div class="pos-stale-alert">
                        <h4>Sessions ouvertes a cloturer</h4>
                        <div class="muted">
                            Ces caisses sont ouvertes depuis plus de 24h. Il faut les controler et les cloturer pour garder les rapports propres.
                        </div>
                        <div class="pos-stale-list">
                            @foreach ($staleOpenSessions as $staleSession)
                                <div class="pos-stale-row">
                                    <div>
                                        <strong>{{ $staleSession->session_number }}</strong>
                                        <div class="muted">
                                            {{ $staleSession->cashAccount?->name }} · {{ $staleSession->opener?->name }} · ouverte {{ $staleSession->opened_at?->diffForHumans() }}
                                        </div>
                                        <div class="help">
                                            {{ number_format((float) ($staleSession->orders_count ?? 0), 0, ',', ' ') }} ticket(s) · {{ number_format((float) ($staleSession->payments_total ?? 0), 0, ',', ' ') }} XOF encaisses
                                        </div>
                                    </div>
                                    <div class="pos-table-actions">
                                        <a href="{{ route('pos.show', $staleSession) }}#cloture-session" class="button button-primary">Controler</a>
                                        <a href="{{ route('pos.count-sheet', $staleSession) }}" class="button button-secondary">Comptage</a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </section>

        @unless ($isCashier)
            <section class="pos-panel">
                <div class="pos-panel-head">
                    <div>
                        <h3>Postes de caisse</h3>
                        <div class="muted">{{ $openSessions->count() }} poste(s) ouvert(s) sur {{ $cashAccounts->count() }} caisse(s) active(s).</div>
                    </div>
                </div>
                <div class="pos-panel-body">
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Caisse</th>
                                    <th>Etat</th>
                                    <th>Operateur</th>
                                    <th>Depot</th>
                                    <th>Ouverture</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($cashAccounts as $account)
                                    @php
                                        $registerSession = $openSessions->firstWhere('cash_account_id', $account->id);
                                    @endphp
                                    <tr>
                                        <td><strong>{{ $account->name }}</strong></td>
                                        <td>
                                            <span class="badge {{ $registerSession ? 'badge-warning' : 'badge-success' }}">
                                                {{ $registerSession ? 'Ouverte' : 'Disponible' }}
                                            </span>
                                        </td>
                                        <td>{{ $registerSession?->opener?->name ?? '-' }}</td>
                                        <td>{{ $registerSession?->warehouse?->name ?? '-' }}</td>
                                        <td>{{ $registerSession?->opened_at?->format('d/m H:i') ?? '-' }}</td>
                                        <td>
                                            @if ($registerSession)
                                                <a href="{{ route('pos.show', $registerSession) }}" class="button button-secondary">Voir</a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        @endunless

        @if (! $currentSession)
            <div class="pos-home-grid">
                <section class="pos-panel">
                    <div class="pos-panel-head">
                        <div>
                            <h3>Ouvrir une session de caisse</h3>
                            <div class="muted">Prepare le fond de caisse, choisis le compte et l entrepot de vente, puis demarre la session POS.</div>
                        </div>
                        @include('partials.erp-status-badge', ['label' => 'Pret a demarrer', 'tone' => 'warning'])
                    </div>
                    <div class="pos-panel-body">
                        <form method="POST" action="{{ route('pos.open') }}" class="form-grid">
                            @csrf
                            <div>
                                <label for="cash_account_id">Compte de caisse</label>
                                <select id="cash_account_id" name="cash_account_id" required>
                                    <option value="">Choisir un compte</option>
                                    @foreach ($cashAccounts as $account)
                                        @php
                                            $occupiedSession = $openSessions->firstWhere('cash_account_id', $account->id);
                                        @endphp
                                        <option
                                            value="{{ $account->id }}"
                                            @selected(old('cash_account_id') == $account->id)
                                            @disabled($occupiedSession)
                                        >
                                            {{ $account->name }}{{ $occupiedSession ? ' · ouverte par '.$occupiedSession->opener?->name : ' · disponible' }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('cash_account_id')<div class="field-error">{{ $message }}</div>@enderror
                            </div>
                            <div>
                                <label for="warehouse_id">Entrepot de vente</label>
                                <select id="warehouse_id" name="warehouse_id" required>
                                    <option value="">Choisir un entrepot</option>
                                    @foreach ($warehouses as $warehouse)
                                        <option value="{{ $warehouse->id }}" @selected(old('warehouse_id') == $warehouse->id)>{{ $warehouse->name }}</option>
                                    @endforeach
                                </select>
                                @error('warehouse_id')<div class="field-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="full">
                                <div class="pos-kpi-row">
                                    <div class="pos-kpi">
                                        <div class="label">Comptes disponibles</div>
                                        <div class="value">{{ $cashAccounts->count() }}</div>
                                    </div>
                                    <div class="pos-kpi">
                                        <div class="label">Entrepots actifs</div>
                                        <div class="value">{{ $warehouses->count() }}</div>
                                    </div>
                                    <div class="pos-kpi">
                                        <div class="label">Coupures gerees</div>
                                        <div class="value">{{ count($cashDenominations) }}</div>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label for="opening_amount">Montant initial</label>
                                <input id="opening_amount" name="opening_amount" type="number" min="0" step="0.01" value="{{ old('opening_amount', '0') }}" required>
                                <div class="help">Tu peux le saisir directement ou le calculer automatiquement depuis les coupures.</div>
                                @error('opening_amount')<div class="field-error">{{ $message }}</div>@enderror
                            </div>
                            @php
                                $openingBreakdown = old('opening_cash_breakdown', []);
                                $openingBreakdownTotal = collect(array_keys($cashDenominations))->sum(fn ($denomination) => ((int) ($openingBreakdown[$denomination] ?? 0)) * (int) $denomination);
                            @endphp
                            <div class="full">
                                <label>Detail especes par coupures</label>
                                <div class="help">Renseigne les quantites de billets et pieces pour calculer automatiquement le fond de caisse.</div>
                                <div class="pos-opening-grid">
                                    @foreach ($cashDenominations as $denomination => $label)
                                        <div class="pos-count-card">
                                            <label for="opening_cash_breakdown_{{ $denomination }}">{{ $label }}</label>
                                            <div class="help" style="margin-bottom:8px;">{{ number_format((int) $denomination, 0, ',', ' ') }} XOF</div>
                                            <input
                                                id="opening_cash_breakdown_{{ $denomination }}"
                                                name="opening_cash_breakdown[{{ $denomination }}]"
                                                type="number"
                                                min="0"
                                                step="1"
                                                value="{{ old('opening_cash_breakdown.'.$denomination, 0) }}"
                                                data-opening-cash-count
                                                data-denomination="{{ $denomination }}"
                                            >
                                        </div>
                                    @endforeach
                                </div>
                                @error('opening_cash_breakdown')<div class="field-error">{{ $message }}</div>@enderror
                                <div class="help" style="margin-top:10px;">Total calcule : <strong id="opening-breakdown-total">{{ number_format($openingBreakdownTotal, 0, ',', ' ') }} XOF</strong></div>
                            </div>
                            <div class="full">
                                <label for="opening_notes">Notes d ouverture</label>
                                <textarea id="opening_notes" name="opening_notes">{{ old('opening_notes') }}</textarea>
                                @error('opening_notes')<div class="field-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="full actions">
                                <button type="submit" class="button button-primary">Ouvrir la caisse</button>
                            </div>
                        </form>
                    </div>
                </section>

                <aside class="pos-panel">
                    <div class="pos-panel-head">
                        <div>
                            <h3>Poste selectionne</h3>
                            <div class="muted">La caisse, le depot et le fond initial seront attaches a toutes les ventes de cette session.</div>
                        </div>
                    </div>
                    <div class="pos-panel-body pos-side-list">
                        <div class="pos-side-item"><strong>Caisse</strong><div class="pos-mini-meta">Une seule session ouverte par poste.</div></div>
                        <div class="pos-side-item"><strong>Operateur</strong><div class="pos-mini-meta">{{ auth()->user()?->name }}</div></div>
                        <div class="pos-side-item"><strong>Agence</strong><div class="pos-mini-meta">{{ $workspace->branch()?->name }}</div></div>
                    </div>
                </aside>
            </div>
        @else
            <div class="pos-summary-cards">
                <div class="pos-stat-card"><div class="label">Session ouverte</div><div class="value">{{ $currentSession->session_number }}</div></div>
                <div class="pos-stat-card"><div class="label">Brut {{ strtolower($productsLabel) }}</div><div class="value">{{ number_format($summary['gross_sales_total'], 0, ',', ' ') }}</div></div>
                <div class="pos-stat-card"><div class="label">Remises</div><div class="value">{{ number_format($summary['discount_total'], 0, ',', ' ') }}</div></div>
                <div class="pos-stat-card"><div class="label">{{ $salesLabel }} nettes</div><div class="value">{{ number_format($summary['sales_total'], 0, ',', ' ') }}</div></div>
                <div class="pos-stat-card"><div class="label">Retours</div><div class="value">{{ number_format($summary['return_total'], 0, ',', ' ') }}</div></div>
                <div class="pos-stat-card"><div class="label">Encaisse attendu</div><div class="value">{{ number_format($summary['expected_amount'], 0, ',', ' ') }}</div></div>
            </div>

            <div class="pos-home-grid">
                <section class="pos-panel">
                    <div class="pos-panel-head">
                        <div>
                            <h3>Session en cours</h3>
                        </div>
                        @include('partials.erp-status-badge', ['label' => 'Ouverte', 'tone' => 'warning'])
                    </div>
                    <div class="pos-panel-body">
                        <div class="pos-kpi-row" style="margin-bottom:16px;">
                            <div class="pos-kpi">
                                <div class="label">Compte</div>
                                <div class="value">{{ $currentSession->cashAccount->name }}</div>
                            </div>
                            <div class="pos-kpi">
                                <div class="label">Entrepot</div>
                                <div class="value">{{ $currentSession->warehouse->name }}</div>
                            </div>
                            <div class="pos-kpi">
                                <div class="label">Ouverte le</div>
                                <div class="value">{{ $currentSession->opened_at?->format('d/m H:i') }}</div>
                            </div>
                        </div>

                        <div class="pos-mini-grid">
                            @forelse ($recentInvoices as $invoice)
                                @php
                                    $refundedAmount = (float) $invoice->posReturns->sum('total');
                                @endphp
                                <div class="pos-mini-card">
                                    <div>
                                        <strong>{{ $invoice->invoice_number }}</strong>
                                <div class="pos-mini-meta">{{ $invoice->customer?->name ?? $customerLabel.' comptoir' }} · {{ number_format((float) $invoice->total, 0, ',', ' ') }} XOF</div>
                                        @if ((float) $invoice->discount_total > 0)
                                            <div class="help">Remise : {{ number_format((float) $invoice->discount_total, 0, ',', ' ') }} XOF</div>
                                        @endif
                                        @if ($refundedAmount > 0)
                                            <div class="help">Retour cumule : {{ number_format($refundedAmount, 0, ',', ' ') }} XOF</div>
                                        @endif
                                    </div>
                                    <div style="display:grid; gap:8px; justify-items:end;">
                                        <a href="{{ route('pos.receipt', $invoice) }}" class="button button-secondary">Ticket</a>
                                        <a href="{{ route('pos.receipt.thermal', $invoice) }}" class="button button-secondary">Thermique</a>
                                        <a href="{{ route('pos.returns.create', ['sale' => $invoice, 'session' => $currentSession->id]) }}" class="button button-secondary">Retour</a>
                                    </div>
                                </div>
                            @empty
                                <div class="empty-state" style="padding:26px 18px;">
                                    <h3 style="font-size:22px;">Aucun ticket pour cette session</h3>
                                    <div class="muted">Ouvre une nouvelle {{ strtolower($saleLabel) }} comptoir pour commencer a encaisser.</div>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </section>

                <aside class="pos-panel">
                    <div class="pos-panel-head">
                        <div>
                            <h3>Suivi de caisse</h3>
                        </div>
                    </div>
                    <div class="pos-panel-body" style="display:grid; gap:18px;">
                        <div class="summary-stack">
                            @foreach ($methodOptions as $method => $label)
                                <div class="summary-box">
                                    <strong>{{ $label }}</strong>
                                    <div class="value" style="font-size:24px;">{{ number_format($summary['expected_breakdown'][$method] ?? 0, 0, ',', ' ') }}</div>
                                </div>
                            @endforeach
                        </div>

                        <div class="pos-side-list">
                            @forelse ($recentReturns as $return)
                                <div class="pos-side-item">
                                    <strong>{{ $return->return_number }}</strong>
                                    <div class="pos-mini-meta">{{ $return->invoice?->invoice_number }} · {{ number_format((float) $return->total, 0, ',', ' ') }} XOF</div>
                                    <div class="help">{{ $return->payment?->cashAccount?->name ?? 'Aucun compte de remboursement' }}</div>
                                </div>
                            @empty
                                <div class="pos-side-item">
                                    <strong>Aucun retour traite</strong>
                                    <div class="pos-mini-meta">Les remboursements et echanges traites dans la session remonteront ici.</div>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </aside>
            </div>
        @endif

        <section class="pos-panel">
            <div class="pos-panel-head">
                <div>
                    <h3>Sessions recentes</h3>
                    <div class="muted">{{ $isCashier ? 'Tes ouvertures, clotures et impressions de controle.' : 'Historique des ouvertures, clotures, ecarts et impressions de controle.' }}</div>
                </div>
                @unless ($isCashier)
                    <a href="{{ route('pos.sessions.index') }}" class="button button-secondary">Pilotage sessions</a>
                @endunless
            </div>
            <div class="pos-panel-body">
                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>Session</th>
                            <th>Compte</th>
                            <th>Entrepot</th>
                            <th>Ouverte par</th>
                            <th>Ouverture</th>
                            <th>Fermeture</th>
                            <th class="right">Tickets</th>
                            <th class="right">Paiements</th>
                            <th class="right">Attendu</th>
                            <th class="right">Compte</th>
                            <th class="right">Ecart</th>
                            <th>Statut</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($recentSessions as $session)
                            @php
                                $variance = (float) ($session->variance_amount ?? 0);
                                $varianceTone = abs($variance) > 0.009 ? 'warning' : 'success';
                            @endphp
                            <tr>
                                <td><strong>{{ $session->session_number }}</strong></td>
                                <td>{{ $session->cashAccount?->name }}</td>
                                <td>{{ $session->warehouse?->name }}</td>
                                <td>{{ $session->opener?->name }}</td>
                                <td>{{ $session->opened_at?->format('d/m/Y H:i') }}</td>
                                <td>{{ $session->closed_at?->format('d/m/Y H:i') ?? '-' }}</td>
                                <td class="right">{{ number_format((float) ($session->orders_count ?? 0), 0, ',', ' ') }}</td>
                                <td class="right">{{ number_format((float) ($session->payments_total ?? 0), 0, ',', ' ') }} XOF</td>
                                <td class="right">{{ $session->expected_amount !== null ? number_format((float) $session->expected_amount, 0, ',', ' ').' XOF' : '-' }}</td>
                                <td class="right">{{ $session->closing_amount !== null ? number_format((float) $session->closing_amount, 0, ',', ' ').' XOF' : '-' }}</td>
                                <td class="right">
                                    @if ($session->status === 'closed')
                                        @include('partials.erp-status-badge', [
                                            'label' => number_format($variance, 0, ',', ' ').' XOF',
                                            'tone' => $varianceTone,
                                        ])
                                    @else
                                        <span class="muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @include('partials.erp-status-badge', [
                                        'label' => $session->status === 'open' ? 'Ouverte' : 'Cloturee',
                                        'tone' => $session->status === 'open' ? 'warning' : 'success',
                                    ])
                                </td>
                                <td>
                                    <div class="pos-table-actions">
                                        <a href="{{ route('pos.show', $session) }}" class="button button-secondary">Voir</a>
                                        <a href="{{ route('pos.count-sheet', $session) }}" class="button button-secondary">Comptage</a>
                                        <a href="{{ route('pos.session.print', $session) }}" class="button button-secondary">Imprimer</a>
                                        @if ($session->status === 'open')
                                            <a href="{{ route('pos.sales.create', ['session' => $session->id]) }}" class="button button-primary">{{ $saleLabel }}</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="13" class="muted">Aucune session enregistree.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const amountInput = document.getElementById('opening_amount');
        const totalOutput = document.getElementById('opening-breakdown-total');
        const breakdownInputs = Array.from(document.querySelectorAll('[data-opening-cash-count]'));

        if (! amountInput || ! totalOutput || breakdownInputs.length === 0) {
            return;
        }

        const formatter = new Intl.NumberFormat('fr-FR');

        const updateOpeningBreakdownTotal = () => {
            const total = breakdownInputs.reduce((sum, input) => {
                const count = parseInt(input.value || '0', 10) || 0;
                const denomination = parseInt(input.dataset.denomination || '0', 10) || 0;

                return sum + (count * denomination);
            }, 0);

            totalOutput.textContent = formatter.format(total) + ' XOF';

            if (total > 0) {
                amountInput.value = total.toFixed(2);
            }
        };

        breakdownInputs.forEach((input) => input.addEventListener('input', updateOpeningBreakdownTotal));
        updateOpeningBreakdownTotal();
    });
    </script>
@endsection
