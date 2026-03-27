@extends('layouts.app')

@section('title', 'Depenses - Nema ERP')
@section('page-title', 'Depenses')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Sorties d'argent</h2>
            <div class="muted">Le module couvre les charges du quotidien avec suivi d'approbation et lecture immediate des urgences de reglement.</div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            @allowed('approvals.view')
                <a href="{{ route('approvals.index', ['module' => 'expenses']) }}" class="button button-secondary">Approvals depenses</a>
            @endallowed
            <a href="{{ route('expenses.export', request()->query()) }}" class="button button-secondary">Exporter CSV</a>
            @allowed('expenses.manage')
                <a href="{{ route('expenses.create') }}" class="button button-primary">Nouvelle depense</a>
            @endallowed
        </div>
    </div>

    <div class="grid stats-grid" style="margin-bottom:20px;">
        <div class="card"><div class="muted">Depenses non reglees</div><div class="stat-value">{{ $summary['unpaid_count'] }}</div></div>
        <div class="card"><div class="muted">Montant a regler</div><div class="stat-value">{{ number_format($summary['unpaid_total'], 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">A approuver</div><div class="stat-value">{{ $summary['pending_approval_count'] }}</div></div>
        <div class="card"><div class="muted">Age 8 a 30 jours</div><div class="stat-value">{{ number_format($summary['aging_8_30_total'], 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Age 31+ jours</div><div class="stat-value">{{ number_format($summary['aging_31_plus_total'], 0, ',', ' ') }}</div></div>
    </div>

    <section class="card" style="margin-bottom:18px;">
        <form method="GET" action="{{ route('expenses.index') }}" class="form-grid" style="align-items:end; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));">
            <div style="grid-column:span 2; min-width:220px;">
                <label for="search">Recherche</label>
                <input type="text" id="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Numero, description, categorie, fournisseur...">
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
                <label for="category_id">Categorie</label>
                <select id="category_id" name="category_id">
                    <option value="">Toutes les categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((int) ($filters['category_id'] ?? 0) === $category->id)>{{ $category->name }}</option>
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
                    <option value="unpaid" @selected(($filters['payment_status'] ?? null) === 'unpaid')>Non payees</option>
                    <option value="paid" @selected(($filters['payment_status'] ?? null) === 'paid')>Payees</option>
                </select>
            </div>
            <div>
                <label for="aging_state">Suivi</label>
                <select id="aging_state" name="aging_state">
                    <option value="">Tous</option>
                    <option value="pending" @selected(($filters['aging_state'] ?? null) === 'pending')>Workflow</option>
                    <option value="unpaid" @selected(($filters['aging_state'] ?? null) === 'unpaid')>A regler</option>
                    <option value="age_8_30" @selected(($filters['aging_state'] ?? null) === 'age_8_30')>Age 8 a 30 jours</option>
                    <option value="age_31_plus" @selected(($filters['aging_state'] ?? null) === 'age_31_plus')>Age 31+ jours</option>
                </select>
            </div>
            <div class="actions" style="margin-top:0; justify-content:flex-start; align-self:end;">
                <button type="submit" class="button button-primary">Filtrer</button>
                <a href="{{ route('expenses.index') }}" class="button button-secondary">Reinitialiser</a>
            </div>
        </form>
    </section>

    <section class="card table-wrap">
        <table>
            <thead>
            <tr>
                <th>Numero</th>
                <th>Date</th>
                <th>Description</th>
                <th>Categorie</th>
                <th>Fournisseur</th>
                <th>Workflow</th>
                <th>Total</th>
                <th>Suivi</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($expenses as $expense)
                @php
                    $nextStep = $expense->approvalSteps->firstWhere('status', 'pending');
                    $followUpLabel = 'Recent';
                    $followUpClass = 'badge-muted';

                    if ($expense->status !== 'validated') {
                        $followUpLabel = 'Workflow';
                        $followUpClass = 'badge-warning';
                    } elseif ($expense->payment_status === 'paid') {
                        $followUpLabel = 'Payee';
                        $followUpClass = 'badge-success';
                    } else {
                        $ageInDays = $expense->expense_date?->diffInDays($today) ?? 0;

                        if ($ageInDays > 30) {
                            $followUpLabel = 'A regler';
                            $followUpClass = 'badge-warning';
                        } elseif ($ageInDays > 7) {
                            $followUpLabel = 'A planifier';
                            $followUpClass = 'badge-muted';
                        }
                    }
                @endphp
                <tr>
                    <td>
                        <strong>{{ $expense->expense_number }}</strong>
                        @if ($expense->payment_reference)
                            <div class="muted" style="font-size:13px;">Ref. {{ $expense->payment_reference }}</div>
                        @endif
                    </td>
                    <td>{{ $expense->expense_date?->format('d/m/Y') }}</td>
                    <td>{{ $expense->description }}</td>
                    <td>{{ $expense->category?->name }}</td>
                    <td>{{ $expense->supplier?->name ?? 'Non renseigne' }}</td>
                    <td>
                        <span class="badge {{ $expense->status === 'validated' ? 'badge-success' : 'badge-warning' }}">{{ $expense->status === 'validated' ? 'Approuvee' : 'En attente' }}</span>
                        @if ($expense->status !== 'validated' && $nextStep)
                            <div class="muted" style="margin-top:6px; font-size:12px;">Etape : {{ $nextStep->label }}</div>
                        @endif
                    </td>
                    <td>{{ number_format((float) $expense->total, 0, ',', ' ') }} XOF</td>
                    <td>
                        <div style="display:grid; gap:8px;">
                            <span class="badge {{ $expense->payment_status === 'paid' ? 'badge-success' : 'badge-muted' }}">{{ $expense->payment_status === 'paid' ? 'Payee' : 'Non payee' }}</span>
                            <span class="badge {{ $followUpClass }}">{{ $followUpLabel }}</span>
                        </div>
                    </td>
                    <td>
                        <div style="display:flex; gap:10px; flex-wrap:wrap;">
                            <a href="{{ route('expenses.show', $expense) }}" class="button button-secondary">Voir</a>
                            @if ($expense->status === 'pending_approval')
                                @allowed('expenses.approve')
                                    <form method="POST" action="{{ route('expenses.approve', $expense) }}">
                                        @csrf
                                        <button type="submit" class="button button-primary">Approuver</button>
                                    </form>
                                @endallowed
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="muted">Aucune depense ne correspond aux filtres selectionnes.</td>
                </tr>
            @endforelse
            </tbody>
        </table>

        @if (method_exists($expenses, 'links'))
            <div style="margin-top:18px;">{{ $expenses->links() }}</div>
        @endif
    </section>
@endsection
