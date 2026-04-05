@extends('layouts.app')

@section('title', 'Session POS - Nema ERP')
@section('page-title', 'Session de caisse')

@section('content')
    @php($openingCashBreakdown = is_array($session->opening_cash_breakdown) ? $session->opening_cash_breakdown : [])
    @php($openingHasBreakdown = collect(array_keys($cashDenominations))->sum(fn ($denomination) => (int) ($openingCashBreakdown[$denomination] ?? 0)) > 0)
    @php($closingCashBreakdown = is_array($session->closing_cash_breakdown) ? $session->closing_cash_breakdown : [])
    @php($closingHasBreakdown = collect(array_keys($cashDenominations))->sum(fn ($denomination) => (int) ($closingCashBreakdown[$denomination] ?? 0)) > 0)

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
        @media (max-width: 1080px) {
            .pos-session-grid { grid-template-columns: 1fr; }
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
        <section class="pos-session-hero">
            <div>
                <h2>{{ $session->session_number }}</h2>
                <div class="muted">{{ $session->branch?->name }} · {{ $session->warehouse?->name }} · {{ $session->cashAccount?->name }}</div>
                <div class="pos-status-chip">{{ $session->status === 'open' ? 'Session ouverte' : 'Session cloturee' }}</div>
                <div class="pos-view-tabs">
                    @if ($session->status === 'open')
                        <a href="{{ route('pos.sales.create', ['session' => $session->id]) }}" class="pos-view-tab">Caisse</a>
                    @else
                        <a href="{{ route('pos.index') }}" class="pos-view-tab">Caisse</a>
                    @endif
                    <a href="#tickets-session" class="pos-view-tab is-active">Commandes</a>
                </div>
            </div>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <a href="{{ route('pos.index') }}" class="button button-secondary">Retour POS</a>
                <a href="{{ route('pos.report', ['date' => $session->opened_at?->toDateString(), 'warehouse_id' => $session->warehouse_id, 'cash_account_id' => $session->cash_account_id]) }}" class="button button-secondary">Rapport du jour</a>
                <a href="{{ route('pos.count-sheet', $session) }}" class="button button-secondary">Comptage imprimable</a>
                @if ($session->status === 'open')
                    <a href="{{ route('pos.sales.create', ['session' => $session->id]) }}" class="button button-primary">Nouvelle vente comptoir</a>
                @endif
            </div>
        </section>

        <div class="pos-stat-grid">
            <div class="pos-stat-card"><div class="label">Montant initial</div><div class="value">{{ number_format((float) $session->opening_amount, 0, ',', ' ') }}</div></div>
            <div class="pos-stat-card"><div class="label">Brut articles</div><div class="value">{{ number_format($summary['gross_sales_total'], 0, ',', ' ') }}</div></div>
            <div class="pos-stat-card"><div class="label">Remises</div><div class="value">{{ number_format($summary['discount_total'], 0, ',', ' ') }}</div></div>
            <div class="pos-stat-card"><div class="label">Ventes nettes</div><div class="value">{{ number_format($summary['sales_total'], 0, ',', ' ') }}</div></div>
            <div class="pos-stat-card"><div class="label">Retours</div><div class="value">{{ number_format($summary['return_total'], 0, ',', ' ') }}</div></div>
            <div class="pos-stat-card"><div class="label">Flux net caisse</div><div class="value">{{ number_format($summary['net_cash'], 0, ',', ' ') }}</div></div>
            <div class="pos-stat-card"><div class="label">Ecart</div><div class="value">{{ number_format((float) ($session->variance_amount ?? 0), 0, ',', ' ') }}</div></div>
        </div>

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
                        <div class="pos-kpi"><div class="label">Tickets</div><div class="value">{{ $summary['sales_count'] }}</div></div>
                        <div class="pos-kpi"><div class="label">Retours</div><div class="value">{{ $summary['return_count'] }}</div></div>
                    </div>

                    <ul class="pos-detail-list">
                        <li><span>Articles vendus</span><span>{{ $summary['items_count'] }}</span></li>
                        <li><span>Articles retournes</span><span>{{ $summary['returned_items_count'] }}</span></li>
                        <li><span>Remises accordees</span><span>{{ number_format($summary['discount_total'], 0, ',', ' ') }} XOF</span></li>
                        <li><span>Fond de caisse detaille</span><span>{{ $openingHasBreakdown ? 'Oui' : 'Non' }}</span></li>
                        @if ($session->closed_at)
                            <li><span>Cloturee le</span><span>{{ $session->closed_at?->format('d/m/Y H:i') }}</span></li>
                            <li><span>Compte physique</span><span>{{ number_format((float) $session->closing_amount, 0, ',', ' ') }} XOF</span></li>
                            <li><span>Comptage especes detaille</span><span>{{ $closingHasBreakdown ? 'Oui' : 'Non' }}</span></li>
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
                        @php($closingBreakdown = old('closing_cash_breakdown', []))
                        @php($closingBreakdownTotal = collect(array_keys($cashDenominations))->sum(fn ($denomination) => ((int) ($closingBreakdown[$denomination] ?? 0)) * (int) $denomination))
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
                                @php($isCashMethod = $method === 'cash')
                                @php(
                                    $defaultCountedValue = $isCashMethod && $closingBreakdownTotal > 0
                                        ? number_format($closingBreakdownTotal, 2, '.', '')
                                        : old('counted_methods.'.$method, number_format($summary['expected_breakdown'][$method] ?? 0, 2, '.', ''))
                                )
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

        <section id="tickets-session" class="pos-session-panel">
            <div class="pos-session-head">
                <div>
                    <h3>Commandes en attente</h3>
                    <div class="muted">Retrouve les commandes brouillon mises en attente sur cette caisse et reprends-les quand tu veux.</div>
                </div>
                <span class="badge badge-warning">{{ $pendingDrafts->count() }} en attente</span>
            </div>
            <div class="pos-session-body">
                <div class="pos-history-list is-compact">
                    @forelse ($pendingDrafts as $draft)
                        <div class="pos-history-item is-compact">
                            <div class="pos-history-main">
                                <div class="pos-history-title-row">
                                    <strong>{{ $draft->label }}</strong>
                                    <div class="pos-amount">{{ number_format((float) $draft->total, 0, ',', ' ') }} XOF</div>
                                </div>
                                <div class="pos-history-meta">{{ $draft->customer?->name ?? 'Client comptoir' }} · {{ $draft->sale_date?->format('d/m/Y') }} · {{ $methodOptions[$draft->method] ?? $draft->method }}</div>
                                <div class="pos-history-tags">
                                    <span class="pos-mini-chip">{{ $draft->items_count }} article(s)</span>
                                    <span class="pos-mini-chip">Maj {{ $draft->last_activity_at?->format('d/m H:i') ?? '-' }}</span>
                                    <span class="pos-mini-chip">{{ $draft->updater?->name ?? $draft->creator?->name ?? 'Operateur' }}</span>
                                </div>
                            </div>
                            <div class="pos-inline-actions">
                                <a href="{{ route('pos.sales.create', ['session' => $session->id, 'draft' => $draft->id]) }}" class="button button-primary">Reprendre</a>
                            </div>
                        </div>
                    @empty
                        <div class="empty-state" style="padding:28px 18px;">
                            <h3 style="font-size:22px;">Aucune commande en attente</h3>
                            <div class="muted">Utilise le bouton "Mettre en attente" dans la caisse pour retrouver ici les commandes non encore encaissees.</div>
                        </div>
                    @endforelse
                </div>
            </div>
        </section>

        <div class="pos-session-grid">
            <section class="pos-session-panel">
                <div class="pos-session-head">
                    <div>
                        <h3>Tickets de la session</h3>
                        <div class="muted">Acces rapide aux tickets, versions thermiques et retours.</div>
                    </div>
                </div>
                <div class="pos-session-body">
                    <div class="pos-history-list is-compact">
                        @forelse ($recentInvoices as $invoice)
                            @php($refundedAmount = (float) $invoice->posReturns->sum('total'))
                            <div class="pos-history-item is-compact">
                                <div class="pos-history-main">
                                    <div class="pos-history-title-row">
                                        <strong>{{ $invoice->invoice_number }}</strong>
                                        <div class="pos-amount">{{ number_format((float) $invoice->total, 0, ',', ' ') }} XOF</div>
                                    </div>
                                    <div class="pos-history-meta">{{ $invoice->invoice_date?->format('d/m/Y') }} · {{ $invoice->customer?->name }}</div>
                                    <div class="pos-history-tags">
                                        <span class="pos-mini-chip">Ticket</span>
                                        @if ((float) $invoice->discount_total > 0)
                                            <span class="pos-mini-chip">Remise {{ number_format((float) $invoice->discount_total, 0, ',', ' ') }} XOF</span>
                                        @endif
                                        @if ($refundedAmount > 0)
                                            <span class="pos-mini-chip">Retour {{ number_format($refundedAmount, 0, ',', ' ') }} XOF</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="pos-inline-actions">
                                    <a href="{{ route('pos.receipt', $invoice) }}" class="button button-secondary">Ticket</a>
                                    <a href="{{ route('pos.receipt.thermal', $invoice) }}" class="button button-secondary">Thermique</a>
                                    @if ($session->status === 'open')
                                        <a href="{{ route('pos.returns.create', ['sale' => $invoice, 'session' => $session->id]) }}" class="button button-secondary">Retour</a>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="empty-state" style="padding:28px 18px;">
                                <h3 style="font-size:22px;">Aucun ticket sur cette session</h3>
                                <div class="muted">Les ventes comptoir apparaitront ici au fur et a mesure.</div>
                            </div>
                        @endforelse
                    </div>
                </div>
            </section>

            <section class="pos-session-panel">
                <div class="pos-session-head">
                    <div>
                        <h3>Retours de la session</h3>
                        <div class="muted">Remboursements et echanges traites sur cette caisse.</div>
                    </div>
                </div>
                <div class="pos-session-body">
                    <div class="pos-history-list is-compact">
                        @forelse ($recentReturns as $return)
                            <div class="pos-history-item is-compact">
                                <div class="pos-history-main">
                                    <div class="pos-history-title-row">
                                        <strong>{{ $return->return_number }}</strong>
                                        <div class="pos-amount">{{ number_format((float) $return->total, 0, ',', ' ') }} XOF</div>
                                    </div>
                                    <div class="pos-history-meta">{{ $return->invoice?->invoice_number }} · {{ $return->return_date?->format('d/m/Y') }}</div>
                                    <div class="pos-history-tags">
                                        <span class="pos-mini-chip">Remboursement</span>
                                        <span class="pos-mini-chip">Echange {{ $return->exchangeInvoice?->invoice_number ?? 'Aucun' }}</span>
                                    </div>
                                </div>
                                <div class="pos-history-meta">{{ $return->payment?->cashAccount?->name ?? 'Sans remboursement' }}</div>
                            </div>
                        @empty
                            <div class="empty-state" style="padding:28px 18px;">
                                <h3 style="font-size:22px;">Aucun retour sur cette session</h3>
                                <div class="muted">Les annulations et echanges de caisse apparaitront ici.</div>
                            </div>
                        @endforelse
                    </div>
                </div>
            </section>
        </div>
    </div>

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


