@extends('layouts.app')

@section('title', 'Production - Nema ERP')
@section('page-title', 'Production')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Ordres de production</h2>
            <div class="muted">Premier socle manufacturing: OF, quantites, jalons atelier et suivi des retards.</div>
        </div>
    </div>

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

    <div class="grid stats-grid" style="margin-bottom:20px;">
        <div class="card"><div class="muted">Ordres</div><div class="stat-value">{{ $summary['orders'] }}</div></div>
        <div class="card"><div class="muted">En cours</div><div class="stat-value">{{ $summary['in_progress'] }}</div></div>
        <div class="card"><div class="muted">Retards</div><div class="stat-value">{{ $summary['late'] }}</div></div>
        <div class="card"><div class="muted">Quantite planifiee</div><div class="stat-value">{{ number_format($summary['planned_qty'], 0, ',', ' ') }}</div></div>
    </div>

    @allowed('manufacturing.manage')
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
    @endallowed

    <section class="card">
        <h3 class="section-title">Ordres atelier</h3>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Numero</th>
                    <th>Article</th>
                    <th>Site</th>
                    <th>Planifie</th>
                    <th>Termine</th>
                    <th>Etape</th>
                    <th>Statut</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($orders as $order)
                    <tr>
                        <td>{{ $order->order_number }}</td>
                        <td>{{ $order->item_name }}</td>
                        <td>{{ $order->branch?->name ?? 'Principal' }}</td>
                        <td>{{ number_format((float) $order->planned_quantity, 3, ',', ' ') }}</td>
                        <td>{{ number_format((float) $order->completed_quantity, 3, ',', ' ') }}</td>
                        <td>{{ $routingOptions[$order->routing_stage] ?? $order->routing_stage }}</td>
                        <td><span class="badge badge-muted">{{ $statusOptions[$order->status] ?? $order->status }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">Aucun ordre de production enregistre pour le moment.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
