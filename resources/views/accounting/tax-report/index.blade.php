@extends('layouts.app')

@section('title', 'Fiscalite - Nema ERP')
@section('page-title', 'Fiscalite de base')

@section('content')
    <div class="erp-work-page">
        <section class="erp-work-toolbar">
            <div class="erp-work-toolbar__context">
                <div>
                    <strong>Fiscalité de base</strong>
                    <div class="muted">TVA collectée, déductible et nette sur la période.</div>
                </div>
            </div>
        </section>

        <details class="card erp-filter-panel" @if(request()->hasAny(['from', 'to'])) open @endif>
            <summary>Filtres fiscaux</summary>
            <div class="erp-filter-panel__body">
                <form method="GET" class="form-grid" style="grid-template-columns: repeat(3, minmax(0, 1fr)); align-items:end;">
                    <div>
                        <label for="tax_from">Du</label>
                        <input type="date" id="tax_from" name="from" value="{{ $from }}">
                    </div>
                    <div>
                        <label for="tax_to">Au</label>
                        <input type="date" id="tax_to" name="to" value="{{ $to }}">
                    </div>
                    <div>
                        <button type="submit" class="button button-primary">Actualiser</button>
                    </div>
                </form>
            </div>
        </details>

        <div class="erp-kpi-strip">
            <div class="card erp-kpi-card"><div class="label">TVA collectée</div><div class="value">{{ number_format($summary['collected_vat'], 0, ',', ' ') }} XOF</div></div>
            <div class="card erp-kpi-card"><div class="label">TVA déductible</div><div class="value">{{ number_format($summary['deductible_vat'], 0, ',', ' ') }} XOF</div></div>
            <div class="card erp-kpi-card"><div class="label">TVA nette</div><div class="value">{{ number_format($summary['net_vat'], 0, ',', ' ') }} XOF</div></div>
        </div>

        <div class="split">
        <section class="card">
            <h2 style="margin-top:0;">Ventes taxeés</h2>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Document</th><th>Client</th><th>Date</th><th>TVA</th></tr></thead>
                    <tbody>
                        @forelse ($sales as $invoice)
                            <tr>
                                <td><a href="{{ route('sales.show', $invoice) }}">{{ $invoice->invoice_number }}</a></td>
                                <td>{{ $invoice->customer?->name }}</td>
                                <td>{{ $invoice->invoice_date?->format('d/m/Y') }}</td>
                                <td>{{ number_format((float) $invoice->tax_total, 0, ',', ' ') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="muted">Aucune vente taxee sur la periode.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <h2 style="margin-top:0;">Achats taxes</h2>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Document</th><th>Fournisseur</th><th>Date</th><th>TVA</th></tr></thead>
                    <tbody>
                        @forelse ($purchases as $bill)
                            <tr>
                                <td><a href="{{ route('purchases.show', $bill) }}">{{ $bill->bill_number }}</a></td>
                                <td>{{ $bill->supplier?->name }}</td>
                                <td>{{ $bill->bill_date?->format('d/m/Y') }}</td>
                                <td>{{ number_format((float) $bill->tax_total, 0, ',', ' ') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="muted">Aucun achat taxe sur la periode.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
        </div>
    </div>
@endsection
