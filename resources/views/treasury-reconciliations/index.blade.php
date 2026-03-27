@extends('layouts.app')

@section('title', 'Rapprochements - Nema ERP')
@section('page-title', 'Rapprochements')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Rapprochements banque / mobile money</h2>
            <div class="muted">Controler les mouvements de tresorerie deja vus sur les releves reels.</div>
        </div>
        @allowed('reconciliations.manage')
            <a href="{{ route('treasury-reconciliations.create') }}" class="button button-primary">Nouveau rapprochement</a>
        @endallowed
    </div>

    <div class="grid stats-grid" style="margin-bottom:20px;">
        <div class="card"><div class="muted">Rapprochements</div><div class="stat-value">{{ $summary['count'] }}</div></div>
        <div class="card"><div class="muted">Equilibres</div><div class="stat-value">{{ $summary['balanced_count'] }}</div></div>
        <div class="card"><div class="muted">Avec ecart</div><div class="stat-value">{{ $summary['gap_count'] }}</div></div>
        <div class="card"><div class="muted">Mouvements rapproches</div><div class="stat-value">{{ $summary['payments_count'] }}</div></div>
    </div>

    <section class="card table-wrap">
        <table>
            <thead>
            <tr>
                <th>Numero</th>
                <th>Date releve</th>
                <th>Compte</th>
                <th>Reference</th>
                <th>Mouvements</th>
                <th>Solde comptable</th>
                <th>Solde releve</th>
                <th>Ecart</th>
                <th>Statut</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($reconciliations as $reconciliation)
                <tr>
                    <td><a href="{{ route('treasury-reconciliations.show', $reconciliation) }}" style="font-weight:700;">{{ $reconciliation->reconciliation_number }}</a></td>
                    <td>{{ $reconciliation->statement_date?->format('d/m/Y') }}</td>
                    <td>{{ $reconciliation->cashAccount?->name }}</td>
                    <td>{{ $reconciliation->statement_reference ?: 'Sans reference' }}</td>
                    <td>{{ $reconciliation->payments_count }}</td>
                    <td>{{ number_format((float) $reconciliation->book_balance, 0, ',', ' ') }} XOF</td>
                    <td>{{ number_format((float) $reconciliation->statement_balance, 0, ',', ' ') }} XOF</td>
                    <td>{{ number_format((float) $reconciliation->difference, 0, ',', ' ') }} XOF</td>
                    <td>
                        <span class="badge {{ $reconciliation->status === 'balanced' ? 'badge-success' : 'badge-warning' }}">
                            {{ $reconciliation->status === 'balanced' ? 'Equilibre' : 'Avec ecart' }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="muted">Aucun rapprochement enregistre pour le moment.</td>
                </tr>
            @endforelse
            </tbody>
        </table>

        <div style="margin-top:18px;">{{ $reconciliations->links() }}</div>
    </section>
@endsection
