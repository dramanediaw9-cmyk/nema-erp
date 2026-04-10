<!DOCTYPE html>
<html lang="fr">
<head>
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
        .ticket {
            width: 72mm;
            margin: 0 auto 16px;
            background: #fff;
            padding: 6px 5px 8px;
            box-shadow: 0 10px 18px rgba(0, 0, 0, 0.08);
            font-size: 10.5px;
            line-height: 1.2;
        }
        .center { text-align: center; }
        .muted { color: #667085; }
        .strong { font-weight: 700; }
        .divider { border-top: 1px dashed #98a2b3; margin: 6px 0; }
        .line { display: flex; justify-content: space-between; gap: 8px; padding: 1px 0; }
        .item { padding: 4px 0; }
        .item-name { font-weight: 700; word-break: break-word; }
        .item-meta { display: flex; justify-content: space-between; gap: 8px; font-size: 9.5px; color: #667085; }
        .item-total { text-align: right; font-weight: 700; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .ticket { box-shadow: none; margin: 0 auto; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" class="button" onclick="window.print()">Imprimer</button>
        <a href="{{ route('pos.receipt', $invoice) }}" class="button">Apercu</a>
        <a href="javascript:history.back()" class="button">Retour</a>
    </div>

    @php($payments = $payments ?? collect())
    @php($payment = $payment ?? null)
    @php($paymentLabel = $payments->count() > 1 ? 'Mixte' : ($payment?->method ? str($payment->method)->replace('_', ' ')->title() : 'N/A'))
    @php($refundedTotal = (float) $invoice->posReturns->sum('total'))
    <div class="ticket">
        <div class="center strong">{{ $invoice->company?->name }}</div>
        <div class="center muted">{{ $invoice->branch?->name }}</div>
        <div class="center strong">TICKET CAISSE</div>
        <div class="center strong">{{ $invoice->invoice_number }}</div>
        <div class="center muted">Session {{ $invoice->posSession?->session_number }}</div>

        <div class="divider"></div>
        <div class="line"><span>Date</span><strong>{{ $invoice->invoice_date?->format('d/m/Y') }} {{ $invoice->created_at?->format('H:i') }}</strong></div>
        <div class="line"><span>Client</span><strong>{{ $invoice->customer?->name }}</strong></div>
        <div class="line"><span>Caissier</span><strong>{{ $invoice->creator?->name }}</strong></div>
        <div class="line"><span>Mode</span><strong>{{ $paymentLabel }}</strong></div>
        @if ($payments->isNotEmpty())
            @foreach ($payments as $ticketPayment)
                <div class="line"><span>{{ $payments->count() > 1 ? 'Paiement '.$loop->iteration : 'Paiement' }}</span><strong>{{ str($ticketPayment->method)->replace('_', ' ')->title() }} {{ number_format((float) $ticketPayment->amount, 0, ',', ' ') }}</strong></div>
            @endforeach
        @endif

        <div class="divider"></div>
        @foreach ($invoice->items as $item)
            @php($returnedQty = (float) $item->posReturnItems->sum('qty'))
            <div class="item">
                <div class="item-name">{{ $item->description }}</div>
                <div class="item-meta">
                    <span>{{ number_format((float) $item->qty, 3, ',', ' ') }} x {{ number_format((float) $item->unit_price, 0, ',', ' ') }}</span>
                    <span>{{ $item->product?->barcode ?: $item->product?->sku }}</span>
                </div>
                @if ((float) $item->discount_total > 0 || $returnedQty > 0)
                    <div class="item-meta">
                        <span>@if ((float) $item->discount_total > 0) Remise -{{ number_format((float) $item->discount_total, 0, ',', ' ') }} @endif</span>
                        <span>@if ($returnedQty > 0) Retour {{ number_format($returnedQty, 3, ',', ' ') }} @endif</span>
                    </div>
                @endif
                <div class="item-total">{{ number_format((float) $item->line_total, 0, ',', ' ') }}</div>
            </div>
        @endforeach

        <div class="divider"></div>
        <div class="line"><span>Sous-total</span><strong>{{ number_format((float) $invoice->subtotal, 0, ',', ' ') }}</strong></div>
        @if ($invoice->hasDiscount())
            <div class="line"><span>Remise</span><strong>-{{ number_format((float) $invoice->discount_total, 0, ',', ' ') }}</strong></div>
        @endif
        <div class="line strong"><span>Total</span><strong>{{ number_format((float) $invoice->total, 0, ',', ' ') }}</strong></div>
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

        @if ($invoice->notes)
            <div class="divider"></div>
            <div class="muted" style="font-size:9.5px;">{{ $invoice->notes }}</div>
        @endif

        <div class="divider"></div>
        <div class="center muted">Merci pour votre achat</div>
        <div class="center muted">Nema ERP</div>
    </div>

    @if (request()->boolean('auto_print'))
        <script>
            window.addEventListener('load', () => {
                window.setTimeout(() => {
                    window.print();
                }, 150);
            });

            window.addEventListener('afterprint', () => {
                if (window.opener && @json(request()->boolean('from_pos'))) {
                    window.opener.focus();
                }
            });
        </script>
    @endif
</body>
</html>
