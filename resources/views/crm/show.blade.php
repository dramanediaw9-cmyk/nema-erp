@extends('layouts.app')

@section('title', 'Opportunite '.$opportunity->title.' - Nema ERP')
@section('page-title', 'Opportunite '.$opportunity->title)

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">{{ $opportunity->lead_name }}</h2>
            <div class="muted">{{ $opportunity->branch?->name ?? 'Agence active' }} · {{ $opportunity->source ?: 'Source non renseignee' }}</div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="{{ route('crm.index') }}" class="button button-secondary">Retour CRM</a>
            @if ($opportunity->partner)
                <a href="{{ route('customers.show', $opportunity->partner) }}" class="button button-secondary">Voir le client</a>
            @else
                @allowed('crm.manage')
                    <form method="POST" action="{{ route('crm.convert-customer', $opportunity) }}">
                        @csrf
                        <button type="submit" class="button button-primary">Convertir en client</button>
                    </form>
                @endallowed
            @endif
        </div>
    </div>

    <div class="grid stats-grid" style="margin-bottom:20px;">
        <div class="card"><div class="muted">Etape</div><div class="stat-value" style="font-size:24px;">{{ $stageOptions[$opportunity->stage] ?? ucfirst($opportunity->stage) }}</div></div>
        <div class="card"><div class="muted">Montant espere</div><div class="stat-value">{{ number_format((float) ($opportunity->expected_amount ?? 0), 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Closing vise</div><div class="stat-value" style="font-size:24px;">{{ $opportunity->expected_close_date?->format('d/m/Y') ?: 'n/a' }}</div></div>
        <div class="card"><div class="muted">Dernier contact</div><div class="stat-value" style="font-size:24px;">{{ $opportunity->last_contact_date?->format('d/m/Y') ?: 'n/a' }}</div></div>
    </div>

    <div class="split" style="margin-bottom:20px;">
        <section class="card">
            <h2 style="margin-top:0;">Fiche commerciale</h2>
            <div class="grid">
                <div><strong>Objet</strong><div class="muted">{{ $opportunity->title }}</div></div>
                <div><strong>Prospect</strong><div class="muted">{{ $opportunity->lead_name }}</div></div>
                <div><strong>Interlocuteur</strong><div class="muted">{{ $opportunity->contact_name ?: 'Non renseigne' }}</div></div>
                <div><strong>Telephone</strong><div class="muted">{{ $opportunity->contact_phone ?: 'Non renseigne' }}</div></div>
                <div><strong>Email</strong><div class="muted">{{ $opportunity->contact_email ?: 'Non renseigne' }}</div></div>
                <div><strong>Client lie</strong><div class="muted">{{ $opportunity->partner?->name ?: 'Aucun client encore cree' }}</div></div>
            </div>
            @if ($opportunity->notes)
                <div class="muted" style="margin-top:14px;">{{ $opportunity->notes }}</div>
            @endif
        </section>

        <section class="card">
            <h2 style="margin-top:0;">Changer d etape</h2>
            @allowed('crm.manage')
                <form method="POST" action="{{ route('crm.update-stage', $opportunity) }}" class="form-grid">
                    @csrf
                    <div>
                        <label for="stage">Etape pipeline</label>
                        <select id="stage" name="stage" required>
                            @foreach ($stageOptions as $value => $label)
                                <option value="{{ $value }}" @selected($opportunity->stage === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="actions" style="margin-top:28px; justify-content:flex-start;">
                        <button type="submit" class="button button-primary">Mettre a jour</button>
                    </div>
                </form>
            @else
                <p class="muted">Acces lecture seule sur cette opportunite.</p>
            @endallowed
        </section>
    </div>
@endsection
