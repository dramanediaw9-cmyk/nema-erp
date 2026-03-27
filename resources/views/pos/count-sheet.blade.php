@extends('layouts.print')

@section('title', 'Feuille de comptage caisse - Nema ERP')

@section('content')
    @php($openingCashBreakdown = is_array($session->opening_cash_breakdown) ? $session->opening_cash_breakdown : [])
    @php($openingHasBreakdown = collect(array_keys($cashDenominations))->sum(fn ($denomination) => (int) ($openingCashBreakdown[$denomination] ?? 0)) > 0)
    @php($closingCashBreakdown = is_array($session->closing_cash_breakdown) ? $session->closing_cash_breakdown : [])
    @php($closingHasBreakdown = collect(array_keys($cashDenominations))->sum(fn ($denomination) => (int) ($closingCashBreakdown[$denomination] ?? 0)) > 0)

    <header class="doc-header">
        <div>
            <div class="doc-chip">Comptage caisse</div>
            <h1>{{ $session->session_number }}</h1>
            <div class="meta">{{ $session->branch?->name }} · {{ $session->warehouse?->name }}</div>
            <div class="meta">Compte : {{ $session->cashAccount?->name }}</div>
        </div>
        <div style="text-align:right;">
            <strong>Statut</strong>
            <div>{{ $session->status === 'open' ? 'Session ouverte' : 'Session cloturee' }}</div>
            <div class="meta">Ouverte le {{ $session->opened_at?->format('d/m/Y H:i') }}</div>
            @if ($session->closed_at)
                <div class="meta">Cloturee le {{ $session->closed_at?->format('d/m/Y H:i') }}</div>
            @endif
        </div>
    </header>

    <div class="grid grid-2">
        <section class="panel">
            <h2>Contexte session</h2>
            <div>Caissier ouverture : <strong>{{ $session->opener?->name }}</strong></div>
            <div>Montant initial : <strong>{{ number_format((float) $session->opening_amount, 0, ',', ' ') }} XOF</strong></div>
            <div>Tickets : <strong>{{ number_format($summary['sales_count'], 0, ',', ' ') }}</strong></div>
            <div>Retours : <strong>{{ number_format($summary['return_count'], 0, ',', ' ') }}</strong></div>
            <div>Flux net caisse : <strong>{{ number_format($summary['net_cash'], 0, ',', ' ') }} XOF</strong></div>
        </section>
        <section class="panel">
            <h2>Totaux a controler</h2>
            <div>Brut articles : <strong>{{ number_format($summary['gross_sales_total'], 0, ',', ' ') }} XOF</strong></div>
            <div>Remises : <strong>{{ number_format($summary['discount_total'], 0, ',', ' ') }} XOF</strong></div>
            <div>Ventes nettes : <strong>{{ number_format($summary['sales_total'], 0, ',', ' ') }} XOF</strong></div>
            <div>Retours : <strong>{{ number_format($summary['return_total'], 0, ',', ' ') }} XOF</strong></div>
            <div>Encaisse attendu : <strong>{{ number_format($summary['expected_amount'], 0, ',', ' ') }} XOF</strong></div>
        </section>
    </div>

    <div class="grid grid-2" style="margin-top:22px;">
        <section class="panel">
            <h2>Coupures ouverture</h2>
            @if (! $openingHasBreakdown)
                <div class="meta" style="margin-bottom:10px;">Montant initial saisi sans detail de coupures.</div>
            @endif
            @include('pos.partials.cash-breakdown-table', [
                'cashDenominations' => $cashDenominations,
                'breakdown' => $openingCashBreakdown,
                'placeholder' => '................................',
            ])
        </section>
        <section class="panel">
            <h2>{{ $session->status === 'closed' ? 'Coupures cloture' : 'Zone de comptage especes' }}</h2>
            @if ($session->status === 'closed' && ! $closingHasBreakdown)
                <div class="meta" style="margin-bottom:10px;">Cloture saisie sans detail de coupures.</div>
            @elseif ($session->status === 'open')
                <div class="meta" style="margin-bottom:10px;">A renseigner au moment du comptage physique.</div>
            @endif
            @include('pos.partials.cash-breakdown-table', [
                'cashDenominations' => $cashDenominations,
                'breakdown' => $session->status === 'closed' ? $closingCashBreakdown : [],
                'placeholder' => '................................',
            ])
        </section>
    </div>

    <table style="margin-top:22px;">
        <thead>
        <tr>
            <th>Mode de paiement</th>
            <th class="right">Attendu</th>
            <th class="right">Compte physique</th>
            <th class="right">Ecart</th>
            <th>Justification</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($methodOptions as $method => $label)
            <tr>
                <td>{{ $label }}</td>
                <td class="right">{{ number_format($summary['expected_breakdown'][$method] ?? 0, 0, ',', ' ') }} XOF</td>
                <td class="right">{{ $session->status === 'closed' ? number_format($summary['counted_breakdown'][$method] ?? 0, 0, ',', ' ') . ' XOF' : '................................' }}</td>
                <td class="right">{{ $session->status === 'closed' ? number_format($summary['variance_breakdown'][$method] ?? 0, 0, ',', ' ') . ' XOF' : '................................' }}</td>
                <td>{{ $session->status === 'closed' ? ($summary['variance_notes'][$method] ?: 'Aucune') : '........................................................................' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>Total attendu</td>
            <td class="right">{{ number_format($summary['expected_amount'], 0, ',', ' ') }} XOF</td>
        </tr>
        @if ($session->status === 'closed')
            <tr>
                <td>Total compte</td>
                <td class="right">{{ number_format((float) $session->closing_amount, 0, ',', ' ') }} XOF</td>
            </tr>
            <tr class="grand-total">
                <td>Ecart final</td>
                <td class="right">{{ number_format((float) $session->variance_amount, 0, ',', ' ') }} XOF</td>
            </tr>
        @endif
    </table>

    <div class="signatures">
        <div class="signature-box">Caissier</div>
        <div class="signature-box">Controleur / Responsable</div>
    </div>

    <div class="footer">
        Feuille de comptage imprimee depuis Nema ERP. Utilisable avant cloture pour comptage physique puis archivage.
    </div>
@endsection