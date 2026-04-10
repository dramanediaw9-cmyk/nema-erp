@extends('layouts.app')

@section('title', 'Projets - Nema ERP')
@section('page-title', 'Projets')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Pilotage projets</h2>
            <div class="muted">Socle execution projet: proprietaire, client, budget et niveau de risque.</div>
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
        <div class="card"><div class="muted">Projets</div><div class="stat-value">{{ $summary['projects'] }}</div></div>
        <div class="card"><div class="muted">Actifs</div><div class="stat-value">{{ $summary['active'] }}</div></div>
        <div class="card"><div class="muted">Sous tension</div><div class="stat-value">{{ $summary['at_risk'] }}</div></div>
        <div class="card"><div class="muted">Budget cumule</div><div class="stat-value">{{ number_format($summary['budget'], 0, ',', ' ') }} XOF</div></div>
    </div>

    @allowed('projects.manage')
        <form method="POST" action="{{ route('projects.store') }}" class="card form-grid" style="margin-bottom:18px;">
            @csrf
            <div class="full">
                <h3 class="section-title">Nouveau projet</h3>
            </div>
            <div>
                <label for="code">Code</label>
                <input id="code" name="code" value="{{ old('code') }}" placeholder="PRJ-2026-0001">
            </div>
            <div>
                <label for="name">Nom</label>
                <input id="name" name="name" value="{{ old('name') }}" required>
            </div>
            <div>
                <label for="customer_name">Client</label>
                <input id="customer_name" name="customer_name" value="{{ old('customer_name') }}">
            </div>
            <div>
                <label for="owner_id">Proprietaire</label>
                <select id="owner_id" name="owner_id">
                    <option value="">Non affecte</option>
                    @foreach ($owners as $owner)
                        <option value="{{ $owner->id }}" @selected(old('owner_id') == $owner->id)>{{ $owner->name }}</option>
                    @endforeach
                </select>
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
                        <option value="{{ $value }}" @selected(old('status', 'planning') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="start_date">Debut</label>
                <input id="start_date" name="start_date" type="date" value="{{ old('start_date', now()->toDateString()) }}" required>
            </div>
            <div>
                <label for="target_end_date">Echeance</label>
                <input id="target_end_date" name="target_end_date" type="date" value="{{ old('target_end_date', now()->addMonth()->toDateString()) }}">
            </div>
            <div>
                <label for="progress">Avancement</label>
                <input id="progress" name="progress" type="number" min="0" max="100" value="{{ old('progress', 0) }}">
            </div>
            <div>
                <label for="budget_amount">Budget</label>
                <input id="budget_amount" name="budget_amount" type="number" min="0" step="0.01" value="{{ old('budget_amount', 0) }}">
            </div>
            <div class="full">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes">{{ old('notes') }}</textarea>
            </div>
            <div class="full actions">
                <button type="submit" class="button button-primary">Enregistrer le projet</button>
            </div>
        </form>
    @endallowed

    <section class="card">
        <h3 class="section-title">Portefeuille projets</h3>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Code</th>
                    <th>Nom</th>
                    <th>Client</th>
                    <th>Proprietaire</th>
                    <th>Avancement</th>
                    <th>Budget</th>
                    <th>Statut</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($projects as $project)
                    <tr>
                        <td>{{ $project->code }}</td>
                        <td>{{ $project->name }}</td>
                        <td>{{ $project->customer_name ?: '-' }}</td>
                        <td>{{ $project->owner?->name ?? '-' }}</td>
                        <td>{{ $project->progress }}%</td>
                        <td>{{ number_format((float) $project->budget_amount, 0, ',', ' ') }} XOF</td>
                        <td><span class="badge badge-muted">{{ $statusOptions[$project->status] ?? $project->status }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">Aucun projet enregistre pour le moment.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
