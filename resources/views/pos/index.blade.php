@extends('layouts.app')

@section('title', 'Point de vente - Nema ERP')
@section('page-title', 'Point de vente')

@section('content')
    <style>
        .pos-home {
            display: grid;
            gap: 22px;
        }
        .pos-home-hero {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            flex-wrap: wrap;
            align-items: flex-start;
            padding: 24px 26px;
            border-radius: 24px;
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
            border-radius: 24px;
            background: linear-gradient(180deg, #ffffff 0%, #f9fbfd 100%);
            box-shadow: 0 18px 35px rgba(17, 24, 39, 0.05);
        }
        .pos-panel-head {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            align-items: flex-start;
            padding: 22px 22px 14px;
            border-bottom: 1px solid #e7edf4;
        }
        .pos-panel-head h3 { margin: 0; font-size: 18px; letter-spacing: -0.02em; }
        .pos-panel-body { padding: 20px 22px 22px; }
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
            border-radius: 18px;
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
        .pos-shortcuts-grid {
            display: grid;
            gap: 14px;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
        }
        .pos-shortcut-card {
            display: block;
            padding: 16px 18px;
            border-radius: 20px;
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
                <div class="muted">Ouverture de caisse, tickets rapides, remboursements, suivi journalier et cloture d equipe dans une interface plus proche d un vrai point de vente.</div>
                <div class="pos-mode-strip">
                    <span class="pos-mode-chip">Vente detail</span>
                    <span class="pos-mode-chip">Scan / recherche</span>
                    <span class="pos-mode-chip">Remises</span>
                    <span class="pos-mode-chip">Retours</span>
                    <span class="pos-mode-chip">Cloture caisse</span>
                </div>
            </div>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <a href="{{ route('pos.report') }}" class="button button-secondary">Rapport journalier</a>
                <a href="{{ route('pos.preparation.index') }}" class="button button-secondary">Board preparation</a>
                @if ($currentSession)
                    <a href="{{ route('pos.sales.create', ['session' => $currentSession->id]) }}" class="button button-primary">Nouvelle vente comptoir</a>
                    <a href="{{ route('pos.show', $currentSession) }}" class="button button-secondary">Voir la session</a>
                @endif
            </div>
        </section>

        <section class="pos-panel">
            <div class="pos-panel-head">
                <div>
                    <h3>Operations caisse</h3>
                    <div class="muted">Acces direct aux actions frequentes sans quitter l accueil POS.</div>
                </div>
            </div>
            <div class="pos-panel-body">
                <div class="pos-shortcuts-grid">
                    <a href="{{ $currentSession ? route('pos.sales.create', ['session' => $currentSession->id]) : route('pos.index') }}" class="pos-shortcut-card">
                        <strong>{{ $currentSession ? 'Nouvelle vente comptoir' : 'Ouvrir une session' }}</strong>
                        <div class="muted">{{ $currentSession ? 'Lancer rapidement un ticket dans la session ouverte.' : 'Demarrer la caisse avec le bon compte et le bon entrepot.' }}</div>
                    </a>
                    <a href="{{ $currentSession ? route('pos.show', $currentSession) : route('pos.sessions.index') }}" class="pos-shortcut-card">
                        <strong>{{ $currentSession ? 'Voir la session active' : 'Voir les sessions' }}</strong>
                        <div class="muted">{{ $currentSession ? 'Suivre les tickets, retours et la cloture du poste courant.' : 'Retrouver les ouvertures et clotures recentes de caisse.' }}</div>
                    </a>
                    <a href="{{ route('pos.payments.index') }}" class="pos-shortcut-card">
                        <strong>Paiements POS</strong>
                        <div class="muted">Controler les encaissements comptoir et les modes de paiement utilises.</div>
                    </a>
                    <a href="{{ route('pos.report') }}" class="pos-shortcut-card">
                        <strong>Rapport journalier</strong>
                        <div class="muted">Comparer les ventes, les retours, la caisse attendue et les ecarts du jour.</div>
                    </a>
                </div>
            </div>
        </section>

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
                                        <option value="{{ $account->id }}" @selected(old('cash_account_id') == $account->id)>{{ $account->name }}</option>
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
                                        <div class="label">Caissier connecte</div>
                                        <div class="value">{{ auth()->user()?->name }}</div>
                                    </div>
                                    <div class="pos-kpi">
                                        <div class="label">Heure d ouverture</div>
                                        <div class="value">{{ now()->format('H:i') }}</div>
                                    </div>
                                    <div class="pos-kpi">
                                        <div class="label">Statut apres ouverture</div>
                                        <div class="value">OPEN</div>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label for="opening_amount">Montant initial</label>
                                <input id="opening_amount" name="opening_amount" type="number" min="0" step="0.01" value="{{ old('opening_amount', '0') }}" required>
                                <div class="help">Tu peux le saisir directement ou le calculer automatiquement depuis les coupures.</div>
                                @error('opening_amount')<div class="field-error">{{ $message }}</div>@enderror
                            </div>
                            @php($openingBreakdown = old('opening_cash_breakdown', []))
                            @php($openingBreakdownTotal = collect(array_keys($cashDenominations))->sum(fn ($denomination) => ((int) ($openingBreakdown[$denomination] ?? 0)) * (int) $denomination))
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
                            <h3>Avant de commencer</h3>
                            <div class="muted">Petit rappel operateur pour lancer la caisse dans de bonnes conditions.</div>
                        </div>
                    </div>
                    <div class="pos-panel-body pos-side-list">
                        <div class="pos-side-item">
                            <strong>1. Verifie le compte de caisse</strong>
                            <div class="pos-mini-meta">Choisis le bon compte physique ou mobile money pour les encaissements du poste.</div>
                        </div>
                        <div class="pos-side-item">
                            <strong>2. Verifie l entrepot de vente</strong>
                            <div class="pos-mini-meta">Les tickets comptoir sortiront le stock de cet entrepot pendant toute la session.</div>
                        </div>
                        <div class="pos-side-item">
                            <strong>3. Compte les especes</strong>
                            <div class="pos-mini-meta">Le detail par coupure alimente automatiquement le montant initial et facilite la cloture.</div>
                        </div>
                        <div class="pos-side-item">
                            <strong>4. Lance la session</strong>
                            <div class="pos-mini-meta">Tu pourras ensuite vendre, rembourser, imprimer les tickets et cloturer la caisse.</div>
                        </div>
                    </div>
                </aside>
            </div>
        @else
            <div class="pos-summary-cards">
                <div class="pos-stat-card"><div class="label">Session ouverte</div><div class="value">{{ $currentSession->session_number }}</div></div>
                <div class="pos-stat-card"><div class="label">Statut</div><div class="value">{{ $currentSession->status === 'open' ? 'OPEN' : 'CLOSED' }}</div></div>
                <div class="pos-stat-card"><div class="label">Montant initial</div><div class="value">{{ number_format((float) $currentSession->opening_amount, 0, ',', ' ') }}</div></div>
                <div class="pos-stat-card"><div class="label">Caissier</div><div class="value">{{ $currentSession->opener?->name }}</div></div>
                <div class="pos-stat-card"><div class="label">Ouverture</div><div class="value">{{ $currentSession->opened_at?->format('d/m H:i') }}</div></div>
                <div class="pos-stat-card"><div class="label">Brut articles</div><div class="value">{{ number_format($summary['gross_sales_total'], 0, ',', ' ') }}</div></div>
                <div class="pos-stat-card"><div class="label">Remises</div><div class="value">{{ number_format($summary['discount_total'], 0, ',', ' ') }}</div></div>
                <div class="pos-stat-card"><div class="label">Ventes nettes</div><div class="value">{{ number_format($summary['sales_total'], 0, ',', ' ') }}</div></div>
                <div class="pos-stat-card"><div class="label">Retours</div><div class="value">{{ number_format($summary['return_total'], 0, ',', ' ') }}</div></div>
                <div class="pos-stat-card"><div class="label">Encaisse attendu</div><div class="value">{{ number_format($summary['expected_amount'], 0, ',', ' ') }}</div></div>
            </div>

            <div class="pos-home-grid">
                <section class="pos-panel">
                    <div class="pos-panel-head">
                        <div>
                            <h3>Session en cours</h3>
                            <div class="muted">Vue rapide de la caisse active, des tickets du jour et des retours deja traites.</div>
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
                            <div class="pos-kpi">
                                <div class="label">Statut</div>
                                <div class="value">{{ $currentSession->status === 'open' ? 'OPEN' : 'CLOSED' }}</div>
                            </div>
                        </div>

                        <div class="pos-mini-grid">
                            @forelse ($recentInvoices as $invoice)
                                @php($refundedAmount = (float) $invoice->posReturns->sum('total'))
                                <div class="pos-mini-card">
                                    <div>
                                        <strong>{{ $invoice->invoice_number }}</strong>
                                        <div class="pos-mini-meta">{{ $invoice->customer?->name }} · {{ number_format((float) $invoice->total, 0, ',', ' ') }} XOF</div>
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
                                    <div class="muted">Ouvre une nouvelle vente comptoir pour commencer a encaisser.</div>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </section>

                <aside class="pos-panel">
                    <div class="pos-panel-head">
                        <div>
                            <h3>Suivi de caisse</h3>
                            <div class="muted">Lecture rapide par mode de paiement et derniers retours.</div>
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
                    <div class="muted">Historique des ouvertures et clotures de caisse sur cette agence.</div>
                </div>
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
                            <th>Statut</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($recentSessions as $session)
                            <tr>
                                <td>{{ $session->session_number }}</td>
                                <td>{{ $session->cashAccount?->name }}</td>
                                <td>{{ $session->warehouse?->name }}</td>
                                <td>{{ $session->opener?->name }}</td>
                                <td>{{ $session->opened_at?->format('d/m/Y H:i') }}</td>
                                <td>
                                    @include('partials.erp-status-badge', [
                                        'label' => $session->status === 'open' ? 'OPEN' : 'CLOSED',
                                        'tone' => $session->status === 'open' ? 'warning' : 'success',
                                    ])
                                </td>
                                <td><a href="{{ route('pos.show', $session) }}" class="button button-secondary">Voir</a></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="muted">Aucune session enregistree.</td>
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

