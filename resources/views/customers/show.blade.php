@extends('layouts.app')

@section('title', 'Detail client - Nema ERP')
@section('page-title', 'Client '.$customer->code)

@section('content')
    @php
        $headerActions = [
            ['label' => 'Retour aux clients', 'url' => route('customers.index', ['search' => $customer->code]), 'style' => 'secondary'],
        ];
        $openBalance = (float) ($stats['open_balance'] ?? 0);
        $portfolioState = $openBalance > 0 ? 'open' : 'clear';

        if (auth()->user()?->hasPermission('sales.manage')) {
            $headerActions[] = ['label' => 'Nouvelle vente', 'url' => route('sales.create'), 'style' => 'primary'];
        }

        if (auth()->user()?->hasPermission('payments.manage')) {
            $headerActions[] = ['label' => 'Nouvel encaissement', 'url' => route('payments.create', ['type' => 'customer_receipt']), 'style' => 'secondary'];
        }

        if (auth()->user()?->hasPermission('collections.view')) {
            $headerActions[] = ['label' => 'Portefeuille recouvrement', 'url' => route('collections.index', ['customer_id' => $customer->id]), 'style' => 'secondary'];
        }
    @endphp

    @include('partials.erp-page-head', [
        'eyebrow' => 'Client',
        'title' => $customer->name,
        'description' => $customer->code.' · '.($customer->city ?: 'Ville non renseignee').' · '.($customer->phone ?: 'Telephone non renseigne'),
        'actions' => $headerActions,
        'chips' => [
            ['type' => 'activity', 'value' => $customer->is_active ? 'active' : 'inactive'],
            ['type' => 'portfolio', 'value' => $portfolioState],
        ],
    ])

    <section class="card" style="margin-bottom:20px;">
        <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
            <a href="#sales-documents" class="card" style="padding:16px; display:block;">
                <strong>Factures clients</strong>
                <div class="muted" style="margin-top:8px;">Voir les ventes et les soldes ouverts de ce client.</div>
            </a>
            <a href="#customer-payments" class="card" style="padding:16px; display:block;">
                <strong>Paiements recus</strong>
                <div class="muted" style="margin-top:8px;">Acceder a l historique des encaissements lies.</div>
            </a>
            @allowed('collections.view')
                <a href="{{ route('collections.index', ['customer_id' => $customer->id]) }}" class="card" style="padding:16px; display:block;">
                    <strong>Recouvrement</strong>
                    <div class="muted" style="margin-top:8px;">Voir les echeances, promesses et relances de ce client.</div>
                </a>
            @endallowed
            <a href="#accounting-effects" class="card" style="padding:16px; display:block;">
                <strong>Ecritures comptables liees</strong>
                <div class="muted" style="margin-top:8px;">Suivre la trace comptable du tiers.</div>
            </a>
        </div>
    </section>

    <div class="grid stats-grid" style="margin-bottom:20px;">
        <div class="card"><div class="muted">CA valide</div><div class="stat-value">{{ number_format($stats['sales_total'], 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Reste a encaisser</div><div class="stat-value">{{ number_format($stats['open_balance'], 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Encaissements</div><div class="stat-value">{{ number_format($stats['payments_total'], 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Factures</div><div class="stat-value">{{ $stats['invoice_count'] }}</div></div>
    </div>

    <div class="split" style="margin-bottom:20px;">
        <section class="card">
            <h2 style="margin-top:0;">Informations generales</h2>
            <div class="grid">
                <div><strong>Code</strong><div class="muted">{{ $customer->code }}</div></div>
                <div><strong>Email</strong><div class="muted">{{ $customer->email ?: 'Non renseigne' }}</div></div>
                <div><strong>Telephone</strong><div class="muted">{{ $customer->phone ?: 'Non renseigne' }}</div></div>
                <div><strong>Ville</strong><div class="muted">{{ $customer->city ?: 'Non renseignee' }}</div></div>
                <div><strong>Adresse</strong><div class="muted">{{ $customer->address ?: 'Non renseignee' }}</div></div>
                <div><strong>NIF</strong><div class="muted">{{ $customer->nif ?: 'Non renseigne' }}</div></div>
                <div><strong>Solde initial</strong><div class="muted">{{ number_format((float) $customer->opening_balance, 0, ',', ' ') }} XOF</div></div>
                <div>
                    <strong>Statut</strong>
                    <div style="margin-top:6px;">
                        @include('partials.erp-status-badge', [
                            'type' => 'activity',
                            'value' => $customer->is_active ? 'active' : 'inactive',
                        ])
                    </div>
                </div>
            </div>
        </section>

        <section class="card">
            <h2 style="margin-top:0;">Lecture relation client</h2>
            <div class="grid">
                <div><strong>Factures ouvertes</strong><div class="muted">{{ $stats['open_balance'] > 0 ? 'Recouvrement a suivre' : 'Aucun solde ouvert' }}</div></div>
                <div><strong>Historique ventes</strong><div class="muted">{{ $invoices->count() }} facture(s) affichee(s)</div></div>
                <div><strong>Historique paiements</strong><div class="muted">{{ $payments->count() }} paiement(s) affiche(s)</div></div>
                <div><strong>Comptabilite</strong><div class="muted">{{ $journalEntries->count() }} ecriture(s) recente(s)</div></div>
            </div>
            @if ($customer->notes)
                <div class="muted" style="margin-top:14px;">{{ $customer->notes }}</div>
            @endif
        </section>
    </div>

    @include('partials.activity-history', [
        'activities' => $recentActivities,
        'title' => 'Historique des actions',
        'description' => 'Creation du client, ventes, encaissements et autres operations recentes rattachees a ce dossier.',
        'sectionId' => 'activity-history',
    ])

    <div class="split" style="margin-top:20px;">
        <section class="card" id="sales-documents">
            <h2 style="margin-top:0;">Factures clients</h2>
            @forelse ($invoices as $invoice)
                <div style="padding-bottom:14px; border-bottom:1px solid #efe4d3; margin-bottom:14px;">
                    <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start; flex-wrap:wrap;">
                        <div>
                            <div style="font-weight:600;">{{ $invoice->invoice_number }}</div>
                            <div class="muted" style="margin-top:6px;">{{ $invoice->invoice_date?->format('d/m/Y') }} · {{ $invoice->branch?->name }}</div>
                            <div style="margin-top:6px;">Total {{ number_format((float) $invoice->total, 0, ',', ' ') }} XOF · Reste {{ number_format((float) $invoice->balance_due, 0, ',', ' ') }} XOF</div>
                        </div>
                        <a href="{{ route('sales.show', $invoice) }}" class="button button-secondary">Ouvrir</a>
                    </div>
                </div>
            @empty
                <p class="muted">Aucune facture client liee pour le moment.</p>
            @endforelse
        </section>

        <section class="card" id="customer-payments">
            <h2 style="margin-top:0;">Paiements recus</h2>
            @forelse ($payments as $payment)
                <div style="padding-bottom:14px; border-bottom:1px solid #efe4d3; margin-bottom:14px;">
                    <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start; flex-wrap:wrap;">
                        <div>
                            <div style="font-weight:600;">{{ $payment->payment_number }}</div>
                            <div class="muted" style="margin-top:6px;">{{ $payment->payment_date?->format('d/m/Y') }} · {{ $payment->cashAccount?->name }}</div>
                            <div style="margin-top:6px;">{{ number_format((float) $payment->amount, 0, ',', ' ') }} XOF</div>
                        </div>
                        <a href="{{ route('payments.show', $payment) }}" class="button button-secondary">Ouvrir</a>
                    </div>
                </div>
            @empty
                <p class="muted">Aucun paiement recu pour ce client.</p>
            @endforelse
        </section>
    </div>

    <section class="card" id="accounting-effects" style="margin-top:20px;">
        <h2 style="margin-top:0;">Ecritures comptables liees</h2>
        @forelse ($journalEntries as $entry)
            <div style="padding-bottom:14px; border-bottom:1px solid #efe4d3; margin-bottom:14px;">
                <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start; flex-wrap:wrap;">
                    <div>
                        <div style="font-weight:600;">{{ $entry->journal_number }}</div>
                        <div class="muted" style="margin-top:6px;">{{ $entry->entry_date?->format('d/m/Y') }} · {{ $entry->description }}</div>
                        <div style="margin-top:6px;">Debit {{ number_format((float) $entry->total_debit, 0, ',', ' ') }} XOF · Credit {{ number_format((float) $entry->total_credit, 0, ',', ' ') }} XOF</div>
                    </div>
                    @allowed('accounting.view')
                        <a href="{{ route('accounting.journal-entries.show', $entry) }}" class="button button-secondary">Ouvrir</a>
                    @endallowed
                </div>
            </div>
        @empty
            <p class="muted">Aucune ecriture comptable liee a ce client pour le moment.</p>
        @endforelse
    </section>

    @include('partials.partner-directory', ['partner' => $customer])
@endsection


