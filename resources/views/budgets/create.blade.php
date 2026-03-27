@extends('layouts.app')

@section('title', 'Nouveau budget - Nema ERP')
@section('page-title', 'Nouveau budget')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Construction du budget</h2>
            <div class="muted">Definis les objectifs par mois et par axe metier.</div>
        </div>
        <a href="{{ route('budgets.index') }}" class="button button-secondary">Retour</a>
    </div>

    <form method="POST" action="{{ route('budgets.store') }}" class="grid" style="gap:20px;">
        @csrf

        <section class="card">
            <div class="form-grid">
                <div>
                    <label for="name">Nom du budget</label>
                    <input id="name" name="name" value="{{ old('name', 'Budget de pilotage '.$currentYear) }}" required>
                    @error('name')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="fiscal_year">Exercice</label>
                    <input id="fiscal_year" name="fiscal_year" type="number" min="2020" max="2100" value="{{ old('fiscal_year', $currentYear) }}" required>
                    @error('fiscal_year')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="branch_id">Agence</label>
                    <select id="branch_id" name="branch_id">
                        <option value="">Toutes les agences</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                    @error('branch_id')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="status">Statut</label>
                    <select id="status" name="status" required>
                        @foreach ($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', 'draft') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('status')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="full">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" placeholder="Contexte budgetaire, hypothese de croissance, saisonnalite...">{{ old('notes') }}</textarea>
                    @error('notes')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>
        </section>

        <section class="card">
            <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:flex-start; margin-bottom:14px;">
                <div>
                    <h2 style="margin:0;">Lignes budgetaires</h2>
                    <div class="muted" style="margin-top:6px;">Utilise une ligne par axe et par mois.</div>
                </div>
            </div>
            @error('lines')<div class="alert alert-error">{{ $message }}</div>@enderror
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Axe</th>
                        <th>Mois</th>
                        <th>Montant cible</th>
                        <th>Note</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($defaultRows as $index => $line)
                        <tr>
                            <td>
                                <select name="lines[{{ $index }}][metric]">
                                    <option value="">Choisir</option>
                                    @foreach ($metricOptions as $value => $label)
                                        <option value="{{ $value }}" @selected(($line['metric'] ?? '') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select name="lines[{{ $index }}][period_month]">
                                    <option value="">Choisir</option>
                                    @foreach ($monthOptions as $value => $label)
                                        <option value="{{ $value }}" @selected((string) ($line['period_month'] ?? '') === (string) $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input type="number" name="lines[{{ $index }}][amount]" min="0" step="0.01" value="{{ $line['amount'] ?? '' }}" placeholder="0">
                            </td>
                            <td>
                                <input type="text" name="lines[{{ $index }}][notes]" value="{{ $line['notes'] ?? '' }}" placeholder="Optionnel">
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <div class="actions">
            <a href="{{ route('budgets.index') }}" class="button button-secondary">Annuler</a>
            <button type="submit" class="button button-primary">Enregistrer le budget</button>
        </div>
    </form>
@endsection
