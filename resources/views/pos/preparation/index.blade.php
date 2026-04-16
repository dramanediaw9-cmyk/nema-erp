@extends('layouts.app')

@section('title', 'Preparation POS - Nema ERP')
@section('page-title', 'Preparation POS')

@section('content')
    @php($summary = $board['summary'])
    @php($tickets = $board['tickets'])
    @php($statusOptions = $board['status_options'])

    <style>
        .pos-prep { display: grid; gap: 22px; }
        .pos-prep-hero {
            display: flex; justify-content: space-between; gap: 18px; flex-wrap: wrap; align-items: flex-start;
            padding: 24px 26px; border-radius: 24px; color: #fff;
            background: linear-gradient(135deg, #12263f 0%, #1a4c68 52%, #1d7f74 100%);
            box-shadow: 0 20px 40px rgba(12, 31, 53, 0.18);
        }
        .pos-prep-hero h2 { margin: 0; font-size: 30px; letter-spacing: -0.03em; }
        .pos-prep-hero .muted { color: rgba(255,255,255,.78); max-width: 780px; }
        .pos-prep-kpis { display: grid; gap: 14px; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); }
        .pos-prep-kpi { padding: 16px 18px; border-radius: 18px; border: 1px solid rgba(255,255,255,.18); background: rgba(255,255,255,.09); }
        .pos-prep-kpi .label { font-size: 12px; text-transform: uppercase; letter-spacing: .08em; color: rgba(255,255,255,.7); font-weight: 700; }
        .pos-prep-kpi .value { margin-top: 8px; font-size: 28px; font-weight: 800; }
        .pos-prep-filters, .pos-prep-list {
            border: 1px solid #d9e4ef; border-radius: 24px; background: linear-gradient(180deg, #ffffff 0%, #f8fbfd 100%);
            box-shadow: 0 16px 32px rgba(15, 23, 42, 0.05);
        }
        .pos-prep-head { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; padding: 20px 22px 12px; border-bottom: 1px solid #e7edf4; }
        .pos-prep-body { padding: 18px 22px 22px; }
        .pos-prep-grid { display: grid; gap: 14px; }
        .pos-prep-ticket { border: 1px solid #dfebf4; border-radius: 22px; padding: 16px 18px; background: #fff; display: grid; gap: 14px; }
        .pos-prep-ticket-head { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; flex-wrap: wrap; }
        .pos-prep-ticket-title { display: grid; gap: 6px; }
        .pos-prep-ticket-title strong { font-size: 18px; color: #10233a; }
        .pos-prep-chip-row { display: flex; gap: 8px; flex-wrap: wrap; }
        .pos-prep-chip {
            display: inline-flex; align-items: center; gap: 6px; padding: 7px 10px; border-radius: 999px;
            border: 1px solid #d8e4ef; background: #f6f9fc; color: #24405f; font-size: 12px; font-weight: 700;
        }
        .pos-prep-chip.is-status-queued { background: #fff7e6; border-color: #f1d08a; color: #8b5e10; }
        .pos-prep-chip.is-status-in_progress { background: #eaf4ff; border-color: #9cc2ff; color: #1e4f96; }
        .pos-prep-chip.is-status-ready { background: #e8fbf3; border-color: #8fd9b8; color: #166546; }
        .pos-prep-chip.is-status-served { background: #eef7f1; border-color: #bed7c7; color: #335d43; }
        .pos-prep-chip.is-late { background: #fff1f1; border-color: #f0b6b6; color: #9a2e2e; }
        .pos-prep-ticket-items { display: grid; gap: 10px; }
        .pos-prep-item { border-radius: 16px; border: 1px solid #e6edf4; background: #fbfdff; padding: 12px 14px; }
        .pos-prep-item strong { display: block; margin-bottom: 4px; color: #12263f; }
        .pos-prep-actions { display: flex; flex-wrap: wrap; gap: 8px; }
        .pos-prep-actions form { display: inline-flex; }
        .pos-prep-empty {
            padding: 22px; border-radius: 18px; border: 1px dashed #d5e1ec; text-align: center; color: #6d8197; background: #fbfdff;
        }
        @media (max-width: 780px) {
            .pos-prep-hero { padding: 20px 18px; }
            .pos-prep-hero h2 { font-size: 26px; }
        }
    </style>

    <div class="pos-prep">
        @include('pos.partials.backoffice-nav')

        <section class="pos-prep-hero">
            <div>
                <h2>Preparation comptoir et cuisine</h2>
                <div class="muted">Chaque ticket caisse peut maintenant alimenter un flux de preparation par zone, imprimante et display. L equipe suit ici les commandes en file, en cours et prêtes a servir.</div>
            </div>
            <div class="pos-prep-kpis">
                <div class="pos-prep-kpi"><div class="label">En file</div><div class="value">{{ $summary['queued'] }}</div></div>
                <div class="pos-prep-kpi"><div class="label">En preparation</div><div class="value">{{ $summary['in_progress'] }}</div></div>
                <div class="pos-prep-kpi"><div class="label">Prets</div><div class="value">{{ $summary['ready'] }}</div></div>
                <div class="pos-prep-kpi"><div class="label">En retard</div><div class="value">{{ $summary['late'] }}</div></div>
            </div>
        </section>

        <section class="pos-prep-filters">
            <div class="pos-prep-head">
                <div>
                    <h3 style="margin:0; font-size:22px;">Filtres d execution</h3>
                    <div class="muted">Tu peux focaliser l affichage par statut, imprimante ou Preparation Display.</div>
                </div>
                @if ($board['displays']->isNotEmpty())
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        @foreach ($board['displays'] as $display)
                            <a href="{{ route('pos.preparation.display', $display) }}" class="button button-secondary" target="_blank" rel="noopener">
                                Ouvrir {{ $display->name }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="pos-prep-body">
                <form method="GET" class="form-grid">
                    <div>
                        <label for="status">Statut</label>
                        <select id="status" name="status">
                            <option value="">Tous</option>
                            @foreach ($statusOptions as $statusKey => $statusLabel)
                                <option value="{{ $statusKey }}" @selected(($board['filters']['status'] ?? null) === $statusKey)>{{ $statusLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="printer_id">Imprimante</label>
                        <select id="printer_id" name="printer_id">
                            <option value="">Toutes</option>
                            @foreach ($board['printers'] as $printer)
                                <option value="{{ $printer->id }}" @selected(($board['filters']['printer_id'] ?? null) === $printer->id)>{{ $printer->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="display_id">Display</label>
                        <select id="display_id" name="display_id">
                            <option value="">Tous</option>
                            @foreach ($board['displays'] as $display)
                                <option value="{{ $display->id }}" @selected(($board['filters']['display_id'] ?? null) === $display->id)>{{ $display->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div style="display:flex; align-items:flex-end; gap:10px; flex-wrap:wrap;">
                        <button type="submit" class="button button-primary">Appliquer</button>
                        <a href="{{ route('pos.preparation.index') }}" class="button button-secondary">Reinitialiser</a>
                    </div>
                </form>
            </div>
        </section>

        <section class="pos-prep-list">
            <div class="pos-prep-head">
                <div>
                    <h3 style="margin:0; font-size:22px;">Tickets preparation</h3>
                    <div class="muted">Le board se recharge automatiquement quand tu actualises la page, comme un cockpit atelier/comptoir.</div>
                </div>
            </div>
            <div class="pos-prep-body">
                @if ($tickets->isEmpty())
                    <div class="pos-prep-empty">Aucun ticket de preparation ne correspond aux filtres pour le moment.</div>
                @else
                    <div class="pos-prep-grid">
                        @foreach ($tickets as $ticket)
                            @php($deadline = $ticket->target_minutes ? $ticket->created_at?->copy()->addMinutes($ticket->target_minutes) : null)
                            @php($isLate = $ticket->target_minutes && !in_array($ticket->status, ['ready', 'served', 'cancelled'], true) && $deadline?->isPast())
                            <article class="pos-prep-ticket">
                                <div class="pos-prep-ticket-head">
                                    <div class="pos-prep-ticket-title">
                                        <strong>{{ $ticket->ticket_number }}</strong>
                                        <div class="muted">Ticket {{ $ticket->invoice?->invoice_number }} · Session {{ $ticket->session?->session_number }} · {{ $ticket->invoice?->customer?->name ?? 'Client comptoir' }}</div>
                                    </div>
                                    <div class="pos-prep-chip-row">
                                        <span class="pos-prep-chip is-status-{{ $ticket->status }}">{{ $statusOptions[$ticket->status] ?? str($ticket->status)->replace('_', ' ')->title() }}</span>
                                        @if ($isLate)<span class="pos-prep-chip is-late">Retard</span>@endif
                                        @if ($ticket->target_area)<span class="pos-prep-chip">{{ $ticket->target_area }}</span>@endif
                                        @if ($ticket->printer)<span class="pos-prep-chip">Impr. {{ $ticket->printer->name }}</span>@endif
                                        @if ($ticket->display)<span class="pos-prep-chip">Display {{ $ticket->display->name }}</span>@endif
                                    </div>
                                </div>

                                <div class="muted" style="display:flex; gap:14px; flex-wrap:wrap;">
                                    <span>Creé {{ $ticket->created_at?->format('d/m H:i') }}</span>
                                    @if ($deadline)<span>Cible {{ $ticket->target_minutes }} min · avant {{ $deadline->format('H:i') }}</span>@endif
                                    @if ($ticket->profile?->name)<span>Profil {{ $ticket->profile->name }}</span>@endif
                                </div>

                                @if ($ticket->note_snapshot)
                                    <div class="notice" style="margin:0;">{{ $ticket->note_snapshot }}</div>
                                @endif

                                <div class="pos-prep-ticket-items">
                                    @foreach ($ticket->items as $item)
                                        <div class="pos-prep-item">
                                            <strong>{{ number_format((float) $item->qty, 3, ',', ' ') }} × {{ $item->description }}</strong>
                                            <div class="muted">
                                                {{ $item->product?->barcode ?: $item->product?->sku ?: 'Reference libre' }}
                                                @if ($item->combo_label) · Combo {{ $item->combo_label }} @endif
                                            </div>
                                            @if (!empty($item->menu_category_labels) || !empty($item->tag_labels))
                                                <div class="pos-prep-chip-row" style="margin-top:8px;">
                                                    @foreach ($item->menu_category_labels ?? [] as $menuLabel)
                                                        <span class="pos-prep-chip">{{ $menuLabel }}</span>
                                                    @endforeach
                                                    @foreach ($item->tag_labels ?? [] as $tagLabel)
                                                        <span class="pos-prep-chip">{{ $tagLabel }}</span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>

                                <div class="pos-prep-actions">
                                    @foreach (['queued' => 'Remettre en file', 'in_progress' => 'Demarrer', 'ready' => 'Marquer pret', 'served' => 'Marquer servi'] as $statusKey => $statusLabel)
                                        @continue($statusKey === $ticket->status)
                                        @continue($ticket->status === 'served' && $statusKey !== 'queued')
                                        <form method="POST" action="{{ route('pos.preparation.update', $ticket) }}">
                                            @csrf
                                            <input type="hidden" name="status" value="{{ $statusKey }}">
                                            <button type="submit" class="button button-secondary">{{ $statusLabel }}</button>
                                        </form>
                                    @endforeach
                                    <a href="{{ route('pos.preparation.print', [
                                        'ticket' => $ticket,
                                        'auto_print' => 1,
                                        'return_to' => route('pos.preparation.index', array_filter([
                                            'status' => $board['filters']['status'] ?? null,
                                            'printer_id' => $board['filters']['printer_id'] ?? null,
                                            'display_id' => $board['filters']['display_id'] ?? null,
                                        ])),
                                        'return_label' => 'Retour board',
                                    ]) }}" class="button button-primary">Imprimer ticket</a>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    </div>

    <script>
        window.setTimeout(() => {
            const hasFormFocus = document.activeElement && ['INPUT', 'SELECT', 'TEXTAREA'].includes(document.activeElement.tagName);
            if (!hasFormFocus) {
                window.location.reload();
            }
        }, 20000);
    </script>
@endsection
