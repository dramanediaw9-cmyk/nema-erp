@extends('layouts.print')

@section('title', 'Cloture session POS - Nema ERP')

@section('content')
    @php
        $customerLabel = $businessVocabulary['client'] ?? 'Client';
        $productsLabel = $businessVocabulary['products'] ?? 'Produits';
        $salesLabel = $businessVocabulary['sales'] ?? 'Ventes';
        $cashierLabel = $businessVocabulary['cashier'] ?? 'Caissier';
        $openingCashBreakdown = is_array($session->opening_cash_breakdown) ? $session->opening_cash_breakdown : [];
        $closingCashBreakdown = is_array($session->closing_cash_breakdown) ? $session->closing_cash_breakdown : [];
    @endphp

    <section class="doc-header">
        <div>
            <div class="doc-chip">Fiche session caisse</div>
            <h1>{{ $session->session_number }}</h1>
            @include('partials.print-company-block', ['company' => $company])
        </div>
        <div class="right">
            <div><strong>Agence :</strong> {{ $branch?->name ?? $session->branch?->name }}</div>
            <div class="meta">Entrepot : {{ $session->warehouse?->name ?? 'n/a' }}</div>
            <div class="meta">Compte : {{ $session->cashAccount?->name ?? 'n/a' }}</div>
            <div class="meta">Imprime le {{ now()->format('d/m/Y H:i') }}</div>
        </div>
    </section>

    <section class="grid grid-2">
        <div class="panel">
            <h2>Ouverture et cloture</h2>
            <div>Statut : <strong>{{ $session->status === 'open' ? 'Ouverte' : 'Cloturee' }}</strong></div>
            <div>Ouverte par : <strong>{{ $session->opener?->name ?? 'n/a' }}</strong></div>
            <div>Ouverture : <strong>{{ $session->opened_at?->format('d/m/Y H:i') ?? 'n/a' }}</strong></div>
            <div>Cloturee par : <strong>{{ $session->closer?->name ?? '-' }}</strong></div>
            <div>Cloture : <strong>{{ $session->closed_at?->format('d/m/Y H:i') ?? '-' }}</strong></div>
        </div>
        <div class="panel">
            <h2>Controle caisse</h2>
            <div>Fond de caisse : <strong>{{ number_format((float) $session->opening_amount, 0, ',', ' ') }} XOF</strong></div>
            <div>Attendu : <strong>{{ number_format((float) $summary['expected_amount'], 0, ',', ' ') }} XOF</strong></div>
            <div>Compte physique : <strong>{{ $session->closing_amount !== null ? number_format((float) $session->closing_amount, 0, ',', ' ').' XOF' : '-' }}</strong></div>
            <div>Ecart : <strong>{{ $session->variance_amount !== null ? number_format((float) $session->variance_amount, 0, ',', ' ').' XOF' : '-' }}</strong></div>
            <div>Flux net caisse : <strong>{{ number_format((float) $summary['net_cash'], 0, ',', ' ') }} XOF</strong></div>
        </div>
    </section>

    <section class="grid grid-2" style="margin-top:18px;">
        <div class="panel">
            <h2>{{ $salesLabel }}</h2>
            <div>Tickets : <strong>{{ number_format((float) $summary['sales_count'], 0, ',', ' ') }}</strong></div>
            <div>{{ $productsLabel }} vendus : <strong>{{ number_format((float) $summary['items_count'], 3, ',', ' ') }}</strong></div>
            <div>Brut {{ strtolower($productsLabel) }} : <strong>{{ number_format((float) $summary['gross_sales_total'], 0, ',', ' ') }} XOF</strong></div>
            <div>Remises : <strong>{{ number_format((float) $summary['discount_total'], 0, ',', ' ') }} XOF</strong></div>
            <div>Total net : <strong>{{ number_format((float) $summary['sales_total'], 0, ',', ' ') }} XOF</strong></div>
        </div>
        <div class="panel">
            <h2>Retours</h2>
            <div>Retours : <strong>{{ number_format((float) $summary['return_count'], 0, ',', ' ') }}</strong></div>
            <div>{{ $productsLabel }} retournes : <strong>{{ number_format((float) $summary['returned_items_count'], 3, ',', ' ') }}</strong></div>
            <div>Montant retours : <strong>{{ number_format((float) $summary['return_total'], 0, ',', ' ') }} XOF</strong></div>
            <div>{{ $salesLabel }} apres retours : <strong>{{ number_format((float) $summary['net_sales_total'], 0, ',', ' ') }} XOF</strong></div>
            <div>Remboursements : <strong>{{ number_format((float) $summary['refund_total'], 0, ',', ' ') }} XOF</strong></div>
        </div>
    </section>

    <table>
        <thead>
        <tr>
            <th>Mode de paiement</th>
            <th class="right">Attendu</th>
            <th class="right">Compte</th>
            <th class="right">Ecart</th>
            <th>Justification</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($methodOptions as $method => $label)
            <tr>
                <td>{{ $label }}</td>
                <td class="right">{{ number_format((float) ($summary['expected_breakdown'][$method] ?? 0), 0, ',', ' ') }} XOF</td>
                <td class="right">{{ $session->status === 'closed' ? number_format((float) ($summary['counted_breakdown'][$method] ?? 0), 0, ',', ' ').' XOF' : '-' }}</td>
                <td class="right">{{ $session->status === 'closed' ? number_format((float) ($summary['variance_breakdown'][$method] ?? 0), 0, ',', ' ').' XOF' : '-' }}</td>
                <td>{{ $session->status === 'closed' ? (($summary['variance_notes'][$method] ?? '') ?: 'Aucune') : '-' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <section class="grid grid-2" style="margin-top:18px;">
        <div class="panel">
            <h2>Coupures ouverture</h2>
            @include('pos.partials.cash-breakdown-table', [
                'cashDenominations' => $cashDenominations,
                'breakdown' => $openingCashBreakdown,
                'placeholder' => '0',
            ])
        </div>
        <div class="panel">
            <h2>Coupures cloture</h2>
            @include('pos.partials.cash-breakdown-table', [
                'cashDenominations' => $cashDenominations,
                'breakdown' => $closingCashBreakdown,
                'placeholder' => $session->status === 'closed' ? '0' : '-',
            ])
        </div>
    </section>

    <table>
        <thead>
        <tr>
            <th>Ticket</th>
            <th>{{ $customerLabel }}</th>
            <th>{{ $cashierLabel }}</th>
            <th class="right">Articles</th>
            <th class="right">Total</th>
            <th class="right">Paye</th>
            <th class="right">Reste</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($session->salesInvoices->sortByDesc('id') as $invoice)
            <tr>
                <td>{{ $invoice->invoice_number }}<br><span class="meta">{{ $invoice->invoice_date?->format('d/m/Y') }}</span></td>
                <td>{{ $invoice->customer?->name ?? $customerLabel.' comptoir' }}</td>
                <td>{{ $invoice->creator?->name ?? $session->opener?->name }}</td>
                <td class="right">{{ number_format((float) $invoice->items->sum('qty'), 3, ',', ' ') }}</td>
                <td class="right">{{ number_format((float) $invoice->total, 0, ',', ' ') }} XOF</td>
                <td class="right">{{ number_format((float) $invoice->amount_paid, 0, ',', ' ') }} XOF</td>
                <td class="right">{{ number_format((float) $invoice->balance_due, 0, ',', ' ') }} XOF</td>
            </tr>
        @empty
            <tr><td colspan="7" class="meta">Aucun ticket enregistre sur cette session.</td></tr>
        @endforelse
        </tbody>
    </table>

    <table>
        <thead>
        <tr>
            <th>Paiement</th>
            <th>Date</th>
            <th>Mode</th>
            <th>Compte</th>
            <th>Reference</th>
            <th class="right">Entree</th>
            <th class="right">Sortie</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($session->payments->sortByDesc('id') as $payment)
            <tr>
                <td>{{ $payment->payment_number }}</td>
                <td>{{ $payment->payment_date?->format('d/m/Y') }}</td>
                <td>{{ $methodOptions[$payment->method] ?? $payment->method }}</td>
                <td>{{ $payment->cashAccount?->name ?? '-' }}</td>
                <td>{{ $payment->reference ?: '-' }}</td>
                <td class="right">{{ $payment->direction === 'in' ? number_format((float) $payment->amount, 0, ',', ' ').' XOF' : '-' }}</td>
                <td class="right">{{ $payment->direction === 'out' ? number_format((float) $payment->amount, 0, ',', ' ').' XOF' : '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="meta">Aucun paiement enregistre sur cette session.</td></tr>
        @endforelse
        </tbody>
    </table>

    <table>
        <thead>
        <tr>
            <th>Retour</th>
            <th>Ticket origine</th>
            <th>Echange</th>
            <th class="right">Total</th>
            <th>Note</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($session->returns->sortByDesc('id') as $return)
            <tr>
                <td>{{ $return->return_number ?? ('RET-'.$return->id) }}<br><span class="meta">{{ $return->return_date?->format('d/m/Y') }}</span></td>
                <td>{{ $return->invoice?->invoice_number ?? '-' }}</td>
                <td>{{ $return->exchangeInvoice?->invoice_number ?? '-' }}</td>
                <td class="right">{{ number_format((float) $return->total, 0, ',', ' ') }} XOF</td>
                <td>{{ $return->notes ?: '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="meta">Aucun retour sur cette session.</td></tr>
        @endforelse
        </tbody>
    </table>

    @if ($session->closing_notes)
        <section class="panel" style="margin-top:18px;">
            <h2>Note de cloture</h2>
            <div>{{ $session->closing_notes }}</div>
        </section>
    @endif

    <div class="signatures">
        <div class="signature-box">{{ $cashierLabel }}</div>
        <div class="signature-box">Controle / direction</div>
    </div>

    <div class="footer">
        Document de controle imprime depuis Nema ERP. A conserver avec le comptage physique et les justificatifs d ecart.
    </div>
@endsection
