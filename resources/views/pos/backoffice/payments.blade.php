@extends('layouts.app')

@section('title', 'Paiements POS - Nema ERP')
@section('page-title', 'Paiements POS')

@section('content')
    @php
        $customerLabel = $businessVocabulary['client'] ?? 'Client';
        $counterCustomerLabel = in_array($businessVocabulary['profile_key'] ?? '', ['food_store', 'general_trade', 'pharmacy_parapharmacy'], true)
            ? 'Client comptoir'
            : $customerLabel.' comptoir';
    @endphp

    <div class="grid" style="gap:18px;">
        @include('pos.partials.backoffice-nav')

        <div class="page-head">
            <div>
                <h2 style="margin:0;">Paiements comptoir</h2>
                <div class="muted">Suivi des encaissements POS et des modes de paiement actives dans les caisses.</div>
            </div>
            <a href="{{ route('pos.settings.index', ['focus' => 'payment-methods']) }}" class="button button-secondary">Configurer les modes</a>
        </div>

        <div class="grid stats-grid">
            <div class="card"><div class="muted">Paiements recents</div><div class="stat-value">{{ $data['summary']['payments'] }}</div></div>
            <div class="card"><div class="muted">Especes</div><div class="stat-value">{{ number_format($data['summary']['cash_total'], 0, ',', ' ') }} XOF</div></div>
            <div class="card"><div class="muted">Mobile money</div><div class="stat-value">{{ number_format($data['summary']['mobile_total'], 0, ',', ' ') }} XOF</div></div>
            <div class="card"><div class="muted">Modes configures</div><div class="stat-value">{{ $data['summary']['configured_methods'] }}</div></div>
        </div>

        <div class="grid stats-grid">
            @foreach ($data['totals_by_method'] as $method)
                <div class="card">
                    <div class="muted">{{ $method['label'] }}</div>
                    <div class="stat-value">{{ number_format($method['amount'], 0, ',', ' ') }}</div>
                    <div class="help">{{ $method['count'] }} paiement(s)</div>
                </div>
            @endforeach
        </div>

        <section class="card">
            <h3 class="section-title">Paiements recents</h3>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Numero</th>
                            <th>{{ $customerLabel }}</th>
                            <th>Methode</th>
                            <th>Compte</th>
                            <th>Montant</th>
                            <th>Session</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($data['payments'] as $payment)
                            <tr>
                                <td>{{ $payment->payment_number }}</td>
                                <td>{{ $payment->partner?->name ?? $counterCustomerLabel }}</td>
                                <td>{{ \App\Support\PaymentMethodCatalog::label($payment->method) }}</td>
                                <td>{{ $payment->cashAccount?->name ?? 'n/a' }}</td>
                                <td>{{ number_format((float) $payment->amount, 0, ',', ' ') }} XOF</td>
                                <td>{{ $payment->posSession?->session_number ?? 'n/a' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="muted">Aucun paiement POS recent.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <h3 class="section-title">Methode POS configurees</h3>
            <div class="summary-stack">
                @forelse ($data['configured_methods'] as $method)
                    <div class="summary-box">
                        <strong>{{ $method->label }}</strong>
                        <div class="muted" style="margin-top:8px;">{{ $method->transaction_label ?: 'Sans libelle transaction' }} · {{ $method->cashAccount?->name ?? 'Compte non lie' }}</div>
                        <div class="help" style="margin-top:8px;">{{ $method->supports_change ? 'Rend la monnaie' : 'Sans rendu monnaie' }} · {{ $method->requires_reference ? 'Reference requise' : 'Reference optionnelle' }}</div>
                    </div>
                @empty
                    <div class="muted">Aucune methode POS specifique configuree.</div>
                @endforelse
            </div>
        </section>
    </div>
@endsection
