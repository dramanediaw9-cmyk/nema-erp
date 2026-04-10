@extends('layouts.app')

@section('title', 'Capital humain - Nema ERP')
@section('page-title', 'Capital humain')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Capital humain</h2>
            <div class="muted">Socle RH operationnel: departements, collaborateurs, contrats, cycles de paie et gestion des conges.</div>
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
        <div class="card"><div class="muted">Departements</div><div class="stat-value">{{ $summary['departments'] }}</div></div>
        <div class="card"><div class="muted">Collaborateurs</div><div class="stat-value">{{ $summary['employees'] }}</div></div>
        <div class="card"><div class="muted">Actifs</div><div class="stat-value">{{ $summary['active_employees'] }}</div></div>
        <div class="card"><div class="muted">Paie mensuelle</div><div class="stat-value">{{ number_format($summary['monthly_payroll'], 0, ',', ' ') }} XOF</div></div>
        <div class="card"><div class="muted">Conges ouverts</div><div class="stat-value">{{ $summary['open_leave_requests'] }}</div></div>
    </div>

    @allowed('hr.manage')
        <div class="split" style="margin-bottom:18px;">
            <form method="POST" action="{{ route('hr.departments.store') }}" class="card form-grid">
                @csrf
                <div class="full">
                    <h3 class="section-title">Nouveau departement</h3>
                </div>
                <div>
                    <label for="department_code">Code</label>
                    <input id="department_code" name="code" value="{{ old('code') }}" placeholder="DEP-0001">
                </div>
                <div>
                    <label for="department_name">Nom</label>
                    <input id="department_name" name="name" value="{{ old('name') }}" required>
                </div>
                <div>
                    <label for="department_branch">Agence</label>
                    <select id="department_branch" name="branch_id">
                        <option value="">Toutes les agences</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="department_manager">Manager</label>
                    <input id="department_manager" name="manager_name" value="{{ old('manager_name') }}">
                </div>
                <div>
                    <label for="department_target">Effectif cible</label>
                    <input id="department_target" name="headcount_target" type="number" min="0" value="{{ old('headcount_target', 0) }}">
                </div>
                <div>
                    <label for="department_status">Statut</label>
                    <select id="department_status" name="status" required>
                        @foreach ($departmentStatusOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', 'active') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="full">
                    <label for="department_notes">Notes</label>
                    <textarea id="department_notes" name="notes">{{ old('notes') }}</textarea>
                </div>
                <div class="full actions">
                    <button type="submit" class="button button-primary">Enregistrer le departement</button>
                </div>
            </form>

            <form method="POST" action="{{ route('hr.employees.store') }}" class="card form-grid">
                @csrf
                <div class="full">
                    <h3 class="section-title">Nouveau collaborateur</h3>
                </div>
                <div>
                    <label for="employee_number">Matricule</label>
                    <input id="employee_number" name="employee_number" value="{{ old('employee_number') }}" placeholder="EMP-2026-00001">
                </div>
                <div>
                    <label for="employee_name">Nom complet</label>
                    <input id="employee_name" name="full_name" value="{{ old('full_name') }}" required>
                </div>
                <div>
                    <label for="employee_email">Email</label>
                    <input id="employee_email" name="email" type="email" value="{{ old('email') }}">
                </div>
                <div>
                    <label for="employee_phone">Telephone</label>
                    <input id="employee_phone" name="phone" value="{{ old('phone') }}">
                </div>
                <div>
                    <label for="employee_job_title">Poste</label>
                    <input id="employee_job_title" name="job_title" value="{{ old('job_title') }}">
                </div>
                <div>
                    <label for="employee_department">Departement</label>
                    <select id="employee_department" name="department_id">
                        <option value="">Aucun</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected(old('department_id') == $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="employee_branch">Agence</label>
                    <select id="employee_branch" name="branch_id">
                        <option value="">Agence globale</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="employee_contract">Contrat</label>
                    <select id="employee_contract" name="contract_type" required>
                        @foreach ($contractOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('contract_type', 'permanent') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="employee_hire_date">Date embauche</label>
                    <input id="employee_hire_date" name="hire_date" type="date" value="{{ old('hire_date', now()->toDateString()) }}" required>
                </div>
                <div>
                    <label for="employee_status">Statut</label>
                    <select id="employee_status" name="status" required>
                        @foreach ($employeeStatusOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', 'active') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="employee_cycle">Cycle paie</label>
                    <select id="employee_cycle" name="payroll_cycle" required>
                        @foreach ($payrollCycleOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('payroll_cycle', 'monthly') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="employee_salary">Salaire base</label>
                    <input id="employee_salary" name="base_salary" type="number" min="0" step="0.01" value="{{ old('base_salary', 0) }}">
                </div>
                <div class="full">
                    <label for="employee_notes">Notes</label>
                    <textarea id="employee_notes" name="notes">{{ old('notes') }}</textarea>
                </div>
                <div class="full actions">
                    <button type="submit" class="button button-primary">Enregistrer le collaborateur</button>
                </div>
            </form>
        </div>

        <form method="POST" action="{{ route('hr.leave-requests.store') }}" class="card form-grid" style="margin-bottom:18px;">
            @csrf
            <div class="full">
                <h3 class="section-title">Nouvelle demande de conge</h3>
            </div>
            <div>
                <label for="leave_number">Numero</label>
                <input id="leave_number" name="leave_number" value="{{ old('leave_number') }}" placeholder="CONGE-2026-0001">
            </div>
            <div>
                <label for="leave_employee">Collaborateur</label>
                <select id="leave_employee" name="employee_id" required>
                    <option value="">Selectionner</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}" @selected(old('employee_id') == $employee->id)>{{ $employee->full_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="leave_branch">Agence</label>
                <select id="leave_branch" name="branch_id">
                    <option value="">Agence de l employe</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="leave_type">Type</label>
                <select id="leave_type" name="leave_type" required>
                    @foreach ($leaveTypeOptions as $value => $label)
                        <option value="{{ $value }}" @selected(old('leave_type', 'annual') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="leave_start_date">Debut</label>
                <input id="leave_start_date" name="start_date" type="date" value="{{ old('start_date', now()->toDateString()) }}" required>
            </div>
            <div>
                <label for="leave_end_date">Fin</label>
                <input id="leave_end_date" name="end_date" type="date" value="{{ old('end_date', now()->addDays(2)->toDateString()) }}" required>
            </div>
            <div>
                <label for="leave_total_days">Jours</label>
                <input id="leave_total_days" name="total_days" type="number" min="0.5" step="0.5" value="{{ old('total_days', 3) }}">
            </div>
            <div>
                <label for="leave_status">Statut</label>
                <select id="leave_status" name="status" required>
                    @foreach ($leaveStatusOptions as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', 'draft') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="full">
                <label for="coverage_plan">Plan de couverture</label>
                <input id="coverage_plan" name="coverage_plan" value="{{ old('coverage_plan') }}" placeholder="Relais caisse assure par l equipe BKO 2">
            </div>
            <div class="full">
                <label for="leave_notes">Notes</label>
                <textarea id="leave_notes" name="notes">{{ old('notes') }}</textarea>
            </div>
            <div class="full actions">
                <button type="submit" class="button button-primary">Enregistrer la demande</button>
            </div>
        </form>
    @endallowed

    <div class="split">
        <section class="card">
            <h3 class="section-title">Departements</h3>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Code</th>
                        <th>Nom</th>
                        <th>Agence</th>
                        <th>Manager</th>
                        <th>Cible</th>
                        <th>Equipe</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($departments as $department)
                        <tr>
                            <td>{{ $department->code }}</td>
                            <td>{{ $department->name }}</td>
                            <td>{{ $department->branch?->name ?? 'Toutes' }}</td>
                            <td>{{ $department->manager_name ?: '-' }}</td>
                            <td>{{ $department->headcount_target }}</td>
                            <td>{{ $department->employees->count() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="muted">Aucun departement enregistre pour le moment.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <h3 class="section-title">Collaborateurs</h3>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Matricule</th>
                        <th>Nom</th>
                        <th>Poste</th>
                        <th>Departement</th>
                        <th>Cycle</th>
                        <th>Salaire</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($employees as $employee)
                        <tr>
                            <td>{{ $employee->employee_number }}</td>
                            <td>{{ $employee->full_name }}</td>
                            <td>{{ $employee->job_title ?: '-' }}</td>
                            <td>{{ $employee->department?->name ?? '-' }}</td>
                            <td>{{ $payrollCycleOptions[$employee->payroll_cycle] ?? $employee->payroll_cycle }}</td>
                            <td>{{ number_format((float) $employee->base_salary, 0, ',', ' ') }} XOF</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="muted">Aucun collaborateur enregistre pour le moment.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <section class="card" style="margin-top:18px;">
        <h3 class="section-title">Conges et absences</h3>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Numero</th>
                    <th>Collaborateur</th>
                    <th>Type</th>
                    <th>Periode</th>
                    <th>Jours</th>
                    <th>Couverture</th>
                    <th>Statut</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($leaveRequests as $leaveRequest)
                    <tr>
                        <td>{{ $leaveRequest->leave_number }}</td>
                        <td>{{ $leaveRequest->employee?->full_name ?? '-' }}</td>
                        <td>{{ $leaveTypeOptions[$leaveRequest->leave_type] ?? $leaveRequest->leave_type }}</td>
                        <td>{{ $leaveRequest->start_date?->format('d/m/Y') }} - {{ $leaveRequest->end_date?->format('d/m/Y') }}</td>
                        <td>{{ number_format((float) $leaveRequest->total_days, 1, ',', ' ') }}</td>
                        <td>{{ $leaveRequest->coverage_plan ?: '-' }}</td>
                        <td><span class="badge badge-muted">{{ $leaveStatusOptions[$leaveRequest->status] ?? $leaveRequest->status }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">Aucune demande de conge enregistree pour le moment.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
