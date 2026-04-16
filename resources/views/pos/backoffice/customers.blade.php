@extends('layouts.app')

@section('title', 'Clients POS - Nema ERP')
@section('page-title', 'Clients POS')

@section('content')
    <div class="grid" style="gap:18px;">
        @include('pos.partials.backoffice-nav')

        <div class="page-head">
            <div>
                <h2 style="margin:0;">Portefeuille clients du comptoir</h2>
                <div class="muted">Clients actifs, meilleurs clients POS et connexion avec le referentiel client global.</div>
            </div>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <a href="{{ route('customers.index') }}" class="button button-secondary">Ouvrir les clients</a>
                <a href="{{ route('customers.create') }}" class="button button-primary">Nouveau client</a>
            </div>
        </div>

        <div class="grid stats-grid">
            <div class="card"><div class="muted">Clients</div><div class="stat-value">{{ $data['summary']['customers'] }}</div></div>
            <div class="card"><div class="muted">Actifs</div><div class="stat-value">{{ $data['summary']['active_customers'] }}</div></div>
            <div class="card"><div class="muted">Clients POS</div><div class="stat-value">{{ $data['summary']['pos_customers'] }}</div></div>
            <div class="card"><div class="muted">E-wallets actifs</div><div class="stat-value">{{ $data['summary']['wallets'] }}</div></div>
        </div>

        <section class="card">
            <h3 class="section-title">Meilleurs clients POS</h3>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Telephone</th>
                            <th>Tickets</th>
                            <th>CA POS</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($data['top_customers'] as $customer)
                            <tr>
                                <td>{{ $customer->name }}</td>
                                <td>{{ $customer->phone ?: 'n/a' }}</td>
                                <td>{{ $customer->tickets }}</td>
                                <td>{{ number_format((float) $customer->amount, 0, ',', ' ') }} XOF</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="muted">Aucun historique POS client pour le moment.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <h3 class="section-title">Nouveaux clients</h3>
            <div class="summary-stack">
                @forelse ($data['recent_customers'] as $customer)
                    <div class="summary-box">
                        <strong>{{ $customer->name }}</strong>
                        <div class="muted" style="margin-top:8px;">{{ $customer->phone ?: 'Telephone non renseigne' }} · {{ $customer->city ?: 'Ville non renseignee' }}</div>
                    </div>
                @empty
                    <div class="muted">Aucun client enregistre.</div>
                @endforelse
            </div>
        </section>
    </div>
@endsection
