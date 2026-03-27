@extends('layouts.app')

@section('title', 'Detail facture - Nema ERP')
@section('page-title', 'Facture '.$invoice->invoice_number)

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">{{ $invoice->customer?->name }}</h2>
            <div class="muted">Facture du {{ $invoice->invoice_date?->format('d/m/Y') }} · Agence {{ $invoice->branch?->name }} · {{ $invoice->warehouse?->name ?? 'Entrepot par defaut' }}</div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="{{ route('sales.print', $invoice) }}" class="button button-secondary" target="_blank">Imprimer</a>
            @allowed('accounting.view')
                <a href="{{ route('accounting.journal-entries.index', ['source_type' => 'sales', 'search' => $invoice->invoice_number]) }}" class="button button-secondary">Voir les ecritures</a>
            @endallowed
            @allowed('collections.view')
                <a href="{{ route('collections.show', $invoice) }}" class="button button-secondary">Suivi recouvrement</a>
            @endallowed
            @if ($invoice->status === 'pending_approval')
                @allowed('sales.approve')
                    <form method="POST" action="{{ route('sales.approve', $invoice) }}">
                        @csrf
                        <button type="submit" class="button button-primary">Valider l etape suivante</button>
                    </form>
                @endallowed
            @elseif ($invoice->payment_status !== 'paid')
                @allowed('payments.manage')
                    <a href="{{ route('payments.create', ['invoice' => $invoice->id]) }}" class="button button-primary">Enregistrer un paiement</a>
                @endallowed
            @endif
        </div>
    </div>

    <section class="card" style="margin-bottom:20px;">
        <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
            <a href="{{ route('sales.index', ['search' => $invoice->invoice_number]) }}" class="card" style="padding:16px; display:block;">
                <strong>Retour au dossier commercial</strong>
                <div class="muted" style="margin-top:8px;">Retrouver cette facture dans la liste filtree.</div>
            </a>
            @allowed('accounting.view')
                <a href="{{ route('accounting.journal-entries.index', ['source_type' => 'sales', 'search' => $invoice->invoice_number]) }}" class="card" style="padding:16px; display:block;">
                    <strong>Ecriture comptable</strong>
                    <div class="muted" style="margin-top:8px;">Ouvrir directement les journaux lies a cette facture.</div>
                </a>
            @endallowed
            <a href="#stock-effects" class="card" style="padding:16px; display:block;">
                <strong>Impacts stock</strong>
                <div class="muted" style="margin-top:8px;">Voir les sorties de stock generees par cette facture.</div>
            </a>
            <a href="#payments" class="card" style="padding:16px; display:block;">
                <strong>Recouvrement</strong>
                <div class="muted" style="margin-top:8px;">Acceder directement aux paiements deja rattaches.</div>
            </a>
            @allowed('collections.view')
                <a href="{{ route('collections.show', $invoice) }}" class="card" style="padding:16px; display:block;">
                    <strong>Relances client</strong>
                    <div class="muted" style="margin-top:8px;">Suivre les promesses de paiement et prochaines actions.</div>
                </a>
            @endallowed
        </div>
    </section>

    <div class="grid stats-grid" style="margin-bottom:20px;">
        <div class="card"><div class="muted">Workflow</div><div class="stat-value" style="font-size:24px;">{{ $invoice->status === 'validated' ? 'Approuvee' : 'En attente' }}</div></div>
        <div class="card"><div class="muted">Total facture</div><div class="stat-value">{{ number_format((float) $invoice->total, 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Montant paye</div><div class="stat-value">{{ number_format((float) $invoice->amount_paid, 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Solde restant</div><div class="stat-value">{{ number_format((float) $invoice->balance_due, 0, ',', ' ') }}</div></div>
    </div>

    @if ($invoice->followUps->isNotEmpty())
        <section class="card" style="margin-bottom:20px;">
            <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:flex-start;">
                <div>
                    <h2 style="margin:0;">Dernieres relances client</h2>
                    <div class="muted" style="margin-top:6px;">Lecture rapide des derniers echanges de recouvrement sur cette facture.</div>
                </div>
                @allowed('collections.view')
                    <a href="{{ route('collections.show', $invoice) }}" class="button button-secondary">Ouvrir le dossier recouvrement</a>
                @endallowed
            </div>
            <div style="margin-top:18px; display:grid; gap:14px;">
                @foreach ($invoice->followUps->sortByDesc('id')->take(3) as $followUp)
                    <div style="padding-bottom:14px; border-bottom:1px solid #efe4d3;">
                        <div style="font-weight:600;">{{ $followUp->action_date?->format('d/m/Y') }} · {{ ucfirst(str_replace('_', ' ', $followUp->action_type)) }}</div>
                        <div class="muted" style="margin-top:6px;">{{ $followUp->creator?->name ?? 'Systeme' }}</div>
                        @if ($followUp->outcome)
                            <div class="help" style="margin-top:6px;">Issue : {{ ucfirst(str_replace('_', ' ', $followUp->outcome)) }}</div>
                        @endif
                        @if ($followUp->promised_date || $followUp->next_action_date)
                            @php($followUpMeta = [])
                            @if ($followUp->promised_date)
                                @php($followUpMeta[] = 'Promesse '.$followUp->promised_date->format('d/m/Y'))
                            @endif
                            @if ($followUp->promised_amount)
                                @php($followUpMeta[] = number_format((float) $followUp->promised_amount, 0, ',', ' ').' XOF')
                            @endif
                            @if ($followUp->next_action_date)
                                @php($followUpMeta[] = 'Prochaine action '.$followUp->next_action_date->format('d/m/Y'))
                            @endif
                            <div class="help" style="margin-top:6px;">{{ implode(' · ', $followUpMeta) }}</div>
                        @endif
                        @if ($followUp->notes)
                            <div style="margin-top:6px;">{{ $followUp->notes }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <div class="split" style="margin-bottom:20px;">
        <section class="card">
            <h2 style="margin-top:0;">Validation</h2>
            <div class="grid">
                <div><strong>Statut</strong><div class="muted">{{ $invoice->status === 'validated' ? 'Approuvee' : 'En attente d approbation' }}</div></div>
                <div><strong>Creee par</strong><div class="muted">{{ $invoice->creator?->name ?? 'Systeme' }}</div></div>
                <div><strong>Approuvee par</strong><div class="muted">{{ $invoice->approver?->name ?? 'Non approuvee' }}</div></div>
                <div><strong>Date d approbation</strong><div class="muted">{{ $invoice->approved_at?->format('d/m/Y H:i') ?? 'Non disponible' }}</div></div>
            </div>
            @include('partials.approval-steps', ['approvalSteps' => $invoice->approvalSteps])
        </section>

        <section class="card">
            <h2 style="margin-top:0;">Synthese d impact</h2>
            <div class="grid">
                <div><strong>Statut paiement</strong><div class="muted">{{ $invoice->payment_status === 'paid' ? 'Payee' : ($invoice->payment_status === 'partial' ? 'Partielle' : 'Impayee') }}</div></div>
                <div><strong>Entrepot</strong><div class="muted">{{ $invoice->warehouse?->name ?? 'Entrepot par defaut' }}</div></div>
                <div><strong>Effet stock</strong><div class="muted">{{ $invoice->status === 'validated' ? 'Stock mis a jour' : 'En attente d approbation finale' }}</div></div>
                <div><strong>Effet comptable</strong><div class="muted">{{ $invoice->status === 'validated' ? 'Ecriture generee' : 'Ecriture en attente' }}</div></div>
                <div><strong>Nombre de mouvements</strong><div class="muted">{{ $stockMovements->count() }} mouvement(s) de stock lie(s)</div></div>
            </div>
        </section>
    </div>

    <div class="split" style="margin-bottom:20px;">
        <section class="card">
            <h2 style="margin-top:0;">Articles</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Produit</th>
                        <th>Description</th>
                        <th>Quantite</th>
                        <th>PU</th>
                        <th>Total</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($invoice->items as $item)
                        <tr>
                            <td>@include('partials.product-inline', ['product' => $item->product, 'meta' => $item->product?->barcode ?: $item->product?->sku, 'size' => 42])</td>
                            <td>{{ $item->description }}</td>
                            <td>{{ number_format((float) $item->qty, 3, ',', ' ') }}</td>
                            <td>{{ number_format((float) $item->unit_price, 0, ',', ' ') }} XOF</td>
                            <td>{{ number_format((float) $item->line_total, 0, ',', ' ') }} XOF</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card" id="payments">
            <h2 style="margin-top:0;">Encaissements</h2>
            @if ($invoice->status !== 'validated')
                <p class="muted">Aucun paiement possible tant que la facture client n est pas completement approuvee.</p>
            @else
                @forelse ($invoice->paymentAllocations->sortByDesc(fn ($allocation) => optional($allocation->payment)->payment_date) as $allocation)
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
                    <p class="muted">Aucun paiement enregistre sur cette facture.</p>
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
                    <div style="margin-top:6px;">Sortie : {{ number_format((float) $movement->quantity_out, 3, ',', ' ') }}</div>
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
                <p class="muted">Aucune ecriture comptable liee a cette facture pour le moment.</p>
            @endforelse
        </section>
    </div>
    @include('partials.document-collaboration', ['document' => $invoice, 'documentType' => 'sales_invoice', 'managePermission' => 'sales.manage'])
@endsection



