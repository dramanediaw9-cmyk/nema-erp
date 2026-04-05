@extends('layouts.app')

@section('title', 'Recherche globale - Nema ERP')
@section('page-title', 'Recherche globale')

@push('page-styles')
    <style>
        .search-shell {
            display: grid;
            gap: 20px;
        }
        .search-hero {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, rgba(255, 249, 240, 0.98) 0%, rgba(241, 248, 245, 0.96) 58%, rgba(255, 241, 221, 0.92) 100%);
            border-color: rgba(11, 79, 86, 0.12);
        }
        .search-hero::after {
            content: "";
            position: absolute;
            width: 220px;
            height: 220px;
            right: -60px;
            top: -40px;
            border-radius: 999px;
            background: rgba(197, 106, 24, 0.12);
            filter: blur(8px);
            pointer-events: none;
        }
        .search-hero__grid {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: minmax(0, 1.25fr) minmax(320px, .95fr);
            gap: 20px;
            align-items: start;
        }
        .search-hero h2 {
            margin: 12px 0 8px;
            font-size: clamp(28px, 4vw, 40px);
            line-height: 1.03;
            letter-spacing: -.04em;
        }
        .search-hero p {
            margin: 0;
            max-width: 760px;
        }
        .search-panel {
            border: 1px solid rgba(102, 82, 56, 0.1);
            border-radius: 20px;
            padding: 16px 18px;
            background: rgba(255, 255, 255, 0.74);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.88);
        }
        .search-panel form {
            display: grid;
            gap: 10px;
        }
        .search-summary {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: center;
            flex-wrap: wrap;
        }
        .search-grid {
            display: grid;
            gap: 20px;
        }
        .search-results {
            display: grid;
            gap: 16px;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        }
        .search-result-card {
            display: block;
            border: 1px solid rgba(102, 82, 56, 0.1);
            border-radius: 20px;
            padding: 18px;
            background: rgba(255, 255, 255, 0.78);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.92);
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
        }
        .search-result-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 18px 32px rgba(42, 28, 18, 0.08);
            border-color: rgba(15, 118, 110, 0.18);
        }
        .search-result-card strong {
            display: block;
        }
        .search-result-card .muted,
        .search-result-card .help {
            margin-top: 8px;
        }
        @media (max-width: 980px) {
            .search-hero__grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    <div class="search-shell">
        <section class="card search-hero">
            <div class="search-hero__grid">
                <div>
                    <div class="badge badge-muted">Recherche transverse</div>
                    <h2>Retrouve rapidement un document, un tiers ou un produit.</h2>
                    <p class="muted">La recherche globale traverse les modules autorises pour ton profil. Elle renvoie directement vers la bonne fiche au lieu de te faire naviguer menu par menu.</p>
                    <div class="help" style="margin-top:10px;">Perimetre actif : {{ $availableScopes->join(' | ') }}</div>
                </div>
                <div class="search-panel">
                    <strong style="display:block; margin-bottom:10px;">Recherche rapide</strong>
                    <form method="GET" action="{{ route('search.index') }}">
                        <input type="search" name="q" value="{{ $query }}" placeholder="Numero, client, produit, paiement, reception...">
                        <div style="display:flex; gap:10px; flex-wrap:wrap;">
                            <button type="submit" class="button button-primary">Rechercher</button>
                            @if ($query !== '')
                                <a href="{{ route('search.index') }}" class="button button-secondary">Effacer</a>
                            @endif
                        </div>
                    </form>
                </div>
            </div>
        </section>

        @if ($query === '')
            <section class="card empty-state">
                <div class="badge badge-warning">Conseil</div>
                <h3>Lance une recherche pour commencer</h3>
                <p class="muted">Exemples utiles : un numero de facture, un code produit, le nom d un client, une reference de paiement ou un fournisseur.</p>
            </section>
        @elseif ($groups->isEmpty())
            <section class="card empty-state">
                <div class="badge badge-warning">Aucun resultat</div>
                <h3>Rien n a ete trouve pour "{{ $query }}"</h3>
                <p class="muted">Essaie un numero de document, un code partenaire, un SKU produit ou un mot present dans les notes.</p>
            </section>
        @else
            <section class="card">
                <div class="search-summary">
                    <div>
                        <h2 style="margin:0; font-size:26px; letter-spacing:-.03em;">{{ $totalResults }} resultat(s) proposes</h2>
                        <div class="muted">Les blocs ci-dessous regroupent les correspondances par module.</div>
                    </div>
                </div>
            </section>

            <div class="search-grid">
                @foreach ($groups as $group)
                    <section class="card">
                        <div class="search-summary" style="margin-bottom:14px; align-items:flex-start;">
                            <div>
                                <h2 style="margin:0; font-size:24px; letter-spacing:-.03em;">{{ $group['title'] }}</h2>
                                <div class="muted">{{ $group['count'] }} resultat(s) dans ce module.</div>
                            </div>
                            <a href="{{ $group['index_url'] }}" class="button button-secondary">Ouvrir la liste</a>
                        </div>
                        <div class="search-results">
                            @foreach ($group['items'] as $item)
                                <a href="{{ $item['url'] }}" class="search-result-card">
                                    <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start;">
                                        <strong>{{ $item['title'] }}</strong>
                                        <span class="badge badge-muted">{{ $item['badge'] }}</span>
                                    </div>
                                    @if (! empty($item['subtitle']))
                                        <div class="muted">{{ $item['subtitle'] }}</div>
                                    @endif
                                    @if (! empty($item['meta']))
                                        <div class="help">{{ $item['meta'] }}</div>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
        @endif
    </div>
@endsection
