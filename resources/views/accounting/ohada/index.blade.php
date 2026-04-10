@extends('layouts.app')

@section('title', 'Localisation OHADA - Nema ERP')
@section('page-title', 'Localisation OHADA')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Localisation OHADA / SYSCOHADA</h2>
            <div class="muted">Vue guidee du socle comptable local: classes, comptes pivots, pont fiscal, paie et production.</div>
        </div>
        <a href="{{ route('accounting.accounts.index') }}" class="button button-secondary">Ouvrir le plan comptable</a>
    </div>

    <div class="grid stats-grid" style="margin-bottom:20px;">
        <div class="card"><div class="muted">Standard</div><div class="stat-value">{{ $profile['standard'] }}</div></div>
        <div class="card"><div class="muted">Couverture</div><div class="stat-value">{{ number_format((float) $profile['coverage_rate'], 1, ',', ' ') }}%</div></div>
        <div class="card"><div class="muted">Monnaie</div><div class="stat-value">{{ $profile['currency'] }}</div></div>
        <div class="card"><div class="muted">Perimetre</div><div class="stat-value">{{ $profile['locale'] }}</div></div>
    </div>

    <div class="split" style="margin-bottom:18px;">
        <section class="card">
            <h3 class="section-title">Classes SYSCOHADA</h3>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Classe</th>
                        <th>Libelle</th>
                        <th>Comptes</th>
                        <th>Exemples</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($profile['classes'] as $class)
                        <tr>
                            <td>{{ $class['class'] }}</td>
                            <td>{{ $class['label'] }}</td>
                            <td>{{ $class['count'] }}</td>
                            <td>{{ implode(', ', $class['sample_codes']) ?: '-' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <h3 class="section-title">Ponts metiers</h3>
            <div class="summary-stack">
                <div class="summary-box">
                    <strong>Fiscalite</strong>
                    <div class="muted" style="margin-top:8px;">TVA collectee: <code>{{ $profile['bridges']['tax']['vat_collected'] }}</code></div>
                    <div class="muted" style="margin-top:8px;">TVA deductible: <code>{{ $profile['bridges']['tax']['vat_deductible'] }}</code></div>
                    <div class="muted" style="margin-top:8px;">Retenues: <code>{{ $profile['bridges']['tax']['withholding'] }}</code></div>
                </div>
                <div class="summary-box">
                    <strong>Paie</strong>
                    <div class="muted" style="margin-top:8px;">Dettes salariales: <code>{{ $profile['bridges']['payroll']['payables'] }}</code></div>
                    <div class="muted" style="margin-top:8px;">Organismes sociaux: <code>{{ $profile['bridges']['payroll']['social_security'] }}</code></div>
                    <div class="muted" style="margin-top:8px;">Charges salariales: <code>{{ $profile['bridges']['payroll']['salary_expense'] }}</code></div>
                </div>
                <div class="summary-box">
                    <strong>Production</strong>
                    <div class="muted" style="margin-top:8px;">Stock: <code>{{ $profile['bridges']['manufacturing']['stock'] }}</code></div>
                    <div class="muted" style="margin-top:8px;">Achats: <code>{{ $profile['bridges']['manufacturing']['purchases'] }}</code></div>
                    <div class="muted" style="margin-top:8px;">Produits: <code>{{ $profile['bridges']['manufacturing']['sales'] }}</code></div>
                </div>
            </div>
        </section>
    </div>

    <section class="card">
        <h3 class="section-title">Comptes recommandes</h3>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Compte</th>
                    <th>Zone</th>
                    <th>Libelle</th>
                    <th>Etat</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($profile['recommended_accounts'] as $account)
                    <tr>
                        <td>{{ $account['code'] }}</td>
                        <td>{{ $account['area'] }}</td>
                        <td>{{ $account['label'] }}</td>
                        <td>
                            <span class="badge badge-muted">{{ $account['present'] ? 'Present' : 'A completer' }}</span>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection
