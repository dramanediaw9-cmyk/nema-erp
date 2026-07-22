@extends('layouts.app')

@section('title', 'Rapports - Nema ERP')
@section('page-title', 'Rapports dirigeant')

@section('content')
    @php
        $customerLabel = $businessVocabulary['client'] ?? 'Client';
        $customersLabel = $businessVocabulary['clients'] ?? 'Clients';
        $productLabel = $businessVocabulary['product'] ?? 'Produit';
        $productsLabel = $businessVocabulary['products'] ?? 'Produits';
        $saleLabel = $businessVocabulary['sale'] ?? 'Vente';
        $salesLabel = $businessVocabulary['sales'] ?? 'Ventes';
        $stockLabel = $businessVocabulary['stock'] ?? 'Stock';
        $supplierLabel = $businessVocabulary['supplier'] ?? 'Fournisseur';
        $suppliersLabel = $businessVocabulary['suppliers'] ?? 'Fournisseurs';
        $purchaseLabel = $businessVocabulary['purchase'] ?? 'Achat';
        $purchasesLabel = $businessVocabulary['purchases'] ?? 'Achats';
        $today = now()->format('Y-m-d');
        $weekStart = now()->subDays(6)->format('Y-m-d');
        $thirtyStart = now()->subDays(29)->format('Y-m-d');
        $monthStart = now()->startOfMonth()->format('Y-m-d');
        $cashNet = (float) $treasury['in'] - (float) $treasury['out'];
        $money = fn ($value) => number_format((float) $value, 0, ',', ' ') . ' XOF';
        $deltaClass = fn (array $metric) => $metric['direction'] === 'up' ? 'badge-success' : ($metric['direction'] === 'down' ? 'badge-warning' : 'badge-muted');
        $signalBorder = fn (string $level) => $level === 'danger' ? '#b42318' : ($level === 'warning' ? '#ca6702' : '#176b4d');
        $sectorProfile = $sectorProfile ?? [];
        $reportBlueprint = $reportBlueprint ?? [];
    @endphp

    <div class="page-head">
        <div>
            <h2 style="margin:0;">Rapports de pilotage</h2>
            <div class="muted">Periode du {{ \Illuminate\Support\Carbon::parse($filters['date_from'])->format('d/m/Y') }} au {{ \Illuminate\Support\Carbon::parse($filters['date_to'])->format('d/m/Y') }} · perimetre {{ $scopeLabel }}</div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="{{ route('sales.export', ['date_from' => $filters['date_from'], 'date_to' => $filters['date_to'], 'branch_id' => $filters['branch_id']]) }}" class="button button-secondary">CSV {{ strtolower($salesLabel) }}</a>
            <a href="{{ route('purchases.export', ['date_from' => $filters['date_from'], 'date_to' => $filters['date_to'], 'branch_id' => $filters['branch_id']]) }}" class="button button-secondary">CSV {{ strtolower($purchasesLabel) }}</a>
            <a href="{{ route('payments.export', ['date_from' => $filters['date_from'], 'date_to' => $filters['date_to'], 'branch_id' => $filters['branch_id']]) }}" class="button button-secondary">CSV paiements</a>
            <a href="{{ route('expenses.export', ['date_from' => $filters['date_from'], 'date_to' => $filters['date_to'], 'branch_id' => $filters['branch_id']]) }}" class="button button-secondary">CSV depenses</a>
        </div>
    </div>

    <section class="card" style="margin-bottom:20px;">
        <div class="filter-pills">
            <a href="{{ route('reports.index', ['date_from' => $today, 'date_to' => $today, 'branch_id' => $filters['branch_id']]) }}" class="button button-secondary">Aujourd hui</a>
            <a href="{{ route('reports.index', ['date_from' => $weekStart, 'date_to' => $today, 'branch_id' => $filters['branch_id']]) }}" class="button button-secondary">7 derniers jours</a>
            <a href="{{ route('reports.index', ['date_from' => $thirtyStart, 'date_to' => $today, 'branch_id' => $filters['branch_id']]) }}" class="button button-secondary">30 derniers jours</a>
            <a href="{{ route('reports.index', ['date_from' => $monthStart, 'date_to' => $today, 'branch_id' => $filters['branch_id']]) }}" class="button button-secondary">Mois en cours</a>
            @if ($canAccessAllBranches)
                <a href="{{ route('reports.index', ['date_from' => $filters['date_from'], 'date_to' => $filters['date_to'], 'branch_id' => null]) }}" class="button button-secondary">Toutes agences</a>
            @endif
        </div>

        <form method="GET" action="{{ route('reports.index') }}" class="form-grid" style="align-items:end; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));">
            <div>
                <label for="date_from">Date debut</label>
                <input type="date" id="date_from" name="date_from" value="{{ $filters['date_from'] }}">
            </div>
            <div>
                <label for="date_to">Date fin</label>
                <input type="date" id="date_to" name="date_to" value="{{ $filters['date_to'] }}">
            </div>
            <div>
                <label for="branch_id">Perimetre agence</label>
                <select id="branch_id" name="branch_id">
                    @if ($canAccessAllBranches)
                        <option value="">Toutes les agences</option>
                    @endif
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((int) ($filters['branch_id'] ?? 0) === $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
                @unless ($canAccessAllBranches)
                    <div class="help" style="margin-top:6px;">Perimetre agence verrouille par tes permissions.</div>
                @endunless
            </div>
            <div class="actions" style="margin-top:0; justify-content:flex-start; align-self:end;">
                <button type="submit" class="button button-primary">Actualiser le rapport</button>
                <a href="{{ route('reports.index') }}" class="button button-secondary">Perimetre par defaut</a>
            </div>
        </form>
    </section>

    <section class="card" style="margin-bottom:20px;">
        <div class="page-head" style="margin-bottom:14px;">
            <div style="display:flex; gap:14px; align-items:flex-start;">
                <span class="dashboard-icon-badge dashboard-icon-badge--success">
                    @include('dashboard.partials.icon', ['name' => $sectorProfile['icon'] ?? 'building', 'size' => 22])
                </span>
                <div>
                    <h2 style="margin:0;">{{ $reportBlueprint['title'] ?? 'Pilotage metier' }}</h2>
                    <div class="muted">{{ $reportBlueprint['subtitle'] ?? 'Les indicateurs suivent le profil d activite de cette entreprise.' }}</div>
                </div>
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                @foreach (($reportBlueprint['quick_links'] ?? []) as $link)
                    <a href="{{ $link['url'] }}" class="button button-secondary">{{ $link['label'] }}</a>
                @endforeach
            </div>
        </div>
        <div class="grid" style="grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:14px;">
            <div class="summary-box">
                <strong>Indicateurs a suivre</strong>
                <div class="filter-pills" style="margin-top:12px;">
                    @foreach (($reportBlueprint['kpis'] ?? []) as $kpi)
                        <span class="badge badge-success">{{ ucfirst($kpi) }}</span>
                    @endforeach
                </div>
            </div>
            <div class="summary-box">
                <strong>Alertes sensibles</strong>
                <div class="filter-pills" style="margin-top:12px;">
                    @foreach (($reportBlueprint['alerts'] ?? []) as $alert)
                        <span class="badge badge-warning">{{ ucfirst($alert) }}</span>
                    @endforeach
                </div>
            </div>
            <div class="summary-box">
                <strong>Documents utiles</strong>
                <div class="filter-pills" style="margin-top:12px;">
                    @foreach (($reportBlueprint['documents'] ?? []) as $document)
                        <span class="badge badge-muted">{{ ucfirst($document) }}</span>
                    @endforeach
                </div>
            </div>
        </div>
    </section>


    @if (! empty($executiveBrief['items']))
        <section class="card" style="margin-bottom:20px; background:linear-gradient(135deg, rgba(10,27,44,0.96) 0%, rgba(12,64,89,0.92) 52%, rgba(179,126,30,0.18) 100%); color:#eef8f8; border-color:rgba(12,64,89,0.18);">
            <div class="page-head" style="margin-bottom:14px;">
                <div>
                    <h2 style="margin:0; color:#fff;">{{ $executiveBrief['headline'] }}</h2>
                    <div style="margin-top:8px; color:rgba(238,248,248,0.78);">{{ $executiveBrief['summary'] }}</div>
                </div>
            </div>
            <div class="grid" style="grid-template-columns:repeat(auto-fit, minmax(250px, 1fr)); gap:16px;">
                @foreach ($executiveBrief['items'] as $item)
                    <a href="{{ $item['action_url'] }}" class="card" style="display:block; background:rgba(255,255,255,0.08); border-color:rgba(255,255,255,0.12); color:#eef8f8;">
                        <span class="badge {{ $item['tone'] === 'danger' ? 'badge-warning' : ($item['tone'] === 'warning' ? 'badge-muted' : 'badge-success') }}">{{ strtoupper($item['tone']) }}</span>
                        <strong style="display:block; margin-top:12px; color:#fff;">{{ $item['title'] }}</strong>
                        <div style="margin-top:10px; color:rgba(238,248,248,0.78);">{{ $item['message'] }}</div>
                        <div style="margin-top:12px; color:#fff4cf; font-weight:700;">{{ $item['action_label'] }}</div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
    <div class="grid stats-grid" style="margin-bottom:20px;">
        <div class="card"><div class="muted">CA {{ strtolower($salesLabel) }}</div><div class="stat-value">{{ number_format($sales['total'], 0, ',', ' ') }}</div><div class="muted">{{ $sales['count'] }} facture(s)</div></div>
        @if ($canViewMargin)
            <div class="card"><div class="muted">Marge estimee</div><div class="stat-value">{{ number_format($grossMargin['margin'], 0, ',', ' ') }}</div><div class="muted">{{ number_format($grossMargin['rate'], 1, ',', ' ') }} % du CA</div></div>
        @else
            <div class="card"><div class="muted">Indicateurs sensibles</div><div class="stat-value" style="font-size:24px;">Masques</div><div class="muted">Marge et rentabilite reservees aux profils autorises</div></div>
        @endif
        <div class="card"><div class="muted">Flux net</div><div class="stat-value">{{ number_format($cashNet, 0, ',', ' ') }}</div><div class="muted">Tresorerie sur la periode</div></div>
        <div class="card"><div class="muted">Creances {{ strtolower($customersLabel) }}</div><div class="stat-value">{{ number_format($receivables['total'], 0, ',', ' ') }}</div><div class="muted">{{ $receivables['count'] }} facture(s)</div></div>
        <div class="card"><div class="muted">{{ $purchasesLabel }} periode</div><div class="stat-value">{{ number_format($purchases['total'], 0, ',', ' ') }}</div><div class="muted">{{ $purchases['count'] }} facture(s)</div></div>
        <div class="card"><div class="muted">Depenses periode</div><div class="stat-value">{{ number_format($expenses['total'], 0, ',', ' ') }}</div><div class="muted">{{ $expenses['count'] }} depense(s)</div></div>
        <div class="card"><div class="muted">{{ $stockLabel }} valorise</div><div class="stat-value">{{ number_format($stock['valuation'], 0, ',', ' ') }}</div><div class="muted">{{ $stock['product_count'] }} {{ strtolower($productLabel) }}(s)</div></div>
        <div class="card"><div class="muted">Dettes {{ strtolower($suppliersLabel) }}</div><div class="stat-value">{{ number_format($payables['total'], 0, ',', ' ') }}</div><div class="muted">{{ $payables['count'] }} facture(s)</div></div>
    </div>

    <section class="card" style="margin-bottom:20px;">
        <div class="page-head" style="margin-bottom:14px;">
            <div>
                <h2 style="margin:0;">Comparaison periode precedente</h2>
                <div class="muted">Reference du {{ \Illuminate\Support\Carbon::parse($comparison['window']['previous_from'])->format('d/m/Y') }} au {{ \Illuminate\Support\Carbon::parse($comparison['window']['previous_to'])->format('d/m/Y') }}</div>
            </div>
        </div>
        <div class="grid stats-grid">
            <div class="card">
                <div class="muted">{{ $salesLabel }}</div>
                <div class="stat-value">{{ $money($comparison['sales']['current']) }}</div>
                <div style="margin-top:8px;"><span class="badge {{ $deltaClass($comparison['sales']) }}">{{ number_format($comparison['sales']['delta_percent'], 1, ',', ' ') }} %</span></div>
                <div class="muted" style="margin-top:8px;">Avant: {{ $money($comparison['sales']['previous']) }}</div>
            </div>
            @if ($canViewMargin)
                <div class="card">
                    <div class="muted">Marge estimee</div>
                    <div class="stat-value">{{ $money($comparison['margin']['current']) }}</div>
                    <div style="margin-top:8px;"><span class="badge {{ $deltaClass($comparison['margin']) }}">{{ number_format($comparison['margin']['delta_percent'], 1, ',', ' ') }} %</span></div>
                    <div class="muted" style="margin-top:8px;">Avant: {{ $money($comparison['margin']['previous']) }}</div>
                </div>
            @endif
            <div class="card">
                <div class="muted">Flux net</div>
                <div class="stat-value">{{ $money($comparison['cash_net']['current']) }}</div>
                <div style="margin-top:8px;"><span class="badge {{ $deltaClass($comparison['cash_net']) }}">{{ number_format($comparison['cash_net']['delta_percent'], 1, ',', ' ') }} %</span></div>
                <div class="muted" style="margin-top:8px;">Avant: {{ $money($comparison['cash_net']['previous']) }}</div>
            </div>
            <div class="card">
                <div class="muted">Depenses</div>
                <div class="stat-value">{{ $money($comparison['expenses']['current']) }}</div>
                <div style="margin-top:8px;"><span class="badge {{ $deltaClass($comparison['expenses']) }}">{{ number_format($comparison['expenses']['delta_percent'], 1, ',', ' ') }} %</span></div>
                <div class="muted" style="margin-top:8px;">Avant: {{ $money($comparison['expenses']['previous']) }}</div>
            </div>
        </div>
    </section>

    <section class="card" style="margin-bottom:20px;">
        <div class="page-head" style="margin-bottom:14px;">
            <div>
                <h2 style="margin:0;">Signaux de pilotage</h2>
                <div class="muted">Les points qui meritent une attention immediate sur le perimetre analyse.</div>
            </div>
        </div>
        <div class="grid" style="grid-template-columns:repeat(auto-fit, minmax(260px, 1fr));">
            @foreach ($signals as $signal)
                <section class="card" style="padding:16px; border-left:6px solid {{ $signalBorder($signal['level']) }};">
                    <strong>{{ $signal['title'] }}</strong>
                    <div class="muted" style="margin-top:8px;">{{ $signal['message'] }}</div>
                    <div style="margin-top:12px;">
                        <a href="{{ $signal['action_url'] }}" class="button button-secondary">Ouvrir</a>
                    </div>
                </section>
            @endforeach
        </div>
    </section>

    <div class="split">
        <section class="card">
            <h2 style="margin-top:0;">Top {{ strtolower($productsLabel) }}</h2>
            <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>{{ $productLabel }}</th>
                        <th>Quantite</th>
                        <th>CA</th>
                        @if ($canViewMargin)
                            <th>Marge estimee</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                @forelse ($topProducts as $product)
                    <tr>
                        <td>
                            <strong>{{ $product['name'] }}</strong>
                            <div class="muted">{{ $product['sku'] }} · {{ $product['category_name'] ?: 'Sans categorie' }}</div>
                        </td>
                        <td>{{ number_format($product['qty'], 0, ',', ' ') }}</td>
                        <td>{{ $money($product['amount']) }}</td>
                        @if ($canViewMargin)
                            <td>{{ $money($product['estimated_margin']) }}</td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $canViewMargin ? 4 : 3 }}" class="muted">Aucune {{ strtolower($saleLabel) }} sur cette periode.</td></tr>
                @endforelse
                </tbody>
            </table>
            </div>
        </section>

        @if ($canViewMargin)
            <section class="card">
                <h2 style="margin-top:0;">Marge par categorie</h2>
                <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Categorie</th>
                            <th>CA</th>
                            <th>Marge</th>
                            <th>Taux</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($marginByCategory as $category)
                        <tr>
                            <td>
                                <strong>{{ $category['category_name'] }}</strong>
                                <div class="muted">{{ number_format($category['qty'], 0, ',', ' ') }} unite(s)</div>
                            </td>
                            <td>{{ $money($category['amount']) }}</td>
                            <td>{{ $money($category['estimated_margin']) }}</td>
                            <td>{{ number_format($category['rate'], 1, ',', ' ') }} %</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="muted">Aucune categorie valorisee sur cette periode.</td></tr>
                    @endforelse
                    </tbody>
                </table>
                </div>
            </section>
        @endif
    </div>

    <div class="split" style="margin-top:20px;">
        <section class="card">
            <h2 style="margin-top:0;">Top {{ strtolower($customersLabel) }}</h2>
            <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>{{ $customerLabel }}</th>
                        <th>Factures</th>
                        <th>CA</th>
                        <th>Reste</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($topCustomers as $customer)
                    <tr>
                        <td>
                            <strong>{{ $customer['name'] }}</strong>
                            <div class="muted">{{ $customer['code'] }}</div>
                        </td>
                        <td>{{ $customer['invoice_count'] }}</td>
                        <td>{{ $money($customer['total']) }}</td>
                        <td>{{ $money($customer['due']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">Aucun {{ strtolower($customerLabel) }} facture sur cette periode.</td></tr>
                @endforelse
                </tbody>
            </table>
            </div>
        </section>

        @if ($canAccessAllBranches)
            <section class="card">
                <h2 style="margin-top:0;">Ventes par agence</h2>
                <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Agence</th>
                            <th>Factures</th>
                            <th>CA</th>
                            <th>Evolution</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($salesByBranch as $branchRow)
                        <tr>
                            <td>
                                <strong>{{ $branchRow['branch_name'] }}</strong>
                                @if ($branchRow['is_selected'])
                                    <span class="badge badge-muted">perimetre actif</span>
                                @endif
                                <div class="muted">Avant: {{ $money($branchRow['previous_total']) }}</div>
                            </td>
                            <td>{{ $branchRow['invoice_count'] }}</td>
                            <td>{{ $money($branchRow['total']) }}</td>
                            <td><span class="badge {{ $branchRow['direction'] === 'up' ? 'badge-success' : ($branchRow['direction'] === 'down' ? 'badge-warning' : 'badge-muted') }}">{{ number_format($branchRow['delta_percent'], 1, ',', ' ') }} %</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="muted">Aucune agence active pour ce rapport.</td></tr>
                    @endforelse
                    </tbody>
                </table>
                </div>
            </section>
        @endif
    </div>

    <section class="card" style="margin-top:20px;">
        <div class="page-head" style="margin-bottom:14px;">
            <div>
                <h2 style="margin:0;">Performance {{ strtolower($suppliersLabel) }}</h2>
                <div class="muted">Pilotage {{ strtolower($purchasesLabel) }} : ponctualite, execution et exposition financiere sur la periode.</div>
            </div>
        </div>
        <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>{{ $supplierLabel }}</th>
                    <th>Score</th>
                    <th>A temps</th>
                    <th>Retard moyen</th>
                    <th>{{ $purchasesLabel }}</th>
                    <th>Reste</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($supplierPerformance as $supplier)
                <tr>
                    <td>
                        <strong><a href="{{ route('suppliers.show', $supplier['supplier_id']) }}">{{ $supplier['supplier_name'] }}</a></strong>
                        <div class="muted">{{ $supplier['supplier_code'] ?: 'Code n.c.' }} · {{ $supplier['orders_count'] }} commande(s)</div>
                    </td>
                    <td>
                        <span class="badge {{ $supplier['score'] >= 85 ? 'badge-success' : ($supplier['score'] >= 55 ? 'badge-warning' : 'badge-danger') }}">{{ number_format((float) $supplier['score'], 1, ',', ' ') }}/100</span>
                        <div class="muted" style="margin-top:6px;">{{ $supplier['score_label'] }}</div>
                    </td>
                    <td>{{ $supplier['on_time_rate'] !== null ? number_format((float) $supplier['on_time_rate'], 1, ',', ' ') . ' %' : 'n.c.' }}</td>
                    <td>{{ $supplier['avg_delay_days'] !== null ? number_format((float) $supplier['avg_delay_days'], 1, ',', ' ') . ' j' : 'n.c.' }}</td>
                    <td>{{ $money($supplier['spend_total']) }}</td>
                    <td>{{ $money($supplier['open_balance']) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">Aucun {{ strtolower($supplierLabel) }} avec historique {{ strtolower($purchasesLabel) }} sur cette periode.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </section>

    <div class="split" style="margin-top:20px;">
        <section class="card">
            <h2 style="margin-top:0;">Tresorerie et dettes</h2>
            <div class="table-wrap">
            <table>
                <tbody>
                <tr>
                    <th style="width:50%;">Encaissements</th>
                    <td>{{ $money($treasury['in']) }}</td>
                </tr>
                <tr>
                    <th>Decaissements</th>
                    <td>{{ $money($treasury['out']) }}</td>
                </tr>
                <tr>
                    <th>Flux net</th>
                    <td>{{ $money($cashNet) }}</td>
                </tr>
                <tr>
                    <th>Creances {{ strtolower($customersLabel) }}</th>
                    <td>{{ $money($receivables['total']) }}</td>
                </tr>
                <tr>
                    <th>Dettes {{ strtolower($suppliersLabel) }}</th>
                    <td>{{ $money($payables['total']) }}</td>
                </tr>
                </tbody>
            </table>
            </div>
        </section>

        <section class="card">
            <h2 style="margin-top:0;">{{ $productsLabel }} dormants</h2>
            <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>{{ $productLabel }}</th>
                        <th>{{ $stockLabel }}</th>
                        <th>Valeur</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($dormantProducts as $product)
                    <tr>
                        <td>
                            <strong>{{ $product['name'] }}</strong>
                            <div class="muted">{{ $product['sku'] }} · {{ $product['category_name'] }}</div>
                        </td>
                        <td>{{ number_format($product['current_stock'], 0, ',', ' ') }}</td>
                        <td>{{ $money($product['stock_value']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="muted">Aucun {{ strtolower($productLabel) }} dormant sur les 30 derniers jours.</td></tr>
                @endforelse
                </tbody>
            </table>
            </div>
        </section>
    </div>
@endsection
