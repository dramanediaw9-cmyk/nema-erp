@php
    $display = $board['display'];
    $summary = $board['summary'];
    $statusOptions = $board['status_options'];
    $nextStatusMap = $board['next_status_map'];
    $previousStatusMap = $board['previous_status_map'];
    $customerLabel = $businessVocabulary['client'] ?? 'Client';
    $counterCustomerLabel = in_array($businessVocabulary['profile_key'] ?? '', ['food_store', 'general_trade', 'pharmacy_parapharmacy'], true)
        ? 'Client comptoir'
        : $customerLabel.' comptoir';
@endphp
@php
    $columns = [
        'queued' => ['title' => 'En file', 'hint' => 'Nouvelles commandes a lancer'],
        'in_progress' => ['title' => 'En preparation', 'hint' => 'Equipe en cours d execution'],
        'ready' => ['title' => 'Pret', 'hint' => 'Commande prete a servir'],
    ];
@endphp

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
            Ecran tactique pour preparation, comptoir ou retrait. Il reste focalise sur les tickets vivants et avance le flux d un geste:
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
        @php
            $tickets = $board['grouped_tickets'][$statusKey] ?? collect();
        @endphp
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
                        @php
                            $deadline = $ticket->target_minutes ? $ticket->created_at?->copy()->addMinutes($ticket->target_minutes) : null;
                            $isLate = $ticket->target_minutes && $deadline?->isPast();
                        @endphp
                        <article class="prep-display-ticket">
                            <div class="prep-display-ticket-head">
                                <div>
                                    <strong>{{ $ticket->ticket_number }}</strong>
                                    <div class="prep-display-ticket-meta">
                                        <span>{{ $ticket->invoice?->customer?->name ?? $counterCustomerLabel }} · Ticket {{ $ticket->invoice?->invoice_number }}</span>
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

                                <a href="{{ route('pos.preparation.print', [
                                    'ticket' => $ticket,
                                    'auto_print' => 1,
                                    'return_to' => route('pos.preparation.display', $display),
                                    'return_label' => 'Retour display',
                                ]) }}" class="button button-secondary">Imprimer</a>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    @endforeach
</div>

<div class="prep-display-footer">
    <div>Rafraichissement live: toutes les {{ $board['refresh_seconds'] }} secondes.</div>
    <div>{{ $display->endpoint ?: 'Endpoint non renseigne' }}</div>
</div>
