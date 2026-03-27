@extends('layouts.app')

@section('title', 'Nouvelle immobilisation - Nema ERP')
@section('page-title', 'Nouvelle immobilisation')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Fiche d actif</h2>
            <div class="muted">Enregistre un bien a amortir dans le registre.</div>
        </div>
        <a href="{{ route('fixed-assets.index') }}" class="button button-secondary">Retour</a>
    </div>

    <form method="POST" action="{{ route('fixed-assets.store') }}" class="card form-grid">
        @csrf
        <div>
            <label for="name">Nom</label>
            <input id="name" name="name" value="{{ old('name') }}" required>
            @error('name')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <div>
            <label for="category">Categorie</label>
            <input id="category" name="category" value="{{ old('category') }}" placeholder="Materiel roulant, informatique, mobilier...">
            @error('category')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <div>
            <label for="branch_id">Agence</label>
            <select id="branch_id" name="branch_id">
                <option value="">Agence active / globale</option>
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
                    <option value="{{ $value }}" @selected(old('status', 'active') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('status')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <div>
            <label for="acquisition_date">Date d acquisition</label>
            <input id="acquisition_date" name="acquisition_date" type="date" value="{{ old('acquisition_date', now()->toDateString()) }}" required>
            @error('acquisition_date')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <div>
            <label for="commissioning_date">Date de mise en service</label>
            <input id="commissioning_date" name="commissioning_date" type="date" value="{{ old('commissioning_date', now()->toDateString()) }}">
            @error('commissioning_date')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <div>
            <label for="depreciation_start_date">Debut amortissement</label>
            <input id="depreciation_start_date" name="depreciation_start_date" type="date" value="{{ old('depreciation_start_date', now()->startOfMonth()->toDateString()) }}" required>
            @error('depreciation_start_date')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <div>
            <label for="depreciation_method">Methode</label>
            <select id="depreciation_method" name="depreciation_method" required>
                @foreach ($methodOptions as $value => $label)
                    <option value="{{ $value }}" @selected(old('depreciation_method', 'linear') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('depreciation_method')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <div>
            <label for="useful_life_months">Duree utile (mois)</label>
            <input id="useful_life_months" name="useful_life_months" type="number" min="1" max="600" value="{{ old('useful_life_months', 36) }}" required>
            @error('useful_life_months')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <div>
            <label for="acquisition_cost">Cout d acquisition</label>
            <input id="acquisition_cost" name="acquisition_cost" type="number" min="0" step="0.01" value="{{ old('acquisition_cost') }}" required>
            @error('acquisition_cost')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <div>
            <label for="salvage_value">Valeur residuelle</label>
            <input id="salvage_value" name="salvage_value" type="number" min="0" step="0.01" value="{{ old('salvage_value', 0) }}">
            @error('salvage_value')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <div class="full">
            <label for="notes">Notes</label>
            <textarea id="notes" name="notes" placeholder="Reference, emplacement, commentaire utile...">{{ old('notes') }}</textarea>
            @error('notes')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <div class="full actions">
            <a href="{{ route('fixed-assets.index') }}" class="button button-secondary">Annuler</a>
            <button type="submit" class="button button-primary">Enregistrer</button>
        </div>
    </form>
@endsection
