@extends('layouts.app')

@section('title', 'Analyse POS - Nema ERP')
@section('page-title', 'Analyse POS')

@section('content')
    @php
        $productLabel = $businessVocabulary['product'] ?? 'Produit';
        $productsLabel = $businessVocabulary['products'] ?? 'Produits';
        $salesLabel = $businessVocabulary['sales'] ?? 'Ventes';
    @endphp

    <div class="grid" style="gap:18px;">
        @include('pos.partials.backoffice-nav')

        <div class="page-head">
            <div>
                <h2 style="margin:0;">Analyse commandes, {{ strtolower($salesLabel) }} et preparation</h2>
                <div class="muted">Lecture rapide des indicateurs de caisse, du rapport de session et des objectifs de preparation.</div>
            </div>
            <a href="{{ route('pos.report') }}" class="button button-secondary">Rapport journalier detaille</a>
        </div>

        <div class="grid stats-grid">
            <div class="card"><div class="muted">Tickets du jour</div><div class="stat-value">{{ $data['report']['sales_count'] }}</div></div>
            <div class="card"><div class="muted">Brut {{ strtolower($productsLabel) }}</div><div class="stat-value">{{ number_format($data['report']['gross_sales'], 0, ',', ' ') }}</div></div>
            <div class="card"><div class="muted">Total net</div><div class="stat-value">{{ number_format($data['report']['net_sales'], 0, ',', ' ') }}</div></div>
            <div class="card"><div class="muted">Ticket moyen</div><div class="stat-value">{{ number_format($data['report']['average_ticket'], 0, ',', ' ') }}</div></div>
            <div class="card"><div class="muted">Objectif prep moyen</div><div class="stat-value">{{ $data['prep']['average_target_minutes'] ? number_format($data['prep']['average_target_minutes'], 1, ',', ' ') . ' min' : 'n/a' }}</div></div>
        </div>

        <div class="split">
            <section class="card">
                <h3 class="section-title">Top {{ strtolower($productsLabel) }} du jour</h3>
                <div class="summary-stack">
                    @forelse ($data['report']['top_products'] as $product)
                        <div class="summary-box">
                            <strong>{{ $product->name }}</strong>
                            <div class="muted" style="margin-top:8px;">{{ number_format((float) $product->qty, 0, ',', ' ') }} unite(s) · {{ number_format((float) $product->amount, 0, ',', ' ') }} XOF</div>
                        </div>
                    @empty
                        <div class="muted">Aucune {{ strtolower($salesLabel) }} POS aujourd hui.</div>
                    @endforelse
                </div>
            </section>

            <section class="card">
                <h3 class="section-title">Rapport de session</h3>
                <div class="summary-stack">
                    @forelse ($data['session_report'] as $session)
                        <div class="summary-box">
                            <strong>{{ $session->session_number }}</strong>
                            <div class="muted" style="margin-top:8px;">{{ optional($session->opened_at)->format('d/m/Y H:i') }} · {{ strtoupper($session->status) }}</div>
                            <div class="help" style="margin-top:8px;">Attendu {{ number_format((float) $session->expected_amount, 0, ',', ' ') }} XOF · variance {{ number_format((float) $session->variance_amount, 0, ',', ' ') }} XOF</div>
                        </div>
                    @empty
                        <div class="muted">Aucune session POS disponible.</div>
                    @endforelse
                </div>
            </section>
        </div>

        <section class="card">
            <h3 class="section-title">Temps de preparation</h3>
            <div class="grid stats-grid">
                <div class="card"><div class="muted">Imprimantes</div><div class="stat-value">{{ $data['prep']['printers']->count() }}</div></div>
                <div class="card"><div class="muted">Displays</div><div class="stat-value">{{ $data['prep']['displays']->count() }}</div></div>
                <div class="card"><div class="muted">Objectif max</div><div class="stat-value">{{ $data['prep']['max_target_minutes'] ? $data['prep']['max_target_minutes'].' min' : 'n/a' }}</div></div>
            </div>
            <div class="summary-stack" style="margin-top:16px;">
                @foreach ($data['prep']['printers'] as $printer)
                    <div class="summary-box">
                        <strong>{{ $printer->name }}</strong>
                        <div class="muted" style="margin-top:8px;">Imprimante · {{ $printer->target_area ?: 'Zone non renseignee' }}</div>
                        <div class="help" style="margin-top:8px;">Objectif preparation {{ $printer->prep_time_target_minutes ?: 0 }} min</div>
                    </div>
                @endforeach
                @foreach ($data['prep']['displays'] as $display)
                    <div class="summary-box">
                        <strong>{{ $display->name }}</strong>
                        <div class="muted" style="margin-top:8px;">Display · {{ $display->target_area ?: 'Zone non renseignee' }}</div>
                        <div class="help" style="margin-top:8px;">Objectif preparation {{ $display->prep_time_target_minutes ?: 0 }} min</div>
                    </div>
                @endforeach
                @if ($data['prep']['printers']->isEmpty() && $data['prep']['displays']->isEmpty())
                    <div class="muted">Aucun equipement de preparation configure pour mesurer les temps cibles.</div>
                @endif
            </div>
        </section>
    </div>
@endsection
