@extends('layouts.app')

@section('title', 'Detail fournisseur - Nema ERP')
@section('page-title', 'Fournisseur '.$supplier->code)

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">{{ $supplier->name }}</h2>
            <div class="muted">{{ $supplier->code }} · {{ $supplier->city ?: 'Ville non renseignee' }} · {{ $supplier->phone ?: 'Telephone non renseigne' }}</div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="{{ route('suppliers.index', ['search' => $supplier->code]) }}" class="button button-secondary">Retour aux fournisseurs</a>
            @allowed('purchases.manage')
                <a href="{{ route('purchases.create') }}" class="button button-primary">Nouvel achat</a>
            @endallowed
            @allowed('expenses.manage')
                <a href="{{ route('expenses.create') }}" class="button button-secondary">Nouvelle depense</a>
            @endallowed
        </div>
    </div>

    <section class="card" style="margin-bottom:20px;">
        <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
            <a href="#purchase-documents" class="card" style="padding:16px; display:block;">
                <strong>Factures fournisseurs</strong>
                <div class="muted" style="margin-top:8px;">Voir les achats et les soldes ouverts de ce fournisseur.</div>
            </a>
            <a href="#expense-documents" class="card" style="padding:16px; display:block;">
                <strong>Depenses liees</strong>
                <div class="muted" style="margin-top:8px;">Suivre les charges rattachees au fournisseur.</div>
            </a>
            <a href="#supplier-payments" class="card" style="padding:16px; display:block;">
                <strong>Reglements</strong>
                <div class="muted" style="margin-top:8px;">Voir les paiements deja emis vers ce tiers.</div>
            </a>
            <a href="#accounting-effects" class="card" style="padding:16px; display:block;">
                <strong>Ecritures comptables liees</strong>
                <div class="muted" style="margin-top:8px;">Suivre la trace comptable du fournisseur.</div>
            </a>
        </div>
    </section>

    <div class="grid stats-grid" style="margin-bottom:20px;">
        <div class="card"><div class="muted">Achats valides</div><div class="stat-value">{{ number_format($stats['purchase_total'], 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Reste a payer</div><div class="stat-value">{{ number_format($stats['open_balance'], 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Reglements</div><div class="stat-value">{{ number_format($stats['payments_total'], 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Depenses</div><div class="stat-value">{{ number_format($stats['expenses_total'], 0, ',', ' ') }}</div></div>
    </div>

    <section class="card" style="margin-bottom:20px;">
        <div class="page-head" style="margin-bottom:14px;">
            <div>
                <h2 style="margin:0;">Performance fournisseur</h2>
                <div class="muted">Score calcule sur la ponctualite, l execution des commandes et l exposition financiere.</div>
            </div>
        </div>
        <div class="grid stats-grid">
            <div class="card">
                <div class="muted">Score</div>
                <div class="stat-value">{{ number_format((float) $performance['score'], 1, ',', ' ') }}/100</div>
                <div class="muted">{{ $performance['score_label'] }}</div>
            </div>
            <div class="card">
                <div class="muted">Livraisons a temps</div>
                <div class="stat-value">{{ $performance['on_time_rate'] !== null ? number_format((float) $performance['on_time_rate'], 1, ',', ' ') . ' %' : 'n.c.' }}</div>
                <div class="muted">{{ $performance['on_time_orders_count'] }} / {{ $performance['expected_orders_count'] }} commandes avec date cible</div>
            </div>
            <div class="card">
                <div class="muted">Retard moyen</div>
                <div class="stat-value">{{ $performance['avg_delay_days'] !== null ? number_format((float) $performance['avg_delay_days'], 1, ',', ' ') . ' j' : 'n.c.' }}</div>
                <div class="muted">Lead time moyen {{ $performance['avg_lead_time_days'] !== null ? number_format((float) $performance['avg_lead_time_days'], 1, ',', ' ') . ' j' : 'n.c.' }}</div>
            </div>
            <div class="card">
                <div class="muted">Execution commandes</div>
                <div class="stat-value">{{ $performance['receipt_completion_rate'] !== null ? number_format((float) $performance['receipt_completion_rate'], 1, ',', ' ') . ' %' : 'n.c.' }}</div>
                <div class="muted">{{ $performance['received_orders_count'] }} commande(s) recues sur {{ $performance['orders_count'] }}</div>
            </div>
        </div>
    </section>

    <div class="split" style="margin-bottom:20px;">
        <section class="card">
            <h2 style="margin-top:0;">Informations generales</h2>
            <div class="grid">
                <div><strong>Code</strong><div class="muted">{{ $supplier->code }}</div></div>
                <div><strong>Email</strong><div class="muted">{{ $supplier->email ?: 'Non renseigne' }}</div></div>
                <div><strong>Telephone</strong><div class="muted">{{ $supplier->phone ?: 'Non renseigne' }}</div></div>
                <div><strong>Ville</strong><div class="muted">{{ $supplier->city ?: 'Non renseignee' }}</div></div>
                <div><strong>Adresse</strong><div class="muted">{{ $supplier->address ?: 'Non renseignee' }}</div></div>
                <div><strong>NIF</strong><div class="muted">{{ $supplier->nif ?: 'Non renseigne' }}</div></div>
                <div><strong>Solde initial</strong><div class="muted">{{ number_format((float) $supplier->opening_balance, 0, ',', ' ') }} XOF</div></div>
                <div><strong>Condition de paiement</strong><div class="muted">{{ $supplier->paymentTerm?->name ?: 'Aucune' }}</div></div>
                <div><strong>Liste de prix</strong><div class="muted">{{ $supplier->priceList?->name ?: 'Tarif standard' }}</div></div>
                <div><strong>Statut</strong><div class="muted">{{ $supplier->is_active ? 'Actif' : 'Inactif' }}</div></div>
            </div>
        </section>

        <section class="card">
            <h2 style="margin-top:0;">Lecture relation fournisseur</h2>
            <div class="grid">
                <div><strong>Dettes ouvertes</strong><div class="muted">{{ $stats['open_balance'] > 0 ? 'Reglements a planifier' : 'Aucune dette ouverte' }}</div></div>
                <div><strong>Historique achats</strong><div class="muted">{{ $bills->count() }} facture(s) affichee(s)</div></div>
                <div><strong>Historique depenses</strong><div class="muted">{{ $expenses->count() }} depense(s) affichee(s)</div></div>
                <div><strong>Comptabilite</strong><div class="muted">{{ $journalEntries->count() }} ecriture(s) recente(s)</div></div>
                <div><strong>Derniere reception</strong><div class="muted">{{ $performance['last_receipt_date'] ? \Illuminate\Support\Carbon::parse($performance['last_receipt_date'])->format('d/m/Y') : 'Aucune' }}</div></div>
                <div><strong>Exposition dettes / achats</strong><div class="muted">{{ $performance['open_balance_ratio'] !== null ? number_format((float) $performance['open_balance_ratio'], 1, ',', ' ') . ' %' : 'n.c.' }}</div></div>
            </div>
            @if ($supplier->notes)
                <div class="muted" style="margin-top:14px;">{{ $supplier->notes }}</div>
            @endif
        </section>
    </div>

    <div class="split">
        <section class="card" id="purchase-documents">
            <h2 style="margin-top:0;">Factures fournisseurs</h2>
            @forelse ($bills as $bill)
                <div style="padding-bottom:14px; border-bottom:1px solid #efe4d3; margin-bottom:14px;">
                    <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start; flex-wrap:wrap;">
                        <div>
                            <div style="font-weight:600;">{{ $bill->bill_number }}</div>
                            <div class="muted" style="margin-top:6px;">{{ $bill->bill_date?->format('d/m/Y') }} · {{ $bill->branch?->name }}</div>
                            <div style="margin-top:6px;">Total {{ number_format((float) $bill->total, 0, ',', ' ') }} XOF · Reste {{ number_format((float) $bill->balance_due, 0, ',', ' ') }} XOF</div>
                        </div>
                        <a href="{{ route('purchases.show', $bill) }}" class="button button-secondary">Ouvrir</a>
                    </div>
                </div>
            @empty
                <p class="muted">Aucune facture fournisseur liee pour le moment.</p>
            @endforelse
        </section>

        <section class="card" id="expense-documents">
            <h2 style="margin-top:0;">Depenses liees</h2>
            @forelse ($expenses as $expense)
                <div style="padding-bottom:14px; border-bottom:1px solid #efe4d3; margin-bottom:14px;">
                    <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start; flex-wrap:wrap;">
                        <div>
                            <div style="font-weight:600;">{{ $expense->expense_number }}</div>
                            <div class="muted" style="margin-top:6px;">{{ $expense->expense_date?->format('d/m/Y') }} · {{ $expense->category?->name }}</div>
                            <div style="margin-top:6px;">{{ number_format((float) $expense->total, 0, ',', ' ') }} XOF</div>
                        </div>
                        <a href="{{ route('expenses.show', $expense) }}" class="button button-secondary">Ouvrir</a>
                    </div>
                </div>
            @empty
                <p class="muted">Aucune depense liee a ce fournisseur.</p>
            @endforelse
        </section>
    </div>

    <div class="split" style="margin-top:20px;">
        <section class="card" id="supplier-payments">
            <h2 style="margin-top:0;">Reglements</h2>
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
                <p class="muted">Aucun reglement enregistre pour ce fournisseur.</p>
            @endforelse
        </section>

        <section class="card" id="accounting-effects">
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
                <p class="muted">Aucune ecriture comptable liee a ce fournisseur pour le moment.</p>
            @endforelse
        </section>
    </div>

    @include('partials.partner-directory', ['partner' => $supplier])
@endsection





