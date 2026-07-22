@extends('layouts.print')

@section('title', 'Ticket POS - Nema ERP')

@section('content')
    @php
        $customerLabel = $businessVocabulary['client'] ?? 'Client';
        $productLabel = $businessVocabulary['product'] ?? 'Produit';
        $saleLabel = $businessVocabulary['sale'] ?? 'Vente';
        $stockLabel = $businessVocabulary['stock'] ?? 'Stock';
        $cashierLabel = $businessVocabulary['cashier'] ?? 'Caissier';
        $payments = $payments ?? collect();
        $payment = $payment ?? null;
        $paymentLabel = $payments->count() > 1 ? 'Mixte' : ($payment?->method ? str($payment->method)->replace('_', ' ')->title() : 'N/A');
        $refundedTotal = (float) $invoice->posReturns->sum('total');
        $netKept = max((float) $invoice->total - $refundedTotal, 0);
        $preparationTickets = $preparationTickets ?? collect();
        $receiptProfile = $receiptProfile ?? null;
    @endphp


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
        .pos-ticket-logo {
            display: block;
            max-width: 46mm;
            max-height: 18mm;
            object-fit: contain;
            margin: 0 auto 6px;
        }
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
        .pos-a4-print {
            display: none;
        }
        .pos-a4-header {
            display: grid;
            grid-template-columns: minmax(0, 1.25fr) minmax(240px, .75fr);
            gap: 24px;
            align-items: start;
            padding-bottom: 18px;
            border-bottom: 2px solid var(--line);
        }
        .pos-a4-title {
            margin: 0;
            font-size: 28px;
            letter-spacing: -.03em;
        }
        .pos-a4-logo {
            width: 78px;
            max-height: 78px;
            object-fit: contain;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 6px;
            background: #fff;
        }
        .pos-a4-brand {
            display: flex;
            gap: 14px;
            align-items: flex-start;
        }
        .pos-a4-boxes {
            display: grid;
            gap: 14px;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            margin-top: 18px;
        }
        .pos-a4-box {
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 12px 14px;
        }
        .pos-a4-box h2 {
            margin: 0 0 8px;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--muted);
        }
        .pos-a4-total-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 320px;
            gap: 20px;
            align-items: start;
            margin-top: 18px;
        }
        .pos-a4-notes {
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 12px 14px;
            min-height: 96px;
        }
        @media print {
            @page {
                size: A4;
                margin: 12mm;
            }
            .pos-ticket-layout {
                display: none;
            }
            .pos-a4-print {
                display: block;
            }
            .pos-ticket-toolbar {
                display: none;
            }
            .pos-a4-boxes {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
            .pos-a4-total-grid {
                grid-template-columns: minmax(0, 1fr) 300px;
            }
            .signatures {
                break-inside: avoid;
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
        <button type="button" class="button button-primary" onclick="window.print()">Imprimer A4</button>
        <a href="{{ route('pos.receipt.thermal', $invoice) }}" class="button">Version thermique</a>
        @if ($invoice->pos_session_id)
            <a href="{{ route('pos.sales.create', ['session' => $invoice->pos_session_id]) }}" class="button">Retour caisse</a>
        @endif
        @if ($preparationTickets->isNotEmpty())
            <a href="{{ route('pos.preparation.index') }}" class="button">Board preparation</a>
        @endif
    </div>

    <section class="pos-a4-print">
        <header class="pos-a4-header">
            <div>
                <div class="doc-chip">Facture / detail POS</div>
                <h1 class="pos-a4-title">{{ $invoice->invoice_number }}</h1>
                <div class="meta">Document imprime le {{ now()->format('d/m/Y H:i') }}</div>
            </div>
            <div class="pos-a4-brand">
                @if ($invoice->company?->logo_path)
                    <img class="pos-a4-logo" src="{{ asset('storage/'.$invoice->company->logo_path) }}" alt="Logo {{ $invoice->company->name }}">
                @endif
                <div>
                    <strong>{{ $invoice->company?->legal_name ?: $invoice->company?->name }}</strong>
                    <div class="meta">{{ $invoice->company?->address }}</div>
                    <div class="meta">Tel : {{ $invoice->company?->phone ?: 'N/A' }} @if($invoice->company?->email) · {{ $invoice->company->email }} @endif</div>
                    <div class="meta">NIF : {{ $invoice->company?->nif ?: 'N/A' }} · RCCM : {{ $invoice->company?->rccm ?: 'N/A' }}</div>
                </div>
            </div>
        </header>

        <div class="pos-a4-boxes">
            <div class="pos-a4-box">
                <h2>{{ $customerLabel }}</h2>
                <div><strong>{{ $invoice->customer?->name ?: $customerLabel.' comptoir' }}</strong></div>
                <div class="meta">{{ $invoice->customer?->phone }}</div>
                <div class="meta">{{ $invoice->customer?->email }}</div>
            </div>
            <div class="pos-a4-box">
                <h2>{{ $saleLabel }}</h2>
                <div>Date : <strong>{{ $invoice->invoice_date?->format('d/m/Y') }}</strong></div>
                <div>{{ $cashierLabel }} : <strong>{{ $invoice->creator?->name }}</strong></div>
                <div>Session : <strong>{{ $invoice->posSession?->session_number ?: '-' }}</strong></div>
            </div>
            <div class="pos-a4-box">
                <h2>Point de vente</h2>
                <div>Agence : <strong>{{ $invoice->branch?->name ?: '-' }}</strong></div>
                <div>Depot : <strong>{{ $invoice->warehouse?->name ?: '-' }}</strong></div>
                <div>Paiement : <strong>{{ $paymentLabel }}</strong></div>
            </div>
        </div>

        <table>
            <thead>
            <tr>
                <th>Code</th>
                <th>{{ $productLabel }}</th>
                <th class="right">Qte</th>
                <th class="right">PU</th>
                <th class="right">Remise</th>
                <th class="right">Total</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td>{{ $item->product?->barcode ?: $item->product?->sku ?: '-' }}</td>
                    <td>
                        <strong>{{ $item->description }}</strong>
                        @php
                            $returnedQtyA4 = (float) $item->posReturnItems->sum('qty');
                        @endphp
                        @if ($returnedQtyA4 > 0)
                            <div class="meta">Retour : {{ number_format($returnedQtyA4, 3, ',', ' ') }}</div>
                        @endif
                    </td>
                    <td class="right">{{ number_format((float) $item->qty, 3, ',', ' ') }}</td>
                    <td class="right">{{ number_format((float) $item->unit_price, 0, ',', ' ') }}</td>
                    <td class="right">{{ number_format((float) $item->discount_total, 0, ',', ' ') }}</td>
                    <td class="right"><strong>{{ number_format((float) $item->line_total, 0, ',', ' ') }} XOF</strong></td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <div class="pos-a4-total-grid">
            <div>
                <section class="pos-a4-notes">
                    <strong>Notes</strong>
                    <div class="meta" style="margin-top:8px;">{{ $invoice->notes ?: 'Aucune note particuliere.' }}</div>
                </section>
                <table>
                    <thead>
                    <tr>
                        <th>Paiement</th>
                        <th>Reference</th>
                        <th>Compte</th>
                        <th class="right">Montant</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($payments as $ticketPayment)
                        <tr>
                            <td>{{ str($ticketPayment->method)->replace('_', ' ')->title() }}</td>
                            <td>{{ $ticketPayment->reference ?: $ticketPayment->payment_number ?: '-' }}</td>
                            <td>{{ $ticketPayment->cashAccount?->name ?: '-' }}</td>
                            <td class="right">{{ number_format((float) $ticketPayment->amount, 0, ',', ' ') }} XOF</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="meta">Aucun paiement rattache.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <table class="totals">
                <tr><td>Sous-total</td><td class="right">{{ number_format((float) $invoice->subtotal, 0, ',', ' ') }} XOF</td></tr>
                <tr><td>Remise</td><td class="right">-{{ number_format((float) $invoice->discount_total, 0, ',', ' ') }} XOF</td></tr>
                <tr class="grand-total"><td>Total facture</td><td class="right">{{ number_format((float) $invoice->total, 0, ',', ' ') }} XOF</td></tr>
                <tr><td>Encaisse</td><td class="right">{{ number_format((float) $invoice->amount_paid, 0, ',', ' ') }} XOF</td></tr>
                @if ($refundedTotal > 0)
                    <tr><td>Rembourse</td><td class="right">{{ number_format($refundedTotal, 0, ',', ' ') }} XOF</td></tr>
                    <tr><td>Net conserve</td><td class="right">{{ number_format($netKept, 0, ',', ' ') }} XOF</td></tr>
                @endif
                <tr><td>Reste</td><td class="right">{{ number_format((float) $invoice->balance_due, 0, ',', ' ') }} XOF</td></tr>
            </table>
        </div>

        @if ($invoice->posReturns->isNotEmpty())
            <table>
                <thead>
                <tr>
                    <th>Retour</th>
                    <th>Date</th>
                    <th>Echange</th>
                    <th class="right">Montant</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($invoice->posReturns as $return)
                    <tr>
                        <td>{{ $return->return_number }}</td>
                        <td>{{ $return->return_date?->format('d/m/Y') }}</td>
                        <td>{{ $return->exchangeInvoice?->invoice_number ?: '-' }}</td>
                        <td class="right">{{ number_format((float) $return->total, 0, ',', ' ') }} XOF</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif

        <div class="signatures">
            <div class="signature-box">{{ $customerLabel }}</div>
            <div class="signature-box">Caisse / controle</div>
        </div>

        <div class="footer">
            Document de {{ strtolower($saleLabel) }} imprime depuis Nema ERP. Conservez ce detail avec le ticket de caisse et les justificatifs de paiement.
        </div>
    </section>

    <div class="pos-ticket-layout">
        <section class="pos-ticket-card">
            <div class="pos-ticket-center">
                <div class="doc-chip">Ticket caisse</div>
                @if ($receiptProfile?->receipt_logo_path)
                    <img class="pos-ticket-logo" src="{{ asset('storage/'.$receiptProfile->receipt_logo_path) }}" alt="Logo">
                @endif
                @if ($receiptProfile?->receipt_header)
                    <div class="pos-ticket-subtitle">{{ $receiptProfile->receipt_header }}</div>
                @endif
                <h1 class="pos-ticket-title">{{ $invoice->company?->name }}</h1>
                <div class="pos-ticket-subtitle">{{ $invoice->branch?->name }}</div>
                @if (($receiptProfile?->receipt_show_address ?? true) && $invoice->company?->address)
                    <div class="pos-ticket-subtitle">{{ $invoice->company->address }}</div>
                @endif
                <div class="pos-ticket-subtitle">{{ $invoice->invoice_number }}</div>
                <div class="pos-ticket-subtitle">Session {{ $invoice->posSession?->session_number }}</div>
            </div>

            <div class="pos-ticket-divider"></div>
            <div class="pos-ticket-line"><span>Date</span><strong>{{ $invoice->invoice_date?->format('d/m/Y') }} {{ $invoice->created_at?->format('H:i') }}</strong></div>
            <div class="pos-ticket-line"><span>{{ $customerLabel }}</span><strong>{{ $invoice->customer?->name ?? $customerLabel.' comptoir' }}</strong></div>
            @if ($receiptProfile?->receipt_show_cashier ?? true)
                <div class="pos-ticket-line"><span>{{ $cashierLabel }}</span><strong>{{ $invoice->creator?->name }}</strong></div>
            @endif
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
                    @php
                        $returnedQty = (float) $item->posReturnItems->sum('qty');
                    @endphp
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
                {{ $receiptProfile?->receipt_footer ?: 'Merci pour votre achat' }}<br>
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
                    <div class="label">{{ $cashierLabel }}</div>
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
