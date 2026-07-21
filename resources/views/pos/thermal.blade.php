@php
    $nextReceiptUrl = request('next') ?: route('pos.receipt', $invoice);
    $cashierReturnUrl = request('return_to') ?: ($invoice->pos_session_id
        ? route('pos.sales.create', ['session' => $invoice->pos_session_id])
        : route('pos.index'));
    $customerLabel = $businessVocabulary['client'] ?? 'Client';
    $cashierLabel = $businessVocabulary['cashier'] ?? 'Caissier';
    $payments = $payments ?? collect();
    $payment = $payment ?? null;
    $paymentLabel = $payments->count() > 1 ? 'Mixte' : ($payment?->method ? str($payment->method)->replace('_', ' ')->title() : 'N/A');
    $refundedTotal = (float) $invoice->posReturns->sum('total');
    $receiptProfile = $receiptProfile ?? null;
    $itemCount = (float) $invoice->items->sum('qty');
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    @include('partials.security-csp-meta')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ticket thermique {{ $invoice->invoice_number }}</title>
    <style>
        body {
            margin: 0;
            background: #eef2f6;
            color: #101828;
            font-family: Arial, Helvetica, sans-serif;
        }
        .toolbar {
            display: flex;
            justify-content: center;
            gap: 10px;
            padding: 10px;
            flex-wrap: wrap;
        }
        .button {
            display: inline-block;
            padding: 8px 12px;
            border-radius: 8px;
            background: #ffffff;
            border: 1px solid #d0d5dd;
            text-decoration: none;
            color: #101828;
            font-weight: 700;
            font-size: 12px;
        }
        .notice {
            width: min(72mm, calc(100% - 24px));
            margin: 0 auto 12px;
            padding: 10px 12px;
            border-radius: 10px;
            background: #fff4d6;
            border: 1px solid #f5d77a;
            color: #7a2e0b;
            font-size: 12px;
            line-height: 1.4;
        }
        .notice strong {
            display: block;
            margin-bottom: 4px;
        }
        .ticket {
            width: 72mm;
            margin: 0 auto 16px;
            background: #fff;
            padding: 6px 6px 8px;
            box-shadow: 0 10px 18px rgba(0, 0, 0, 0.08);
            font-size: 10px;
            line-height: 1.18;
        }
        .center { text-align: center; }
        .brand {
            font-size: 13px;
            font-weight: 800;
            letter-spacing: .03em;
            text-transform: uppercase;
        }
        .ticket-title {
            margin-top: 4px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .ticket-logo {
            display: block;
            max-width: 46mm;
            max-height: 18mm;
            object-fit: contain;
            margin: 0 auto 5px;
        }
        .muted { color: #667085; }
        .strong { font-weight: 700; }
        .divider { border-top: 1px dashed #98a2b3; margin: 6px 0; }
        .line { display: flex; justify-content: space-between; gap: 8px; padding: 1px 0; }
        .line span:first-child { color: #475467; }
        .item { padding: 3px 0; }
        .item-name { font-weight: 700; word-break: break-word; }
        .item-meta { display: flex; justify-content: space-between; gap: 8px; font-size: 9px; color: #667085; }
        .item-total { text-align: right; font-weight: 700; }
        .micro { font-size: 9px; line-height: 1.15; }
        .total-line {
            font-size: 13px;
            padding-top: 3px;
            padding-bottom: 3px;
        }
        .footer-ref { margin-top: 4px; font-size: 8.5px; word-break: break-all; }
        @media print {
            body { background: #fff; }
            .toolbar, .notice { display: none; }
            .ticket { box-shadow: none; margin: 0 auto; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" class="button" onclick="window.nemaPosPrintReceipt()">Imprimer</button>
        <a href="{{ $nextReceiptUrl }}" class="button">Ticket detaille</a>
        <a href="{{ $cashierReturnUrl }}" class="button">{{ request()->boolean('from_pos') ? 'Retour caisse' : 'Retour' }}</a>
    </div>

    @if (request()->boolean('from_pos'))
        <div class="notice" id="print-notice">
            <strong>Ticket pret a imprimer</strong>
            <span>Si l'impression ne se lance pas sur cet appareil, appuyez sur "Imprimer". Apres impression, la caisse reviendra automatiquement sur une nouvelle commande.</span>
        </div>
    @endif

    <div class="ticket">
        @if ($receiptProfile?->receipt_logo_path)
            <img class="ticket-logo" src="{{ asset('storage/'.$receiptProfile->receipt_logo_path) }}" alt="" onerror="this.remove()">
        @endif
        @if ($receiptProfile?->receipt_header)
            <div class="center muted">{{ $receiptProfile->receipt_header }}</div>
        @endif
        <div class="center brand">{{ $invoice->company?->name }}</div>
        @if (($receiptProfile?->receipt_show_address ?? true) && $invoice->company?->address)
            <div class="center muted">{{ $invoice->company->address }}</div>
        @endif
        <div class="center muted micro">
            {{ $invoice->branch?->name }} · Tel: {{ $invoice->company?->phone ?: 'N/A' }}
        </div>
        <div class="center muted micro">
            @if ($invoice->company?->nif) NIF: {{ $invoice->company->nif }} @endif
            @if ($invoice->company?->rccm) · RCCM: {{ $invoice->company->rccm }} @endif
        </div>
        <div class="center ticket-title">Ticket de caisse</div>
        <div class="center strong">{{ $invoice->invoice_number }}</div>

        <div class="divider"></div>
        <div class="line"><span>Date</span><strong>{{ $invoice->invoice_date?->format('d/m/Y') }} {{ $invoice->created_at?->format('H:i') }}</strong></div>
        <div class="line"><span>{{ $customerLabel }}</span><strong>{{ $invoice->customer?->name ?? $customerLabel.' comptoir' }}</strong></div>
        @if ($receiptProfile?->receipt_show_cashier ?? true)
            <div class="line"><span>{{ $cashierLabel }}</span><strong>{{ $invoice->creator?->name }}</strong></div>
        @endif
        <div class="line"><span>Caisse</span><strong>{{ $invoice->posSession?->session_number }}</strong></div>
        <div class="line"><span>Paiement</span><strong>{{ $paymentLabel }}</strong></div>
        @if ($payments->isNotEmpty())
            @foreach ($payments as $ticketPayment)
                <div class="line micro"><span>{{ str($ticketPayment->method)->replace('_', ' ')->title() }}</span><strong>{{ number_format((float) $ticketPayment->amount, 0, ',', ' ') }} XOF</strong></div>
            @endforeach
        @endif

        <div class="divider"></div>
        @foreach ($invoice->items as $item)
            @php
                $returnedQty = (float) $item->posReturnItems->sum('qty');
            @endphp
            <div class="item">
                <div class="item-name">{{ $item->description }}</div>
                <div class="item-meta">
                    <span>{{ number_format((float) $item->qty, 3, ',', ' ') }} x {{ number_format((float) $item->unit_price, 0, ',', ' ') }}</span>
                    <span>{{ number_format((float) $item->line_total, 0, ',', ' ') }} XOF</span>
                </div>
                @if ((float) $item->discount_total > 0 || $returnedQty > 0)
                    <div class="item-meta">
                        <span>@if ((float) $item->discount_total > 0) Remise -{{ number_format((float) $item->discount_total, 0, ',', ' ') }} @endif</span>
                        <span>@if ($returnedQty > 0) Retour {{ number_format($returnedQty, 3, ',', ' ') }} @endif</span>
                    </div>
                @endif
            </div>
        @endforeach

        <div class="divider"></div>
        <div class="line"><span>Sous-total</span><strong>{{ number_format((float) $invoice->subtotal, 0, ',', ' ') }}</strong></div>
        @if ($invoice->hasDiscount())
            <div class="line"><span>Remise</span><strong>-{{ number_format((float) $invoice->discount_total, 0, ',', ' ') }}</strong></div>
        @endif
        <div class="line strong total-line"><span>Total</span><strong>{{ number_format((float) $invoice->total, 0, ',', ' ') }} XOF</strong></div>
        <div class="line"><span>Encaisse</span><strong>{{ number_format((float) $invoice->amount_paid, 0, ',', ' ') }}</strong></div>
        @if ((float) $invoice->pos_cash_received > 0)
            <div class="line"><span>Montant recu</span><strong>{{ number_format((float) $invoice->pos_cash_received, 0, ',', ' ') }}</strong></div>
        @endif
        @if ((float) $invoice->pos_change_due > 0)
            <div class="line"><span>Monnaie rendue</span><strong>{{ number_format((float) $invoice->pos_change_due, 0, ',', ' ') }}</strong></div>
        @endif
        @if ($refundedTotal > 0)
            <div class="line"><span>Rembourse</span><strong>{{ number_format($refundedTotal, 0, ',', ' ') }}</strong></div>
        @endif
        <div class="line"><span>Reste</span><strong>{{ number_format((float) $invoice->balance_due, 0, ',', ' ') }}</strong></div>

        @if ($invoice->notes)
            <div class="divider"></div>
            <div class="muted" style="font-size:9.5px;">{{ $invoice->notes }}</div>
        @endif

        <div class="divider"></div>
        <div class="center muted">{{ $receiptProfile?->receipt_footer ?: 'Merci pour votre achat' }}</div>
        <div class="center muted footer-ref">{{ $invoice->invoice_number }} · Nema ERP</div>
    </div>

    @if (request()->boolean('auto_print') || request()->boolean('from_pos'))
        <script>
            const printNotice = document.getElementById('print-notice');
            const shouldAutoPrint = {{ request()->boolean('auto_print') ? 'true' : 'false' }};
            const shouldReturnToCash = {{ request()->boolean('from_pos') ? 'true' : 'false' }};
            const cashierReturnUrl = @json($cashierReturnUrl);
            const supportsFinePointer = typeof window.matchMedia === 'function'
                ? window.matchMedia('(pointer: fine)').matches
                : false;
            const hasTouchInput = Number.isFinite(navigator.maxTouchPoints)
                ? navigator.maxTouchPoints > 0
                : false;
            const canAutoPrint = supportsFinePointer && !hasTouchInput;
            let printFlowStarted = false;
            let printViewWasHidden = document.visibilityState === 'hidden';
            let hasReturnedToCash = false;

            const returnToCashDesk = () => {
                if (!shouldReturnToCash || hasReturnedToCash) {
                    return;
                }

                hasReturnedToCash = true;
                window.location.replace(cashierReturnUrl);
            };

            const queueCashDeskReturn = () => {
                if (!shouldReturnToCash || !printFlowStarted || hasReturnedToCash) {
                    return;
                }

                window.setTimeout(() => {
                    returnToCashDesk();
                }, 180);
            };

            const startPrintFlow = () => {
                printFlowStarted = true;
            };

            window.nemaPosPrintReceipt = () => {
                startPrintFlow();
                window.print();
            };

            if (printNotice && !canAutoPrint) {
                printNotice.querySelector('strong').textContent = 'Ticket affiche';
            }

            if (printNotice && shouldReturnToCash) {
                printNotice.querySelector('span').textContent = canAutoPrint
                    ? 'La page reviendra automatiquement sur la caisse juste apres l impression.'
                    : 'Appuyez sur "Imprimer". Apres impression, la caisse reviendra automatiquement sur une nouvelle commande.';
            }

            window.addEventListener('afterprint', () => {
                queueCashDeskReturn();
            });

            if (typeof window.matchMedia === 'function') {
                const printMedia = window.matchMedia('print');
                const onPrintMediaChange = (event) => {
                    if (!event.matches) {
                        queueCashDeskReturn();
                    }
                };

                if (typeof printMedia.addEventListener === 'function') {
                    printMedia.addEventListener('change', onPrintMediaChange);
                } else if (typeof printMedia.addListener === 'function') {
                    printMedia.addListener(onPrintMediaChange);
                }
            }

            window.addEventListener('focus', () => {
                queueCashDeskReturn();
            });

            document.addEventListener('visibilitychange', () => {
                if (printViewWasHidden && document.visibilityState === 'visible') {
                    queueCashDeskReturn();
                }

                printViewWasHidden = document.visibilityState === 'hidden';
            });

            window.addEventListener('load', () => {
                if (!shouldAutoPrint || !canAutoPrint) {
                    return;
                }

                startPrintFlow();
                window.setTimeout(() => {
                    window.print();
                }, 150);
            });
        </script>
    @else
        <script>
            window.nemaPosPrintReceipt = () => {
                window.print();
            };
        </script>
    @endif
</body>
</html>
