@extends('layouts.app')

@section('title', 'Detail achat - Nema ERP')
@section('page-title', 'Facture '.$bill->bill_number)

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">{{ $bill->supplier?->name }}</h2>
            <div class="muted">Facture du {{ $bill->bill_date?->format('d/m/Y') }} · Agence {{ $bill->branch?->name }} · {{ $bill->warehouse?->name ?? 'Entrepot par defaut' }}</div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="{{ route('purchases.print', $bill) }}" class="button button-secondary" target="_blank">Imprimer</a>
            @allowed('accounting.view')
                <a href="{{ route('accounting.journal-entries.index', ['source_type' => 'purchases', 'search' => $bill->bill_number]) }}" class="button button-secondary">Voir les ecritures</a>
            @endallowed
            @if ($bill->status === 'pending_approval')
                @allowed('purchases.approve')
                    <form method="POST" action="{{ route('purchases.approve', $bill) }}">
                        @csrf
                        <button type="submit" class="button button-primary">Valider l etape suivante</button>
                    </form>
                @endallowed
            @elseif ($bill->payment_status !== 'paid')
                @allowed('payments.manage')
                    <a href="{{ route('payments.create', ['type' => 'supplier_payment', 'purchase_bill' => $bill->id]) }}" class="button button-primary">Enregistrer un reglement</a>
                @endallowed
            @endif
        </div>
    </div>

    <section class="card" style="margin-bottom:20px;">
        <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
            <a href="{{ route('purchases.index', ['search' => $bill->bill_number]) }}" class="card" style="padding:16px; display:block;">
                <strong>Retour au dossier achat</strong>
                <div class="muted" style="margin-top:8px;">Retrouver cette facture dans la liste filtree.</div>
            </a>
            @allowed('accounting.view')
                <a href="{{ route('accounting.journal-entries.index', ['source_type' => 'purchases', 'search' => $bill->bill_number]) }}" class="card" style="padding:16px; display:block;">
                    <strong>Ecriture comptable</strong>
                    <div class="muted" style="margin-top:8px;">Ouvrir directement les journaux lies a cet achat.</div>
                </a>
            @endallowed
            <a href="#stock-effects" class="card" style="padding:16px; display:block;">
                <strong>Impacts stock</strong>
                <div class="muted" style="margin-top:8px;">Voir les entrees de stock generees par cet achat.</div>
            </a>
            <a href="#payments" class="card" style="padding:16px; display:block;">
                <strong>Reglements</strong>
                <div class="muted" style="margin-top:8px;">Acceder directement aux paiements rattaches.</div>
            </a>
        </div>
    </section>

    @if ($bill->goodsReceipt || $bill->purchaseOrder)
        <section class="card" style="margin-bottom:20px;">
            <h2 style="margin-top:0;">Origine du dossier achat</h2>
            <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:16px;">
                @if ($bill->purchaseOrder)
                    <div class="card" style="padding:16px;">
                        <div class="muted">Commande fournisseur</div>
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

    <div class="grid stats-grid" style="margin-bottom:20px;">
        <div class="card"><div class="muted">Workflow</div><div class="stat-value" style="font-size:24px;">{{ $bill->status === 'validated' ? 'Approuvee' : 'En attente' }}</div></div>
        <div class="card"><div class="muted">Total facture</div><div class="stat-value">{{ number_format((float) $bill->total, 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Montant paye</div><div class="stat-value">{{ number_format((float) $bill->amount_paid, 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Solde restant</div><div class="stat-value">{{ number_format((float) $bill->balance_due, 0, ',', ' ') }}</div></div>
    </div>

    <div class="split" style="margin-bottom:20px;">
        <section class="card">
            <h2 style="margin-top:0;">Validation</h2>
            <div class="grid">
                <div><strong>Statut</strong><div class="muted">{{ $bill->status === 'validated' ? 'Approuvee' : 'En attente d approbation' }}</div></div>
                <div><strong>Creee par</strong><div class="muted">{{ $bill->creator?->name ?? 'Systeme' }}</div></div>
                <div><strong>Approuvee par</strong><div class="muted">{{ $bill->approver?->name ?? 'Non approuvee' }}</div></div>
                <div><strong>Date d approbation</strong><div class="muted">{{ $bill->approved_at?->format('d/m/Y H:i') ?? 'Non disponible' }}</div></div>
            </div>
            @include('partials.approval-steps', ['approvalSteps' => $bill->approvalSteps])
        </section>

        <section class="card">
            <h2 style="margin-top:0;">Synthese d impact</h2>
            <div class="grid">
                <div><strong>Statut paiement</strong><div class="muted">{{ $bill->payment_status === 'paid' ? 'Payee' : ($bill->payment_status === 'partial' ? 'Partielle' : 'Impayee') }}</div></div>
                <div><strong>Entrepot</strong><div class="muted">{{ $bill->warehouse?->name ?? 'Entrepot par defaut' }}</div></div>
                <div><strong>Effet stock</strong><div class="muted">{{ $bill->goodsReceipt ? 'Stock deja receptionne avant facturation' : ($bill->status === 'validated' ? 'Stock mis a jour' : 'En attente d approbation finale') }}</div></div>
                <div><strong>Effet comptable</strong><div class="muted">{{ $bill->status === 'validated' ? 'Ecriture generee' : 'Ecriture en attente' }}</div></div>
                <div><strong>Nombre de mouvements</strong><div class="muted">{{ $stockMovements->count() }} mouvement(s) de stock lie(s)</div></div>
            </div>
        </section>
    </div>

    <div class="split" style="margin-bottom:20px;">
        <section class="card">
            <h2 style="margin-top:0;">Articles receptionnes</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Produit</th>
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
                <p class="muted">Aucun reglement possible tant que la facture fournisseur n est pas completement approuvee.</p>
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
                    <p class="muted">Aucun reglement enregistre sur cette facture fournisseur.</p>
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
                <p class="muted">Aucune ecriture comptable liee a cette facture fournisseur pour le moment.</p>
            @endforelse
        </section>
    </div>
    @include('partials.document-collaboration', ['document' => $bill, 'documentType' => 'purchase_bill', 'managePermission' => 'purchases.manage'])
@endsection
