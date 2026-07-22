@extends('layouts.print')

@section('title', 'Pilotage manager - Nema ERP')

@section('content')
    @php
        $productLabel = $businessVocabulary['product'] ?? 'Produit';
        $productsLabel = $businessVocabulary['products'] ?? 'Produits';
        $saleLabel = $businessVocabulary['sale'] ?? 'Vente';
        $salesLabel = $businessVocabulary['sales'] ?? 'Ventes';
        $stockLabel = $businessVocabulary['stock'] ?? 'Stock';
        $cashierLabel = $businessVocabulary['cashier'] ?? 'Caissier';
        $money = fn ($value) => number_format((float) $value, 0, ',', ' ').' XOF';
        $qty = fn ($value) => number_format((float) $value, 2, ',', ' ');
        $stockAlerts = $report['stock_alerts'] ?? ['out_of_stock_count' => 0, 'low_stock_count' => 0, 'items' => collect()];
        $settlementWatch = $report['settlement_watch'] ?? ['methods' => []];
    @endphp

    <section class="doc-header">
        <div>
            <div class="doc-chip">Pilotage manager</div>
            <h1>Synthese du {{ \Illuminate\Support\Carbon::parse($date)->format('d/m/Y') }}</h1>
            @include('partials.print-company-block', ['company' => $company])
        </div>
        <div class="right">
            <div><strong>Agence :</strong> {{ $branch?->name ?? 'Agence active' }}</div>
            <div class="meta">Imprime le {{ now()->format('d/m/Y H:i') }}</div>
            <div class="meta">Tickets : {{ number_format((int) ($report['sales_count'] ?? 0), 0, ',', ' ') }}</div>
        </div>
    </section>

    <section class="grid grid-2">
        <div class="panel">
            <h2>Performance du jour</h2>
            <div>{{ $salesLabel }} nettes : <strong>{{ $money($report['net_sales'] ?? 0) }}</strong></div>
            <div>Encaissements nets : <strong>{{ $money($report['net_cash'] ?? 0) }}</strong></div>
            <div>Marge estimee : <strong>{{ $money($report['estimated_margin'] ?? 0) }}</strong></div>
            <div>Taux de marge : <strong>{{ number_format((float) ($report['estimated_margin_rate'] ?? 0), 1, ',', ' ') }} %</strong></div>
            <div>Ticket moyen : <strong>{{ $money($report['average_ticket'] ?? 0) }}</strong></div>
        </div>
        <div class="panel">
            <h2>Controle manager</h2>
            <div>Alertes actives : <strong>{{ number_format($activeAlerts->count(), 0, ',', ' ') }}</strong></div>
            <div>Alertes critiques : <strong>{{ number_format($activeAlerts->where('level', 'danger')->count(), 0, ',', ' ') }}</strong></div>
            <div>Sessions ouvertes : <strong>{{ number_format($openSessions->count(), 0, ',', ' ') }}</strong></div>
            <div>Ruptures : <strong>{{ number_format((int) ($stockAlerts['out_of_stock_count'] ?? 0), 0, ',', ' ') }}</strong></div>
            <div>{{ $stockLabel }} critique : <strong>{{ number_format((int) ($stockAlerts['low_stock_count'] ?? 0), 0, ',', ' ') }}</strong></div>
        </div>
    </section>

    <table>
        <thead>
        <tr>
            <th>Plan d'action</th>
            <th>Niveau</th>
            <th class="right">Valeur</th>
            <th>Controle attendu</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($actionPlan as $item)
            <tr>
                <td><strong>{{ $item['title'] }}</strong></td>
                <td>{{ $item['level'] === 'danger' ? 'Critique' : ($item['level'] === 'warning' ? 'A suivre' : 'OK') }}</td>
                <td class="right">{{ number_format((int) $item['value'], 0, ',', ' ') }}</td>
                <td>{{ $item['message'] }}</td>
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
                <td class="right">{{ $money($row->total_sales) }}</td>
                <td class="right">{{ $money($row->estimated_cost) }}</td>
                <td class="right">{{ $money($row->estimated_margin) }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="meta">Aucune {{ strtolower($saleLabel) }} par {{ strtolower($cashierLabel) }}.</td></tr>
        @endforelse
        </tbody>
    </table>

    <table>
        <thead>
        <tr>
            <th>Top {{ strtolower($productsLabel) }}</th>
            <th>Code</th>
            <th class="right">Quantite</th>
            <th class="right">Montant</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($report['top_products'] as $product)
            <tr>
                <td><strong>{{ $product->name }}</strong></td>
                <td>{{ $product->scan_code ?: $product->sku }}</td>
                <td class="right">{{ $qty($product->qty) }}</td>
                <td class="right">{{ $money($product->amount) }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="meta">Aucun {{ strtolower($productLabel) }} vendu sur cette date.</td></tr>
        @endforelse
        </tbody>
    </table>

    <table>
        <thead>
        <tr>
            <th>Ruptures / {{ strtolower($stockLabel) }} critique</th>
            <th>Code</th>
            <th class="right">{{ $stockLabel }}</th>
            <th class="right">Minimum</th>
            <th>Etat</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($stockAlerts['items'] as $product)
            <tr>
                <td><strong>{{ $product->name }}</strong><div class="meta">{{ $product->category_name ?: 'Sans categorie' }}</div></td>
                <td>{{ $product->barcode ?: $product->sku }}</td>
                <td class="right">{{ $qty($product->current_stock) }} {{ $product->unit }}</td>
                <td class="right">{{ $qty($product->min_stock) }} {{ $product->unit }}</td>
                <td>{{ (float) $product->current_stock <= 0.0001 ? 'Rupture' : 'Critique' }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="meta">Aucune rupture ni alerte {{ strtolower($stockLabel) }}.</td></tr>
        @endforelse
        </tbody>
    </table>

    <table>
        <thead>
        <tr>
            <th>Mode paiement</th>
            <th class="right">Attendu</th>
            <th class="right">Ecart</th>
            <th class="right">Non rapproche</th>
            <th>Signal</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($settlementWatch['methods'] as $row)
            <tr>
                <td>{{ $row['label'] }}</td>
                <td class="right">{{ $money($row['expected_total'] ?? 0) }}</td>
                <td class="right">{{ $money($row['variance'] ?? 0) }}</td>
                <td class="right">{{ $money(abs((float) ($row['unreconciled_amount'] ?? 0))) }}</td>
                <td>{{ ($row['status'] ?? 'ok') === 'ok' ? 'OK' : 'A verifier' }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="meta">Aucun flux a controler.</td></tr>
        @endforelse
        </tbody>
    </table>

    <table>
        <thead>
        <tr>
            <th>Alertes actives</th>
            <th>Niveau</th>
            <th>Message</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($activeAlerts as $alert)
            <tr>
                <td><strong>{{ $alert->title }}</strong></td>
                <td>{{ $alert->level === 'danger' ? 'Critique' : 'A suivre' }}</td>
                <td>{{ $alert->message }}</td>
            </tr>
        @empty
            <tr><td colspan="3" class="meta">Aucune alerte active.</td></tr>
        @endforelse
        </tbody>
    </table>

    <table>
        <thead>
        <tr>
            <th>Actions sensibles recentes</th>
            <th>Utilisateur</th>
            <th class="right">Date</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($sensitiveActions as $log)
            <tr>
                <td><strong>{{ $log->action }}</strong><div class="meta">{{ $log->description }}</div></td>
                <td>{{ $log->user?->name ?? 'Systeme' }}</td>
                <td class="right">{{ $log->created_at?->format('d/m/Y H:i') }}</td>
            </tr>
        @empty
            <tr><td colspan="3" class="meta">Aucune action sensible recente.</td></tr>
        @endforelse
        </tbody>
    </table>

    <div class="signatures">
        <div class="signature-box">Manager / responsable</div>
        <div class="signature-box">Controle / direction</div>
    </div>
@endsection
