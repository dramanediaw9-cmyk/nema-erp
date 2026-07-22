@extends('layouts.app')

@section('title', 'Entreprises - Nema ERP')
@section('page-title', 'Entreprises')

@section('content')
    <style>
        .company-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(420px, 1fr));
            gap: 14px;
        }

        .company-panel {
            background: rgba(255, 255, 255, 0.94);
            border: 1px solid rgba(15, 65, 70, 0.12);
            border-radius: 8px;
            box-shadow: 0 18px 42px rgba(29, 21, 13, 0.08);
            padding: 16px;
        }

        .company-panel-head {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: flex-start;
            border-bottom: 1px solid rgba(15, 65, 70, 0.10);
            padding-bottom: 12px;
        }

        .company-score {
            min-width: 78px;
            text-align: center;
            border-radius: 8px;
            border: 1px solid rgba(15, 65, 70, 0.14);
            background: #f6fbfa;
            padding: 8px 10px;
            font-weight: 800;
            color: #006c68;
        }

        .company-score span {
            display: block;
            font-size: 12px;
            color: #65726f;
            font-weight: 700;
            text-transform: uppercase;
        }

        .company-meta {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
            margin: 12px 0;
            font-size: 14px;
        }

        .company-meta div {
            border-radius: 8px;
            border: 1px solid rgba(15, 65, 70, 0.10);
            background: #fbfaf7;
            padding: 8px 10px;
            min-width: 0;
        }

        .company-checklist {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
            margin-top: 10px;
        }

        .company-check {
            border: 1px solid rgba(15, 65, 70, 0.10);
            border-left: 4px solid #d6c2a5;
            border-radius: 8px;
            padding: 8px 10px;
            min-height: 64px;
            background: #fff;
        }

        .company-check.ready {
            border-left-color: #00796f;
            background: #f5fbf9;
        }

        .company-check-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            font-weight: 800;
            color: #223332;
            font-size: 13px;
        }

        .company-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
            margin-top: 14px;
        }

        @media (max-width: 760px) {
            .company-grid,
            .company-checklist {
                grid-template-columns: 1fr;
            }

            .company-panel-head {
                flex-direction: column;
            }
        }
    </style>

    <div class="page-head">
        <div>
            <h2 style="margin:0;">Mise en service clients</h2>
            <div class="muted">Chaque entreprise doit avoir son agence, sa caisse, son stock, ses droits et ses documents prêts avant exploitation.</div>
        </div>
        @if (auth()->user()?->hasRole('platform_admin'))
            <a href="{{ route('companies.create') }}" class="button button-primary">Nouvelle entreprise</a>
        @endif
    </div>

    <div class="company-grid">
        @forelse ($companies as $company)
            @php
                $readiness = $readinessByCompany[$company->id] ?? ['score' => 0, 'ready_count' => 0, 'total_count' => 0, 'items' => []];
                $isCurrent = $workspace->companyId() === $company->id;
            @endphp

            <section class="company-panel">
                <div class="company-panel-head">
                    <div>
                        <h3 style="margin:0 0 4px;">{{ $company->name }}</h3>
                        <div class="muted">{{ $company->legal_name ?: 'Raison sociale non renseignée' }}</div>
                        <div style="margin-top:8px;">
                            <span class="badge {{ $company->is_active ? 'badge-success' : 'badge-muted' }}">
                                {{ $company->is_active ? 'Active' : 'Inactive' }}
                            </span>
                            @if ($isCurrent)
                                <span class="badge badge-success">Entreprise ouverte</span>
                            @endif
                        </div>
                    </div>
                    <div class="company-score">
                        {{ $readiness['score'] }}%
                        <span>{{ $readiness['ready_count'] }}/{{ $readiness['total_count'] }} prêts</span>
                    </div>
                </div>

                <div class="company-meta">
                    <div>
                        <strong>NIF</strong>
                        <div class="muted">{{ $company->nif ?: 'A compléter' }}</div>
                    </div>
                    <div>
                        <strong>RCCM</strong>
                        <div class="muted">{{ $company->rccm ?: 'A compléter' }}</div>
                    </div>
                    <div>
                        <strong>Contact</strong>
                        <div class="muted">{{ $company->phone ?: $company->email ?: 'A compléter' }}</div>
                    </div>
                    <div>
                        <strong>Devise</strong>
                        <div class="muted">{{ $company->currency_code ?: 'XOF' }}</div>
                    </div>
                </div>

                <div class="company-checklist">
                    @foreach ($readiness['items'] as $item)
                        <div class="company-check {{ $item['ready'] ? 'ready' : '' }}">
                            <div class="company-check-title">
                                <span>{{ $item['label'] }}</span>
                                <span class="badge {{ $item['ready'] ? 'badge-success' : 'badge-muted' }}">
                                    {{ $item['ready'] ? 'OK' : 'A faire' }}
                                </span>
                            </div>
                            <div class="muted" style="margin-top:4px;">{{ $item['detail'] }}</div>
                        </div>
                    @endforeach
                </div>

                <div class="company-actions">
                    @if (! $isCurrent && $company->is_active && auth()->user()?->hasRole('platform_admin'))
                        <form method="POST" action="{{ route('companies.switch', $company) }}">
                            @csrf
                            <button type="submit" class="button button-primary">Ouvrir</button>
                        </form>
                    @endif

                    @allowed('companies.manage')
                        <a href="{{ route('companies.edit', $company) }}" class="button button-secondary">Modifier</a>
                        <form method="POST" action="{{ route('companies.provision', $company) }}">
                            @csrf
                            <button type="submit" class="button button-secondary">Réparer le socle</button>
                        </form>
                    @endallowed

                    @if ($isCurrent)
                        <a href="{{ route('settings.index') }}" class="button button-secondary">Paramètres</a>
                        <a href="{{ route('imports.index') }}" class="button button-secondary">Imports Excel</a>
                        <a href="{{ route('activity-logs.index') }}" class="button button-secondary">Audit</a>
                    @endif
                </div>
            </section>
        @empty
            <div class="card">
                <div class="muted">Aucune entreprise disponible pour le moment.</div>
            </div>
        @endforelse
    </div>

    <div style="margin-top: 18px;">{{ $companies->links() }}</div>
@endsection
