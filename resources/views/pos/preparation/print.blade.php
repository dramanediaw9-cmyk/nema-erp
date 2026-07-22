@php
    $returnUrl = request('return_to') ?: ($ticket->display
        ? route('pos.preparation.display', $ticket->display)
        : route('pos.preparation.index'));
    $returnLabel = request('return_label') ?: ($ticket->display ? 'Retour display' : 'Retour board');
    $customerLabel = $businessVocabulary['client'] ?? 'Client';
    $counterCustomerLabel = in_array($businessVocabulary['profile_key'] ?? '', ['food_store', 'general_trade', 'pharmacy_parapharmacy'], true)
        ? 'Client comptoir'
        : $customerLabel.' comptoir';
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    @include('partials.security-csp-meta')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Preparation {{ $ticket->ticket_number }}</title>
    <style>
        body { margin: 0; background: #eef2f6; color: #101828; font-family: Arial, Helvetica, sans-serif; }
        .toolbar { display: flex; justify-content: center; gap: 10px; padding: 10px; flex-wrap: wrap; }
        .button { display: inline-block; padding: 8px 12px; border-radius: 8px; background: #ffffff; border: 1px solid #d0d5dd; text-decoration: none; color: #101828; font-weight: 700; font-size: 12px; }
        .ticket { width: 72mm; margin: 0 auto 16px; background: #fff; padding: 6px 5px 8px; box-shadow: 0 10px 18px rgba(0, 0, 0, 0.08); font-size: 10.5px; line-height: 1.2; }
        .center { text-align: center; }
        .muted { color: #667085; }
        .strong { font-weight: 700; }
        .divider { border-top: 1px dashed #98a2b3; margin: 6px 0; }
        .line { display: flex; justify-content: space-between; gap: 8px; padding: 1px 0; }
        .item { padding: 4px 0; }
        .item-name { font-weight: 700; word-break: break-word; }
        .item-meta { font-size: 9.5px; color: #667085; margin-top: 2px; }
        @media print { body { background: #fff; } .toolbar { display: none; } .ticket { box-shadow: none; margin: 0 auto; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" class="button" onclick="window.nemaPreparationPrintTicket()">Imprimer</button>
        <a href="{{ $returnUrl }}" class="button">{{ $returnLabel }}</a>
    </div>

    <div class="ticket">
        <div class="center strong">{{ $ticket->invoice?->company?->name }}</div>
        <div class="center muted">{{ $ticket->invoice?->branch?->name }}</div>
        <div class="center strong">BON PREPARATION</div>
        <div class="center strong">{{ $ticket->ticket_number }}</div>
        <div class="center muted">{{ $ticket->target_area ?: 'Preparation' }}</div>

        <div class="divider"></div>
        <div class="line"><span>Ticket POS</span><strong>{{ $ticket->invoice?->invoice_number }}</strong></div>
        <div class="line"><span>Session</span><strong>{{ $ticket->session?->session_number }}</strong></div>
        <div class="line"><span>{{ $customerLabel }}</span><strong>{{ $ticket->invoice?->customer?->name ?? $counterCustomerLabel }}</strong></div>
        <div class="line"><span>Statut</span><strong>{{ str($ticket->status)->replace('_', ' ')->title() }}</strong></div>
        @if ($ticket->printer)<div class="line"><span>Imprimante</span><strong>{{ $ticket->printer->name }}</strong></div>@endif
        @if ($ticket->display)<div class="line"><span>Display</span><strong>{{ $ticket->display->name }}</strong></div>@endif

        @if ($ticket->note_snapshot)
            <div class="divider"></div>
            <div class="muted">{{ $ticket->note_snapshot }}</div>
        @endif

        <div class="divider"></div>
        @foreach ($ticket->items as $item)
            <div class="item">
                <div class="item-name">{{ number_format((float) $item->qty, 3, ',', ' ') }} × {{ $item->description }}</div>
                <div class="item-meta">
                    {{ $item->product?->barcode ?: $item->product?->sku ?: 'Reference libre' }}
                    @if ($item->combo_label) · Combo {{ $item->combo_label }} @endif
                    @if (!empty($item->menu_category_labels)) · {{ implode(' · ', $item->menu_category_labels) }} @endif
                    @if (!empty($item->tag_labels)) · {{ implode(' · ', $item->tag_labels) }} @endif
                </div>
            </div>
        @endforeach

        <div class="divider"></div>
        <div class="center muted">Genere depuis le board preparation Nema ERP</div>
    </div>

    @if (request()->boolean('auto_print') || request()->filled('return_to'))
        <script>
            const shouldAutoPrint = {{ request()->boolean('auto_print') ? 'true' : 'false' }};
            const shouldReturn = {{ request()->filled('return_to') ? 'true' : 'false' }};
            const returnUrl = @json($returnUrl);
            let printFlowStarted = false;
            let printViewWasHidden = document.visibilityState === 'hidden';
            let hasReturned = false;

            const returnToSource = () => {
                if (!shouldReturn || hasReturned) {
                    return;
                }

                hasReturned = true;
                window.location.replace(returnUrl);
            };

            const queueReturn = () => {
                if (!shouldReturn || !printFlowStarted || hasReturned) {
                    return;
                }

                window.setTimeout(() => {
                    returnToSource();
                }, 180);
            };

            const startPrintFlow = () => {
                printFlowStarted = true;
            };

            window.nemaPreparationPrintTicket = () => {
                startPrintFlow();
                window.print();
            };

            window.addEventListener('afterprint', () => {
                queueReturn();
            });

            if (typeof window.matchMedia === 'function') {
                const printMedia = window.matchMedia('print');
                const onPrintMediaChange = (event) => {
                    if (!event.matches) {
                        queueReturn();
                    }
                };

                if (typeof printMedia.addEventListener === 'function') {
                    printMedia.addEventListener('change', onPrintMediaChange);
                } else if (typeof printMedia.addListener === 'function') {
                    printMedia.addListener(onPrintMediaChange);
                }
            }

            window.addEventListener('focus', () => {
                queueReturn();
            });

            document.addEventListener('visibilitychange', () => {
                if (printViewWasHidden && document.visibilityState === 'visible') {
                    queueReturn();
                }

                printViewWasHidden = document.visibilityState === 'hidden';
            });

            window.addEventListener('load', () => {
                if (!shouldAutoPrint) {
                    return;
                }

                startPrintFlow();
                window.setTimeout(() => {
                    window.print();
                }, 120);
            });
        </script>
    @else
        <script>
            window.nemaPreparationPrintTicket = () => {
                window.print();
            };
        </script>
    @endif
</body>
</html>
