@extends('layouts.app')

@section('title', 'Detail facture - Nema ERP')
@section('page-title', 'Facture '.$invoice->invoice_number)

@section('content')
    @php
        $workflowLabel = match ($invoice->status) {
            'validated' => 'Approuvee',
            'cancelled' => 'Annulee',
            default => 'En attente',
        };
        $workflowTone = match ($invoice->status) {
            'validated' => 'badge-success',
            'cancelled' => 'badge-danger',
            default => 'badge-warning',
        };
        $canIssueCreditNote = $invoice->status === 'validated' && (float) $invoice->balance_due > 0 && $creditableLinesCount > 0;
    @endphp

    <div class="premium-detail-page">
        <section class="card premium-detail-hero premium-detail-hero--teal">
            <div class="premium-detail-hero__grid">
                <div class="premium-detail-hero__copy">
                    <div class="badge badge-muted">Facturation client</div>
                    <h2>{{ $invoice->invoice_number }} · {{ $invoice->customer?->name }}</h2>
                    <p class="muted">Facture du {{ $invoice->invoice_date?->format('d/m/Y') }} reliee a l agence {{ $invoice->branch?->name }} et au depot {{ $invoice->warehouse?->name ?? 'Entrepot par defaut' }}. Cet ecran met en avant les impacts business avant le detail comptable et stock.</p>
                    <div class="premium-detail-hero__meta">
                        <span class="badge {{ $workflowTone }}">{{ $workflowLabel }}</span>
                        <span class="badge badge-muted">Paiement : {{ str($invoice->payment_status)->replace('_', ' ')->title() }}</span>
                        <span class="badge badge-muted">Agence : {{ $invoice->branch?->name }}</span>
                        <span class="badge badge-muted">Depot : {{ $invoice->warehouse?->name ?? 'Entrepot par defaut' }}</span>
                    </div>
                </div>
                <div class="premium-detail-panel">
                    <div>
                        <strong>Actions immediates</strong>
                        <div class="muted" style="margin-top:8px;">Imprimer, encaisser, partager le portail client ou ouvrir les ecritures sans quitter la facture.</div>
                    </div>
                    <div class="premium-detail-panel__actions">
                        <a href="{{ route('sales.print', $invoice) }}" class="button button-secondary" target="_blank">PDF</a>
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
                            @allowed('sales.cancel')
                                <form method="POST" action="{{ route('sales.cancel', $invoice) }}">
                                    @csrf
                                    <button type="submit" class="button button-secondary">Annuler la facture</button>
                                </form>
                            @endallowed
                        @elseif ($invoice->status === 'validated')
                            @if (isset($paymentPortal) && $invoice->payment_status !== 'paid')
                                <a href="{{ $paymentPortal['view_url'] }}" class="button button-secondary" target="_blank" rel="noopener">Portail reglement client</a>
                            @endif
                            @allowed('payments.validate')
                                @if ($invoice->payment_status !== 'paid')
                                    <a href="{{ route('payments.create', ['invoice' => $invoice->id]) }}" class="button button-primary">Enregistrer un paiement</a>
                                @endif
                            @endallowed
                            @allowed('credit_notes.issue')
                                @if ($canIssueCreditNote)
                                    <a href="{{ route('credit-notes.create', $invoice) }}" class="button button-secondary">Emettre un avoir</a>
                                @endif
                            @endallowed
                        @endif
                    </div>
                </div>
            </div>
        </section>

        <section class="premium-anchor-grid">
            <a href="{{ route('sales.index', ['search' => $invoice->invoice_number]) }}" class="premium-anchor-card">
                <strong>Retour au dossier commercial</strong>
                <div class="muted">Retrouver cette facture dans la liste filtree.</div>
            </a>
            @allowed('accounting.view')
                <a href="{{ route('accounting.journal-entries.index', ['source_type' => 'sales', 'search' => $invoice->invoice_number]) }}" class="premium-anchor-card">
                    <strong>Ecriture comptable</strong>
                    <div class="muted">Ouvrir directement les journaux lies a cette facture.</div>
                </a>
            @endallowed
            <a href="#stock-effects" class="premium-anchor-card">
                <strong>Impacts stock</strong>
                <div class="muted">Voir les sorties de stock generees par cette facture.</div>
            </a>
            <a href="#payments" class="premium-anchor-card">
                <strong>Recouvrement</strong>
                <div class="muted">Acceder directement aux paiements deja rattaches.</div>
            </a>
            @allowed('collections.view')
                <a href="{{ route('collections.show', $invoice) }}" class="premium-anchor-card">
                    <strong>Relances client</strong>
                    <div class="muted">Suivre les promesses de paiement et prochaines actions.</div>
                </a>
            @endallowed
        </section>

        <section class="premium-stat-grid">
            <article class="premium-stat-card"><div class="label">Workflow</div><div class="value">{{ $workflowLabel }}</div><div class="hint">Etat de la facture dans le flux metier.</div></article>
            <article class="premium-stat-card"><div class="label">Total facture</div><div class="value">{{ number_format((float) $invoice->total, 0, ',', ' ') }}</div><div class="hint">Montant total emis au client.</div></article>
            <article class="premium-stat-card"><div class="label">Montant paye</div><div class="value">{{ number_format((float) $invoice->amount_paid, 0, ',', ' ') }}</div><div class="hint">Encaissements deja relies a la facture.</div></article>
            <article class="premium-stat-card"><div class="label">Solde restant</div><div class="value">{{ number_format((float) $invoice->balance_due, 0, ',', ' ') }}</div><div class="hint">Montant encore ouvert au recouvrement.</div></article>
        </section>
    @if ($invoice->status === 'validated' || $invoice->status === 'pending_approval' || $invoice->creditNotes->isNotEmpty())
        <section class="card" style="margin-bottom:20px;">
            <div style="display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap; align-items:flex-start;">
                <div>
                    <h2 style="margin:0;">Correction controlee de la vente</h2>
                    <div class="muted" style="margin-top:8px;">
                        @if ($invoice->status === 'validated')
                            Une facture approuvee ne se supprime pas librement. Toute correction doit passer par un avoir client trace.
                        @elseif ($invoice->status === 'pending_approval')
                            Tant que la facture n est pas approuvee, elle peut etre stoppee proprement par une annulation sous permission dediee.
                        @else
                            Historique des avoirs deja emis sur cette facture.
                        @endif
                    </div>
                </div>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    @if ($invoice->status === 'pending_approval')
                        @allowed('sales.cancel')
                            <form method="POST" action="{{ route('sales.cancel', $invoice) }}">
                                @csrf
                                <button type="submit" class="button button-secondary">Annuler la facture en attente</button>
                            </form>
                        @endallowed
                    @elseif ($invoice->status === 'validated')
                        @allowed('credit_notes.issue')
                            @if ($canIssueCreditNote)
                                <a href="{{ route('credit-notes.create', $invoice) }}" class="button button-primary">Emettre un avoir client</a>
                            @endif
                        @endallowed
                    @endif
                </div>
            </div>

            @if ($invoice->status === 'validated' && ! $canIssueCreditNote)
                <div class="help" style="margin-top:12px;">
                    @if ((float) $invoice->balance_due <= 0)
                        Cette facture est deja soldee. Dans cette version, l avoir est reserve aux factures avec un solde encore ouvert.
                    @elseif ($creditableLinesCount <= 0)
                        Toutes les quantites eligibles ont deja ete avoirees sur cette facture.
                    @else
                        Aucun avoir suplementaire n est possible sur cette facture pour le moment.
                    @endif
                </div>
            @endif

            @if ($invoice->creditNotes->isNotEmpty())
                <div style="margin-top:18px; display:grid; gap:14px;">
                    @foreach ($invoice->creditNotes->sortByDesc('credit_note_date') as $creditNote)
                        <div style="padding:14px 16px; border:1px solid #efe4d3; border-radius:16px; background:#fbf6ef; display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:flex-start;">
                            <div>
                                <div style="font-weight:600;"><a href="{{ route('credit-notes.show', $creditNote) }}">{{ $creditNote->credit_note_number }}</a></div>
                                <div class="muted" style="margin-top:6px;">{{ $creditNote->credit_note_date?->format('d/m/Y') }} · {{ $creditNote->restock_items ? 'Retour stock inclus' : 'Sans reintegration stock' }}</div>
                                <div style="margin-top:6px;">{{ number_format((float) $creditNote->total, 0, ',', ' ') }} XOF</div>
                                @if ($creditNote->notes)
                                    <div class="help" style="margin-top:6px;">{{ $creditNote->notes }}</div>
                                @endif
                            </div>
                            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                                <a href="{{ route('credit-notes.show', $creditNote) }}" class="button button-secondary">Voir l avoir</a>
                                <a href="{{ route('credit-notes.print', $creditNote) }}" class="button button-secondary" target="_blank">PDF</a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    @if (isset($paymentPortal) && $invoice->status === 'validated')
        <section class="card" style="margin-bottom:20px;">
            <div style="display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap; align-items:flex-start;">
                <div>
                    <h2 style="margin:0;">Portail de reglement client</h2>
                    <div class="muted" style="margin-top:8px;">Partage le lien de reglement pour que le client transmette un avis de paiement. L equipe transforme ensuite cet avis en encaissement reel dans l ERP.</div>
                </div>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <a href="{{ $paymentPortal['view_url'] }}" class="button button-secondary" target="_blank" rel="noopener">Ouvrir le portail</a>
                    @if ($paymentPortal['whatsapp_url'])
                        <a href="{{ $paymentPortal['whatsapp_url'] }}" class="button button-primary" target="_blank" rel="noopener">Partager via WhatsApp</a>
                    @endif
                </div>
            </div>
            <div style="margin-top:14px;">
                <label for="invoice_payment_portal_url">Lien de reglement</label>
                <input id="invoice_payment_portal_url" type="text" value="{{ $paymentPortal['view_url'] }}" readonly onclick="this.select()" style="font-size:12px;">
            </div>

            @if (! empty($paymentPortal['payment_channels']))
                <div class="grid" style="margin-top:16px; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));">
                    @foreach ($paymentPortal['payment_channels'] as $channel)
                        <div class="summary-box">
                            <strong>{{ $channel['label'] }}</strong>
                            <div class="muted" style="margin-top:8px;">{{ $channel['target'] ?: 'Coordonnee interne a completer' }}</div>
                            @if ($channel['instructions'])
                                <div class="help" style="margin-top:8px;">{{ $channel['instructions'] }}</div>
                            @endif
                            @if ($channel['requires_reference'])
                                <div class="help" style="margin-top:8px;">Reference a rappeler : {{ $paymentPortal['payment_reference_hint'] }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($invoice->latestPortalAction && $invoice->latestPortalAction->action_type === 'invoice_payment_notice')
                <div class="card" style="padding:16px; margin-top:16px;">
                    @include('partials.portal-action-summary', ['portalAction' => $invoice->latestPortalAction, 'title' => 'Dernier avis de reglement client'])
                </div>
                @allowed('payments.validate')
                    <div class="actions" style="justify-content:flex-start; margin-top:14px;">
                        <a href="{{ route('payments.create', ['type' => 'customer_receipt', 'invoice' => $invoice->id, 'amount' => (float) $invoice->latestPortalAction->deposit_amount, 'method' => $invoice->latestPortalAction->deposit_method, 'reference' => $invoice->latestPortalAction->deposit_reference, 'notes' => trim('Avis portail '.$invoice->invoice_number.' · '.($invoice->latestPortalAction->deposit_note ?? '')), 'source' => 'portal_payment_notice']) }}" class="button button-primary">Creer l encaissement pre-rempli</a>
                    </div>
                @endallowed
            @endif

            @php
                $gatewayCallbacks = $invoice->paymentGatewayCallbacks->sortByDesc('received_at')->take(5);
            @endphp
            @if ($gatewayCallbacks->isNotEmpty())
                <div class="card" style="padding:16px; margin-top:16px;">
                    <div style="display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap; align-items:flex-start;">
                        <div>
                            <h3 style="margin:0;">Callbacks paiements entrants</h3>
                            <div class="muted" style="margin-top:8px;">Journal des retours temps reel provenant de Wave, Orange Money, Moov Money ou virement.</div>
                        </div>
                        @if ($invoice->latestPaymentGatewayCallback)
                            <span class="badge badge-muted">Dernier retour {{ $invoice->latestPaymentGatewayCallback->received_at?->format('d/m/Y H:i') }}</span>
                        @endif
                    </div>
                    <div style="margin-top:16px; display:grid; gap:14px;">
                        @foreach ($gatewayCallbacks as $callback)
                            @php
                                $gatewayTone = $callback->gateway_status === 'success' ? 'badge-success' : ($callback->gateway_status === 'failed' ? 'badge-danger' : 'badge-warning');
                                $processingTone = in_array($callback->processing_status, ['auto_recorded'], true) ? 'badge-success' : (in_array($callback->processing_status, ['error', 'rejected'], true) ? 'badge-danger' : 'badge-warning');
                            @endphp
                            <div style="padding:14px 16px; border:1px solid #efe4d3; border-radius:16px; background:#fffaf3; display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap; align-items:flex-start;">
                                <div>
                                    <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                                        <strong>{{ $callback->channelLabel() }}</strong>
                                        <span class="badge {{ $gatewayTone }}">{{ $callback->gatewayStatusLabel() }}</span>
                                        <span class="badge {{ $processingTone }}">{{ $callback->processingStatusLabel() }}</span>
                                    </div>
                                    <div class="muted" style="margin-top:8px;">Ref {{ $callback->reference }}</div>
                                    @if ($callback->external_reference)
                                        <div class="help" style="margin-top:6px;">Ref externe {{ $callback->external_reference }}</div>
                                    @endif
                                    <div style="margin-top:8px;">{{ number_format((float) $callback->amount, 0, ',', ' ') }} XOF · recu le {{ $callback->received_at?->format('d/m/Y H:i') }}</div>
                                    <div class="help" style="margin-top:8px;">{{ collect([$callback->payer_name, $callback->payer_phone, $callback->cashAccount?->name])->filter()->implode(' · ') ?: 'Aucune precision payeur supplementaire' }}</div>
                                    @if ($callback->error_message)
                                        <div class="help" style="margin-top:8px; color:#9f1239;">{{ $callback->error_message }}</div>
                                    @endif
                                </div>
                                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                                    @if ($callback->payment)
                                        <a href="{{ route('payments.show', $callback->payment) }}" class="button button-secondary">Voir l encaissement</a>
                                    @endif
                                    @if (! $callback->payment && $callback->gateway_status === 'success')
                                        <a href="{{ route('payments.create', ['type' => 'customer_receipt', 'invoice' => $invoice->id, 'amount' => (float) $callback->amount, 'method' => $callback->channel, 'reference' => $callback->reference, 'notes' => trim('Callback '.$callback->channelLabel().' '.$invoice->invoice_number.' · '.($callback->notes ?? '')), 'source' => 'gateway_callback']) }}" class="button button-primary">Creer l encaissement pre-rempli</a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </section>
    @endif

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
                            @php
                                $followUpMeta = [];
                            @endphp
                            @if ($followUp->promised_date)
                                @php
                                    $followUpMeta[] = 'Promesse '.$followUp->promised_date->format('d/m/Y');
                                @endphp
                            @endif
                            @if ($followUp->promised_amount)
                                @php
                                    $followUpMeta[] = number_format((float) $followUp->promised_amount, 0, ',', ' ').' XOF';
                                @endphp
                            @endif
                            @if ($followUp->next_action_date)
                                @php
                                    $followUpMeta[] = 'Prochaine action '.$followUp->next_action_date->format('d/m/Y');
                                @endphp
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
                <div><strong>Statut</strong><div class="muted">{{ $workflowLabel }}</div></div>
                <div><strong>Creee par</strong><div class="muted">{{ $invoice->creator?->name ?? 'Systeme' }}</div></div>
                <div><strong>Approuvee par</strong><div class="muted">{{ $invoice->approver?->name ?? 'Non approuvee' }}</div></div>
                <div><strong>Date d approbation</strong><div class="muted">{{ $invoice->approved_at?->format('d/m/Y H:i') ?? 'Non disponible' }}</div></div>
                <div><strong>Annulee par</strong><div class="muted">{{ $invoice->cancelledBy?->name ?? 'Non annulee' }}</div></div>
                <div><strong>Date d annulation</strong><div class="muted">{{ $invoice->cancelled_at?->format('d/m/Y H:i') ?? 'Non disponible' }}</div></div>
            </div>
            @include('partials.approval-steps', ['approvalSteps' => $invoice->approvalSteps])
        </section>

        <section class="card">
            <h2 style="margin-top:0;">Synthese d impact</h2>
            <div class="grid">
                <div><strong>Statut paiement</strong><div class="muted">{{ $invoice->payment_status === 'paid' ? 'Payee' : ($invoice->payment_status === 'partial' ? 'Partielle' : 'Impayee') }}</div></div>
                <div><strong>Entrepot</strong><div class="muted">{{ $invoice->warehouse?->name ?? 'Entrepot par defaut' }}</div></div>
                <div><strong>Effet stock</strong><div class="muted">{{ $invoice->status === 'validated' ? 'Stock mis a jour' : ($invoice->status === 'cancelled' ? 'Aucun effet stock final' : 'En attente d approbation finale') }}</div></div>
                <div><strong>Effet comptable</strong><div class="muted">{{ $invoice->status === 'validated' ? 'Ecriture generee' : ($invoice->status === 'cancelled' ? 'Aucune ecriture finale' : 'Ecriture en attente') }}</div></div>
                <div><strong>Nombre de mouvements</strong><div class="muted">{{ $stockMovements->count() }} mouvement(s) de stock lie(s)</div></div>
                <div><strong>Correction requise</strong><div class="muted">{{ $invoice->status === 'validated' ? 'Avoir client seulement' : ($invoice->status === 'cancelled' ? 'Dossier clos' : 'Annulation possible sous permission') }}</div></div>
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
            @if ($invoice->status === 'cancelled')
                <p class="muted">Aucun paiement possible: cette facture a ete annulee avant validation finale.</p>
            @elseif ($invoice->status !== 'validated')
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






