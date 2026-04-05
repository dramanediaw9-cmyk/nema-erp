@extends('layouts.app')

@section('title', 'Demande d achat')
@section('page-title', 'Demande d achat')

@section('content')
    @php
        $generatedOrders = $purchaseRequest->generatedPurchaseOrders->isNotEmpty()
            ? $purchaseRequest->generatedPurchaseOrders
            : ($purchaseRequest->convertedPurchaseOrder ? collect([$purchaseRequest->convertedPurchaseOrder]) : collect());
    @endphp

    <div class="page-head">
        <div>
            <h2 class="section-title">{{ $purchaseRequest->request_number }}</h2>
            <div class="muted">Entrepot {{ $purchaseRequest->warehouse?->name }} · Priorite {{ ucfirst($purchaseRequest->priority) }}</div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            @if ($purchaseRequest->status === 'pending_approval')
                @allowed('purchase_requests.approve')
                    <form method="POST" action="{{ route('purchase-requests.approve', $purchaseRequest) }}" class="inline-form">@csrf<button class="button button-primary" type="submit">Approuver</button></form>
                    <form method="POST" action="{{ route('purchase-requests.reject', $purchaseRequest) }}" class="inline-form">@csrf<button class="button button-danger" type="submit">Rejeter</button></form>
                @endallowed
            @endif
            <a href="{{ route('purchase-requests.index') }}" class="button button-secondary">Retour</a>
        </div>
    </div>

    <div class="split">
        <div class="card summary-stack">
            <div class="summary-box"><strong>Statut</strong><div class="value">{{ str_replace('_', ' ', $purchaseRequest->status) }}</div></div>
            <div class="summary-box"><strong>Date demande</strong><div class="value" style="font-size:22px;">{{ $purchaseRequest->request_date?->format('d/m/Y') }}</div></div>
            <div class="summary-box"><strong>Total estime</strong><div class="value">{{ number_format((float) $purchaseRequest->total, 0, ',', ' ') }} XOF</div></div>
            <div class="summary-box"><strong>Plan recommande</strong><div class="value" style="font-size:22px;">{{ $supplierPlan['recommended_orders_count'] }}</div><div class="help" style="margin-top:8px;">commande(s) fournisseur suggeree(s)</div></div>
            @if ($purchaseRequest->originSalesOrder)
                <div class="summary-box">
                    <strong>Commande source</strong>
                    <div style="margin-top:8px;"><a href="{{ route('orders.show', $purchaseRequest->originSalesOrder) }}">{{ $purchaseRequest->originSalesOrder->order_number }}</a></div>
                    <div class="muted" style="margin-top:6px;">{{ $purchaseRequest->originSalesOrder->customer?->name }}</div>
                </div>
            @endif
            @if ($purchaseRequest->notes)
                <div class="summary-box"><strong>Notes</strong><div style="margin-top:8px;">{{ $purchaseRequest->notes }}</div></div>
            @endif
        </div>

        <div class="card">
            <h3 class="section-title">Lignes</h3>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Produit</th>
                        <th>Source</th>
                        <th>Quantite</th>
                        <th>Coût estime</th>
                        <th>Fournisseur recommande</th>
                        <th>Total</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($purchaseRequest->items as $item)
                        @php($linePlan = $supplierPlan['item_map']->get($item->id))
                        <tr>
                            <td>@include('partials.product-inline', ['product' => $item->product, 'meta' => $item->product?->barcode ?: $item->product?->sku, 'size' => 42])</td>
                            <td>
                                @if ($item->originSalesOrderItem)
                                    <span class="badge badge-muted">Commande {{ $purchaseRequest->originSalesOrder?->order_number }}</span>
                                @else
                                    <span class="muted">Manuelle</span>
                                @endif
                            </td>
                            <td>{{ number_format((float) $item->qty, 3, ',', ' ') }}</td>
                            <td>{{ number_format((float) $item->estimated_unit_cost, 0, ',', ' ') }} XOF</td>
                            <td>
                                @if ($linePlan && $linePlan['recommended_supplier_name'])
                                    <strong>{{ $linePlan['recommended_supplier_name'] }}</strong>
                                    <div class="muted" style="margin-top:6px;">{{ number_format((float) $linePlan['recommended_unit_cost'], 0, ',', ' ') }} XOF · {{ $linePlan['recommended_lead_time_days'] !== null ? $linePlan['recommended_lead_time_days'].' j' : 'Delai n.c.' }}</div>
                                    @if ($linePlan['recommended_supplier_score'] !== null)
                                        <div class="help" style="margin-top:6px;">Score fournisseur : {{ number_format((float) $linePlan['recommended_supplier_score'], 1, ',', ' ') }}/100 · {{ $linePlan['recommended_supplier_score_label'] }}</div>
                                    @endif
                                    @if ($linePlan['recommended_supplier_code'])
                                        <div class="help" style="margin-top:6px;">Ref fournisseur : {{ $linePlan['recommended_supplier_code'] }}</div>
                                    @endif
                                @else
                                    <span class="badge badge-warning">Aucun fournisseur configure</span>
                                @endif
                            </td>
                            <td>{{ number_format((float) $item->line_total, 0, ',', ' ') }} XOF</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card" style="margin-top:20px;">
        <div class="page-head" style="margin-bottom:18px;">
            <div>
                <h3 class="section-title" style="margin:0;">Plan fournisseurs recommande</h3>
                <div class="muted">L ERP propose le meilleur regroupement par fournisseur a partir des fiches produit, des coûts et des delais.</div>
            </div>
            @if ($purchaseRequest->status === 'approved' && $generatedOrders->isEmpty() && $supplierPlan['can_auto_convert'])
                <form method="POST" action="{{ route('purchase-requests.auto-convert', $purchaseRequest) }}">
                    @csrf
                    <button type="submit" class="button button-primary">
                        {{ $supplierPlan['recommended_orders_count'] > 1 ? 'Generer '.$supplierPlan['recommended_orders_count'].' commandes recommandees' : 'Generer la commande recommandee' }}
                    </button>
                </form>
            @endif
        </div>

        @if ($supplierPlan['recommended_orders_count'] > 0)
            <div class="summary-stack">
                @foreach ($supplierPlan['recommended_orders'] as $group)
                    <div class="summary-box">
                        <strong>{{ $group['supplier_name'] }}</strong>
                        <div class="value" style="font-size:22px;">{{ number_format((float) $group['estimated_total'], 0, ',', ' ') }} XOF</div>
                        <div class="muted" style="margin-top:8px;">{{ $group['items_count'] }} ligne(s) · delai max {{ $group['max_lead_time_days'] !== null ? $group['max_lead_time_days'].' j' : 'n.c.' }}</div>
                        @if ($group['supplier_score'] !== null)
                            <div class="help" style="margin-top:8px;">Score fournisseur {{ number_format((float) $group['supplier_score'], 1, ',', ' ') }}/100 · {{ $group['supplier_score_label'] }}</div>
                        @endif
                        <div class="help" style="margin-top:8px;">{{ implode(' · ', $group['product_names']) }}</div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="muted">Aucune recommandation fournisseur disponible sur cette demande.</div>
        @endif

        @if ($supplierPlan['missing_items_count'] > 0)
            <div class="tip-card" style="margin-top:16px;">
                <strong>Configuration fournisseur incomplete</strong>
                <div class="muted" style="margin-top:8px;">Les produits suivants n ont pas encore de fournisseur actif configure :</div>
                <div style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap;">
                    @foreach ($supplierPlan['missing_items'] as $missing)
                        <span class="badge badge-warning">{{ $missing['product_name'] }}</span>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    @if ($purchaseRequest->status === 'approved' && $generatedOrders->isEmpty())
        <form method="POST" action="{{ route('purchase-requests.convert', $purchaseRequest) }}" class="card" style="margin-top:20px;">
            @csrf
            <h3 class="section-title">Convertir manuellement en commande fournisseur</h3>
            <div class="form-grid">
                <div>
                    <label for="supplier_id">Fournisseur</label>
                    <select id="supplier_id" name="supplier_id" required>
                        <option value="">Choisir</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected(old('supplier_id', $supplierPlan['single_supplier_id']) == $supplier->id)>{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                    @error('supplier_id')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>
            @if ($supplierPlan['recommended_orders_count'] > 1)
                <div class="tip-card" style="margin-top:16px;">
                    <strong>Conseil ERP</strong>
                    <div class="muted" style="margin-top:8px;">La recommandation actuelle suggere plutot {{ $supplierPlan['recommended_orders_count'] }} commandes distinctes pour mieux respecter les fournisseurs preferes, les coûts et les delais.</div>
                </div>
            @endif
            <div class="actions">
                <button type="submit" class="button button-secondary">Creer une commande fournisseur unique</button>
            </div>
        </form>
    @endif

    @if ($generatedOrders->isNotEmpty())
        <div class="card" style="margin-top:20px;">
            <h3 class="section-title">Commandes fournisseurs generees</h3>
            @foreach ($generatedOrders as $generatedOrder)
                <div style="padding-bottom:12px; border-bottom:1px solid #efe4d3; margin-bottom:12px; display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:flex-start;">
                    <div>
                        <strong>{{ $generatedOrder->order_number }}</strong>
                        <div class="muted">{{ $generatedOrder->supplier?->name }} · {{ number_format((float) $generatedOrder->total, 0, ',', ' ') }} XOF</div>
                    </div>
                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                        <a href="{{ route('purchase-orders.show', $generatedOrder) }}" class="button button-secondary">Ouvrir la commande</a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection


