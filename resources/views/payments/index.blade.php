@extends('layouts.app')

@section('title', 'Paiements - Nema ERP')
@section('page-title', 'Paiements')

@section('content')
    @php
        $currentView = ($filters['view'] ?? 'list') === 'kanban' ? 'kanban' : 'list';
        $headerActions = [
            ['label' => 'Exporter CSV', 'url' => route('payments.export', request()->query()), 'style' => 'secondary'],
        ];

        if (auth()->user()?->hasPermission('payments.validate')) {
            $headerActions[] = ['label' => 'Nouvel encaissement', 'url' => route('payments.create', ['type' => 'customer_receipt']), 'style' => 'primary'];
            $headerActions[] = ['label' => 'Nouveau reglement', 'url' => route('payments.create', ['type' => 'supplier_payment']), 'style' => 'secondary'];
            $headerActions[] = ['label' => 'Nouveau versement', 'url' => route('payments.create', ['type' => 'internal_transfer']), 'style' => 'secondary'];
        }

        $headerChips = [];

        if ($scopeBranch) {
            $headerChips[] = ['label' => 'Agence : '.$scopeBranch->name, 'tone' => 'success'];
        }
    @endphp

    @include('partials.erp-page-head', [
        'eyebrow' => 'Facturation et tresorerie',
        'title' => 'Historique des paiements',
        'description' => 'Les encaissements clients, reglements fournisseurs, remboursements POS et versements internes sont centralises ici.',
        'actions' => $headerActions,
        'chips' => $headerChips,
    ])

    <section class="card" style="margin-bottom:18px;">
        <form method="GET" action="{{ route('payments.index') }}" class="form-grid" style="align-items:end; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));">
            <input type="hidden" name="view" value="{{ $currentView }}">
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
                    <option value="internal_transfer" @selected(($filters['payment_type'] ?? null) === 'internal_transfer')>Versements internes</option>
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
            <div>
                <label for="aging_state">Suivi terrain</label>
                <select id="aging_state" name="aging_state">
                    <option value="">Tous les flux</option>
                    <option value="mobile_age_3_plus" @selected(($filters['aging_state'] ?? null) === 'mobile_age_3_plus')>Mobile 3+ jours</option>
                    <option value="mobile_age_7_plus" @selected(($filters['aging_state'] ?? null) === 'mobile_age_7_plus')>Mobile 7+ jours</option>
                    <option value="deposit_bank_age_2_plus" @selected(($filters['aging_state'] ?? null) === 'deposit_bank_age_2_plus')>Versement banque 2+ jours</option>
                    <option value="deposit_bank_age_5_plus" @selected(($filters['aging_state'] ?? null) === 'deposit_bank_age_5_plus')>Versement banque 5+ jours</option>
                </select>
            </div>
            <div class="checkbox-card">
                <label style="display:flex; gap:10px; align-items:center; margin:0;">
                    <input type="checkbox" name="missing_reference" value="1" @checked($filters['missing_reference'] ?? false)>
                    Mobile money sans reference
                </label>
                <div class="help" style="margin-top:8px;">Utile pour controler les transactions Wave, Orange Money et Moov sans identifiant externe.</div>
            </div>
            <div class="checkbox-card">
                <label style="display:flex; gap:10px; align-items:center; margin:0;">
                    <input type="checkbox" name="deposit_missing_reference" value="1" @checked($filters['deposit_missing_reference'] ?? false)>
                    Depot sans bordereau
                </label>
                <div class="help" style="margin-top:8px;">Remonte les versements internes sans reference de depot ni piece jointe exploitable.</div>
            </div>
            <div class="checkbox-card">
                <label style="display:flex; gap:10px; align-items:center; margin:0;">
                    <input type="checkbox" name="deposit_documented" value="1" @checked($filters['deposit_documented'] ?? false)>
                    Depot documente
                </label>
                <div class="help" style="margin-top:8px;">Isole les versements internes deja accompagnes d une reference ou d un justificatif.</div>
            </div>
            <div class="actions" style="margin-top:0; justify-content:flex-start; align-self:end;">
                <button type="submit" class="button button-primary">Filtrer</button>
                <a href="{{ route('payments.index', ['view' => $currentView]) }}" class="button button-secondary">Reinitialiser</a>
            </div>
        </form>
    </section>

    <section class="card" style="margin-bottom:18px;">
        <div style="display:flex; justify-content:space-between; gap:16px; align-items:flex-start; flex-wrap:wrap;">
            <div>
                <h3 style="margin:0;">Pilotage versements agence</h3>
                <div class="muted" style="margin-top:8px;">Suivi des depots internes envoyes vers banque ou wallet central en attente de releve.</div>
            </div>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <a href="{{ route('payments.index', array_filter(array_merge(request()->query(), ['payment_type' => 'internal_transfer', 'reconciliation_status' => 'unreconciled']))) }}" class="button button-secondary">Voir a confirmer</a>
                <a href="{{ route('payments.index', array_filter(array_merge(request()->query(), ['deposit_documented' => 1]))) }}" class="button button-secondary">Voir documentes</a>
                <a href="{{ route('payments.index', array_filter(array_merge(request()->query(), ['deposit_missing_reference' => 1]))) }}" class="button button-secondary">Voir bordereaux manquants</a>
                <a href="{{ route('payments.index', array_filter(array_merge(request()->query(), ['aging_state' => 'deposit_bank_age_2_plus']))) }}" class="button button-secondary">Voir 2+ jours</a>
                <a href="{{ route('payments.index', array_filter(array_merge(request()->query(), ['aging_state' => 'deposit_bank_age_5_plus']))) }}" class="button button-secondary">Voir 5+ jours</a>
            </div>
        </div>

        <div class="grid stats-grid" style="margin-top:18px;">
            <div class="card">
                <div class="muted">A confirmer</div>
                <div class="stat-value">{{ number_format((float) $internalTransferInsights['unreconciled_amount'], 0, ',', ' ') }} XOF</div>
                <div class="help" style="margin-top:6px;">
                    {{ $internalTransferInsights['unreconciled_count'] }} versement(s) encore ouverts
                    @if ($internalTransferInsights['oldest_unreconciled_payment_date'])
                        · Plus ancien depuis le {{ $internalTransferInsights['oldest_unreconciled_payment_date']->format('d/m/Y') }}
                    @endif
                </div>
            </div>
            <div class="card">
                <div class="muted">Versements 2+ jours</div>
                <div class="stat-value">{{ number_format((float) $internalTransferInsights['stale_2_amount'], 0, ',', ' ') }} XOF</div>
                <div class="help" style="margin-top:6px;">{{ $internalTransferInsights['stale_2_count'] }} depot(s) en attente</div>
            </div>
            <div class="card">
                <div class="muted">Versements 5+ jours</div>
                <div class="stat-value">{{ number_format((float) $internalTransferInsights['stale_5_amount'], 0, ',', ' ') }} XOF</div>
                <div class="help" style="margin-top:6px;">{{ $internalTransferInsights['stale_5_count'] }} depot(s) critiques</div>
            </div>
            <div class="card">
                <div class="muted">Documentes</div>
                <div class="stat-value">{{ number_format((float) $internalTransferInsights['documented_amount'], 0, ',', ' ') }} XOF</div>
                <div class="help" style="margin-top:6px;">
                    {{ $internalTransferInsights['documented_count'] }} versement(s) prets pour rapprochement
                    @if (($internalTransferInsights['documented_stale_count'] ?? 0) > 0)
                        · {{ $internalTransferInsights['documented_stale_count'] }} depuis 2+ jours
                    @endif
                </div>
            </div>
            <div class="card">
                <div class="muted">Sans bordereau</div>
                <div class="stat-value">{{ $internalTransferInsights['missing_reference_count'] }}</div>
                <div class="help" style="margin-top:6px;">Depot(s) sans reference ni justificatif avant rapprochement bancaire</div>
            </div>
            <div class="card">
                <div class="muted">Comptes cibles</div>
                <div class="stat-value">{{ $internalTransferInsights['account_count'] }}</div>
                <div class="help" style="margin-top:6px;">{{ $internalTransferInsights['count'] }} mouvement(s) de versement vers comptes bancaires ou wallets centraux</div>
            </div>
        </div>
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
                <a href="{{ route('payments.index', array_filter(array_merge(request()->query(), ['aging_state' => 'mobile_age_3_plus']))) }}" class="button button-secondary">Voir 3+ jours</a>
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
                <div class="help" style="margin-top:6px;">
                    {{ $mobileInsights['unreconciled_count'] }} mouvement(s) encore ouverts
                    @if ($mobileInsights['oldest_unreconciled_payment_date'])
                        · Plus ancien depuis le {{ $mobileInsights['oldest_unreconciled_payment_date']->format('d/m/Y') }}
                    @endif
                </div>
            </div>
            <div class="card">
                <div class="muted">Refs manquantes</div>
                <div class="stat-value">{{ $mobileInsights['missing_reference_count'] }}</div>
                <div class="help" style="margin-top:6px;">
                    Transactions mobiles a verifier
                    @if (($mobileInsights['stale_unreconciled_count'] ?? 0) > 0)
                        · {{ $mobileInsights['stale_unreconciled_count'] }} flux ouverts depuis 3+ jours
                    @endif
                </div>
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
                        <th>Plus ancien ouvert</th>
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
                            <td>
                                {{ $account['oldest_unreconciled_payment_date']?->format('d/m/Y') ?: 'N/A' }}
                                @if (($account['stale_unreconciled_count'] ?? 0) > 0)
                                    <div class="help" style="margin-top:6px;">{{ $account['stale_unreconciled_count'] }} flux en souffrance</div>
                                @endif
                            </td>
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

    <div style="display:flex; justify-content:space-between; gap:16px; align-items:center; flex-wrap:wrap; margin-bottom:18px;">
        <div class="muted">
            <strong>{{ number_format($payments->count(), 0, ',', ' ') }}</strong>
            paiement(s) visibles sur cette page.
            @if ($currentView === 'kanban')
                Lecture par cartes pour reperer vite les depots a confirmer et les flux a rapprocher.
            @else
                Lecture tabulaire detaillee pour le controle quotidien.
            @endif
        </div>
        @include('partials.erp-view-switcher', [
            'view' => $currentView,
            'label' => 'Vue paiements',
            'listUrl' => route('payments.index', array_merge(request()->query(), ['view' => 'list'])),
            'kanbanUrl' => route('payments.index', array_merge(request()->query(), ['view' => 'kanban'])),
        ])
    </div>

    @if ($currentView === 'kanban')
        <div class="erp-kanban-grid">
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
                    } elseif ($document instanceof \App\Modules\Treasury\Models\Payment) {
                        $documentLabel = $document->payment_number;
                        $documentUrl = route('payments.show', $document);
                    }

                    $isReconcilableDeposit = $payment->payment_type === 'internal_transfer'
                        && $payment->direction === 'in'
                        && in_array($payment->cashAccount?->type, ['bank', 'mobile_money'], true);
                    $hasReference = filled(trim((string) $payment->reference));
                    $hasAttachment = (int) ($payment->attachments_count ?? 0) > 0;
                    $isMobileFlow = in_array($payment->method, ['wave', 'orange_money', 'moov_money', 'mobile_money'], true);
                    $missingReference = $isMobileFlow && ! $hasReference && ! $isReconcilableDeposit;
                    $missingDepositSlip = $isReconcilableDeposit && ! $hasReference && ! $hasAttachment;
                    $depositWithAttachment = $isReconcilableDeposit && ! $hasReference && $hasAttachment;
                    $depositWithReference = $isReconcilableDeposit && $hasReference;
                    $readyForReconciliation = $isReconcilableDeposit && ($hasReference || $hasAttachment);
                    $needsReconciliation = ! $payment->reconciliationItem && ($isReconcilableDeposit || $isMobileFlow);
                    $flowLabel = $payment->reconciliationItem
                        ? 'Rapproche'
                        : ($readyForReconciliation
                            ? 'Pret a rapprocher'
                            : ($isReconcilableDeposit
                                ? 'Depot a confirmer'
                                : ($isMobileFlow ? 'A rapprocher' : 'Suivi standard')));
                    $flowTone = $payment->reconciliationItem
                        ? 'success'
                        : ($readyForReconciliation
                            ? 'success'
                            : ($isReconcilableDeposit || $isMobileFlow ? 'warning' : 'muted'));
                    $cardTone = $missingReference || $missingDepositSlip
                        ? 'danger'
                        : ($readyForReconciliation ? 'success' : ($needsReconciliation ? 'warning' : 'muted'));
                    $counterparty = $payment->partner?->name ?? ($payment->payment_type === 'internal_transfer' ? 'Transfert interne' : 'Sans tiers');
                @endphp
                <section class="card erp-kanban-card erp-kanban-card--{{ $cardTone }}">
                    <div class="erp-kanban-head">
                        <div class="erp-kanban-copy">
                            <div class="erp-kanban-code">{{ $payment->payment_number }}</div>
                            <h3>{{ $counterparty }}</h3>
                            <p class="muted">{{ $payment->payment_date?->format('d/m/Y') ?? 'Date non renseignee' }} · {{ $payment->cashAccount?->name ?? 'Compte non renseigne' }}</p>
                        </div>
                        <div style="display:grid; gap:8px; justify-items:end;">
                            @include('partials.erp-status-badge', ['label' => $flowLabel, 'tone' => $flowTone])
                            @if ($missingReference)
                                @include('partials.erp-status-badge', ['label' => 'Reference manquante', 'tone' => 'danger'])
                            @elseif ($missingDepositSlip)
                                @include('partials.erp-status-badge', ['label' => 'Bordereau manquant', 'tone' => 'danger'])
                            @elseif ($depositWithAttachment)
                                @include('partials.erp-status-badge', ['label' => 'Bordereau joint', 'tone' => 'success'])
                            @elseif ($depositWithReference)
                                @include('partials.erp-status-badge', ['label' => 'Reference depot', 'tone' => 'success'])
                            @endif
                        </div>
                    </div>
                    <div class="erp-kanban-stats">
                        <div class="erp-kanban-stat">
                            <div class="label">Montant</div>
                            <div class="value">{{ number_format((float) $payment->amount, 0, ',', ' ') }}</div>
                        </div>
                        <div class="erp-kanban-stat">
                            <div class="label">Mode</div>
                            <div class="value">{{ $methodOptions[$payment->method] ?? str($payment->method)->replace('_', ' ')->title() }}</div>
                        </div>
                        <div class="erp-kanban-stat">
                            <div class="label">Type</div>
                            <div class="value">
                                {{ match ($payment->payment_type) {
                                    'supplier_payment' => 'Reglement fournisseur',
                                    'pos_refund' => 'Remboursement POS',
                                    'internal_transfer' => $payment->direction === 'in' ? 'Reception de versement' : 'Versement interne',
                                    default => 'Encaissement client',
                                } }}
                            </div>
                        </div>
                    </div>
                    <div class="erp-kanban-copy">
                        @if ($payment->partner?->code)
                            <p class="muted">Code tiers : {{ $payment->partner->code }}</p>
                        @endif
                        @if ($documentUrl)
                            <p class="muted">Document : <a href="{{ $documentUrl }}">{{ $documentLabel }}</a></p>
                        @else
                            <p class="muted">Document : {{ $documentLabel }}</p>
                        @endif
                        @if ($payment->reference)
                            <p class="muted">Reference : {{ $payment->reference }}</p>
                        @elseif ($isMobileFlow && ! $isReconcilableDeposit)
                            <p class="muted">Reference operateur a completer avant rapprochement.</p>
                        @elseif ($isReconcilableDeposit && ! $hasAttachment)
                            <p class="muted">Justificatif depot attendu avant rapprochement bancaire.</p>
                        @elseif ($isReconcilableDeposit)
                            <p class="muted">Depot documente, pret pour le releve externe.</p>
                        @elseif ($payment->notes)
                            <p class="muted">{{ $payment->notes }}</p>
                        @endif
                    </div>
                    <div class="erp-kanban-actions">
                        <a href="{{ route('payments.show', $payment) }}" class="button button-secondary">Voir le paiement</a>
                        @allowed('reconciliations.manage')
                            @if ($needsReconciliation)
                                <a href="{{ route('treasury-reconciliations.create', ['cash_account_id' => $payment->cash_account_id, 'statement_date' => $payment->payment_date?->format('Y-m-d')]) }}" class="button button-primary">Rapprocher</a>
                            @endif
                        @endallowed
                    </div>
                </section>
            @empty
                <section class="card empty-state" style="grid-column:1 / -1;">
                    <h3>Aucun paiement ne correspond aux filtres selectionnes.</h3>
                    <p class="muted">Ajuste la recherche, le mode de paiement, le compte ou le suivi terrain.</p>
                </section>
            @endforelse
        </div>
    @else
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
                        } elseif ($document instanceof \App\Modules\Treasury\Models\Payment) {
                            $documentLabel = $document->payment_number;
                            $documentUrl = route('payments.show', $document);
                        }

                        $paymentTypeLabel = match ($payment->payment_type) {
                            'supplier_payment' => 'Reglement fournisseur',
                            'pos_refund' => 'Remboursement POS',
                            'internal_transfer' => $payment->direction === 'in' ? 'Reception de versement' : 'Versement interne',
                            default => 'Encaissement client',
                        };
                    @endphp
                    <tr>
                        <td>
                            <a href="{{ route('payments.show', $payment) }}" style="font-weight:700;">{{ $payment->payment_number }}</a>
                            @if ($payment->reference)
                                <div class="muted" style="font-size:13px;">Ref. {{ $payment->reference }}</div>
                            @endif
                            @if (in_array($payment->method, ['wave', 'orange_money', 'moov_money', 'mobile_money'], true)
                                && blank(trim((string) $payment->reference))
                                && ! ($payment->payment_type === 'internal_transfer' && $payment->direction === 'in' && in_array($payment->cashAccount?->type, ['bank', 'mobile_money'], true)))
                                <div style="margin-top:6px;">
                                    @include('partials.erp-status-badge', ['label' => 'Reference manquante', 'tone' => 'danger'])
                                </div>
                            @endif
                            @if ($payment->payment_type === 'internal_transfer'
                                && $payment->direction === 'in'
                                && in_array($payment->cashAccount?->type, ['bank', 'mobile_money'], true)
                                && blank(trim((string) $payment->reference))
                                && (($payment->attachments_count ?? 0) === 0))
                                <div style="margin-top:6px;">
                                    @include('partials.erp-status-badge', ['label' => 'Bordereau manquant', 'tone' => 'danger'])
                                </div>
                            @endif
                            @if ($payment->payment_type === 'internal_transfer'
                                && $payment->direction === 'in'
                                && in_array($payment->cashAccount?->type, ['bank', 'mobile_money'], true)
                                && blank(trim((string) $payment->reference))
                                && (($payment->attachments_count ?? 0) > 0))
                                <div style="margin-top:6px;">
                                    @include('partials.erp-status-badge', ['label' => 'Bordereau joint', 'tone' => 'success'])
                                </div>
                            @endif
                            @if ($payment->payment_type === 'internal_transfer'
                                && $payment->direction === 'in'
                                && in_array($payment->cashAccount?->type, ['bank', 'mobile_money'], true)
                                && filled(trim((string) $payment->reference)))
                                <div style="margin-top:6px;">
                                    @include('partials.erp-status-badge', ['label' => 'Reference depot', 'tone' => 'success'])
                                </div>
                            @endif
                        </td>
                        <td>{{ $payment->payment_date?->format('d/m/Y') }}</td>
                        <td>{{ $paymentTypeLabel }}</td>
                        <td>
                            <div>{{ $payment->partner?->name ?? ($payment->payment_type === 'internal_transfer' ? 'Transfert interne' : '') }}</div>
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
                                <div style="margin-top:6px;">
                                    @include('partials.erp-status-badge', ['label' => 'Rapproche', 'tone' => 'success'])
                                </div>
                            @elseif ($payment->payment_type === 'internal_transfer'
                                && $payment->direction === 'in'
                                && in_array($payment->cashAccount?->type, ['bank', 'mobile_money'], true)
                                && (filled(trim((string) $payment->reference)) || (($payment->attachments_count ?? 0) > 0)))
                                <div style="margin-top:6px;">
                                    @include('partials.erp-status-badge', ['label' => 'Pret a rapprocher', 'tone' => 'success'])
                                </div>
                            @elseif ($payment->payment_type === 'internal_transfer' && $payment->direction === 'in' && in_array($payment->cashAccount?->type, ['bank', 'mobile_money'], true))
                                <div style="margin-top:6px;">
                                    @include('partials.erp-status-badge', ['label' => 'Depot a confirmer', 'tone' => 'warning'])
                                </div>
                            @elseif (in_array($payment->method, ['wave', 'orange_money', 'moov_money', 'mobile_money'], true))
                                <div style="margin-top:6px;">
                                    @include('partials.erp-status-badge', ['label' => 'A rapprocher', 'tone' => 'warning'])
                                </div>
                            @endif
                        </td>
                        <td>{{ number_format((float) $payment->amount, 0, ',', ' ') }} XOF</td>
                        <td>
                            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                <a href="{{ route('payments.show', $payment) }}">Voir</a>
                                @allowed('reconciliations.manage')
                                    @if (! $payment->reconciliationItem && ($payment->payment_type === 'internal_transfer' && $payment->direction === 'in' && in_array($payment->cashAccount?->type, ['bank', 'mobile_money'], true)))
                                        <a href="{{ route('treasury-reconciliations.create', ['cash_account_id' => $payment->cash_account_id, 'statement_date' => $payment->payment_date?->format('Y-m-d')]) }}">Rapprocher</a>
                                    @elseif (! $payment->reconciliationItem && in_array($payment->method, ['wave', 'orange_money', 'moov_money', 'mobile_money'], true))
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
        </section>
    @endif

    @if (method_exists($payments, 'links'))
        <div style="margin-top:18px;">{{ $payments->links() }}</div>
    @endif
@endsection
