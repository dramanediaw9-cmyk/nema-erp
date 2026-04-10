@extends('layouts.app')

@section('title', 'Paie - Nema ERP')
@section('page-title', 'Paie')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Executions de paie</h2>
            <div class="muted">Socle paie pour planifier les periodes, les montants et la mise en paiement.</div>
        </div>
    </div>

    @if ($errors->any())
        <div class="card" style="margin-bottom:18px; border-color:#9c3d2f;">
            <strong>Des validations sont a corriger</strong>
            <ul class="summary-list" style="margin-top:10px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid stats-grid" style="margin-bottom:20px;">
        <div class="card"><div class="muted">Executions</div><div class="stat-value">{{ $summary['runs'] }}</div></div>
        <div class="card"><div class="muted">Brouillons</div><div class="stat-value">{{ $summary['draft_runs'] }}</div></div>
        <div class="card"><div class="muted">Net planifie</div><div class="stat-value">{{ number_format($summary['scheduled_net'], 0, ',', ' ') }} XOF</div></div>
        <div class="card"><div class="muted">Effectifs planifies</div><div class="stat-value">{{ $summary['people_planned'] }}</div></div>
    </div>

    @allowed('payroll.manage')
        <form method="POST" action="{{ route('payroll.store') }}" class="card form-grid" style="margin-bottom:18px;">
            @csrf
            <div class="full">
                <h3 class="section-title">Nouvelle execution</h3>
            </div>
            <div>
                <label for="run_number">Numero</label>
                <input id="run_number" name="run_number" value="{{ old('run_number') }}" placeholder="PAY-2026-0001">
            </div>
            <div>
                <label for="label">Libelle</label>
                <input id="label" name="label" value="{{ old('label') }}" required>
            </div>
            <div>
                <label for="branch_id">Agence</label>
                <select id="branch_id" name="branch_id">
                    <option value="">Toutes les agences</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="status">Statut</label>
                <select id="status" name="status" required>
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', 'draft') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="period_start">Debut periode</label>
                <input id="period_start" name="period_start" type="date" value="{{ old('period_start', now()->startOfMonth()->toDateString()) }}" required>
            </div>
            <div>
                <label for="period_end">Fin periode</label>
                <input id="period_end" name="period_end" type="date" value="{{ old('period_end', now()->endOfMonth()->toDateString()) }}" required>
            </div>
            <div>
                <label for="scheduled_pay_date">Date de paiement</label>
                <input id="scheduled_pay_date" name="scheduled_pay_date" type="date" value="{{ old('scheduled_pay_date', now()->endOfMonth()->toDateString()) }}">
            </div>
            <div>
                <label for="headcount">Effectif</label>
                <input id="headcount" name="headcount" type="number" min="1" value="{{ old('headcount', 1) }}">
            </div>
            <div>
                <label for="gross_amount">Brut</label>
                <input id="gross_amount" name="gross_amount" type="number" min="0" step="0.01" value="{{ old('gross_amount', 0) }}">
            </div>
            <div>
                <label for="net_amount">Net</label>
                <input id="net_amount" name="net_amount" type="number" min="0" step="0.01" value="{{ old('net_amount', 0) }}">
            </div>
            <div class="full">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes">{{ old('notes') }}</textarea>
            </div>
            <div class="full actions">
                <button type="submit" class="button button-primary">Enregistrer l execution</button>
            </div>
        </form>
    @endallowed

    <section class="card">
        <h3 class="section-title">Calendrier de paie</h3>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Numero</th>
                    <th>Libelle</th>
                    <th>Periode</th>
                    <th>Agence</th>
                    <th>Effectif</th>
                    <th>Net</th>
                    <th>Statut</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($runs as $run)
                    <tr>
                        <td>{{ $run->run_number }}</td>
                        <td>{{ $run->label }}</td>
                        <td>{{ $run->period_start?->format('d/m/Y') }} - {{ $run->period_end?->format('d/m/Y') }}</td>
                        <td>{{ $run->branch?->name ?? 'Toutes' }}</td>
                        <td>{{ $run->headcount }}</td>
                        <td>{{ number_format((float) $run->net_amount, 0, ',', ' ') }} XOF</td>
                        <td><span class="badge badge-muted">{{ $statusOptions[$run->status] ?? $run->status }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">Aucune execution de paie enregistree pour le moment.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
