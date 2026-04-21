@extends('layouts.app')

@section('title', 'Paiements - Nema ERP')
@section('page-title', 'Paiements')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Historique des paiements</h2>
            <div class="muted">Les encaissements clients, reglements fournisseurs et remboursements POS sont centralises ici.</div>
            @if ($scopeBranch)
                <div class="help" style="margin-top:8px;">Perimetre agence actif : <strong>{{ $scopeBranch->name }}</strong></div>
            @endif
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="{{ route('payments.export', request()->query()) }}" class="button button-secondary">Exporter CSV</a>
            @allowed('payments.validate')
                <a href="{{ route('payments.create', ['type' => 'customer_receipt']) }}" class="button button-primary">Nouvel encaissement</a>
                <a href="{{ route('payments.create', ['type' => 'supplier_payment']) }}" class="button button-secondary">Nouveau reglement</a>
            @endallowed
        </div>
    </div>

    <section class="card" style="margin-bottom:18px;">
        <form method="GET" action="{{ route('payments.index') }}" class="form-grid" style="align-items:end; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));">
            <div style="grid-column:span 2; min-width:220px;">
                <label for="search">Recherche</label>
                <input type="text" id="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Numero, reference, tiers, compte...">
            </div>
            <div>
                <label for="date_from">Date debut</label>
                <input type="date" id="date_from" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
            </div>
            <div>
                <label for="date_to">Date fin</label>
                <input type="date" id="date_to" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
            </div>
            <div>
                <label for="payment_type">Type</label>
                <select id="payment_type" name="payment_type">
                    <option value="">Tous les flux</option>
                    <option value="customer_receipt" @selected(($filters['payment_type'] ?? null) === 'customer_receipt')>Encaissements clients</option>
                    <option value="supplier_payment" @selected(($filters['payment_type'] ?? null) === 'supplier_payment')>Reglements fournisseurs</option>
                    <option value="pos_refund" @selected(($filters['payment_type'] ?? null) === 'pos_refund')>Remboursements POS</option>
                </select>
            </div>
            <div>
                <label for="method">Mode</label>
                <select id="method" name="method">
                    <option value="">Tous les modes</option>
                    @foreach ($methodOptions as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['method'] ?? null) === $value)>{{ $label }}</option>
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
            <div>
                <label for="reconciliation_status">Rapprochement</label>
                <select id="reconciliation_status" name="reconciliation_status">
                    <option value="">Tous les statuts</option>
                    <option value="unreconciled" @selected(($filters['reconciliation_status'] ?? null) === 'unreconciled')>A rapprocher</option>
                    <option value="reconciled" @selected(($filters['reconciliation_status'] ?? null) === 'reconciled')>Rapproches</option>
                </select>
            </div>
            <div class="checkbox-card">
                <label style="display:flex; gap:10px; align-items:center; margin:0;">
                    <input type="checkbox" name="missing_reference" value="1" @checked($filters['missing_reference'] ?? false)>
                    Mobile money sans reference
                </label>
                <div class="help" style="margin-top:8px;">Utile pour controler les transactions Wave, Orange Money et Moov sans identifiant externe.</div>
            </div>
            <div class="actions" style="margin-top:0; justify-content:flex-start; align-self:end;">
                <button type="submit" class="button button-primary">Filtrer</button>
                <a href="{{ route('payments.index') }}" class="button button-secondary">Reinitialiser</a>
            </div>
        </form>
    </section>

    <section class="card" style="margin-bottom:18px;">
        <div style="display:flex; justify-content:space-between; gap:16px; align-items:flex-start; flex-wrap:wrap;">
            <div>
                <h3 style="margin:0;">Pilotage mobile money</h3>
                <div class="muted" style="margin-top:8px;">Suivi terrain des flux Wave, Orange Money et Moov pour fermer la journee sans ecart.</div>
            </div>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <a href="{{ route('payments.index', array_filter(array_merge(request()->query(), ['reconciliation_status' => 'unreconciled']))) }}" class="button button-secondary">Voir a rapprocher</a>
                <a href="{{ route('payments.index', array_filter(array_merge(request()->query(), ['missing_reference' => 1]))) }}" class="button button-secondary">Voir refs manquantes</a>
            </div>
        </div>

        <div class="grid stats-grid" style="margin-top:18px;">
            <div class="card">
                <div class="muted">Flux mobile money</div>
                <div class="stat-value">{{ number_format((float) $mobileInsights['amount'], 0, ',', ' ') }} XOF</div>
                <div class="help" style="margin-top:6px;">{{ $mobileInsights['count'] }} mouvement(s)</div>
            </div>
            <div class="card">
                <div class="muted">Encaissements</div>
                <div class="stat-value">{{ number_format((float) $mobileInsights['incoming_amount'], 0, ',', ' ') }} XOF</div>
                <div class="help" style="margin-top:6px;">Sorties {{ number_format((float) $mobileInsights['outgoing_amount'], 0, ',', ' ') }} XOF</div>
            </div>
            <div class="card">
                <div class="muted">A rapprocher</div>
                <div class="stat-value">{{ number_format((float) $mobileInsights['unreconciled_amount'], 0, ',', ' ') }} XOF</div>
                <div class="help" style="margin-top:6px;">{{ $mobileInsights['unreconciled_count'] }} mouvement(s) encore ouverts</div>
            </div>
            <div class="card">
                <div class="muted">Refs manquantes</div>
                <div class="stat-value">{{ $mobileInsights['missing_reference_count'] }}</div>
                <div class="help" style="margin-top:6px;">Transactions mobiles a verifier</div>
            </div>
        </div>

        <div class="grid" style="margin-top:18px; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));">
            @foreach ($mobileInsights['provider_totals'] as $method => $provider)
                <a href="{{ route('payments.index', array_filter(array_merge(request()->query(), ['method' => $method]))) }}" class="summary-box" style="text-decoration:none; color:inherit;">
                    <strong>{{ $provider['label'] }}</strong>
                    <div style="margin-top:8px; font-weight:800; font-size:24px;">{{ number_format((float) $provider['amount'], 0, ',', ' ') }} XOF</div>
                    <div class="help" style="margin-top:8px;">{{ $provider['count'] }} mouvement(s)</div>
                </a>
            @endforeach
        </div>

        @if (collect($mobileInsights['accounts'])->isNotEmpty())
            <div class="table-wrap" style="margin-top:18px;">
                <table>
                    <thead>
                    <tr>
                        <th>Compte mobile</th>
                        <th>Agence</th>
                        <th>Flux</th>
                        <th>A rapprocher</th>
                        <th>Refs manquantes</th>
                        <th>Dernier mouvement</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($mobileInsights['accounts'] as $account)
                        <tr>
                            <td>
                                <div style="font-weight:700;">{{ $account['cash_account_name'] }}</div>
                                @if ($account['account_number'])
                                    <div class="muted" style="font-size:13px;">{{ $account['account_number'] }}</div>
                                @endif
                            </td>
                            <td>{{ $account['branch_name'] ?: 'Toutes agences' }}</td>
                            <td>
                                {{ number_format((float) $account['total_amount'], 0, ',', ' ') }} XOF
                                <div class="help" style="margin-top:6px;">{{ $account['payments_count'] }} mouvement(s)</div>
                            </td>
                            <td>
                                {{ number_format((float) $account['unreconciled_amount'], 0, ',', ' ') }} XOF
                                <div class="help" style="margin-top:6px;">{{ $account['unreconciled_count'] }} ouvert(s)</div>
                            </td>
                            <td>{{ $account['missing_reference_count'] }}</td>
                            <td>{{ $account['latest_payment_date']?->format('d/m/Y') ?: 'N/A' }}</td>
                            <td>
                                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                    <a href="{{ route('payments.index', array_filter(array_merge(request()->query(), ['cash_account_id' => $account['cash_account_id']]))) }}">Voir</a>
                                    @allowed('reconciliations.manage')
                                        @if ($account['cash_account_id'])
                                            <a href="{{ route('treasury-reconciliations.create', ['cash_account_id' => $account['cash_account_id'], 'statement_date' => now()->toDateString()]) }}">Rapprocher</a>
                                        @endif
                                    @endallowed
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="card table-wrap">
        <table>
            <thead>
            <tr>
                <th>Numero</th>
                <th>Date</th>
                <th>Type</th>
                <th>Tiers</th>
                <th>Document</th>
                <th>Compte</th>
                <th>Mode</th>
                <th>Montant</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($payments as $payment)
                @php
                    $document = optional($payment->allocations->first())->allocatable;
                    $documentLabel = 'Non relie';
                    $documentUrl = null;

                    if ($document instanceof \App\Modules\Sales\Models\SalesInvoice) {
                        $documentLabel = $document->invoice_number;
                        $documentUrl = route('sales.show', $document);
                    } elseif ($document instanceof \App\Modules\Purchases\Models\PurchaseBill) {
                        $documentLabel = $document->bill_number;
                        $documentUrl = route('purchases.show', $document);
                    }

                    $paymentTypeLabel = match ($payment->payment_type) {
                        'supplier_payment' => 'Reglement fournisseur',
                        'pos_refund' => 'Remboursement POS',
                        default => 'Encaissement client',
                    };
                @endphp
                <tr>
                    <td>
                        <a href="{{ route('payments.show', $payment) }}" style="font-weight:700;">{{ $payment->payment_number }}</a>
                        @if ($payment->reference)
                            <div class="muted" style="font-size:13px;">Ref. {{ $payment->reference }}</div>
                        @endif
                        @if (in_array($payment->method, ['wave', 'orange_money', 'moov_money', 'mobile_money'], true) && blank(trim((string) $payment->reference)))
                            <div style="margin-top:6px;"><span class="badge badge-danger">Reference manquante</span></div>
                        @endif
                    </td>
                    <td>{{ $payment->payment_date?->format('d/m/Y') }}</td>
                    <td>{{ $paymentTypeLabel }}</td>
                    <td>
                        <div>{{ $payment->partner?->name }}</div>
                        @if ($payment->partner?->code)
                            <div class="muted" style="font-size:13px;">{{ $payment->partner->code }}</div>
                        @endif
                    </td>
                    <td>
                        @if ($documentUrl)
                            <a href="{{ $documentUrl }}">{{ $documentLabel }}</a>
                        @else
                            <span class="muted">{{ $documentLabel }}</span>
                        @endif
                    </td>
                    <td>{{ $payment->cashAccount?->name }}</td>
                    <td>
                        {{ $methodOptions[$payment->method] ?? str($payment->method)->replace('_', ' ')->title() }}
                        @if ($payment->reconciliationItem)
                            <div style="margin-top:6px;"><span class="badge badge-success">Rapproche</span></div>
                        @elseif (in_array($payment->method, ['wave', 'orange_money', 'moov_money', 'mobile_money'], true))
                            <div style="margin-top:6px;"><span class="badge badge-warning">A rapprocher</span></div>
                        @endif
                    </td>
                    <td>{{ number_format((float) $payment->amount, 0, ',', ' ') }} XOF</td>
                    <td>
                        <div style="display:flex; gap:8px; flex-wrap:wrap;">
                            <a href="{{ route('payments.show', $payment) }}">Voir</a>
                            @allowed('reconciliations.manage')
                                @if (! $payment->reconciliationItem && in_array($payment->method, ['wave', 'orange_money', 'moov_money', 'mobile_money'], true))
                                    <a href="{{ route('treasury-reconciliations.create', ['cash_account_id' => $payment->cash_account_id, 'statement_date' => $payment->payment_date?->format('Y-m-d')]) }}">Rapprocher</a>
                                @endif
                            @endallowed
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="muted">Aucun paiement ne correspond aux filtres selectionnes.</td>
                </tr>
            @endforelse
            </tbody>
        </table>

        @if (method_exists($payments, 'links'))
            <div style="margin-top:18px;">{{ $payments->links() }}</div>
        @endif
    </section>
@endsection
