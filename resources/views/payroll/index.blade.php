@extends('layouts.app')

@section('title', 'Paie - Nema ERP')
@section('page-title', 'Paie')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Executions de paie</h2>
            <div class="muted">Socle paie approfondi: periodes, bulletins detailles, lignes salariales et mise en paiement.</div>
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
        <div class="card"><div class="muted">Bulletins a valider</div><div class="stat-value">{{ $summary['ready_slips'] }}</div></div>
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

        <form method="POST" action="{{ route('payroll.slips.store') }}" class="card form-grid" style="margin-bottom:18px;">
            @csrf
            <div class="full">
                <h3 class="section-title">Nouveau bulletin de paie</h3>
            </div>
            <div>
                <label for="slip_number">Numero bulletin</label>
                <input id="slip_number" name="slip_number" value="{{ old('slip_number') }}" placeholder="BUL-2026-00001">
            </div>
            <div>
                <label for="payroll_run_id">Execution</label>
                <select id="payroll_run_id" name="payroll_run_id">
                    <option value="">Hors execution</option>
                    @foreach ($runs as $run)
                        <option value="{{ $run->id }}" @selected(old('payroll_run_id') == $run->id)>{{ $run->run_number }} - {{ $run->label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="employee_id">Collaborateur</label>
                <select id="employee_id" name="employee_id" required>
                    <option value="">Selectionner</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}" @selected(old('employee_id') == $employee->id)>{{ $employee->full_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="slip_branch_id">Agence</label>
                <select id="slip_branch_id" name="branch_id">
                    <option value="">Agence de l employe</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="base_salary">Salaire de base</label>
                <input id="base_salary" name="base_salary" type="number" min="0" step="0.01" value="{{ old('base_salary', 0) }}">
            </div>
            <div>
                <label for="slip_gross_amount">Brut total</label>
                <input id="slip_gross_amount" name="gross_amount" type="number" min="0" step="0.01" value="{{ old('gross_amount', 0) }}">
            </div>
            <div>
                <label for="deductions_amount">Retenues</label>
                <input id="deductions_amount" name="deductions_amount" type="number" min="0" step="0.01" value="{{ old('deductions_amount', 0) }}">
            </div>
            <div>
                <label for="employer_contributions_amount">Charges patronales</label>
                <input id="employer_contributions_amount" name="employer_contributions_amount" type="number" min="0" step="0.01" value="{{ old('employer_contributions_amount', 0) }}">
            </div>
            <div>
                <label for="slip_net_amount">Net a payer</label>
                <input id="slip_net_amount" name="net_amount" type="number" min="0" step="0.01" value="{{ old('net_amount', 0) }}">
            </div>
            <div>
                <label for="slip_status">Statut</label>
                <select id="slip_status" name="status" required>
                    @foreach ($slipStatusOptions as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', 'draft') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="payout_mode">Mode de paiement</label>
                <select id="payout_mode" name="payout_mode" required>
                    @foreach ($payoutModeOptions as $value => $label)
                        <option value="{{ $value }}" @selected(old('payout_mode', 'bank') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="full">
                <label for="slip_notes">Notes</label>
                <textarea id="slip_notes" name="notes">{{ old('notes') }}</textarea>
            </div>
            <div class="full actions">
                <button type="submit" class="button button-primary">Enregistrer le bulletin</button>
            </div>
        </form>
    @endallowed

    <section class="card" style="margin-bottom:18px;">
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

    <section class="card">
        <h3 class="section-title">Bulletins detailles</h3>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Numero</th>
                    <th>Collaborateur</th>
                    <th>Execution</th>
                    <th>Brut</th>
                    <th>Retenues</th>
                    <th>Net</th>
                    <th>Lignes</th>
                    <th>Statut</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($slips as $slip)
                    <tr>
                        <td>{{ $slip->slip_number }}</td>
                        <td>{{ $slip->employee?->full_name ?? '-' }}</td>
                        <td>{{ $slip->payrollRun?->run_number ?? 'Hors execution' }}</td>
                        <td>{{ number_format((float) $slip->gross_amount, 0, ',', ' ') }} XOF</td>
                        <td>{{ number_format((float) $slip->deductions_amount, 0, ',', ' ') }} XOF</td>
                        <td>{{ number_format((float) $slip->net_amount, 0, ',', ' ') }} XOF</td>
                        <td>{{ $slip->lines->count() }}</td>
                        <td><span class="badge badge-muted">{{ $slipStatusOptions[$slip->status] ?? $slip->status }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted">Aucun bulletin detaille enregistre pour le moment.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
