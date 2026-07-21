@extends('layouts.app')

@section('title', 'Guide metier')
@section('page-title', 'Guide metier')
@section('layout-mode', 'compact')

@section('content')
    <section class="card" style="margin-bottom:18px;">
        <div style="display:flex; justify-content:space-between; gap:18px; align-items:flex-start; flex-wrap:wrap;">
            <div style="max-width:820px;">
                <div class="badge badge-success">Profil actif</div>
                <h1 style="margin:10px 0 8px;">{{ $profile['label'] }}</h1>
                <p class="muted" style="margin:0;">{{ $profile['description'] }}</p>
            </div>
            <span class="dashboard-icon-badge dashboard-icon-badge--success" style="width:54px; height:54px;">
                @include('dashboard.partials.icon', ['name' => $profile['icon'] ?? 'building', 'size' => 28])
            </span>
        </div>
    </section>

    <div class="grid" style="margin-bottom:18px;">
        <section class="card">
            <h2 style="margin-top:0;">Modules recommandes</h2>
            <div class="chip-row">
                @foreach ($profile['recommended_modules'] as $module)
                    @include('partials.erp-status-badge', ['label' => $module, 'tone' => 'muted'])
                @endforeach
            </div>
        </section>

        <section class="card">
            <h2 style="margin-top:0;">Vocabulaire adapte</h2>
            <div class="table-wrap">
                <table>
                    <tbody>
                        @foreach ($profile['vocabulary'] as $key => $word)
                            <tr>
                                <th>{{ str($key)->replace('_', ' ')->title() }}</th>
                                <td>{{ $word }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="grid" style="margin-bottom:18px;">
        <section class="card">
            <h2 style="margin-top:0;">Champs importants</h2>
            <div class="help">
                @foreach ($profile['specific_fields'] as $field)
                    <div>• {{ $field }}</div>
                @endforeach
            </div>
        </section>

        <section class="card">
            <h2 style="margin-top:0;">Parcours de travail</h2>
            <div class="help">
                @foreach ($profile['workflows'] as $step)
                    <div>• {{ $step }}</div>
                @endforeach
            </div>
        </section>
    </div>

    <div class="grid" style="margin-bottom:18px;">
        <section class="card">
            <h2 style="margin-top:0;">Indicateurs a suivre</h2>
            <div class="chip-row">
                @foreach ($profile['kpis'] as $kpi)
                    @include('partials.erp-status-badge', ['label' => $kpi, 'tone' => 'muted'])
                @endforeach
            </div>
        </section>

        <section class="card">
            <h2 style="margin-top:0;">Alertes intelligentes</h2>
            <div class="help">
                @foreach ($profile['alerts'] as $alert)
                    <div>• {{ $alert }}</div>
                @endforeach
            </div>
        </section>
    </div>

    <section class="card" style="margin-bottom:18px;">
        <h2 style="margin-top:0;">Configuration de depart</h2>
        <div class="grid">
            <div class="summary-box">
                <strong>Categories</strong>
                <div class="help" style="margin-top:8px;">{{ implode(' · ', $profile['starter']['categories']) }}</div>
            </div>
            <div class="summary-box">
                <strong>Unites</strong>
                <div class="help" style="margin-top:8px;">{{ implode(' · ', $profile['starter']['units']) }}</div>
            </div>
            <div class="summary-box">
                <strong>Paiements</strong>
                <div class="help" style="margin-top:8px;">{{ implode(' · ', $profile['starter']['payments']) }}</div>
            </div>
            <div class="summary-box">
                <strong>Exemples</strong>
                <div class="help" style="margin-top:8px;">{{ implode(' · ', $profile['starter']['examples']) }}</div>
            </div>
        </div>
    </section>

    <div class="grid" style="margin-bottom:18px;">
        <section class="card">
            <h2 style="margin-top:0;">A configurer d abord</h2>
            <div class="help">
                @foreach ($profile['guide']['configure_first'] as $item)
                    <div>• {{ $item }}</div>
                @endforeach
            </div>
        </section>

        <section class="card">
            <h2 style="margin-top:0;">Erreurs a eviter</h2>
            <div class="help">
                @foreach ($profile['guide']['avoid'] as $item)
                    <div>• {{ $item }}</div>
                @endforeach
            </div>
        </section>
    </div>

    <section class="card">
        <div style="display:flex; justify-content:space-between; gap:14px; align-items:center; flex-wrap:wrap;">
            <div>
                <h2 style="margin:0;">Changer de metier</h2>
                <p class="muted" style="margin:8px 0 0;">Le choix se fait dans les parametres generaux de l entreprise.</p>
            </div>
            @allowed('settings.manage')
                <a href="{{ route('settings.index') }}#sector-profile" class="button button-primary">Ouvrir les parametres</a>
            @endallowed
        </div>
    </section>
@endsection
