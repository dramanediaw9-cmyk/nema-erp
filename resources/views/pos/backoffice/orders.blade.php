@extends('layouts.app')

@section('title', 'Commandes POS - Nema ERP')
@section('page-title', 'Commandes POS')

@section('content')
    @php
        $customerLabel = $businessVocabulary['client'] ?? 'Client';
        $saleLabel = $businessVocabulary['sale'] ?? 'Vente';
        $salesLabel = $businessVocabulary['sales'] ?? 'Ventes';
        $counterCustomerLabel = in_array($businessVocabulary['profile_key'] ?? '', ['food_store', 'general_trade', 'pharmacy_parapharmacy'], true)
            ? 'Client comptoir'
            : $customerLabel.' comptoir';
    @endphp

    <div class="grid" style="gap:18px;">
        @include('pos.partials.backoffice-nav')

        <div class="page-head">
            <div>
                <h2 style="margin:0;">Commandes, brouillons et retours</h2>
                <div class="muted">Vision back-office des tickets POS, commandes en attente et tickets retour.</div>
            </div>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <a href="{{ route('pos.sales.create') }}" class="button button-primary">+ {{ $saleLabel }}</a>
                <a href="{{ route('pos.report') }}" class="button button-secondary">Rapport journalier</a>
            </div>
        </div>

        <div class="grid stats-grid">
            <div class="card"><div class="muted">Commandes</div><div class="stat-value">{{ $data['summary']['orders'] }}</div></div>
            <div class="card"><div class="muted">Brouillons</div><div class="stat-value">{{ $data['summary']['drafts'] }}</div></div>
            <div class="card"><div class="muted">Retours</div><div class="stat-value">{{ $data['summary']['returns'] }}</div></div>
            <div class="card"><div class="muted">Tickets payes</div><div class="stat-value">{{ $data['summary']['paid'] }}</div></div>
        </div>

        <section class="card">
            <h3 class="section-title">Tickets POS recents</h3>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Numero</th>
                            <th>{{ $customerLabel }}</th>
                            <th>Session</th>
                            <th>Total</th>
                            <th>Statut</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($data['invoices'] as $invoice)
                            <tr>
                                <td>{{ $invoice->invoice_number }}</td>
                                <td>{{ $invoice->customer?->name ?? $counterCustomerLabel }}</td>
                                <td>{{ $invoice->posSession?->session_number ?? 'n/a' }}</td>
                                <td>{{ number_format((float) $invoice->total, 0, ',', ' ') }} XOF</td>
                                <td><span class="badge {{ $invoice->payment_status === 'paid' ? 'badge-success' : 'badge-warning' }}">{{ strtoupper($invoice->payment_status) }}</span></td>
                                <td><a href="{{ route('pos.receipt', $invoice) }}" class="button button-secondary">Ticket</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="muted">Aucune commande POS pour le moment.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div class="split">
            <section class="card">
                <h3 class="section-title">Brouillons en attente</h3>
                <div class="summary-stack">
                    @forelse ($data['drafts'] as $draft)
                        <div class="summary-box">
                            <strong>{{ $draft->label ?: 'Commande '.str_pad((string) $draft->id, 4, '0', STR_PAD_LEFT) }}</strong>
                            <div class="muted" style="margin-top:8px;">{{ $draft->customer?->name ?? $counterCustomerLabel }} · {{ number_format((float) $draft->total, 0, ',', ' ') }} XOF · {{ $draft->items_count }} ligne(s)</div>
                            <div class="help" style="margin-top:8px;">Derniere activite: {{ optional($draft->last_activity_at)->format('d/m/Y H:i') }}</div>
                        </div>
                    @empty
                        <div class="muted">Aucun brouillon en attente.</div>
                    @endforelse
                </div>
            </section>

            <section class="card">
                <h3 class="section-title">Retours recents</h3>
                <div class="summary-stack">
                    @forelse ($data['returns'] as $return)
                        <div class="summary-box">
                            <strong>{{ $return->return_number }}</strong>
                            <div class="muted" style="margin-top:8px;">{{ $return->invoice?->invoice_number }} · {{ number_format((float) $return->total, 0, ',', ' ') }} XOF</div>
                            <div class="help" style="margin-top:8px;">{{ optional($return->return_date)->format('d/m/Y') }} · {{ strtoupper($return->status) }}</div>
                        </div>
                    @empty
                        <div class="muted">Aucun retour traite pour le moment.</div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
@endsection
