<!DOCTYPE html>
<html lang="fr">
<head>
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
        <button type="button" class="button" onclick="window.print()">Imprimer</button>
        <a href="{{ route('pos.preparation.index') }}" class="button">Board preparation</a>
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
        <div class="line"><span>Client</span><strong>{{ $ticket->invoice?->customer?->name ?? 'Client comptoir' }}</strong></div>
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
</body>
</html>
