@extends('layouts.app')

@section('title', 'Fiscalite - Nema ERP')
@section('page-title', 'Fiscalite de base')

@section('content')
    <section class="card" style="margin-bottom:20px;">
        <form method="GET" class="form-grid" style="grid-template-columns: repeat(3, minmax(0, 1fr)); align-items:end;">
            <div>
                <label>Du</label>
                <input type="date" name="from" value="{{ $from }}">
            </div>
            <div>
                <label>Au</label>
                <input type="date" name="to" value="{{ $to }}">
            </div>
            <div>
                <button type="submit" class="button button-primary">Actualiser</button>
            </div>
        </form>
    </section>

    <div class="grid stats-grid" style="margin-bottom:20px;">
        <div class="card"><div class="muted">TVA collectee</div><div class="stat-value">{{ number_format($summary['collected_vat'], 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">TVA deductible</div><div class="stat-value">{{ number_format($summary['deductible_vat'], 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">TVA nette</div><div class="stat-value">{{ number_format($summary['net_vat'], 0, ',', ' ') }}</div></div>
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
@endsection
