@extends('layouts.app')

@section('title', 'Production - Nema ERP')
@section('page-title', 'Production')

@section('content')
    <div class="erp-work-page">
        <section class="erp-work-toolbar">
            <div class="erp-work-toolbar__context">
                <div>
                    <strong>Ordres de production</strong>
                    <div class="muted">Nomenclatures, ordres atelier, jalons et coûts matières.</div>
                </div>
            </div>
        </section>

    @if ($errors->any())
        <div class="card" style="margin-bottom:18px; border-color:#9c3d2f;">
            <strong>Des validations sont a corriger</strong>
            <ul class="summary-list" style="margin-top:10px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

        <div class="erp-kpi-strip">
            <div class="card erp-kpi-card"><div class="label">Ordres</div><div class="value">{{ $summary['orders'] }}</div></div>
            <div class="card erp-kpi-card"><div class="label">En cours</div><div class="value">{{ $summary['in_progress'] }}</div></div>
            <div class="card erp-kpi-card"><div class="label">Retards</div><div class="value">{{ $summary['late'] }}</div></div>
            <div class="card erp-kpi-card"><div class="label">Quantité planifiée</div><div class="value">{{ number_format($summary['planned_qty'], 0, ',', ' ') }}</div></div>
            <div class="card erp-kpi-card"><div class="label">Nomenclatures</div><div class="value">{{ $summary['boms'] }}</div></div>
        </div>

    @allowed('manufacturing.manage')
        <details class="card erp-filter-panel" @if($errors->any()) open @endif>
            <summary>Ajouter une nomenclature ou un ordre</summary>
            <div class="erp-filter-panel__body">
        <form method="POST" action="{{ route('manufacturing.boms.store') }}" class="card form-grid" style="margin-bottom:18px;">
            @csrf
            <div class="full">
                <h3 class="section-title">Nouvelle nomenclature</h3>
            </div>
            <div>
                <label for="bom_code">Code</label>
                <input id="bom_code" name="code" value="{{ old('code') }}" placeholder="BOM-2026-0001">
            </div>
            <div>
                <label for="bom_item_name">Article / kit</label>
                <input id="bom_item_name" name="item_name" value="{{ old('item_name') }}" required>
            </div>
            <div>
                <label for="bom_branch_id">Site</label>
                <select id="bom_branch_id" name="branch_id">
                    <option value="">Site principal</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="output_quantity">Quantite sortie</label>
                <input id="output_quantity" name="output_quantity" type="number" min="0.001" step="0.001" value="{{ old('output_quantity', 1) }}" required>
            </div>
            <div>
                <label for="bom_status">Statut</label>
                <select id="bom_status" name="status" required>
                    @foreach ($bomStatusOptions as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', 'active') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="full">
                <label for="components">Composants</label>
                <textarea id="components" name="components" placeholder="Carton kraft|1|u|0&#10;Pack sucre 1kg|6|u|1.5&#10;Etiquette promo|1|u|0">{{ old('components') }}</textarea>
                <div class="help">Format: nom|quantite|unite|taux rebut|notes. Une ligne par composant.</div>
            </div>
            <div class="full">
                <label for="bom_notes">Notes</label>
                <textarea id="bom_notes" name="notes">{{ old('notes') }}</textarea>
            </div>
            <div class="full actions">
                <button type="submit" class="button button-primary">Enregistrer la nomenclature</button>
            </div>
        </form>

        <form method="POST" action="{{ route('manufacturing.store') }}" class="card form-grid" style="margin-bottom:18px;">
            @csrf
            <div class="full">
                <h3 class="section-title">Nouvel ordre de production</h3>
            </div>
            <div>
                <label for="order_number">Numero OF</label>
                <input id="order_number" name="order_number" value="{{ old('order_number') }}" placeholder="OF-2026-0001">
            </div>
            <div>
                <label for="reference">Reference</label>
                <input id="reference" name="reference" value="{{ old('reference') }}">
            </div>
            <div>
                <label for="bill_of_material_id">Nomenclature</label>
                <select id="bill_of_material_id" name="bill_of_material_id">
                    <option value="">Sans nomenclature</option>
                    @foreach ($boms as $bom)
                        <option value="{{ $bom->id }}" @selected(old('bill_of_material_id') == $bom->id)>{{ $bom->code }} - {{ $bom->item_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="item_name">Article / gamme</label>
                <input id="item_name" name="item_name" value="{{ old('item_name') }}" required>
            </div>
            <div>
                <label for="branch_id">Agence / site</label>
                <select id="branch_id" name="branch_id">
                    <option value="">Site principal</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="planned_quantity">Quantite planifiee</label>
                <input id="planned_quantity" name="planned_quantity" type="number" min="0.001" step="0.001" value="{{ old('planned_quantity', 1) }}" required>
            </div>
            <div>
                <label for="completed_quantity">Quantite terminee</label>
                <input id="completed_quantity" name="completed_quantity" type="number" min="0" step="0.001" value="{{ old('completed_quantity', 0) }}">
            </div>
            <div>
                <label for="material_cost_estimate">Cout matiere estime</label>
                <input id="material_cost_estimate" name="material_cost_estimate" type="number" min="0" step="0.01" value="{{ old('material_cost_estimate', 0) }}">
            </div>
            <div>
                <label for="actual_material_cost">Cout matiere reel</label>
                <input id="actual_material_cost" name="actual_material_cost" type="number" min="0" step="0.01" value="{{ old('actual_material_cost', 0) }}">
            </div>
            <div>
                <label for="planned_start_date">Demarrage</label>
                <input id="planned_start_date" name="planned_start_date" type="date" value="{{ old('planned_start_date', now()->toDateString()) }}" required>
            </div>
            <div>
                <label for="due_date">Echeance</label>
                <input id="due_date" name="due_date" type="date" value="{{ old('due_date', now()->addWeek()->toDateString()) }}">
            </div>
            <div>
                <label for="status">Statut</label>
                <select id="status" name="status" required>
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', 'planned') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="routing_stage">Etape atelier</label>
                <select id="routing_stage" name="routing_stage" required>
                    @foreach ($routingOptions as $value => $label)
                        <option value="{{ $value }}" @selected(old('routing_stage', 'preparation') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="full">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes">{{ old('notes') }}</textarea>
            </div>
            <div class="full actions">
                <button type="submit" class="button button-primary">Enregistrer l ordre</button>
            </div>
        </form>
            </div>
        </details>
    @endallowed

    <section class="card">
        <h3 class="section-title">Nomenclatures</h3>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Code</th>
                    <th>Article</th>
                    <th>Site</th>
                    <th>Sortie</th>
                    <th>Composants</th>
                    <th>Statut</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($boms as $bom)
                    <tr>
                        <td>{{ $bom->code }}</td>
                        <td>{{ $bom->item_name }}</td>
                        <td>{{ $bom->branch?->name ?? 'Principal' }}</td>
                        <td>{{ number_format((float) $bom->output_quantity, 3, ',', ' ') }}</td>
                        <td>{{ $bom->lines->count() }}</td>
                        <td><span class="badge badge-muted">{{ $bomStatusOptions[$bom->status] ?? $bom->status }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">Aucune nomenclature enregistree pour le moment.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <h3 class="section-title">Ordres atelier</h3>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Numero</th>
                    <th>Article</th>
                    <th>Nomenclature</th>
                    <th>Site</th>
                    <th>Planifie</th>
                    <th>Termine</th>
                    <th>Cout estime</th>
                    <th>Cout reel</th>
                    <th>Statut</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($orders as $order)
                    <tr>
                        <td>{{ $order->order_number }}</td>
                        <td>{{ $order->item_name }}</td>
                        <td>{{ $order->billOfMaterial?->code ?? '-' }}</td>
                        <td>{{ $order->branch?->name ?? 'Principal' }}</td>
                        <td>{{ number_format((float) $order->planned_quantity, 3, ',', ' ') }}</td>
                        <td>{{ number_format((float) $order->completed_quantity, 3, ',', ' ') }}</td>
                        <td>{{ number_format((float) $order->material_cost_estimate, 0, ',', ' ') }} XOF</td>
                        <td>{{ number_format((float) $order->actual_material_cost, 0, ',', ' ') }} XOF</td>
                        <td><span class="badge badge-muted">{{ $statusOptions[$order->status] ?? $order->status }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="muted">Aucun ordre de production enregistre pour le moment.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
    </div>
@endsection
