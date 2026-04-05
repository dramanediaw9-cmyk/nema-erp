@extends('layouts.app')

@section('title', 'Reappro automatique')
@section('page-title', 'Reappro automatique')

@section('content')
    <style>
        .replenishment-stats {
            display: grid;
            gap: 14px;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            margin-bottom: 18px;
        }
        .replenishment-stat {
            border: 1px solid var(--line);
            border-radius: 18px;
            background: #fff;
            padding: 16px 18px;
        }
        .replenishment-stat .label {
            color: var(--muted);
            font-size: 13px;
        }
        .replenishment-stat .value {
            margin-top: 8px;
            font-size: 28px;
            font-weight: 800;
        }
        .replenishment-toolbar {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: end;
            margin-bottom: 18px;
        }
        .replenishment-toolbar .field {
            min-width: 220px;
        }
    </style>

    <div class="page-head">
        <div>
            <h2 class="section-title">Reappro automatique</h2>
            <div class="muted">Suggestions calculees selon le stock reel, les commandes fournisseurs en cours, les demandes deja ouvertes et les regles min/max de chaque produit.</div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="{{ route('purchase-requests.index') }}" class="button button-secondary">Demandes d achat</a>
            @allowed('purchase_requests.manage')
                <a href="{{ route('products.index') }}" class="button button-secondary">Configurer les produits</a>
            @endallowed
        </div>
    </div>

    <div class="replenishment-stats">
        <div class="replenishment-stat">
            <div class="label">Suggestions</div>
            <div class="value">{{ $stats['count'] }}</div>
        </div>
        <div class="replenishment-stat">
            <div class="label">Quantite proposee</div>
            <div class="value">{{ number_format((float) $stats['quantity'], 3, ',', ' ') }}</div>
        </div>
        <div class="replenishment-stat">
            <div class="label">Valeur estimee</div>
            <div class="value">{{ number_format((float) $stats['estimated_total'], 0, ',', ' ') }} XOF</div>
        </div>
        <div class="replenishment-stat">
            <div class="label">Urgences</div>
            <div class="value">{{ $stats['urgent_count'] }}</div>
        </div>
    </div>

    <div class="card">
        <form method="GET" action="{{ route('replenishments.index') }}" class="replenishment-toolbar">
            <div class="field">
                <label for="warehouse_id">Depot analyse</label>
                <select id="warehouse_id" name="warehouse_id" onchange="this.form.submit()">
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected($selectedWarehouse?->id === $warehouse->id)>{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Regles prises en compte</label>
                <div class="help" style="padding-top:11px;">Seuil mini, stock cible, multiple achat, delai achat, commandes ouvertes et demandes deja lancees.</div>
            </div>
        </form>
    </div>

    <form method="POST" action="{{ route('replenishments.generate') }}" class="card" style="margin-top:18px;">
        @csrf
        <input type="hidden" name="warehouse_id" value="{{ $selectedWarehouse?->id }}">

        @if ($suggestions->isEmpty())
            <div class="empty-state" style="padding:30px 0;">
                <div class="muted">Aucune suggestion pour ce depot. Le stock projete couvre deja les seuils mini ou les produits ne sont pas encore configures en reappro automatique.</div>
            </div>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th style="width:44px;">Sel.</th>
                        <th>Produit</th>
                        <th>Priorite</th>
                        <th>Stock reel</th>
                        <th>Cmdes en cours</th>
                        <th>Demandes ouvertes</th>
                        <th>Stock projete</th>
                        <th>Mini</th>
                        <th>Cible</th>
                        <th>Multiple</th>
                        <th>A proposer</th>
                        <th>Valeur</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($suggestions as $suggestion)
                        @php($product = $suggestion['product'])
                        <tr>
                            <td>
                                @allowed('purchase_requests.manage')
                                    <input type="checkbox" name="selected[]" value="{{ $product->id }}" checked>
                                @else
                                    <span class="muted">-</span>
                                @endallowed
                            </td>
                            <td>
                                <strong>{{ $product->display_name }}</strong>
                                <div class="muted">{{ $product->sku }} · {{ $product->purchaseUnitSummary() ?: $product->unit }}</div>
                                @if ($suggestion['supplier_name'])
                                    <div class="muted">Fournisseur : {{ $suggestion['supplier_name'] }}{{ $suggestion['supplier_product_code'] ? ' · '.$suggestion['supplier_product_code'] : '' }}</div>
                                @endif
                                @if ($suggestion['purchase_lead_time_days'])
                                    <div class="muted">Delai : {{ $suggestion['purchase_lead_time_days'] }} j{{ $suggestion['supplier_min_qty'] > 0 ? ' · mini '.$suggestion['supplier_min_qty'] : '' }}</div>
                                @elseif ($suggestion['supplier_min_qty'] > 0)
                                    <div class="muted">Mini fournisseur : {{ number_format((float) $suggestion['supplier_min_qty'], 3, ',', ' ') }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $suggestion['priority'] === 'urgent' ? 'badge-danger' : ($suggestion['priority'] === 'high' ? 'badge-warning' : 'badge-muted') }}">
                                    {{ $suggestion['priority'] === 'urgent' ? 'Urgent' : ($suggestion['priority'] === 'high' ? 'Haute' : 'Normale') }}
                                </span>
                            </td>
                            <td>{{ number_format((float) $suggestion['current_stock'], 3, ',', ' ') }}</td>
                            <td>{{ number_format((float) $suggestion['incoming_qty'], 3, ',', ' ') }}</td>
                            <td>{{ number_format((float) $suggestion['open_request_qty'], 3, ',', ' ') }}</td>
                            <td>{{ number_format((float) $suggestion['projected_stock'], 3, ',', ' ') }}</td>
                            <td>{{ number_format((float) $suggestion['min_stock'], 3, ',', ' ') }}</td>
                            <td>{{ number_format((float) $suggestion['target_stock'], 3, ',', ' ') }}</td>
                            <td>{{ $suggestion['multiple_qty'] > 0 ? number_format((float) $suggestion['multiple_qty'], 3, ',', ' ') : '-' }}</td>
                            <td><strong>{{ number_format((float) $suggestion['suggested_qty'], 3, ',', ' ') }}</strong></td>
                            <td>{{ number_format((float) $suggestion['estimated_total'], 0, ',', ' ') }} XOF</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            @allowed('purchase_requests.manage')
                <div class="actions" style="margin-top:18px;">
                    <a href="{{ route('purchase-requests.create') }}" class="button button-secondary">Saisie manuelle</a>
                    <button type="submit" class="button button-primary">Generer une demande d achat</button>
                </div>
            @else
                <div class="help" style="margin-top:18px;">Tu peux consulter les suggestions, mais la generation d une demande d achat reste reservee aux profils autorises.</div>
            @endallowed
        @endif
    </form>
@endsection
