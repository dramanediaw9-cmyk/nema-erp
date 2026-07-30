@extends('layouts.print')

@section('title', 'Rapport journalier POS - Nema ERP')

@section('content')
    @php
        $productLabel = $businessVocabulary['product'] ?? 'Produit';
        $productsLabel = $businessVocabulary['products'] ?? 'Produits';
        $saleLabel = $businessVocabulary['sale'] ?? 'Vente';
        $salesLabel = $businessVocabulary['sales'] ?? 'Ventes';
        $stockLabel = $businessVocabulary['stock'] ?? 'Stock';
        $cashierLabel = $businessVocabulary['cashier'] ?? 'Caissier';
    @endphp

    <section class="doc-header">
        <div>
            <div class="doc-chip">Rapport journalier POS</div>
            <h1>{{ \Illuminate\Support\Carbon::parse($filters['date'])->format('d/m/Y') }}</h1>
            @include('partials.print-company-block', ['company' => $company])
        </div>
        <div class="right">
            <div><strong>Agence :</strong> {{ $branch?->name }}</div>
            <div class="meta">Tickets : {{ number_format((float) $report['sales_count'], 0, ',', ' ') }}</div>
            <div class="meta">Imprime le {{ now()->format('d/m/Y H:i') }}</div>
        </div>
    </section>

    <section class="grid grid-2">
        <div class="panel">
            <h2>Synthese {{ strtolower($salesLabel) }}</h2>
            <div>Brut {{ strtolower($productsLabel) }} : <strong>{{ number_format((float) $report['gross_sales'], 0, ',', ' ') }} XOF</strong></div>
            <div>Remises : <strong>{{ number_format((float) $report['discounts_total'], 0, ',', ' ') }} XOF</strong></div>
            <div>Total net : <strong>{{ number_format((float) $report['sales_after_discount'], 0, ',', ' ') }} XOF</strong></div>
            <div>Retours : <strong>{{ number_format((float) $report['returns_total'], 0, ',', ' ') }} XOF</strong></div>
            <div>Net apres retours : <strong>{{ number_format((float) $report['net_sales'], 0, ',', ' ') }} XOF</strong></div>
            <div>Cout estime : <strong>{{ number_format((float) $report['estimated_cost'], 0, ',', ' ') }} XOF</strong></div>
            <div>Marge estimee : <strong>{{ number_format((float) $report['estimated_margin'], 0, ',', ' ') }} XOF</strong></div>
            <div>Taux marge : <strong>{{ number_format((float) $report['estimated_margin_rate'], 1, ',', ' ') }} %</strong></div>
        </div>
        <div class="panel">
            <h2>Caisse</h2>
            <div>Flux net caisse : <strong>{{ number_format((float) $report['net_cash'], 0, ',', ' ') }} XOF</strong></div>
            <div>Ticket moyen : <strong>{{ number_format((float) $report['average_ticket'], 0, ',', ' ') }} XOF</strong></div>
            <div>Sessions : <strong>{{ $report['sessions']->count() }}</strong></div>
            <div>Sessions ouvertes : <strong>{{ $report['sessions']->where('status', 'open')->count() }}</strong></div>
            <div>Sessions cloturees : <strong>{{ $report['sessions']->where('status', 'closed')->count() }}</strong></div>
            <div>Ruptures : <strong>{{ number_format((float) $report['stock_alerts']['out_of_stock_count'], 0, ',', ' ') }}</strong></div>
            <div>{{ $stockLabel }} critique : <strong>{{ number_format((float) $report['stock_alerts']['low_stock_count'], 0, ',', ' ') }}</strong></div>
        </div>
    </section>

    <table>
        <thead>
        <tr>
            <th>Mode de paiement</th>
            <th class="right">Encaisse</th>
            <th class="right">Rembourse</th>
            <th class="right">Net</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($report['method_breakdown'] as $row)
            <tr>
                <td>{{ $row['label'] }}</td>
                <td class="right">{{ number_format((float) $row['incoming'], 0, ',', ' ') }} XOF</td>
                <td class="right">{{ number_format((float) $row['outgoing'], 0, ',', ' ') }} XOF</td>
                <td class="right">{{ number_format((float) $row['net'], 0, ',', ' ') }} XOF</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table>
        <thead>
        <tr>
            <th>{{ $cashierLabel }}</th>
            <th class="right">Tickets</th>
            <th class="right">{{ $salesLabel }}</th>
            <th class="right">Cout estime</th>
            <th class="right">Marge estimee</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($report['cashier_breakdown'] as $row)
            <tr>
                <td>{{ $row->cashier_name }}</td>
                <td class="right">{{ number_format((float) $row->sales_count, 0, ',', ' ') }}</td>
                <td class="right">{{ number_format((float) $row->total_sales, 0, ',', ' ') }} XOF</td>
                <td class="right">{{ number_format((float) $row->estimated_cost, 0, ',', ' ') }} XOF</td>
                <td class="right">{{ number_format((float) $row->estimated_margin, 0, ',', ' ') }} XOF</td>
            </tr>
        @empty
            <tr><td colspan="5" class="meta">Aucune operation par {{ strtolower($cashierLabel) }} pour cette date.</td></tr>
        @endforelse
        </tbody>
    </table>

    <table>
        <thead>
        <tr>
            <th>Session</th>
            <th>Entrepot</th>
            <th>Compte</th>
            <th>Statut</th>
            <th class="right">Ouverture</th>
            <th class="right">Attendu</th>
            <th class="right">Compte</th>
            <th class="right">Ecart</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($report['sessions'] as $session)
            <tr>
                <td>{{ $session->session_number }}</td>
                <td>{{ $session->warehouse?->name }}</td>
                <td>{{ $session->cashAccount?->name }}</td>
                <td>{{ $session->status === 'closed' ? 'Cloturee' : 'Ouverte' }}</td>
                <td class="right">{{ number_format((float) $session->opening_amount, 0, ',', ' ') }}</td>
                <td class="right">{{ number_format((float) $session->expected_amount, 0, ',', ' ') }}</td>
                <td class="right">{{ $session->closing_amount !== null ? number_format((float) $session->closing_amount, 0, ',', ' ') : '-' }}</td>
                <td class="right">{{ $session->variance_amount !== null ? number_format((float) $session->variance_amount, 0, ',', ' ') : '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="meta">Aucune session pour cette date.</td></tr>
        @endforelse
        </tbody>
    </table>

    <table>
        <thead>
        <tr>
            <th>{{ $productLabel }}</th>
            <th>SKU / code-barres</th>
            <th class="right">Quantite</th>
            <th class="right">Montant</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($report['top_products'] as $product)
            <tr>
                <td><strong>{{ $product->name }}</strong></td>
                <td>{{ $product->scan_code ?: $product->sku }}</td>
                <td class="right">{{ number_format((float) $product->qty, 3, ',', ' ') }}</td>
                <td class="right">{{ number_format((float) $product->amount, 0, ',', ' ') }} XOF</td>
            </tr>
        @empty
            <tr><td colspan="4" class="meta">Aucune operation POS sur cette date.</td></tr>
        @endforelse
        </tbody>
    </table>

    <table>
        <thead>
        <tr>
            <th>Ruptures / {{ strtolower($stockLabel) }} critique</th>
            <th>Code</th>
            <th>Categorie</th>
            <th class="right">{{ $stockLabel }}</th>
            <th class="right">Minimum</th>
            <th>Etat</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($report['stock_alerts']['items'] as $product)
            <tr>
                <td><strong>{{ $product->name }}</strong></td>
                <td>{{ $product->barcode ?: $product->sku }}</td>
                <td>{{ $product->category_name ?: 'Sans categorie' }}</td>
                <td class="right">{{ number_format((float) $product->current_stock, 3, ',', ' ') }} {{ $product->unit }}</td>
                <td class="right">{{ number_format((float) $product->min_stock, 3, ',', ' ') }} {{ $product->unit }}</td>
                <td>{{ (float) $product->current_stock <= 0.0001 ? 'Rupture' : 'Critique' }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="meta">Aucune rupture ni alerte minimum.</td></tr>
        @endforelse
        </tbody>
    </table>

    <div class="signatures">
        <div class="signature-box">Responsable caisse</div>
        <div class="signature-box">Controle / direction</div>
    </div>
@endsection
