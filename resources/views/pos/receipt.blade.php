@extends('layouts.print')

@section('title', 'Ticket POS - Nema ERP')

@section('content')
    @php($payments = $payments ?? collect())
    @php($payment = $payment ?? null)
    @php($paymentLabel = $payments->count() > 1 ? 'Mixte' : ($payment?->method ? str($payment->method)->replace('_', ' ')->title() : 'N/A'))
    @php($refundedTotal = (float) $invoice->posReturns->sum('total'))
    @php($netKept = max((float) $invoice->total - $refundedTotal, 0))
    @php($preparationTickets = $preparationTickets ?? collect())


    <style>
        .pos-ticket-layout {
            display: grid;
            gap: 22px;
            grid-template-columns: minmax(300px, 360px) minmax(0, 1fr);
            align-items: start;
        }
        .pos-ticket-card {
            width: 80mm;
            max-width: 100%;
            margin: 0 auto;
            border: 1px solid #d7dde6;
            border-radius: 18px;
            background: #fff;
            padding: 14px 12px 16px;
            box-shadow: 0 16px 30px rgba(15, 23, 42, 0.08);
            font-size: 11px;
            line-height: 1.28;
        }
        .pos-ticket-center { text-align: center; }
        .pos-ticket-title {
            margin: 0;
            font-size: 16px;
            font-weight: 800;
            letter-spacing: -0.02em;
        }
        .pos-ticket-subtitle {
            margin-top: 2px;
            color: #667085;
            font-size: 10px;
        }
        .pos-ticket-divider {
            border-top: 1px dashed #98a2b3;
            margin: 9px 0;
        }
        .pos-ticket-line {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            padding: 2px 0;
        }
        .pos-ticket-line strong { font-weight: 800; }
        .pos-ticket-items { display: grid; gap: 8px; }
        .pos-ticket-item { display: grid; gap: 2px; }
        .pos-ticket-item-name {
            font-weight: 700;
            color: #101828;
            word-break: break-word;
        }
        .pos-ticket-item-meta {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            color: #667085;
            font-size: 10px;
        }
        .pos-ticket-item-total {
            text-align: right;
            font-size: 11px;
            font-weight: 800;
            color: #101828;
        }
        .pos-ticket-footer {
            margin-top: 8px;
            text-align: center;
            color: #667085;
            font-size: 10px;
        }
        .pos-ticket-aside {
            display: grid;
            gap: 16px;
        }
        .pos-ticket-panel {
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 16px 18px;
            background: linear-gradient(180deg, #ffffff 0%, #fbfcfd 100%);
        }
        .pos-ticket-panel h2 {
            margin: 0 0 10px;
            font-size: 16px;
        }
        .pos-ticket-kpis {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        }
        .pos-ticket-kpi {
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 12px 14px;
            background: #fff;
        }
        .pos-ticket-kpi .label {
            color: #667085;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .05em;
            font-weight: 700;
        }
        .pos-ticket-kpi .value {
            margin-top: 6px;
            font-size: 18px;
            font-weight: 800;
            color: #101828;
        }
        .pos-ticket-return {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid #eaecef;
        }
        .pos-ticket-return:last-child { border-bottom: none; }
        .pos-ticket-toolbar {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }
        @media print {
            .pos-ticket-layout {
                grid-template-columns: 1fr;
                gap: 0;
            }
            .pos-ticket-card {
                width: 72mm;
                border: 0;
                border-radius: 0;
                box-shadow: none;
                padding: 6px 4px 8px;
            }
            .pos-ticket-aside,
            .pos-ticket-toolbar {
                display: none;
            }
        }
        @media (max-width: 900px) {
            .pos-ticket-layout {
                grid-template-columns: 1fr;
            }
            .pos-ticket-card {
                width: min(100%, 82mm);
            }
        }
    </style>

    <div class="pos-ticket-toolbar">
        <a href="{{ route('pos.receipt.thermal', $invoice) }}" class="button">Version thermique</a>
        @if ($preparationTickets->isNotEmpty())
            <a href="{{ route('pos.preparation.index') }}" class="button">Board preparation</a>
        @endif
    </div>

    <div class="pos-ticket-layout">
        <section class="pos-ticket-card">
            <div class="pos-ticket-center">
                <div class="doc-chip">Ticket caisse</div>
                <h1 class="pos-ticket-title">{{ $invoice->company?->name }}</h1>
                <div class="pos-ticket-subtitle">{{ $invoice->branch?->name }}</div>
                <div class="pos-ticket-subtitle">{{ $invoice->invoice_number }}</div>
                <div class="pos-ticket-subtitle">Session {{ $invoice->posSession?->session_number }}</div>
            </div>

            <div class="pos-ticket-divider"></div>
            <div class="pos-ticket-line"><span>Date</span><strong>{{ $invoice->invoice_date?->format('d/m/Y') }} {{ $invoice->created_at?->format('H:i') }}</strong></div>
            <div class="pos-ticket-line"><span>Client</span><strong>{{ $invoice->customer?->name }}</strong></div>
            <div class="pos-ticket-line"><span>Caissier</span><strong>{{ $invoice->creator?->name }}</strong></div>
            <div class="pos-ticket-line"><span>Mode</span><strong>{{ $paymentLabel }}</strong></div>
            @if ($payments->isNotEmpty())
                @foreach ($payments as $ticketPayment)
                    <div class="pos-ticket-line"><span>{{ $payments->count() > 1 ? 'Paiement '.$loop->iteration : 'Paiement' }}</span><strong>{{ str($ticketPayment->method)->replace('_', ' ')->title() }} · {{ number_format((float) $ticketPayment->amount, 0, ',', ' ') }} XOF</strong></div>
                    @if ($ticketPayment->payment_number)
                        <div class="pos-ticket-line"><span>Ref.</span><span>{{ $ticketPayment->payment_number }}</span></div>
                    @endif
                @endforeach
            @endif

            <div class="pos-ticket-divider"></div>
            <div class="pos-ticket-items">
                @foreach ($invoice->items as $item)
                    @php($returnedQty = (float) $item->posReturnItems->sum('qty'))
                    <div class="pos-ticket-item">
                        <div class="pos-ticket-item-name">{{ $item->description }}</div>
                        <div class="pos-ticket-item-meta">
                            <span>{{ number_format((float) $item->qty, 3, ',', ' ') }} x {{ number_format((float) $item->unit_price, 0, ',', ' ') }}</span>
                            <span>{{ $item->product?->barcode ?: $item->product?->sku }}</span>
                        </div>
                        @if ((float) $item->discount_total > 0 || $returnedQty > 0)
                            <div class="pos-ticket-item-meta">
                                <span>
                                    @if ((float) $item->discount_total > 0)
                                        Remise -{{ number_format((float) $item->discount_total, 0, ',', ' ') }}
                                    @endif
                                </span>
                                <span>
                                    @if ($returnedQty > 0)
                                        Retour {{ number_format($returnedQty, 3, ',', ' ') }}
                                    @endif
                                </span>
                            </div>
                        @endif
                        <div class="pos-ticket-item-total">{{ number_format((float) $item->line_total, 0, ',', ' ') }} XOF</div>
                    </div>
                @endforeach
            </div>

            <div class="pos-ticket-divider"></div>
            <div class="pos-ticket-line"><span>Sous-total</span><strong>{{ number_format((float) $invoice->subtotal, 0, ',', ' ') }}</strong></div>
            @if ($invoice->hasDiscount())
                <div class="pos-ticket-line"><span>Remise</span><strong>-{{ number_format((float) $invoice->discount_total, 0, ',', ' ') }}</strong></div>
            @endif
            <div class="pos-ticket-line"><span>Total</span><strong>{{ number_format((float) $invoice->total, 0, ',', ' ') }}</strong></div>
            <div class="pos-ticket-line"><span>Encaisse</span><strong>{{ number_format((float) $invoice->amount_paid, 0, ',', ' ') }}</strong></div>
            @if ((float) $invoice->pos_cash_received > 0)
                <div class="pos-ticket-line"><span>Montant recu</span><strong>{{ number_format((float) $invoice->pos_cash_received, 0, ',', ' ') }}</strong></div>
            @endif
            @if ((float) $invoice->pos_change_due > 0)
                <div class="pos-ticket-line"><span>Monnaie rendue</span><strong>{{ number_format((float) $invoice->pos_change_due, 0, ',', ' ') }}</strong></div>
            @endif
            @if ($refundedTotal > 0)
                <div class="pos-ticket-line"><span>Rembourse</span><strong>{{ number_format($refundedTotal, 0, ',', ' ') }}</strong></div>
                <div class="pos-ticket-line"><span>Net conserve</span><strong>{{ number_format($netKept, 0, ',', ' ') }}</strong></div>
            @endif
            <div class="pos-ticket-line"><span>Reste</span><strong>{{ number_format((float) $invoice->balance_due, 0, ',', ' ') }}</strong></div>

            @if ($invoice->notes)
                <div class="pos-ticket-divider"></div>
                <div style="font-size:10px; color:#475467;">{{ $invoice->notes }}</div>
            @endif

            <div class="pos-ticket-divider"></div>
            <div class="pos-ticket-footer">
                Merci pour votre achat<br>
                Ticket genere depuis le point de vente Nema ERP
            </div>
        </section>

        <aside class="pos-ticket-aside">
            <section class="pos-ticket-panel">
                <h2>Lecture rapide</h2>
                <div class="pos-ticket-kpis">
                    <div class="pos-ticket-kpi">
                        <div class="label">Total</div>
                        <div class="value">{{ number_format((float) $invoice->total, 0, ',', ' ') }}</div>
                    </div>
                    <div class="pos-ticket-kpi">
                        <div class="label">Paiement</div>
                        <div class="value">{{ $paymentLabel }}</div>
                    </div>
                    <div class="pos-ticket-kpi">
                        <div class="label">Entrepot</div>
                        <div class="value">{{ $invoice->warehouse?->name ?? 'Defaut' }}</div>
                    </div>
                    <div class="pos-ticket-kpi">
                        <div class="label">Caissier</div>
                        <div class="value">{{ $invoice->creator?->name }}</div>
                    </div>
                </div>
            </section>

            @if ($preparationTickets->isNotEmpty())
                <section class="pos-ticket-panel">
                    <h2>Preparation</h2>
                    <div class="pos-history-list is-compact">
                        @foreach ($preparationTickets as $ticket)
                            <div class="pos-history-item is-compact">
                                <div class="pos-history-main">
                                    <div class="pos-history-title-row">
                                        <strong>{{ $ticket->ticket_number }}</strong>
                                        <div class="pos-history-meta">{{ $ticket->target_area ?: 'Preparation' }}</div>
                                    </div>
                                    <div class="pos-history-tags">
                                        <span class="pos-mini-chip">{{ str($ticket->status)->replace('_', ' ')->title() }}</span>
                                        @if ($ticket->printer)
                                            <span class="pos-mini-chip">Impr. {{ $ticket->printer->name }}</span>
                                        @endif
                                        @if ($ticket->display)
                                            <span class="pos-mini-chip">Display {{ $ticket->display->name }}</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="pos-inline-actions">
                                    <a href="{{ route('pos.preparation.print', [
                                        'ticket' => $ticket,
                                        'auto_print' => 1,
                                        'return_to' => route('pos.receipt', $invoice),
                                        'return_label' => 'Retour ticket',
                                    ]) }}" class="button">Imprimer</a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($invoice->posReturns->isNotEmpty())
                <section class="pos-ticket-panel">
                    <h2>Retours lies</h2>
                    @foreach ($invoice->posReturns as $return)
                        <div class="pos-ticket-return">
                            <div>
                                <strong>{{ $return->return_number }}</strong>
                                <div class="muted" style="margin-top:4px;">{{ $return->return_date?->format('d/m/Y') }} · {{ str($return->method)->replace('_', ' ')->title() }}</div>
                                @if ($return->exchangeInvoice)
                                    <div class="muted" style="margin-top:4px;">Echange {{ $return->exchangeInvoice->invoice_number }}</div>
                                @endif
                            </div>
                            <strong>{{ number_format((float) $return->total, 0, ',', ' ') }} XOF</strong>
                        </div>
                    @endforeach
                </section>
            @endif
        </aside>
    </div>
@endsection
