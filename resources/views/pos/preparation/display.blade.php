@extends('layouts.app')

@section('title', 'Preparation Display - Nema ERP')
@section('page-title', 'Preparation Display')

@section('content')
    @php($display = $board['display'])
    @php($summary = $board['summary'])
    @php($statusOptions = $board['status_options'])
    @php($nextStatusMap = $board['next_status_map'])
    @php($previousStatusMap = $board['previous_status_map'])
    @php($columns = [
        'queued' => ['title' => 'En file', 'hint' => 'Nouvelles commandes a lancer'],
        'in_progress' => ['title' => 'En preparation', 'hint' => 'Equipe en cours d execution'],
        'ready' => ['title' => 'Pret', 'hint' => 'Commande prete a servir'],
    ])

    <style>
        .prep-display {
            display: grid;
            gap: 22px;
            color: #eff6ff;
        }
        .prep-display-shell {
            padding: 24px;
            border-radius: 28px;
            background:
                radial-gradient(circle at top right, rgba(57, 198, 178, 0.28), transparent 28%),
                linear-gradient(160deg, #09111f 0%, #10233a 48%, #173b59 100%);
            box-shadow: 0 24px 50px rgba(8, 15, 30, 0.32);
        }
        .prep-display-topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 18px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }
        .prep-display-title {
            display: grid;
            gap: 10px;
        }
        .prep-display-title h2 {
            margin: 0;
            font-size: 36px;
            line-height: 1.05;
            letter-spacing: -0.04em;
        }
        .prep-display-title .muted {
            color: rgba(239, 246, 255, 0.76);
            max-width: 820px;
        }
        .prep-display-chip-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .prep-display-chip {
            display: inline-flex;
            align-items: center;
            padding: 9px 14px;
            border-radius: 999px;
            border: 1px solid rgba(191, 219, 254, 0.16);
            background: rgba(148, 163, 184, 0.14);
            color: #eff6ff;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .prep-display-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .prep-display-kpis {
            display: grid;
            gap: 14px;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            margin-bottom: 20px;
        }
        .prep-display-kpi {
            padding: 18px 18px 16px;
            border-radius: 22px;
            border: 1px solid rgba(148, 163, 184, 0.18);
            background: rgba(8, 15, 30, 0.3);
            backdrop-filter: blur(12px);
        }
        .prep-display-kpi .label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: rgba(191, 219, 254, 0.75);
            font-weight: 700;
        }
        .prep-display-kpi .value {
            margin-top: 10px;
            font-size: 34px;
            line-height: 1;
            font-weight: 800;
        }
        .prep-display-board {
            display: grid;
            gap: 16px;
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
        .prep-display-column {
            display: grid;
            gap: 14px;
            padding: 16px;
            border-radius: 24px;
            border: 1px solid rgba(148, 163, 184, 0.16);
            background: rgba(8, 15, 30, 0.28);
            min-height: 420px;
        }
        .prep-display-column-head {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            align-items: baseline;
        }
        .prep-display-column-head h3 {
            margin: 0;
            font-size: 22px;
            letter-spacing: -0.02em;
        }
        .prep-display-column-head .hint {
            color: rgba(191, 219, 254, 0.7);
            font-size: 13px;
        }
        .prep-display-column-count {
            font-size: 28px;
            font-weight: 800;
            color: #7dd3fc;
        }
        .prep-display-list {
            display: grid;
            gap: 12px;
            align-content: start;
        }
        .prep-display-ticket {
            display: grid;
            gap: 12px;
            padding: 16px;
            border-radius: 22px;
            background: rgba(15, 23, 42, 0.9);
            border: 1px solid rgba(148, 163, 184, 0.16);
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.02);
        }
        .prep-display-ticket-head {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            align-items: flex-start;
        }
        .prep-display-ticket-head strong {
            font-size: 22px;
            line-height: 1.1;
        }
        .prep-display-ticket-meta {
            color: rgba(191, 219, 254, 0.76);
            font-size: 13px;
            display: grid;
            gap: 4px;
        }
        .prep-display-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 74px;
            padding: 7px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            background: rgba(59, 130, 246, 0.18);
            border: 1px solid rgba(96, 165, 250, 0.3);
            color: #dbeafe;
        }
        .prep-display-badge.is-late {
            background: rgba(239, 68, 68, 0.18);
            border-color: rgba(248, 113, 113, 0.38);
            color: #fecaca;
        }
        .prep-display-items {
            display: grid;
            gap: 8px;
        }
        .prep-display-item {
            padding: 12px 14px;
            border-radius: 18px;
            background: rgba(15, 23, 42, 0.82);
            border: 1px solid rgba(148, 163, 184, 0.1);
        }
        .prep-display-item strong {
            display: block;
            margin-bottom: 4px;
            font-size: 18px;
            color: #f8fafc;
        }
        .prep-display-item .muted {
            color: rgba(191, 219, 254, 0.72);
            font-size: 13px;
        }
        .prep-display-ticket-actions {
            display: grid;
            gap: 10px;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        }
        .prep-display-ticket-actions form {
            display: grid;
        }
        .prep-display-empty {
            display: grid;
            place-items: center;
            min-height: 180px;
            padding: 18px;
            border-radius: 18px;
            border: 1px dashed rgba(191, 219, 254, 0.18);
            color: rgba(191, 219, 254, 0.72);
            text-align: center;
            background: rgba(8, 15, 30, 0.22);
        }
        .prep-display-footer {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 18px;
            color: rgba(191, 219, 254, 0.72);
            font-size: 13px;
        }
        .prep-display .button {
            justify-content: center;
            min-height: 52px;
            font-size: 15px;
            font-weight: 800;
            border-radius: 16px;
        }
        .prep-display .button.button-primary {
            background: linear-gradient(135deg, #14b8a6 0%, #0ea5e9 100%);
            border-color: rgba(20, 184, 166, 0.16);
        }
        .prep-display .button.button-secondary {
            background: rgba(15, 23, 42, 0.8);
            color: #e2e8f0;
            border-color: rgba(148, 163, 184, 0.22);
        }
        @media (max-width: 1180px) {
            .prep-display-board {
                grid-template-columns: 1fr;
            }
            .prep-display-column {
                min-height: 0;
            }
        }
    </style>

    <div class="prep-display">
        <div class="prep-display-shell" data-refresh-seconds="{{ $board['refresh_seconds'] }}">
            <div class="prep-display-topbar">
                <div class="prep-display-title">
                    <div class="prep-display-chip-row">
                        <span class="prep-display-chip">Display plein ecran</span>
                        <span class="prep-display-chip">{{ strtoupper($display->display_mode) }}</span>
                        @if ($display->target_area)
                            <span class="prep-display-chip">{{ $display->target_area }}</span>
                        @endif
                    </div>
                    <h2>{{ $display->name }}</h2>
                    <div class="muted">
                        Ecran tactique pour cuisine, comptoir ou retrait. Il reste focalise sur les tickets vivants et avance le flux d un geste:
                        <strong>En file -> En preparation -> Pret -> Servi</strong>.
                    </div>
                </div>
                <div class="prep-display-actions">
                    <button type="button" class="button button-primary" id="prep-display-fullscreen">Plein ecran</button>
                    <a href="{{ route('pos.preparation.index', ['display_id' => $display->id]) }}" class="button button-secondary">Board detaille</a>
                    <a href="{{ route('pos.sales.create') }}" class="button button-secondary">Retour caisse</a>
                </div>
            </div>

            <div class="prep-display-kpis">
                <div class="prep-display-kpi"><div class="label">En file</div><div class="value">{{ $summary['queued'] }}</div></div>
                <div class="prep-display-kpi"><div class="label">En preparation</div><div class="value">{{ $summary['in_progress'] }}</div></div>
                <div class="prep-display-kpi"><div class="label">Prets</div><div class="value">{{ $summary['ready'] }}</div></div>
                <div class="prep-display-kpi"><div class="label">En retard</div><div class="value">{{ $summary['late'] }}</div></div>
            </div>

            <div class="prep-display-board">
                @foreach ($columns as $statusKey => $column)
                    @php($tickets = $board['grouped_tickets'][$statusKey] ?? collect())
                    <section class="prep-display-column">
                        <div class="prep-display-column-head">
                            <div>
                                <h3>{{ $column['title'] }}</h3>
                                <div class="hint">{{ $column['hint'] }}</div>
                            </div>
                            <div class="prep-display-column-count">{{ $tickets->count() }}</div>
                        </div>

                        @if ($tickets->isEmpty())
                            <div class="prep-display-empty">Aucun ticket {{ strtolower($column['title']) }} sur cet ecran pour le moment.</div>
                        @else
                            <div class="prep-display-list">
                                @foreach ($tickets as $ticket)
                                    @php($deadline = $ticket->target_minutes ? $ticket->created_at?->copy()->addMinutes($ticket->target_minutes) : null)
                                    @php($isLate = $ticket->target_minutes && $deadline?->isPast())
                                    <article class="prep-display-ticket">
                                        <div class="prep-display-ticket-head">
                                            <div>
                                                <strong>{{ $ticket->ticket_number }}</strong>
                                                <div class="prep-display-ticket-meta">
                                                    <span>{{ $ticket->invoice?->customer?->name ?? 'Client comptoir' }} · Ticket {{ $ticket->invoice?->invoice_number }}</span>
                                                    <span>{{ $ticket->created_at?->format('d/m H:i') }} · {{ $ticket->items->count() }} ligne(s)</span>
                                                </div>
                                            </div>
                                            <div class="prep-display-badge {{ $isLate ? 'is-late' : '' }}">
                                                {{ $isLate ? 'Retard' : ($statusOptions[$ticket->status] ?? $ticket->status) }}
                                            </div>
                                        </div>

                                        @if ($ticket->note_snapshot)
                                            <div class="muted">{{ $ticket->note_snapshot }}</div>
                                        @endif

                                        <div class="prep-display-items">
                                            @foreach ($ticket->items as $item)
                                                <div class="prep-display-item">
                                                    <strong>{{ number_format((float) $item->qty, 3, ',', ' ') }} × {{ $item->description }}</strong>
                                                    <div class="muted">
                                                        {{ $item->product?->barcode ?: $item->product?->sku ?: 'Reference libre' }}
                                                        @if ($item->combo_label) · Combo {{ $item->combo_label }} @endif
                                                        @if (!empty($item->menu_category_labels)) · {{ implode(', ', $item->menu_category_labels) }} @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>

                                        <div class="prep-display-ticket-actions">
                                            @if (isset($previousStatusMap[$ticket->status]))
                                                <form method="POST" action="{{ route('pos.preparation.update', $ticket) }}">
                                                    @csrf
                                                    <input type="hidden" name="status" value="{{ $previousStatusMap[$ticket->status] }}">
                                                    <button type="submit" class="button button-secondary">
                                                        Retour {{ $statusOptions[$previousStatusMap[$ticket->status]] ?? $previousStatusMap[$ticket->status] }}
                                                    </button>
                                                </form>
                                            @endif

                                            @if (isset($nextStatusMap[$ticket->status]))
                                                <form method="POST" action="{{ route('pos.preparation.update', $ticket) }}">
                                                    @csrf
                                                    <input type="hidden" name="status" value="{{ $nextStatusMap[$ticket->status] }}">
                                                    <button type="submit" class="button button-primary">
                                                        {{ $nextStatusMap[$ticket->status] === 'served' ? 'Marquer servi' : 'Passer '.($statusOptions[$nextStatusMap[$ticket->status]] ?? $nextStatusMap[$ticket->status]) }}
                                                    </button>
                                                </form>
                                            @endif

                                            <a href="{{ route('pos.preparation.print', $ticket) }}" class="button button-secondary" target="_blank" rel="noopener">Imprimer</a>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        @endif
                    </section>
                @endforeach
            </div>

            <div class="prep-display-footer">
                <div>Rafraichissement auto: toutes les {{ $board['refresh_seconds'] }} secondes.</div>
                <div>{{ $display->endpoint ?: 'Endpoint non renseigne' }}</div>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const shell = document.querySelector('[data-refresh-seconds]');
            const refreshSeconds = Number(shell?.dataset.refreshSeconds || 20);
            const fullscreenButton = document.getElementById('prep-display-fullscreen');

            fullscreenButton?.addEventListener('click', async () => {
                const element = document.documentElement;
                if (!document.fullscreenElement && element.requestFullscreen) {
                    await element.requestFullscreen();
                }
            });

            window.setTimeout(() => window.location.reload(), Math.max(5, refreshSeconds) * 1000);
        })();
    </script>
@endsection
