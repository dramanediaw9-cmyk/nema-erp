@extends('layouts.app')

@section('title', 'Recouvrement - Nema ERP')
@section('page-title', 'Recouvrement client')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Portefeuille a encaisser</h2>
            <div class="muted">Relances, promesses de paiement et prochaine action sur les factures ouvertes.</div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="{{ route('sales.index', ['due_state' => 'overdue']) }}" class="button button-secondary">Voir les ventes en retard</a>
        </div>
    </div>

    @if ($currentCustomer)
        <section class="card" style="margin-bottom:18px;">
            <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:center;">
                <div>
                    <strong>Filtre client actif</strong>
                    <div class="muted" style="margin-top:6px;">{{ $currentCustomer->name }} · {{ $currentCustomer->code }}</div>
                </div>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <a href="{{ route('customers.show', $currentCustomer) }}" class="button button-secondary">Voir le client</a>
                    <a href="{{ route('collections.index') }}" class="button button-secondary">Retirer le filtre</a>
                </div>
            </div>
        </section>
    @endif

    <div class="grid stats-grid" style="margin-bottom:20px;">
        <div class="card"><div class="muted">Factures a suivre</div><div class="stat-value">{{ $summary['invoice_count'] }}</div></div>
        <div class="card"><div class="muted">Solde ouvert</div><div class="stat-value">{{ number_format($summary['open_balance_total'], 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Solde en retard</div><div class="stat-value">{{ number_format($summary['overdue_balance_total'], 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Solde promis</div><div class="stat-value">{{ number_format($summary['promised_balance_total'], 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Promesses echues</div><div class="stat-value">{{ $summary['promise_broken_count'] }}</div></div>
        <div class="card"><div class="muted">Actions dues</div><div class="stat-value">{{ $summary['next_actions_due_count'] }}</div></div>
    </div>

    <section class="card" style="margin-bottom:18px;">
        <form method="GET" action="{{ route('collections.index') }}" class="form-grid" style="align-items:end; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));">
            @if ($filters['customer_id'] ?? null)
                <input type="hidden" name="customer_id" value="{{ $filters['customer_id'] }}">
            @endif
            <div style="grid-column:span 2; min-width:220px;">
                <label for="search">Recherche</label>
                <input type="text" id="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Facture, client, telephone, note de relance...">
            </div>
            <div>
                <label for="branch_id">Agence</label>
                <select id="branch_id" name="branch_id">
                    <option value="">Toutes</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected(($filters['branch_id'] ?? null) == $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="state">Etat</label>
                <select id="state" name="state">
                    <option value="">Tous</option>
                    <option value="overdue" @selected(($filters['state'] ?? null) === 'overdue')>En retard</option>
                    <option value="due_soon" @selected(($filters['state'] ?? null) === 'due_soon')>Echeance proche</option>
                    <option value="promised" @selected(($filters['state'] ?? null) === 'promised')>Promesse active</option>
                    <option value="promise_broken" @selected(($filters['state'] ?? null) === 'promise_broken')>Promesse echue</option>
                    <option value="next_action_due" @selected(($filters['state'] ?? null) === 'next_action_due')>Action a faire</option>
                    <option value="no_follow_up" @selected(($filters['state'] ?? null) === 'no_follow_up')>Sans relance</option>
                </select>
            </div>
            <div class="actions" style="margin-top:0; justify-content:flex-start; align-self:end;">
                <button type="submit" class="button button-primary">Filtrer</button>
                <a href="{{ route('collections.index') }}" class="button button-secondary">Reinitialiser</a>
            </div>
        </form>
    </section>

    <div class="card table-wrap">
        <table>
            <thead>
            <tr>
                <th>Facture</th>
                <th>Client</th>
                <th>Agence</th>
                <th>Echeance</th>
                <th>Solde</th>
                <th>Derniere relance</th>
                <th>Promesse</th>
                <th>Prochaine action</th>
                <th>Suivi</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($items as $invoice)
                @php
                    $isOverdue = $invoice->due_date && $invoice->due_date->isBefore($today);
                    $daysOverdue = $isOverdue ? $invoice->due_date->diffInDays($today) : 0;
                    $promiseBroken = $invoice->last_promised_date && \Illuminate\Support\Carbon::parse($invoice->last_promised_date)->isBefore($today);
                    $nextActionDue = $invoice->last_next_action_date && \Illuminate\Support\Carbon::parse($invoice->last_next_action_date)->lessThanOrEqualTo($today);
                @endphp
                <tr>
                    <td>
                        <a href="{{ route('collections.show', $invoice) }}" style="font-weight:600;">{{ $invoice->invoice_number }}</a>
                        <div class="muted" style="font-size:14px; margin-top:6px;">{{ $invoice->invoice_date?->format('d/m/Y') }}</div>
                    </td>
                    <td>
                        <div style="font-weight:600;">{{ $invoice->customer_name }}</div>
                        <div class="muted" style="font-size:14px; margin-top:6px;">{{ $invoice->customer_phone ?: 'Telephone non renseigne' }}</div>
                    </td>
                    <td>{{ $invoice->branch_name ?: 'Agence non renseignee' }}</td>
                    <td>
                        {{ $invoice->due_date?->format('d/m/Y') ?: 'Sans echeance' }}
                        @if ($isOverdue)
                            <div class="help" style="margin-top:6px; color:#b42318;">Retard {{ $daysOverdue }} jour(s)</div>
                        @elseif ($invoice->due_date && $invoice->due_date->lessThanOrEqualTo($soonDate))
                            <div class="help" style="margin-top:6px; color:#9a5b00;">Echeance proche</div>
                        @endif
                    </td>
                    <td>
                        {{ number_format((float) $invoice->balance_due, 0, ',', ' ') }} XOF
                        <div class="help" style="margin-top:6px;">Paye {{ number_format((float) $invoice->amount_paid, 0, ',', ' ') }} XOF</div>
                    </td>
                    <td>
                        @if ($invoice->last_follow_up_id)
                            <div>{{ \Illuminate\Support\Carbon::parse($invoice->last_action_date)->format('d/m/Y') }}</div>
                            <div class="help" style="margin-top:6px;">{{ $actionOptions[$invoice->last_action_type] ?? ucfirst(str_replace('_', ' ', (string) $invoice->last_action_type)) }}</div>
                            <div class="help">{{ $outcomeOptions[$invoice->last_outcome] ?? 'Sans issue' }}</div>
                        @else
                            <span class="muted">Aucune relance</span>
                        @endif
                    </td>
                    <td>
                        @if ($invoice->last_promised_date)
                            <div>{{ \Illuminate\Support\Carbon::parse($invoice->last_promised_date)->format('d/m/Y') }}</div>
                            @if ($invoice->last_promised_amount)
                                <div class="help" style="margin-top:6px;">{{ number_format((float) $invoice->last_promised_amount, 0, ',', ' ') }} XOF</div>
                            @endif
                            @if ($promiseBroken)
                                <div class="help" style="margin-top:6px; color:#b42318;">Promesse echue</div>
                            @endif
                        @else
                            <span class="muted">Aucune</span>
                        @endif
                    </td>
                    <td>
                        @if ($invoice->last_next_action_date)
                            <div>{{ \Illuminate\Support\Carbon::parse($invoice->last_next_action_date)->format('d/m/Y') }}</div>
                            @if ($nextActionDue)
                                <div class="help" style="margin-top:6px; color:#9a5b00;">A traiter</div>
                            @endif
                        @else
                            <span class="muted">Non planifiee</span>
                        @endif
                    </td>
                    <td>
                        <div style="display:grid; gap:8px;">
                            <span class="badge {{ $isOverdue ? 'badge-warning' : 'badge-muted' }}">{{ $isOverdue ? 'Retard' : 'Ouverte' }}</span>
                            <span class="badge {{ ($invoice->follow_up_count ?? 0) > 0 ? 'badge-success' : 'badge-muted' }}">{{ (int) ($invoice->follow_up_count ?? 0) }} relance(s)</span>
                        </div>
                    </td>
                    <td>
                        <div style="display:flex; gap:8px; flex-wrap:wrap;">
                            <a href="{{ route('collections.show', $invoice) }}" class="button button-secondary">Suivre</a>
                            @allowed('payments.manage')
                                <a href="{{ route('payments.create', ['invoice' => $invoice->id]) }}" class="button button-secondary">Encaisser</a>
                            @endallowed
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="muted">Aucune facture ne correspond aux criteres de recouvrement selectionnes.</td>
                </tr>
            @endforelse
            </tbody>
        </table>

        @if (method_exists($items, 'links'))
            <div style="margin-top:18px;">{{ $items->links() }}</div>
        @endif
    </div>
@endsection