<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light">
    <meta name="theme-color" content="#f4ede2">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/pos-192.png') }}">
    @if (request()->routeIs('pos.*'))
        <meta name="theme-color" content="#102730">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <link rel="manifest" href="{{ asset('pos-manifest.webmanifest') }}">
    @else
        <link rel="manifest" href="{{ asset('app-manifest.webmanifest') }}">
    @endif
    <title>@yield('title', config('app.name'))</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <style>
            :root {
                --bg: #f4ede2;
                --bg-soft: #fbf6ee;
                --paper: rgba(255, 252, 246, 0.92);
                --paper-strong: #fffdfa;
                --paper-muted: #f5ede1;
                --line: rgba(102, 82, 56, 0.14);
                --line-strong: rgba(102, 82, 56, 0.22);
                --text: #241b13;
                --muted: #6e6154;
                --brand: #0f766e;
                --brand-deep: #0b4f56;
                --accent: #c56a18;
                --accent-soft: #f3cfaa;
                --success: #176b4d;
                --warning: #9a5b00;
                --danger: #b42318;
                --shadow-soft: 0 18px 40px rgba(42, 28, 18, 0.08);
                --shadow-strong: 0 32px 90px rgba(12, 28, 36, 0.22);
            }
            * { box-sizing: border-box; }
            body {
                margin: 0;
                position: relative;
                font-family: "Aptos", "Trebuchet MS", "Segoe UI", sans-serif;
                background:
                    radial-gradient(circle at top left, rgba(255, 234, 197, 0.9) 0, rgba(255, 234, 197, 0) 32%),
                    radial-gradient(circle at 85% 12%, rgba(15, 118, 110, 0.18) 0, rgba(15, 118, 110, 0) 28%),
                    linear-gradient(180deg, var(--bg-soft) 0%, #efe3d1 46%, #eadcc9 100%);
                color: var(--text);
            }
            body::before,
            body::after {
                content: "";
                position: fixed;
                border-radius: 999px;
                pointer-events: none;
                filter: blur(22px);
                opacity: .75;
            }
            body::before {
                width: 220px;
                height: 220px;
                right: 10%;
                top: 16%;
                background: rgba(243, 207, 170, 0.42);
            }
            body::after {
                width: 280px;
                height: 280px;
                left: -80px;
                bottom: 8%;
                background: rgba(15, 118, 110, 0.12);
            }
            a { color: inherit; text-decoration: none; }
            .shell {
                display: grid;
                grid-template-columns: minmax(248px, 286px) minmax(0, 1fr);
                gap: 20px;
                min-height: 100vh;
                padding: 18px;
                position: relative;
                z-index: 1;
            }
            .sidebar {
                position: relative;
                background:
                    radial-gradient(circle at top right, rgba(62, 199, 201, 0.18) 0, rgba(62, 199, 201, 0) 26%),
                    linear-gradient(180deg, #163742 0%, #102730 54%, #0b2027 100%);
                color: #f7f0e5;
                padding: 24px 16px 28px;
                border-radius: 28px;
                border: 1px solid rgba(255, 255, 255, 0.08);
                box-shadow: var(--shadow-strong);
            }
            .sidebar::after {
                content: "";
                position: absolute;
                inset: 0;
                border-radius: inherit;
                pointer-events: none;
                border: 1px solid rgba(255, 255, 255, 0.03);
            }
            .brand {
                margin-bottom: 22px;
                padding: 14px 14px 18px;
                border-bottom: 1px solid rgba(255, 255, 255, 0.09);
            }
            .brand h1 {
                margin: 0;
                font-size: 28px;
                line-height: 1;
                letter-spacing: .02em;
            }
            .brand small {
                color: #b9d4d4;
                display: block;
                margin-top: 8px;
                font-size: 13px;
                letter-spacing: .04em;
                text-transform: uppercase;
            }
            .nav-title {
                margin: 22px 12px 10px;
                font-size: 11px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: .18em;
                color: #8fb8b8;
            }
            .nav-link {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 12px 14px;
                margin-bottom: 8px;
                border-radius: 16px;
                color: #deebe9;
                border: 1px solid transparent;
                background: rgba(255, 255, 255, 0.02);
                transition: transform .18s ease, background .18s ease, border-color .18s ease, box-shadow .18s ease;
            }
            .nav-link::before {
                content: "";
                width: 8px;
                height: 8px;
                border-radius: 999px;
                flex-shrink: 0;
                background: rgba(255, 255, 255, 0.26);
                box-shadow: 0 0 0 5px rgba(255, 255, 255, 0.02);
                transition: background .18s ease, box-shadow .18s ease;
            }
            .nav-link.active,
            .nav-link:hover {
                background: linear-gradient(135deg, rgba(255, 255, 255, 0.11) 0%, rgba(255, 255, 255, 0.06) 100%);
                border-color: rgba(255, 255, 255, 0.09);
                transform: translateX(4px);
                box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04);
            }
            .nav-link.active::before,
            .nav-link:hover::before {
                background: #ffd39f;
                box-shadow: 0 0 0 6px rgba(255, 211, 159, 0.12);
            }
            .main {
                min-width: 0;
                padding: 8px 4px 24px 0;
            }
            .topbar {
                position: sticky;
                top: 0;
                z-index: 15;
                display: grid;
                grid-template-columns: minmax(250px, 1fr) auto;
                align-items: start;
                gap: 18px;
                margin-bottom: 24px;
                padding: 18px 22px;
                background: linear-gradient(180deg, rgba(255, 253, 249, 0.86) 0%, rgba(250, 243, 233, 0.92) 100%);
                border: 1px solid rgba(102, 82, 56, 0.14);
                border-radius: 28px;
                backdrop-filter: blur(14px);
                box-shadow: var(--shadow-soft);
            }
            .topbar-leading { display: grid; gap: 8px; }
            .topbar-label {
                font-size: 11px;
                font-weight: 700;
                letter-spacing: .22em;
                text-transform: uppercase;
                color: var(--accent);
            }
            .topbar-title {
                font-size: clamp(24px, 3vw, 34px);
                font-weight: 800;
                line-height: 1.05;
                letter-spacing: -.03em;
            }
            .workspace {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                color: var(--muted);
                font-size: 14px;
            }
            .workspace-pill {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 9px 12px;
                border-radius: 999px;
                background: rgba(15, 118, 110, 0.08);
                border: 1px solid rgba(15, 118, 110, 0.12);
                color: #325b59;
                font-weight: 600;
            }
            .workspace-pill strong { color: var(--brand-deep); }
            .topbar-actions {
                display: flex;
                align-items: center;
                gap: 12px;
                flex-wrap: wrap;
                justify-content: flex-end;
                width: 100%;
            }
            .topbar-search { flex: 1 1 360px; max-width: 520px; }
            .global-search-form { display: flex; gap: 10px; align-items: center; width: 100%; }
            .global-search-form input {
                min-width: 0;
                border-color: rgba(120, 99, 74, 0.18);
                background: rgba(255, 255, 255, 0.94);
                box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.92);
            }
            .global-search-form .button { white-space: nowrap; }
            .button,
            button {
                border: 0;
                border-radius: 14px;
                padding: 12px 16px;
                cursor: pointer;
                font-weight: 700;
                font: inherit;
                transition: transform .18s ease, box-shadow .18s ease, background .18s ease, color .18s ease;
            }
            .button:hover,
            button:hover {
                transform: translateY(-1px);
            }
            .button-primary {
                background: linear-gradient(135deg, var(--brand) 0%, var(--brand-deep) 100%);
                color: white;
                box-shadow: 0 16px 30px rgba(11, 79, 86, 0.18);
            }
            .button-secondary {
                background: linear-gradient(180deg, #f9f2e8 0%, #eee1cf 100%);
                color: var(--text);
                box-shadow: inset 0 0 0 1px rgba(119, 95, 67, 0.12);
            }
            .button-danger {
                background: linear-gradient(180deg, #fff0ef 0%, #fee4e2 100%);
                color: var(--danger);
                box-shadow: inset 0 0 0 1px rgba(180, 35, 24, 0.14);
            }
            .page-head {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 16px;
                margin-bottom: 20px;
            }
            .card {
                background: linear-gradient(180deg, rgba(255, 253, 249, 0.96) 0%, rgba(252, 246, 238, 0.94) 100%);
                border: 1px solid var(--line);
                border-radius: 24px;
                padding: 22px;
                box-shadow: var(--shadow-soft);
            }
            .grid { display: grid; gap: 18px; }
            .stats-grid { grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); }
            .stat-value { font-size: 34px; font-weight: 800; margin-top: 12px; letter-spacing: -.03em; }
            .muted { color: var(--muted); }
            .table-wrap {
                overflow-x: auto;
                border-radius: 20px;
                border: 1px solid rgba(102, 82, 56, 0.1);
                background: rgba(255, 255, 255, 0.84);
            }
            table { width: 100%; border-collapse: collapse; }
            th, td {
                padding: 14px 12px;
                border-bottom: 1px solid rgba(102, 82, 56, 0.08);
                text-align: left;
                vertical-align: top;
            }
            tbody tr:hover { background: rgba(15, 118, 110, 0.04); }
            th {
                color: var(--muted);
                font-size: 12px;
                text-transform: uppercase;
                letter-spacing: .14em;
                font-weight: 800;
            }
            .badge {
                display: inline-flex;
                padding: 5px 10px;
                border-radius: 999px;
                font-size: 12px;
                font-weight: 800;
                letter-spacing: .02em;
            }
            .badge-success { background: #dcfae6; color: var(--success); }
            .badge-muted { background: #efe6d8; color: #705e4f; }
            .badge-warning { background: #fff1db; color: var(--warning); }
            .badge-danger { background: rgba(180, 35, 24, 0.14); color: var(--danger); }
            .form-grid { display: grid; gap: 16px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .form-grid .full { grid-column: 1 / -1; }
            label {
                display: block;
                font-size: 13px;
                font-weight: 800;
                margin-bottom: 8px;
                letter-spacing: .06em;
                text-transform: uppercase;
                color: #5f5449;
            }
            input,
            textarea,
            select {
                width: 100%;
                padding: 13px 14px;
                border-radius: 15px;
                border: 1px solid rgba(120, 99, 74, 0.16);
                background: rgba(255, 255, 255, 0.92);
                box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.94);
                font: inherit;
                color: var(--text);
            }
            input:focus,
            textarea:focus,
            select:focus {
                outline: none;
                border-color: rgba(15, 118, 110, 0.38);
                box-shadow: 0 0 0 4px rgba(15, 118, 110, 0.12);
            }
            textarea { min-height: 110px; resize: vertical; }
            .help { color: var(--muted); font-size: 13px; }
            .actions { display: flex; gap: 12px; justify-content: flex-end; margin-top: 22px; }
            .alert {
                padding: 14px 16px;
                border-radius: 16px;
                margin-bottom: 16px;
                border: 1px solid transparent;
                box-shadow: var(--shadow-soft);
            }
            .alert-success { background: #e7f6ec; border-color: #bfe3ca; color: #185b41; }
            .alert-error { background: #feeceb; border-color: #f4c7c3; color: #9a2c22; }
            .checkbox-grid { display: grid; gap: 10px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
            .checkbox-card { border: 1px solid var(--line); background: rgba(255, 255, 255, 0.84); border-radius: 18px; padding: 14px; }
            .checkbox-row { display: flex; gap: 10px; align-items: flex-start; }
            .inline-form { display: inline; }
            .split { display: grid; gap: 18px; grid-template-columns: 1fr 1fr; }
            .progress {
                width: 100%;
                height: 10px;
                background: rgba(120, 99, 74, 0.12);
                border-radius: 999px;
                overflow: hidden;
            }
            .progress-bar { height: 100%; background: linear-gradient(90deg, #0f766e 0%, #22a39a 100%); border-radius: 999px; }
            .notification-pill {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 10px 14px;
                border-radius: 16px;
                background: rgba(255, 255, 255, 0.84);
                border: 1px solid rgba(102, 82, 56, 0.1);
                color: var(--text);
                font-weight: 700;
                box-shadow: 0 14px 26px rgba(42, 28, 18, 0.06);
            }
            .notification-count { display: inline-flex; min-width: 24px; height: 24px; border-radius: 999px; align-items: center; justify-content: center; padding: 0 8px; background: #b42318; color: #fff; font-size: 12px; }
            .tip-grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); }
            .tip-card { border: 1px solid var(--line); background: rgba(255, 255, 255, 0.72); border-radius: 18px; padding: 14px 16px; }
            .tip-card strong { display: block; margin-bottom: 6px; }
            .summary-stack { display: grid; gap: 12px; }
            .summary-box { border: 1px solid var(--line); background: rgba(255, 255, 255, 0.8); border-radius: 18px; padding: 14px 16px; }
            .summary-box .value { font-size: 28px; font-weight: 700; margin-top: 6px; }
            .summary-list { margin: 0; padding-left: 18px; display: grid; gap: 6px; color: var(--muted); }
            .chip-row { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px; }
            .chip {
                border: 1px solid rgba(120, 99, 74, 0.14);
                background: rgba(255, 255, 255, 0.82);
                color: var(--text);
                border-radius: 999px;
                padding: 8px 12px;
                font-size: 13px;
                cursor: pointer;
                box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.92);
            }
            .chip:hover { background: #f9f3ea; }
            .table-foot-note { display: flex; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-top: 16px; }
            .kpi-row { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); }
            .kpi { border: 1px solid var(--line); border-radius: 18px; padding: 14px 16px; background: rgba(255, 255, 255, 0.84); }
            .kpi .label { color: var(--muted); font-size: 13px; }
            .kpi .value { font-size: 24px; font-weight: 700; margin-top: 8px; }
            .section-title { margin: 0 0 8px; font-size: 20px; }
            .empty-state { text-align: center; padding: 34px 24px; }
            .empty-state h3 { margin: 12px 0 8px; font-size: 24px; }
            .empty-actions { display: flex; justify-content: center; gap: 12px; flex-wrap: wrap; margin-top: 18px; }
            .filter-pills { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; }
            .field-error { color: var(--danger); font-size: 13px; margin-top: 6px; }
            input:disabled, textarea:disabled, select:disabled { background: #f6efe4; color: #7b6c5c; }
            .identity-card {
                display: flex;
                flex-direction: column;
                gap: 4px;
                min-width: 190px;
                padding: 10px 14px;
                border-radius: 18px;
                background: rgba(255, 255, 255, 0.72);
                border: 1px solid rgba(102, 82, 56, 0.1);
                text-align: left;
            }
            .identity-card strong { font-size: 14px; }
            .identity-card span { color: var(--muted); font-size: 13px; }
            @media (max-width: 980px) {
                .shell { grid-template-columns: 1fr; padding: 12px; gap: 14px; }
                .sidebar { padding: 18px; border-radius: 22px; }
                .main { padding: 0 0 18px; }
                .form-grid, .split { grid-template-columns: 1fr; }
                .topbar {
                    position: static;
                    grid-template-columns: 1fr;
                    padding: 18px;
                    border-radius: 22px;
                }
                .page-head { flex-direction: column; align-items: flex-start; }
                .topbar-actions { justify-content: flex-start; }
                .topbar-search { width: 100%; max-width: none; }
                .global-search-form { flex-wrap: wrap; }
                .identity-card { width: 100%; }
            }
        </style>
    @endif
    <style>
        body {
            overflow: hidden;
        }
        .shell {
            height: 100vh;
            min-height: 100vh;
        }
        .sidebar {
            max-height: 100vh;
            overflow-y: auto;
            overflow-x: hidden;
            scrollbar-width: thin;
            scrollbar-color: #3ec7c9 #10242c;
        }
        .main {
            max-height: 100vh;
            overflow-y: auto;
            overflow-x: hidden;
            scrollbar-width: thin;
            scrollbar-color: #ca6702 #efe3d3;
        }
        .sidebar::-webkit-scrollbar {
            width: 11px;
        }
        .sidebar::-webkit-scrollbar-track {
            background: #10242c;
        }
        .sidebar::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, #2a9d8f 0%, #0ea5a4 100%);
            border-radius: 999px;
            border: 2px solid #10242c;
        }
        .sidebar::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(180deg, #35b6a7 0%, #22c1c3 100%);
        }
        .main::-webkit-scrollbar {
            width: 13px;
        }
        .main::-webkit-scrollbar-track {
            background: #efe3d3;
            border-left: 1px solid #decdb8;
        }
        .main::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, #ca6702 0%, #ee9b00 100%);
            border-radius: 999px;
            border: 3px solid #efe3d3;
        }
        .main::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(180deg, #d97706 0%, #f59e0b 100%);
        }
        @media (max-width: 980px) {
            body {
                overflow: auto;
            }
            .shell {
                height: auto;
                min-height: 100vh;
            }
            .sidebar,
            .main {
                max-height: none;
                overflow: visible;
            }
        }
    </style>
    <style>
        .shell-backdrop {
            display: none;
        }
        .mobile-menu-button {
            display: none;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
        }
        .mobile-menu-button .lines {
            display: inline-grid;
            gap: 4px;
        }
        .mobile-menu-button .lines span {
            display: block;
            width: 18px;
            height: 2px;
            border-radius: 999px;
            background: currentColor;
        }
        .no-js-warning {
            margin-bottom: 16px;
            padding: 14px 16px;
            border-radius: 16px;
            border: 1px solid rgba(154, 91, 0, 0.24);
            background: rgba(154, 91, 0, 0.08);
            color: var(--warning, #9a5b00);
            box-shadow: var(--shadow-soft, 0 18px 40px rgba(42, 28, 18, 0.08));
        }
        @supports not ((backdrop-filter: blur(14px)) or (-webkit-backdrop-filter: blur(14px))) {
            .topbar {
                background: rgba(255, 249, 241, 0.98);
            }
        }
        @media (max-width: 980px) {
            .mobile-menu-button {
                display: inline-flex;
            }
            .shell-backdrop {
                position: fixed;
                inset: 0;
                background: rgba(11, 27, 33, 0.48);
                opacity: 0;
                pointer-events: none;
                transition: opacity .18s ease;
                z-index: 10;
            }
            body.js-ready {
                overflow: hidden;
            }
            body.js-ready .shell {
                position: relative;
            }
            body.js-ready .sidebar {
                position: fixed;
                top: 12px;
                left: 12px;
                bottom: 12px;
                width: min(330px, calc(100vw - 24px));
                max-width: calc(100vw - 24px);
                max-height: calc(100vh - 24px);
                overflow-y: auto;
                z-index: 20;
                transform: translateX(-115%);
                transition: transform .2s ease;
                box-shadow: 0 28px 80px rgba(8, 20, 26, 0.42);
            }
            body.js-ready .shell[data-sidebar-open="true"] .sidebar {
                transform: translateX(0);
            }
            body.js-ready .shell[data-sidebar-open="true"] .shell-backdrop {
                display: block;
                opacity: 1;
                pointer-events: auto;
            }
        }
    </style>
    @stack('page-styles')
</head>
<body>
@php
    $layoutUser = auth()->user();
    $sectorProfileService = app(\App\Modules\Core\Company\Services\SectorProfileService::class);
    $sectorProfile = $workspace->companyId() ? $sectorProfileService->profileForCompany($workspace->companyId()) : $sectorProfileService->defaultProfile();
    $uiMode = session('ui_mode', 'full');
    $isMerchantMode = $uiMode === 'merchant';
    $globalSearchPlaceholder = $isMerchantMode
        ? 'Recherche simple : produit, client, ticket, paiement...'
        : 'Recherche globale : client, produit, vente, achat, paiement...';
@endphp
<div class="shell" data-layout-shell data-sidebar-open="false">
    <div class="shell-backdrop" data-sidebar-backdrop hidden></div>
    <aside class="sidebar" id="app-sidebar" aria-label="Navigation principale">
        <div class="brand">
            <h1>Nema ERP</h1>
            <small>Socle ERP PME maliennes</small>
        </div>
        @if ($isMerchantMode)
            @include('layouts.partials.sidebar-merchant')
        @else
            @include('layouts.partials.sidebar-full')
        @endif
    </aside>
    <main class="main">
        <noscript>
            <div class="no-js-warning">
                Certaines interactions rapides de l'ERP demandent JavaScript. La navigation reste disponible, mais le menu mobile et quelques aides visuelles seront limites.
            </div>
        </noscript>
        <div class="topbar">
            <div class="topbar-leading">
                <button
                    type="button"
                    class="button button-secondary mobile-menu-button"
                    data-sidebar-toggle
                    aria-controls="app-sidebar"
                    aria-expanded="false"
                    aria-label="Ouvrir le menu principal"
                >
                    <span class="lines" aria-hidden="true">
                        <span></span>
                        <span></span>
                        <span></span>
                    </span>
                    <span>Menu</span>
                </button>
                <div class="topbar-label">{{ $isMerchantMode ? 'Routine commerce' : 'Pilotage en temps reel' }}</div>
                <div class="topbar-title">@yield('page-title', 'Tableau de bord')</div>
                <div class="workspace">
                    <span class="workspace-pill">
                        Entreprise
                        <strong>{{ $workspace->company()?->name ?? 'Non definie' }}</strong>
                    </span>
                    @if ($workspace->branch())
                        <span class="workspace-pill">
                            Agence
                            <strong>{{ $workspace->branch()?->name }}</strong>
                        </span>
                    @endif
                </div>
            </div>
            <div class="topbar-actions">
                @allowed('dashboard.view')
                    <div class="topbar-search">
                        <form method="GET" action="{{ route('search.index') }}" class="global-search-form">
                            <input type="search" name="q" value="{{ request()->routeIs('search.index') ? request('q') : '' }}" placeholder="{{ $globalSearchPlaceholder }}">
                            <button class="button button-secondary" type="submit">Rechercher</button>
                        </form>
                    </div>
                @endallowed
                @allowed('approvals.view')
                    <a href="{{ route('approvals.index') }}" class="notification-pill">
                        <span>Approbations</span>
                        @if (($approvalSummary['count'] ?? 0) > 0)
                            <span class="notification-count">{{ $approvalSummary['count'] }}</span>
                        @else
                            <span class="badge badge-success">0</span>
                        @endif
                    </a>
                @endallowed
                @allowed('notifications.view')
                    <a href="{{ route('notifications.index') }}" class="notification-pill">
                        <span>Alertes</span>
                        @if (($notificationSummary['count'] ?? 0) > 0)
                            <span class="notification-count">{{ $notificationSummary['count'] }}</span>
                        @else
                            <span class="badge badge-success">0</span>
                        @endif
                    </a>
                @endallowed
                <form method="POST" action="{{ route('ui-mode.update') }}">
                    @csrf
                    <input type="hidden" name="mode" value="{{ $isMerchantMode ? 'full' : 'merchant' }}">
                    <button class="button button-secondary" type="submit">{{ $isMerchantMode ? 'Mode complet' : 'Mode commercant' }}</button>
                </form>
                <div class="identity-card">
                    <strong>{{ auth()->user()?->name }}</strong>
                    <span>{{ auth()->user()?->email }}</span>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="button button-secondary" type="submit">Deconnexion</button>
                </form>
            </div>
        </div>

        @include('partials.flash')
        @include('layouts.partials.merchant-quick-actions')

        @yield('content')
    </main>
</div>
<script>
(function () {
    if (window.__nemaLayoutBooted) {
        return;
    }

    window.__nemaLayoutBooted = true;

    const boot = () => {
        const shell = document.querySelector('[data-layout-shell]');
        const sidebar = document.getElementById('app-sidebar');
        const toggle = document.querySelector('[data-sidebar-toggle]');
        const backdrop = document.querySelector('[data-sidebar-backdrop]');

        if (!shell || !sidebar || !toggle || !backdrop) {
            return;
        }

        const mobileQuery = window.matchMedia('(max-width: 980px)');
        const setOpen = (open) => {
            if (!mobileQuery.matches) {
                shell.dataset.sidebarOpen = 'false';
                toggle.setAttribute('aria-expanded', 'false');
                toggle.setAttribute('aria-label', 'Ouvrir le menu principal');
                document.body.classList.remove('js-ready');
                backdrop.hidden = true;
                return;
            }

            document.body.classList.add('js-ready');
            shell.dataset.sidebarOpen = open ? 'true' : 'false';
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.setAttribute('aria-label', open ? 'Fermer le menu principal' : 'Ouvrir le menu principal');
            backdrop.hidden = !open;
        };

        const toggleSidebar = () => setOpen(shell.dataset.sidebarOpen !== 'true');
        const closeSidebar = () => setOpen(false);

        toggle.addEventListener('click', toggleSidebar);
        backdrop.addEventListener('click', closeSidebar);
        sidebar.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', () => {
                if (mobileQuery.matches) {
                    closeSidebar();
                }
            });
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeSidebar();
            }
        });

        const handleViewportChange = () => {
            if (mobileQuery.matches) {
                document.body.classList.add('js-ready');
            } else {
                document.body.classList.remove('js-ready');
            }

            closeSidebar();
        };

        if (typeof mobileQuery.addEventListener === 'function') {
            mobileQuery.addEventListener('change', handleViewportChange);
        } else if (typeof mobileQuery.addListener === 'function') {
            mobileQuery.addListener(handleViewportChange);
        }

        handleViewportChange();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
}());
</script>
</body>
</html>
