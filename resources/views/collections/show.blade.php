@extends('layouts.app')

@section('title', 'Suivi recouvrement - Nema ERP')
@section('page-title', 'Recouvrement '.$invoice->invoice_number)

@section('content')
    @php($lastFollowUp = $stats['last_follow_up'])

    <div class="page-head">
        <div>
            <h2 style="margin:0;">{{ $invoice->customer?->name }}</h2>
            <div class="muted">Facture {{ $invoice->invoice_number }} · Agence {{ $invoice->branch?->name }} · Echeance {{ $invoice->due_date?->format('d/m/Y') ?: 'Non renseignee' }}</div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="{{ route('collections.index') }}" class="button button-secondary">Retour portefeuille</a>
            <a href="{{ route('sales.show', $invoice) }}" class="button button-secondary">Voir la facture</a>
            @allowed('payments.manage')
                @if ($invoice->payment_status !== 'paid')
                    <a href="{{ route('payments.create', ['invoice' => $invoice->id]) }}" class="button button-primary">Enregistrer un paiement</a>
                @endif
            @endallowed
        </div>
    </div>

    <div class="grid stats-grid" style="margin-bottom:20px;">
        <div class="card"><div class="muted">Solde restant</div><div class="stat-value">{{ number_format((float) $invoice->balance_due, 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Total facture</div><div class="stat-value">{{ number_format((float) $invoice->total, 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Jours de retard</div><div class="stat-value">{{ $stats['days_overdue'] }}</div></div>
        <div class="card"><div class="muted">Relances</div><div class="stat-value">{{ $stats['follow_up_count'] }}</div></div>
        <div class="card"><div class="muted">Portefeuille client</div><div class="stat-value">{{ number_format($stats['customer_open_balance'], 0, ',', ' ') }}</div></div>
    </div>

    <div class="split" style="margin-bottom:20px;">
        <section class="card">
            <h2 style="margin-top:0;">Lecture recouvrement</h2>
            <div class="grid">
                <div><strong>Statut facture</strong><div class="muted">{{ $invoice->payment_status === 'paid' ? 'Reglee' : ($invoice->payment_status === 'partial' ? 'Partiellement reglee' : 'Impayee') }}</div></div>
                <div><strong>Derniere relance</strong><div class="muted">{{ $lastFollowUp ? $lastFollowUp->action_date?->format('d/m/Y') : 'Aucune' }}</div></div>
                <div><strong>Derniere issue</strong><div class="muted">{{ $lastFollowUp ? ($outcomeOptions[$lastFollowUp->outcome] ?? 'Sans issue') : 'Aucune' }}</div></div>
                <div><strong>Prochaine action</strong><div class="muted">{{ $lastFollowUp?->next_action_date?->format('d/m/Y') ?: 'Non planifiee' }}</div></div>
            </div>
            @if ($invoice->notes)
                <div class="muted" style="margin-top:14px;">{{ $invoice->notes }}</div>
            @endif
        </section>

        <section class="card">
            <h2 style="margin-top:0;">Nouvelle relance</h2>
            @allowed('collections.manage')
                <form method="POST" action="{{ route('collections.follow-ups.store', $invoice) }}" class="form-grid">
                    @csrf
                    <div>
                        <label for="action_date">Date de relance</label>
                        <input id="action_date" name="action_date" type="date" value="{{ old('action_date', now()->toDateString()) }}" required>
                        @error('action_date')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label for="action_type">Canal</label>
                        <select id="action_type" name="action_type" required>
                            <option value="">Choisir</option>
                            @foreach ($actionOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('action_type') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('action_type')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label for="outcome">Issue</label>
                        <select id="outcome" name="outcome">
                            <option value="">Non renseignee</option>
                            @foreach ($outcomeOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('outcome') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('outcome')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label for="next_action_date">Prochaine action</label>
                        <input id="next_action_date" name="next_action_date" type="date" value="{{ old('next_action_date') }}">
                        @error('next_action_date')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label for="contact_name">Contact</label>
                        <input id="contact_name" name="contact_name" value="{{ old('contact_name') }}" placeholder="Nom de l interlocuteur">
                        @error('contact_name')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label for="contact_phone">Telephone contacte</label>
                        <input id="contact_phone" name="contact_phone" value="{{ old('contact_phone', $invoice->customer?->phone) }}">
                        @error('contact_phone')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label for="promised_amount">Montant promis</label>
                        <input id="promised_amount" name="promised_amount" type="number" min="0" step="0.01" value="{{ old('promised_amount') }}">
                        <div class="help">Maximum conseille : {{ number_format((float) $invoice->balance_due, 0, ',', ' ') }} XOF</div>
                        @error('promised_amount')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label for="promised_date">Date promise</label>
                        <input id="promised_date" name="promised_date" type="date" value="{{ old('promised_date') }}">
                        @error('promised_date')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="full">
                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes" placeholder="Resume de l echange, blocage, commentaire utile pour la prochaine relance">{{ old('notes') }}</textarea>
                        @error('notes')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="full actions">
                        <button type="submit" class="button button-primary">Enregistrer la relance</button>
                    </div>
                </form>
            @else
                <p class="muted">Tu as uniquement l acces en lecture sur ce dossier de recouvrement.</p>
            @endallowed
        </section>
    </div>

    <div class="split">
        <section class="card">
            <h2 style="margin-top:0;">Historique des relances</h2>
            @forelse ($followUps as $followUp)
                <div style="padding-bottom:14px; border-bottom:1px solid #efe4d3; margin-bottom:14px;">
                    <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start; flex-wrap:wrap;">
                        <div>
                            <div style="font-weight:600;">{{ $actionOptions[$followUp->action_type] ?? ucfirst(str_replace('_', ' ', $followUp->action_type)) }}</div>
                            <div class="muted" style="margin-top:6px;">{{ $followUp->action_date?->format('d/m/Y') }} · {{ $followUp->creator?->name ?? 'Systeme' }}</div>
                            <div class="muted" style="margin-top:6px;">Issue : {{ $outcomeOptions[$followUp->outcome] ?? 'Non renseignee' }}</div>
                            @if ($followUp->contact_name || $followUp->contact_phone)
                                <div class="help" style="margin-top:6px;">Contact : {{ $followUp->contact_name ?: 'Non renseigne' }} · {{ $followUp->contact_phone ?: 'Telephone non renseigne' }}</div>
                            @endif
                            @if ($followUp->promised_date)
                                <div class="help" style="margin-top:6px;">Promesse : {{ $followUp->promised_date->format('d/m/Y') }} @if($followUp->promised_amount) · {{ number_format((float) $followUp->promised_amount, 0, ',', ' ') }} XOF @endif</div>
                            @endif
                            @if ($followUp->next_action_date)
                                <div class="help" style="margin-top:6px;">Prochaine action : {{ $followUp->next_action_date->format('d/m/Y') }}</div>
                            @endif
                            @if ($followUp->notes)
                                <div style="margin-top:8px;">{{ $followUp->notes }}</div>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <p class="muted">Aucune relance enregistree sur cette facture.</p>
            @endforelse
        </section>

        <section class="card">
            <h2 style="margin-top:0;">Autres factures ouvertes du client</h2>
            @forelse ($customerOpenInvoices as $otherInvoice)
                <div style="padding-bottom:14px; border-bottom:1px solid #efe4d3; margin-bottom:14px; display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                    <div>
                        <div style="font-weight:600;">{{ $otherInvoice->invoice_number }}</div>
                        <div class="muted" style="margin-top:6px;">{{ $otherInvoice->invoice_date?->format('d/m/Y') }} · Echeance {{ $otherInvoice->due_date?->format('d/m/Y') ?: 'Non renseignee' }}</div>
                        <div style="margin-top:6px;">Reste {{ number_format((float) $otherInvoice->balance_due, 0, ',', ' ') }} XOF</div>
                    </div>
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <a href="{{ route('collections.show', $otherInvoice) }}" class="button button-secondary">Suivre</a>
                        <a href="{{ route('sales.show', $otherInvoice) }}" class="button button-secondary">Facture</a>
                    </div>
                </div>
            @empty
                <p class="muted">Aucune autre facture ouverte pour ce client.</p>
            @endforelse
        </section>
    </div>
@endsection