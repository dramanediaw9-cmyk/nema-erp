@extends('layouts.app')

@section('title', 'Rapport POS - Nema ERP')
@section('page-title', 'Rapport journalier POS')

@section('content')
    <style>
        .pos-report-product {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }
        .pos-report-thumb {
            width: 54px;
            height: 54px;
            flex: 0 0 54px;
            border-radius: 16px;
            object-fit: cover;
            border: 1px solid #d6dfeb;
            background: #fff;
            box-shadow: 0 10px 18px rgba(15, 23, 42, 0.07);
        }
        .pos-report-fallback {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 54px;
            height: 54px;
            flex: 0 0 54px;
            border-radius: 16px;
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            color: #17304f;
            font-weight: 900;
            border: 1px solid #d6dfeb;
            box-shadow: 0 10px 18px rgba(15, 23, 42, 0.07);
        }
    </style>

    <div class="page-head">
        <div>
            <h2 style="margin:0;">Rapport journalier POS</h2>
            <div class="muted">Synthese caisse, ventes comptoir, remises, remboursements et detail par mode de paiement.</div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="{{ route('pos.index') }}" class="button button-secondary">Retour point de vente</a>
        </div>
    </div>

    <section class="card" style="margin-bottom:20px;">
        <form method="GET" action="{{ route('pos.report') }}" class="form-grid" style="grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); align-items:end;">
            <div>
                <label for="date">Date</label>
                <input id="date" name="date" type="date" value="{{ $filters['date'] }}">
            </div>
            <div>
                <label for="warehouse_id">Entrepot</label>
                <select id="warehouse_id" name="warehouse_id">
                    <option value="">Tous les entrepots</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected((int) ($filters['warehouse_id'] ?? 0) === $warehouse->id)>{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="cash_account_id">Compte</label>
                <select id="cash_account_id" name="cash_account_id">
                    <option value="">Tous les comptes</option>
                    @foreach ($cashAccounts as $cashAccount)
                        <option value="{{ $cashAccount->id }}" @selected((int) ($filters['cash_account_id'] ?? 0) === $cashAccount->id)>{{ $cashAccount->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="actions" style="justify-content:flex-start; margin-top:0;">
                <button type="submit" class="button button-primary">Afficher</button>
                <a href="{{ route('pos.report') }}" class="button button-secondary">Reinitialiser</a>
            </div>
        </form>
    </section>

    <div class="grid stats-grid" style="margin-bottom:20px;">
        <div class="card"><div class="muted">Tickets</div><div class="stat-value">{{ $report['sales_count'] }}</div></div>
        <div class="card"><div class="muted">Brut articles</div><div class="stat-value">{{ number_format($report['gross_sales'], 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Remises</div><div class="stat-value">{{ number_format($report['discounts_total'], 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Ventes nettes</div><div class="stat-value">{{ number_format($report['sales_after_discount'], 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Retours traites</div><div class="stat-value">{{ number_format($report['returns_total'], 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Net apres retours</div><div class="stat-value">{{ number_format($report['net_sales'], 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Flux net caisse</div><div class="stat-value">{{ number_format($report['net_cash'], 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Ticket moyen</div><div class="stat-value">{{ number_format($report['average_ticket'], 0, ',', ' ') }}</div></div>
    </div>

    <div class="split" style="margin-bottom:20px; align-items:start;">
        <section class="card">
            <h2 style="margin-top:0;">Detail par mode de paiement</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Mode</th>
                        <th>Encaisse</th>
                        <th>Rembourse</th>
                        <th>Net</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($report['method_breakdown'] as $method => $row)
                        <tr>
                            <td>{{ $row['label'] }}</td>
                            <td>{{ number_format($row['incoming'], 0, ',', ' ') }} XOF</td>
                            <td>{{ number_format($row['outgoing'], 0, ',', ' ') }} XOF</td>
                            <td>{{ number_format($row['net'], 0, ',', ' ') }} XOF</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <section class="card" style="margin-bottom:20px;">
        <div class="page-head" style="margin-bottom:14px;">
            <div>
                <h2 style="margin:0;">Controle fin de journee</h2>
                <div class="muted">Ecarts de cloture et flux mobile money encore a rapprocher pour la date selectionnee.</div>
            </div>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                @allowed('payments.view')
                    <a href="{{ route('payments.index', ['reconciliation_status' => 'unreconciled']) }}" class="button button-secondary">Paiements a rapprocher</a>
                @endallowed
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Canal</th>
                    <th>Attendu cloture</th>
                    <th>Ecart cloture</th>
                    <th>Non rapproche</th>
                    <th>References</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($report['settlement_watch']['methods'] as $row)
                    <tr>
                        <td>
                            <div style="font-weight:800; color:#16293f;">{{ $row['label'] }}</div>
                            <div class="muted" style="font-size:13px; margin-top:6px;">
                                @if ($row['method'] === 'cash')
                                    {{ $row['sessions_with_variance'] }} cloture(s) avec ecart.
                                @else
                                    {{ $row['payment_count'] }} flux enregistres.
                                @endif
                            </div>
                        </td>
                        <td>{{ number_format((float) $row['expected_total'], 0, ',', ' ') }} XOF</td>
                        <td>
                            {{ number_format((float) $row['variance'], 0, ',', ' ') }} XOF
                            @if (abs((float) $row['variance']) > 0.009)
                                <div class="help" style="margin-top:6px; color:#b42318;">Verifier la cloture</div>
                            @endif
                        </td>
                        <td>
                            {{ number_format(abs((float) $row['unreconciled_amount']), 0, ',', ' ') }} XOF
                            @if ($row['unreconciled_count'] > 0)
                                <div class="help" style="margin-top:6px;">{{ $row['unreconciled_count'] }} operation(s)</div>
                            @endif
                        </td>
                        <td>
                            {{ $row['missing_reference_count'] }}
                            @if ($row['missing_reference_count'] > 0)
                                <div class="help" style="margin-top:6px; color:#9a5b00;">Reference manquante</div>
                            @endif
                        </td>
                        <td>
                            @if ($row['is_mobile'] && ($row['unreconciled_count'] > 0 || $row['missing_reference_count'] > 0))
                                @allowed('payments.view')
                                    <a href="{{ route('payments.index', ['method' => $row['method'], 'reconciliation_status' => 'unreconciled']) }}" class="button button-secondary">Rapprocher</a>
                                @else
                                    <span class="badge badge-warning">A suivre</span>
                                @endallowed
                            @elseif ($row['method'] === 'cash' && abs((float) $row['variance']) > 0.009)
                                <span class="badge badge-warning">Controle caisse</span>
                            @else
                                <span class="badge badge-success">OK</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="muted">Aucun canal cash ou mobile money a suivre pour cette date.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="split" style="margin-bottom:20px; align-items:start;">
        <section class="card">
            <h2 style="margin-top:0;">Sessions concernees</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Session</th>
                        <th>Entrepot</th>
                        <th>Compte</th>
                        <th>Ouverture</th>
                        <th>Statut</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($report['sessions'] as $session)
                        <tr>
                            <td><a href="{{ route('pos.show', $session) }}">{{ $session->session_number }}</a></td>
                            <td>{{ $session->warehouse?->name }}</td>
                            <td>{{ $session->cashAccount?->name }}</td>
                            <td>{{ number_format((float) $session->opening_amount, 0, ',', ' ') }} XOF</td>
                            <td>
                                <span class="badge {{ $session->status === 'closed' ? 'badge-success' : 'badge-warning' }}">
                                    {{ $session->status === 'closed' ? 'Cloturee' : 'Ouverte' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="muted">Aucune session POS ouverte, cloturee ou active pour cette date.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="split" style="align-items:start;">
        <section class="card">
            <h2 style="margin-top:0;">Top produits vendus</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Produit</th>
                        <th>Scan</th>
                        <th>Quantite</th>
                        <th>Montant</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($report['top_products'] as $product)
                        @php($imageUrl = $product->image_url)
                        <tr>
                            <td>
                                <div class="pos-report-product">
                                    @if ($imageUrl)
                                        <img src="{{ $imageUrl }}" alt="{{ $product->name }}" class="pos-report-thumb">
                                    @else
                                        <span class="pos-report-fallback">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($product->name, 0, 2)) }}</span>
                                    @endif
                                    <div>
                                        <div style="font-weight:800; color:#16293f;">{{ $product->name }}</div>
                                        <div class="muted" style="font-size:13px;">{{ $product->sku }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $product->scan_code }}</td>
                            <td>{{ number_format((float) $product->qty, 3, ',', ' ') }}</td>
                            <td>{{ number_format((float) $product->amount, 0, ',', ' ') }} XOF</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="muted">Aucune vente POS sur cette date.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <h2 style="margin-top:0;">Top retours</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Produit</th>
                        <th>Quantite</th>
                        <th>Montant</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($report['top_returns'] as $product)
                        @php($imageUrl = $product->image_url)
                        <tr>
                            <td>
                                <div class="pos-report-product">
                                    @if ($imageUrl)
                                        <img src="{{ $imageUrl }}" alt="{{ $product->name }}" class="pos-report-thumb">
                                    @else
                                        <span class="pos-report-fallback">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($product->name, 0, 2)) }}</span>
                                    @endif
                                    <div>
                                        <div style="font-weight:800; color:#16293f;">{{ $product->name }}</div>
                                        <div class="muted" style="font-size:13px;">{{ $product->sku }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>{{ number_format((float) $product->qty, 3, ',', ' ') }}</td>
                            <td>{{ number_format((float) $product->amount, 0, ',', ' ') }} XOF</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="muted">Aucun retour enregistre sur cette date.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection

