@extends('layouts.app')

@section('title', 'Nouvelle opportunite - Nema ERP')
@section('page-title', 'Nouvelle opportunite')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Creation opportunite</h2>
            <div class="muted">Saisir un dossier commercial avant devis ou commande.</div>
        </div>
        <a href="{{ route('crm.index') }}" class="button button-secondary">Retour</a>
    </div>

    <form method="POST" action="{{ route('crm.store') }}" class="card form-grid">
        @csrf
        <div>
            <label for="lead_name">Nom du prospect / client</label>
            <input id="lead_name" name="lead_name" value="{{ old('lead_name') }}" required>
            @error('lead_name')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <div>
            <label for="title">Objet commercial</label>
            <input id="title" name="title" value="{{ old('title') }}" required>
            @error('title')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <div>
            <label for="branch_id">Agence</label>
            <select id="branch_id" name="branch_id">
                <option value="">Agence active</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
            @error('branch_id')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <div>
            <label for="partner_id">Client existant</label>
            <select id="partner_id" name="partner_id">
                <option value="">Aucun</option>
                @foreach ($customers as $customer)
                    <option value="{{ $customer->id }}" @selected(old('partner_id') == $customer->id)>{{ $customer->code }} · {{ $customer->name }}</option>
                @endforeach
            </select>
            @error('partner_id')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <div>
            <label for="contact_name">Interlocuteur</label>
            <input id="contact_name" name="contact_name" value="{{ old('contact_name') }}">
            @error('contact_name')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <div>
            <label for="contact_phone">Telephone</label>
            <input id="contact_phone" name="contact_phone" value="{{ old('contact_phone') }}">
            @error('contact_phone')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <div>
            <label for="contact_email">Email</label>
            <input id="contact_email" name="contact_email" value="{{ old('contact_email') }}">
            @error('contact_email')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <div>
            <label for="source">Source</label>
            <input id="source" name="source" value="{{ old('source') }}" placeholder="Reseau, recommandation, visite terrain...">
            @error('source')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <div>
            <label for="stage">Etape</label>
            <select id="stage" name="stage" required>
                @foreach ($stageOptions as $value => $label)
                    <option value="{{ $value }}" @selected(old('stage', 'new') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('stage')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <div>
            <label for="expected_amount">Montant espere</label>
            <input id="expected_amount" name="expected_amount" type="number" min="0" step="0.01" value="{{ old('expected_amount') }}">
            @error('expected_amount')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <div>
            <label for="expected_close_date">Date de closing visee</label>
            <input id="expected_close_date" name="expected_close_date" type="date" value="{{ old('expected_close_date') }}">
            @error('expected_close_date')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <div>
            <label for="last_contact_date">Dernier contact</label>
            <input id="last_contact_date" name="last_contact_date" type="date" value="{{ old('last_contact_date') }}">
            @error('last_contact_date')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <div class="full">
            <label for="notes">Notes</label>
            <textarea id="notes" name="notes">{{ old('notes') }}</textarea>
            @error('notes')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <div class="full actions">
            <a href="{{ route('crm.index') }}" class="button button-secondary">Annuler</a>
            <button type="submit" class="button button-primary">Enregistrer</button>
        </div>
    </form>
@endsection
