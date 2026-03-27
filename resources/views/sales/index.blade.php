@extends('layouts.app')

@section('title', 'Ventes - Nema ERP')
@section('page-title', 'Factures de vente')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Pilotage commercial</h2>
            <div class="muted">Une facture approuvee decremente le stock et ouvre automatiquement le suivi du recouvrement.</div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            @allowed('approvals.view')
                <a href="{{ route('approvals.index', ['module' => 'sales']) }}" class="button button-secondary">Approvals ventes</a>
            @endallowed
            <a href="{{ route('sales.export', request()->query()) }}" class="button button-secondary">Exporter CSV</a>
            @allowed('sales.manage')
                <a href="{{ route('sales.create') }}" class="button button-primary">Nouvelle facture</a>
            @endallowed
        </div>
    </div>

    <div class="grid stats-grid" style="margin-bottom:20px;">
        <div class="card"><div class="muted">Factures ouvertes</div><div class="stat-value">{{ $summary['open_count'] }}</div></div>
        <div class="card"><div class="muted">Reste a encaisser</div><div class="stat-value">{{ number_format($summary['open_balance'], 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">En retard</div><div class="stat-value">{{ number_format($summary['overdue_balance'], 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Echeance proche</div><div class="stat-value">{{ number_format($summary['due_soon_balance'], 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">En attente d approbation</div><div class="stat-value">{{ $summary['pending_approval_count'] }}</div></div>
    </div>

    <section class="card" style="margin-bottom:18px;">
        <form method="GET" action="{{ route('sales.index') }}" class="form-grid" style="align-items:end; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));">
            <div style="grid-column:span 2; min-width:220px;">
                <label for="search">Recherche</label>
                <input type="text" id="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Numero, client, agence, note...">
            </div>
            <div>
                <label for="date_from">Date debut</label>
                <input type="date" id="date_from" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
            </div>
            <div>
                <label for="date_to">Date fin</label>
                <input type="date" id="date_to" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
            </div>
            <div>
                <label for="branch_id">Agence</label>
                <select id="branch_id" name="branch_id">
                    <option value="">Toutes les agences</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((int) ($filters['branch_id'] ?? 0) === $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="status">Workflow</label>
                <select id="status" name="status">
                    <option value="">Tous</option>
                    <option value="validated" @selected(($filters['status'] ?? null) === 'validated')>Approuvees</option>
                    <option value="pending_approval" @selected(($filters['status'] ?? null) === 'pending_approval')>En attente</option>
                </select>
            </div>
            <div>
                <label for="payment_status">Paiement</label>
                <select id="payment_status" name="payment_status">
                    <option value="">Tous</option>
                    <option value="unpaid" @selected(($filters['payment_status'] ?? null) === 'unpaid')>Impayees</option>
                    <option value="partial" @selected(($filters['payment_status'] ?? null) === 'partial')>Partielles</option>
                    <option value="paid" @selected(($filters['payment_status'] ?? null) === 'paid')>Payees</option>
                </select>
            </div>
            <div>
                <label for="due_state">Suivi echeance</label>
                <select id="due_state" name="due_state">
                    <option value="">Tous</option>
                    <option value="open" @selected(($filters['due_state'] ?? null) === 'open')>A encaisser</option>
                    <option value="overdue" @selected(($filters['due_state'] ?? null) === 'overdue')>En retard</option>
                    <option value="due_soon" @selected(($filters['due_state'] ?? null) === 'due_soon')>Echeance proche</option>
                    <option value="no_due" @selected(($filters['due_state'] ?? null) === 'no_due')>Sans echeance</option>
                </select>
            </div>
            <div class="actions" style="margin-top:0; justify-content:flex-start; align-self:end;">
                <button type="submit" class="button button-primary">Filtrer</button>
                <a href="{{ route('sales.index') }}" class="button button-secondary">Reinitialiser</a>
            </div>
        </form>
    </section>

    <section class="card table-wrap">
        <table>
            <thead>
            <tr>
                <th>Numero</th>
                <th>Date</th>
                <th>Echeance</th>
                <th>Client</th>
                <th>Agence</th>
                <th>Workflow</th>
                <th>Total</th>
                <th>Reste</th>
                <th>Suivi</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($invoices as $invoice)
                @php
                    $nextStep = $invoice->approvalSteps->firstWhere('status', 'pending');
                    $followUpLabel = 'Dans les delais';
                    $followUpClass = 'badge-success';

                    if ($invoice->status !== 'validated') {
                        $followUpLabel = 'Workflow';
                        $followUpClass = 'badge-warning';
                    } elseif ($invoice->payment_status === 'paid') {
                        $followUpLabel = 'A jour';
                        $followUpClass = 'badge-success';
                    } elseif (! $invoice->due_date) {
                        $followUpLabel = 'Sans echeance';
                        $followUpClass = 'badge-muted';
                    } elseif ($invoice->due_date->lt($today)) {
                        $followUpLabel = 'En retard';
                        $followUpClass = 'badge-warning';
                    } elseif ($invoice->due_date->lte($soonDate)) {
                        $followUpLabel = 'Echeance proche';
                        $followUpClass = 'badge-muted';
                    }
                @endphp
                <tr>
                    <td>
                        <strong>{{ $invoice->invoice_number }}</strong>
                        @if ($invoice->notes)
                            <div class="muted" style="font-size:13px;">{{ $invoice->notes }}</div>
                        @endif
                    </td>
                    <td>{{ $invoice->invoice_date?->format('d/m/Y') }}</td>
                    <td>{{ $invoice->due_date?->format('d/m/Y') ?? 'Non renseignee' }}</td>
                    <td>{{ $invoice->customer?->name }}</td>
                    <td>{{ $invoice->branch?->name }}</td>
                    <td>
                        <span class="badge {{ $invoice->status === 'validated' ? 'badge-success' : 'badge-warning' }}">
                            {{ $invoice->status === 'validated' ? 'Approuvee' : 'En attente' }}
                        </span>
                        @if ($invoice->status !== 'validated' && $nextStep)
                            <div class="muted" style="margin-top:6px; font-size:12px;">Etape : {{ $nextStep->label }}</div>
                        @endif
                    </td>
                    <td>{{ number_format((float) $invoice->total, 0, ',', ' ') }} XOF</td>
                    <td>{{ number_format((float) $invoice->balance_due, 0, ',', ' ') }} XOF</td>
                    <td>
                        <div style="display:grid; gap:8px;">
                            <span class="badge {{ $invoice->payment_status === 'paid' ? 'badge-success' : 'badge-muted' }}">
                                {{ $invoice->payment_status === 'paid' ? 'Payee' : ($invoice->payment_status === 'partial' ? 'Partielle' : 'Impayee') }}
                            </span>
                            <span class="badge {{ $followUpClass }}">{{ $followUpLabel }}</span>
                        </div>
                    </td>
                    <td>
                        <div style="display:flex; gap:10px; flex-wrap:wrap;">
                            <a href="{{ route('sales.show', $invoice) }}" class="button button-secondary">Voir</a>
                            @if ($invoice->status === 'pending_approval')
                                @allowed('sales.approve')
                                    <form method="POST" action="{{ route('sales.approve', $invoice) }}">
                                        @csrf
                                        <button type="submit" class="button button-primary">Approuver</button>
                                    </form>
                                @endallowed
                            @elseif ($invoice->payment_status !== 'paid')
                                @allowed('payments.manage')
                                    <a href="{{ route('payments.create', ['invoice' => $invoice->id]) }}" class="button button-primary">Encaisser</a>
                                @endallowed
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="muted">Aucune facture de vente ne correspond aux filtres selectionnes.</td>
                </tr>
            @endforelse
            </tbody>
        </table>

        @if (method_exists($invoices, 'links'))
            <div style="margin-top:18px;">{{ $invoices->links() }}</div>
        @endif
    </section>
@endsection
