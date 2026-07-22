@extends('layouts.app')

@php
    $supplierLabel = $businessVocabulary['supplier'] ?? 'Fournisseur';
    $purchaseLabel = $businessVocabulary['purchase'] ?? 'Achat';
    $purchasesLabel = $businessVocabulary['purchases'] ?? 'Achats';
    $productLabel = $businessVocabulary['product'] ?? 'Produit';
    $productsLabel = $businessVocabulary['products'] ?? 'Produits';
@endphp

@section('title', 'Detail '.$purchaseLabel.' - Nema ERP')
@section('page-title', 'Facture '.$bill->bill_number)

@section('content')
    @php
        $workflowStatus = \App\Support\ErpStatusPresenter::present('workflow', $bill->status);
        $paymentStatus = \App\Support\ErpStatusPresenter::present('payment', $bill->payment_status);
    @endphp
    <div class="premium-detail-page">
        <section class="card premium-detail-hero premium-detail-hero--sage">
            <div class="premium-detail-hero__grid">
                <div class="premium-detail-hero__copy">
                    <div class="badge badge-muted">Facturation {{ strtolower($supplierLabel) }}</div>
                    <h2>{{ $bill->bill_number }} · {{ $bill->supplier?->name }}</h2>
                    <p class="muted">Facture du {{ $bill->bill_date?->format('d/m/Y') }} rattachee a l agence {{ $bill->branch?->name }} et a {{ $bill->warehouse?->name ?? 'Entrepot par defaut' }}. La page donne d abord une lecture approvisionnement et tresorerie avant le detail comptable.</p>
                    <div class="premium-detail-hero__meta">
                        @include('partials.erp-status-badge', ['status' => $workflowStatus])
                        @include('partials.erp-status-badge', ['status' => $paymentStatus])
                        @include('partials.erp-status-badge', ['label' => 'Agence : '.($bill->branch?->name ?? 'Non renseignee'), 'tone' => 'muted'])
                        @include('partials.erp-status-badge', ['label' => 'Depot : '.($bill->warehouse?->name ?? 'Entrepot par defaut'), 'tone' => 'muted'])
                    </div>
                </div>
                <div class="premium-detail-panel">
                    <div>
                        <strong>Actions immediates</strong>
                        <div class="muted" style="margin-top:8px;">Imprimer, ouvrir les ecritures ou enregistrer un reglement {{ strtolower($supplierLabel) }} sans quitter le dossier.</div>
                    </div>
                    <div class="premium-detail-panel__actions">
                        <a href="{{ route('purchases.print', $bill) }}" class="button button-secondary" target="_blank">Imprimer la facture</a>
                        @allowed('accounting.view')
                            <a href="{{ route('accounting.journal-entries.index', ['source_type' => 'purchases', 'search' => $bill->bill_number]) }}" class="button button-secondary">Voir les ecritures</a>
                        @endallowed
                        @if ($bill->status === 'pending_approval')
                            @allowed('purchases.approve')
                                <form method="POST" action="{{ route('purchases.approve', $bill) }}">
                                    @csrf
                                    <button type="submit" class="button button-primary">Valider l etape suivante</button>
                                </form>
                                <form method="POST" action="{{ route('purchases.reject', $bill) }}" style="display:grid; gap:8px;">
                                    @csrf
                                    <input type="text" name="rejection_reason" maxlength="1000" required placeholder="Motif du rejet">
                                    <button type="submit" class="button button-secondary">Rejeter avec motif</button>
                                </form>
                            @endallowed
                        @elseif ($bill->status === 'validated' && $bill->payment_status !== 'paid')
                            @allowed('payments.manage')
                                <a href="{{ route('payments.create', ['type' => 'supplier_payment', 'purchase_bill' => $bill->id]) }}" class="button button-primary">Enregistrer un reglement</a>
                            @endallowed
                        @endif
                        @if ($bill->status === 'validated' && (float) $bill->balance_due > 0)
                            @allowed('supplier_credit_notes.issue')
                                <a href="{{ route('purchase-credit-notes.create', $bill) }}" class="button button-secondary">Emettre un avoir {{ strtolower($supplierLabel) }}</a>
                            @endallowed
                        @endif
                    </div>
                </div>
            </div>
        </section>

        <section class="premium-anchor-grid">
            <a href="{{ route('purchases.index', ['search' => $bill->bill_number]) }}" class="premium-anchor-card">
                <strong>Retour au dossier {{ strtolower($purchaseLabel) }}</strong>
                <div class="muted">Retrouver cette facture dans la liste filtree.</div>
            </a>
            @allowed('accounting.view')
                <a href="{{ route('accounting.journal-entries.index', ['source_type' => 'purchases', 'search' => $bill->bill_number]) }}" class="premium-anchor-card">
                    <strong>Ecriture comptable</strong>
                    <div class="muted">Ouvrir directement les journaux lies a ce {{ strtolower($purchaseLabel) }}.</div>
                </a>
            @endallowed
            @allowed('supplier_credit_notes.view')
                <a href="#supplier-credits" class="premium-anchor-card">
                    <strong>Avoirs {{ strtolower($supplierLabel) }}</strong>
                    <div class="muted">Suivre les reductions de dette et les retours {{ strtolower($supplierLabel) }}.</div>
                </a>
            @endallowed
            <a href="#stock-effects" class="premium-anchor-card">
                <strong>Impacts stock</strong>
                <div class="muted">Voir les entrees de stock generees par ce {{ strtolower($purchaseLabel) }}.</div>
            </a>
            <a href="#payments" class="premium-anchor-card">
                <strong>Reglements</strong>
                <div class="muted">Acceder directement aux paiements rattaches.</div>
            </a>
        </section>
    @if ($bill->goodsReceipt || $bill->purchaseOrder)
        <section class="card" style="margin-bottom:20px;">
            <h2 style="margin-top:0;">Origine du dossier {{ strtolower($purchaseLabel) }}</h2>
            <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:16px;">
                @if ($bill->purchaseOrder)
                    <div class="card" style="padding:16px;">
                        <div class="muted">Commande {{ strtolower($supplierLabel) }}</div>
                        <div style="margin-top:8px; font-weight:600;"><a href="{{ route('purchase-orders.show', $bill->purchaseOrder) }}">{{ $bill->purchaseOrder->order_number }}</a></div>
                    </div>
                @endif
                @if ($bill->goodsReceipt)
                    <div class="card" style="padding:16px;">
                        <div class="muted">Reception source</div>
                        <div style="margin-top:8px; font-weight:600;"><a href="{{ route('goods-receipts.show', $bill->goodsReceipt) }}">{{ $bill->goodsReceipt->receipt_number }}</a></div>
                        <div class="muted" style="margin-top:8px;">Stock deja mis a jour a la reception, sans double impact a la facturation.</div>
                    </div>
                @endif
            </div>
        </section>
    @endif

    <div class="premium-stat-grid" style="margin-bottom:20px;">
        <article class="premium-stat-card"><div class="label">Workflow</div><div class="value">{{ $workflowStatus['label'] }}</div><div class="hint">Etat de validation de la facture {{ strtolower($supplierLabel) }}.</div></article>
        <article class="premium-stat-card"><div class="label">Total facture</div><div class="value">{{ number_format((float) $bill->total, 0, ',', ' ') }}</div><div class="hint">Montant total facture par le {{ strtolower($supplierLabel) }}.</div></article>
        <article class="premium-stat-card"><div class="label">Montant paye</div><div class="value">{{ number_format((float) $bill->amount_paid, 0, ',', ' ') }}</div><div class="hint">Reglements deja associes a ce {{ strtolower($purchaseLabel) }}.</div></article>
        <article class="premium-stat-card"><div class="label">Solde restant</div><div class="value">{{ number_format((float) $bill->balance_due, 0, ',', ' ') }}</div><div class="hint">Montant encore du au {{ strtolower($supplierLabel) }}.</div></article>
    </div>

    <div class="split" style="margin-bottom:20px;">
        <section class="card">
            <h2 style="margin-top:0;">Validation</h2>
            <div class="grid">
                <div><strong>Statut</strong><div class="muted">{{ $workflowStatus['label'] }}</div></div>
                <div><strong>Creee par</strong><div class="muted">{{ $bill->creator?->name ?? 'Systeme' }}</div></div>
                <div><strong>Approuvee par</strong><div class="muted">{{ $bill->approver?->name ?? 'Non approuvee' }}</div></div>
                <div><strong>Date d approbation</strong><div class="muted">{{ $bill->approved_at?->format('d/m/Y H:i') ?? 'Non disponible' }}</div></div>
                <div><strong>Rejetee par</strong><div class="muted">{{ $bill->rejector?->name ?? 'Non rejetee' }}</div></div>
                <div><strong>Date de rejet</strong><div class="muted">{{ $bill->rejected_at?->format('d/m/Y H:i') ?? 'Non disponible' }}</div></div>
                <div style="grid-column:1 / -1;"><strong>Motif du rejet</strong><div class="muted">{{ $bill->rejection_reason ?: 'Aucun motif enregistre' }}</div></div>
            </div>
            @include('partials.approval-steps', ['approvalSteps' => $bill->approvalSteps])
        </section>

        <section class="card">
            <h2 style="margin-top:0;">Synthese d impact</h2>
            <div class="grid">
                <div><strong>Statut paiement</strong><div class="muted">{{ $paymentStatus['label'] }}</div></div>
                <div><strong>Entrepot</strong><div class="muted">{{ $bill->warehouse?->name ?? 'Entrepot par defaut' }}</div></div>
                <div><strong>Effet stock</strong><div class="muted">{{ $bill->goodsReceipt ? 'Stock deja receptionne avant facturation' : ($bill->status === 'validated' ? 'Stock mis a jour' : ($bill->status === 'rejected' ? 'Aucun effet stock final' : 'En attente d approbation finale')) }}</div></div>
                <div><strong>Effet comptable</strong><div class="muted">{{ $bill->status === 'validated' ? 'Ecriture generee' : ($bill->status === 'rejected' ? 'Aucune ecriture finale' : 'Ecriture en attente') }}</div></div>
                <div><strong>Nombre de mouvements</strong><div class="muted">{{ $stockMovements->count() }} mouvement(s) de stock lie(s)</div></div>
            </div>
        </section>
    </div>

    @include('partials.activity-history', [
        'activities' => $recentActivities,
        'title' => 'Historique des actions',
        'description' => 'Validation, receptions, reglements et autres actions recentes liees a cette facture '.$supplierLabel.'.',
        'sectionId' => 'activity-history',
    ])

    @allowed('supplier_credit_notes.view')
        <section class="card" id="supplier-credits" style="margin:20px 0;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
                <div>
                    <h2 style="margin-top:0;">Avoirs {{ strtolower($supplierLabel) }}</h2>
                    <p class="muted" style="margin-top:4px;">Credits obtenus sur cette facture, avec sortie stock possible si les articles sont retournes au {{ strtolower($supplierLabel) }}.</p>
                </div>
                @if ($bill->status === 'validated' && (float) $bill->balance_due > 0)
                    @allowed('supplier_credit_notes.issue')
                        <a href="{{ route('purchase-credit-notes.create', $bill) }}" class="button button-primary">Nouvel avoir {{ strtolower($supplierLabel) }}</a>
                    @endallowed
                @endif
            </div>

            <div class="table-wrap" style="margin-top:16px;">
                <table>
                    <thead>
                    <tr>
                        <th>Numero</th>
                        <th>Date</th>
                        <th>Montant</th>
                        <th>Stock</th>
                        <th>Cree par</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($bill->creditNotes as $creditNote)
                        <tr>
                            <td><strong>{{ $creditNote->credit_note_number }}</strong></td>
                            <td>{{ $creditNote->credit_note_date?->format('d/m/Y') }}</td>
                            <td>{{ number_format((float) $creditNote->total, 0, ',', ' ') }} XOF</td>
                            <td>{{ $creditNote->destock_items ? 'Retour '.$supplierLabel : 'Sans sortie stock' }}</td>
                            <td>{{ $creditNote->creator?->name ?? 'Systeme' }}</td>
                            <td><a href="{{ route('purchase-credit-notes.show', $creditNote) }}" class="button button-secondary">Voir</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="muted">Aucun avoir {{ strtolower($supplierLabel) }} enregistre sur cette facture.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endallowed

    <div class="split" style="margin:20px 0;">
        <section class="card">
            <h2 style="margin-top:0;">{{ $productsLabel }} receptionnes</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>{{ $productLabel }}</th>
                        <th>Description</th>
                        <th>Quantite</th>
                        <th>Cout unitaire</th>
                        <th>Total</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($bill->items as $item)
                        <tr>
                            <td>@include('partials.product-inline', ['product' => $item->product, 'meta' => $item->product?->barcode ?: $item->product?->sku, 'size' => 42])</td>
                            <td>{{ $item->description }}</td>
                            <td>{{ number_format((float) $item->qty, 3, ',', ' ') }}</td>
                            <td>{{ number_format((float) $item->unit_cost, 0, ',', ' ') }} XOF</td>
                            <td>{{ number_format((float) $item->line_total, 0, ',', ' ') }} XOF</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card" id="payments">
            <h2 style="margin-top:0;">Reglements</h2>
            @if ($bill->status !== 'validated')
                <p class="muted">Aucun reglement possible tant que la facture {{ strtolower($supplierLabel) }} n est pas completement approuvee.</p>
            @else
                @forelse ($bill->paymentAllocations->sortByDesc(fn ($allocation) => optional($allocation->payment)->payment_date) as $allocation)
                    <div style="padding-bottom: 14px; border-bottom: 1px solid #efe4d3; margin-bottom:14px;">
                        <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start; flex-wrap:wrap;">
                            <div>
                                <div style="font-weight:600;"><a href="{{ route('payments.show', $allocation->payment) }}">{{ $allocation->payment?->payment_number }}</a></div>
                                <div class="muted" style="margin-top:6px;">{{ $allocation->payment?->payment_date?->format('d/m/Y') }} · {{ $allocation->payment?->cashAccount?->name }}</div>
                                <div class="muted" style="margin-top:6px;">{{ str($allocation->payment?->method)->replace('_', ' ')->title() }}</div>
                                <div style="margin-top:6px;">{{ number_format((float) $allocation->allocated_amount, 0, ',', ' ') }} XOF</div>
                            </div>
                            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                                <a href="{{ route('payments.show', $allocation->payment) }}" class="button button-secondary">Voir le paiement</a>
                                @allowed('accounting.view')
                                    <a href="{{ route('accounting.journal-entries.index', ['source_type' => 'payments', 'search' => $allocation->payment?->payment_number]) }}" class="button button-secondary">Voir l ecriture</a>
                                @endallowed
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="muted">Aucun reglement enregistre sur cette facture {{ strtolower($supplierLabel) }}.</p>
                @endforelse
            @endif
        </section>
    </div>

    <div class="split">
        <section class="card" id="stock-effects">
            <h2 style="margin-top:0;">Mouvements de stock lies</h2>
            @forelse ($stockMovements as $movement)
                <div style="padding-bottom: 14px; border-bottom: 1px solid #efe4d3; margin-bottom:14px;">
                    @include('partials.product-inline', ['product' => $movement->product, 'meta' => $movement->warehouse?->name, 'size' => 40])
                    <div class="muted" style="margin-top:6px;">{{ $movement->movement_date?->format('d/m/Y H:i') }} · {{ $movement->warehouse?->name ?? 'Entrepot par defaut' }} · {{ $movement->creator?->name ?? 'Systeme' }}</div>
                    <div style="margin-top:6px;">Entree : {{ number_format((float) $movement->quantity_in, 3, ',', ' ') }}</div>
                </div>
            @empty
                <p class="muted">Aucun mouvement de stock lie a cette facture pour le moment.</p>
            @endforelse
        </section>

        <section class="card">
            <h2 style="margin-top:0;">Ecritures comptables liees</h2>
            @forelse ($journalEntries as $entry)
                <div style="padding-bottom: 14px; border-bottom: 1px solid #efe4d3; margin-bottom:14px;">
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
                <p class="muted">Aucune ecriture comptable liee a cette facture {{ strtolower($supplierLabel) }} pour le moment.</p>
            @endforelse
        </section>
    </div>
    @include('partials.document-collaboration', ['document' => $bill, 'documentType' => 'purchase_bill', 'managePermission' => 'purchases.manage'])
@endsection
