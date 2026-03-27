@extends('layouts.app')

@section('title', 'Compte de resultat - Nema ERP')
@section('page-title', 'Compte de resultat')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Performance comptable</h2>
            <div class="muted">Produits, charges et resultat net sur la periode selectionnee.</div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="{{ route('accounting.profit-loss.export', request()->query()) }}" class="button button-secondary">Exporter CSV</a>
            <a href="{{ route('accounting.profit-loss.print', request()->query()) }}" class="button button-secondary" target="_blank">Imprimer</a>
        </div>
    </div>

    <section class="card" style="margin-bottom:20px;">
        <form method="GET" action="{{ route('accounting.profit-loss.index') }}" class="form-grid" style="align-items:end;">
            <div>
                <label for="date_from">Date debut</label>
                <input type="date" id="date_from" name="date_from" value="{{ $filters['date_from'] }}">
            </div>
            <div>
                <label for="date_to">Date fin</label>
                <input type="date" id="date_to" name="date_to" value="{{ $filters['date_to'] }}">
            </div>
            <div class="full actions" style="margin-top:0; justify-content:flex-start;">
                <button type="submit" class="button button-primary">Actualiser</button>
                <a href="{{ route('accounting.profit-loss.index') }}" class="button button-secondary">Mois en cours</a>
            </div>
        </form>
    </section>

    <div class="grid stats-grid" style="margin-bottom:20px;">
        <div class="card"><div class="muted">Produits</div><div class="stat-value">{{ number_format((float) $report['income_total'], 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Charges</div><div class="stat-value">{{ number_format((float) $report['expense_total'], 0, ',', ' ') }}</div></div>
        <div class="card"><div class="muted">Resultat net</div><div class="stat-value">{{ number_format((float) $report['net_result'], 0, ',', ' ') }}</div></div>
    </div>

    <div class="split">
        <section class="card table-wrap">
            <h2 style="margin-top:0;">Produits</h2>
            <table>
                <thead>
                <tr>
                    <th>Code</th>
                    <th>Libelle</th>
                    <th>Montant</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($report['income'] as $line)
                    <tr>
                        <td><strong>{{ $line['code'] }}</strong></td>
                        <td>{{ $line['name'] }}</td>
                        <td>{{ number_format((float) $line['balance'], 0, ',', ' ') }} XOF</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="muted">Aucun produit comptable sur la periode.</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>

        <section class="card table-wrap">
            <h2 style="margin-top:0;">Charges</h2>
            <table>
                <thead>
                <tr>
                    <th>Code</th>
                    <th>Libelle</th>
                    <th>Montant</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($report['expenses'] as $line)
                    <tr>
                        <td><strong>{{ $line['code'] }}</strong></td>
                        <td>{{ $line['name'] }}</td>
                        <td>{{ number_format((float) $line['balance'], 0, ',', ' ') }} XOF</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="muted">Aucune charge comptable sur la periode.</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>
    </div>
@endsection
