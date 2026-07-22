<!DOCTYPE html>
<html lang="fr">
<head>
    @include('partials.security-csp-meta')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="product-options-url" content="{{ route('products.options') }}">
    <meta name="color-scheme" content="light">
    <meta name="theme-color" content="#f4ede2">
    <link rel="icon" type="image/png" href="{{ asset('images/nema-technologies-mark.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/nema-technologies-mark.png') }}">
    @if (request()->routeIs('pos.*'))
        <meta name="theme-color" content="#102730">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <link rel="manifest" href="{{ asset('pos-manifest.webmanifest') }}">
    @else
        <link rel="manifest" href="{{ asset('app-manifest.webmanifest') }}">
    @endif
    <title>@yield('title', config('app.name'))</title>
    @if (file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @elseif (file_exists(public_path('build/manifest.json')))
        @php
            $viteManifest = json_decode(file_get_contents(public_path('build/manifest.json')), true) ?: [];
            $cssEntry = $viteManifest['resources/css/app.css']['file'] ?? null;
            $jsEntry = $viteManifest['resources/js/app.js']['file'] ?? null;
        @endphp
        @if ($cssEntry)
            <link rel="stylesheet" href="{{ url('build/'.$cssEntry) }}">
        @endif
        @if ($jsEntry)
            <script type="module" src="{{ url('build/'.$jsEntry) }}"></script>
        @endif
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
                font-size: 14px;
                line-height: 1.5;
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
                grid-template-columns: 260px minmax(0, 1fr);
                gap: 20px;
                min-height: 100vh;
                padding: 18px;
                position: relative;
                z-index: 1;
                transition: grid-template-columns .18s ease, gap .18s ease, padding .18s ease;
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
                min-width: 0;
                transition: width .18s ease, padding .18s ease, border-radius .18s ease, transform .2s ease;
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
                font-size: 24px;
                line-height: 1;
                letter-spacing: .02em;
            }
            .brand small {
                color: #b9d4d4;
                display: block;
                margin-top: 8px;
                font-size: 11px;
                letter-spacing: .04em;
                text-transform: uppercase;
            }
            .nav-title {
                margin: 22px 12px 10px;
                font-size: 12px;
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
                transition: padding .18s ease, margin .18s ease, border-radius .18s ease;
            }
            .topbar-leading { display: grid; gap: 8px; }
            .topbar-label {
                font-size: 12px;
                font-weight: 700;
                letter-spacing: .22em;
                text-transform: uppercase;
                color: var(--accent);
            }
            .topbar-title {
                font-size: clamp(22px, 2.4vw, 28px);
                font-weight: 800;
                line-height: 1.05;
                letter-spacing: -.03em;
            }
            .workspace {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                color: var(--muted);
                font-size: 13px;
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
            .module-pill {
                display: flex;
                align-items: stretch;
                gap: 8px;
                flex-wrap: nowrap;
            }
            .module-pill form {
                margin: 0;
            }
            .module-favorite-button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 42px;
                min-width: 42px;
                padding: 0;
                border-radius: 999px;
            }
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
                font-size: 13px;
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
            .stat-value { font-size: 28px; font-weight: 800; margin-top: 12px; letter-spacing: -.03em; }
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
                font-size: 12px;
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
                font-size: 13px;
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
            .help { color: var(--muted); font-size: 12px; }
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
            .notification-count { display: inline-flex; min-width: 24px; height: 24px; border-radius: 999px; align-items: center; justify-content: center; padding: 0 8px; background: #b42318; color: #fff; font-size: 11px; }
            .tip-grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); }
            .tip-card { border: 1px solid var(--line); background: rgba(255, 255, 255, 0.72); border-radius: 18px; padding: 14px 16px; }
            .tip-card strong { display: block; margin-bottom: 6px; }
            .summary-stack { display: grid; gap: 12px; }
            .summary-box { border: 1px solid var(--line); background: rgba(255, 255, 255, 0.8); border-radius: 18px; padding: 14px 16px; }
            .summary-box .value { font-size: 24px; font-weight: 700; margin-top: 6px; }
            .summary-list { margin: 0; padding-left: 18px; display: grid; gap: 6px; color: var(--muted); }
            .chip-row { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px; }
            .chip {
                border: 1px solid rgba(120, 99, 74, 0.14);
                background: rgba(255, 255, 255, 0.82);
                color: var(--text);
                border-radius: 999px;
                padding: 8px 12px;
                font-size: 12px;
                cursor: pointer;
                box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.92);
            }
            .chip:hover { background: #f9f3ea; }
            .table-foot-note { display: flex; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-top: 16px; }
            .erp-view-switcher {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 4px;
                border-radius: 16px;
                border: 1px solid rgba(102, 82, 56, 0.12);
                background: rgba(255, 255, 255, 0.82);
                box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.92);
            }
            .erp-view-switcher__link {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 84px;
                padding: 8px 12px;
                border-radius: 10px;
                font-size: 13px;
                font-weight: 700;
                color: var(--muted);
            }
            .erp-view-switcher__link.is-active {
                background: var(--brand);
                color: #fff;
                box-shadow: 0 12px 24px rgba(11, 79, 86, 0.16);
            }
            .erp-kanban-grid {
                display: grid;
                gap: 18px;
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            }
            .erp-kanban-card {
                display: grid;
                gap: 14px;
                border: 1px solid rgba(102, 82, 56, 0.1);
                background: linear-gradient(180deg, rgba(255, 253, 249, 0.98) 0%, rgba(247, 239, 228, 0.92) 100%);
            }
            .erp-kanban-card--success {
                border-color: rgba(23, 107, 77, 0.26);
                box-shadow: 0 16px 32px rgba(23, 107, 77, 0.08);
            }
            .erp-kanban-card--warning {
                border-color: rgba(154, 91, 0, 0.26);
                box-shadow: 0 16px 32px rgba(154, 91, 0, 0.08);
            }
            .erp-kanban-card--danger {
                border-color: rgba(180, 35, 24, 0.24);
                box-shadow: 0 16px 32px rgba(180, 35, 24, 0.08);
            }
            .erp-kanban-head {
                display: flex;
                justify-content: space-between;
                gap: 12px;
                align-items: flex-start;
            }
            .erp-kanban-head h3 {
                margin: 0;
                font-size: 18px;
                letter-spacing: -.02em;
            }
            .erp-kanban-code {
                color: var(--muted);
                font-size: 12px;
                text-transform: uppercase;
                letter-spacing: .12em;
                font-weight: 800;
            }
            .erp-kanban-copy {
                display: grid;
                gap: 6px;
            }
            .erp-kanban-copy p {
                margin: 0;
            }
            .erp-kanban-stats {
                display: grid;
                gap: 10px;
                grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            }
            .erp-kanban-stat {
                padding: 12px 14px;
                border-radius: 16px;
                border: 1px solid rgba(102, 82, 56, 0.08);
                background: rgba(255, 255, 255, 0.7);
            }
            .erp-kanban-stat .label {
                color: var(--muted);
                font-size: 12px;
                text-transform: uppercase;
                letter-spacing: .12em;
                font-weight: 800;
            }
            .erp-kanban-stat .value {
                margin-top: 8px;
                font-size: 22px;
                font-weight: 800;
                letter-spacing: -.03em;
            }
            .erp-kanban-actions {
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
            }
            .kpi-row { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); }
            .kpi { border: 1px solid var(--line); border-radius: 18px; padding: 14px 16px; background: rgba(255, 255, 255, 0.84); }
            .kpi .label { color: var(--muted); font-size: 12px; }
            .kpi .value { font-size: 22px; font-weight: 700; margin-top: 8px; }
            .section-title { margin: 0 0 8px; font-size: 18px; }
            .empty-state { text-align: center; padding: 34px 24px; }
            .empty-state h3 { margin: 12px 0 8px; font-size: 22px; }
            .empty-actions { display: flex; justify-content: center; gap: 12px; flex-wrap: wrap; margin-top: 18px; }
            .filter-pills { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; }
            .field-error { color: var(--danger); font-size: 12px; margin-top: 6px; }
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
            .identity-card strong { font-size: 13px; }
            .identity-card span { color: var(--muted); font-size: 12px; }
            .layout-control-row {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
            }
            .layout-icon-button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 42px;
                min-width: 42px;
                height: 42px;
                padding: 0;
                border-radius: 14px;
                font-weight: 900;
                line-height: 1;
            }
            .layout-focus-button[data-focus-active="true"] {
                background: var(--brand);
                color: #fff;
                border-color: rgba(15, 118, 110, .38);
            }
            .sidebar-toggle-desktop {
                position: sticky;
                top: 10px;
                z-index: 3;
                display: inline-flex;
                width: 34px;
                height: 34px;
                align-items: center;
                justify-content: center;
                margin: 0 0 12px auto;
                border: 1px solid rgba(255,255,255,.12);
                border-radius: 12px;
                color: #f8fafc;
                background: rgba(255,255,255,.08);
                cursor: pointer;
            }
            .shell[data-sidebar-state="collapsed"] {
                grid-template-columns: 64px minmax(0, 1fr);
                gap: 14px;
            }
            .shell[data-sidebar-state="collapsed"] .sidebar {
                width: 64px;
                padding: 14px 8px;
                border-radius: 22px;
            }
            .shell[data-sidebar-state="collapsed"] .brand {
                padding: 0 0 12px;
                margin-bottom: 12px;
                text-align: center;
            }
            .shell[data-sidebar-state="collapsed"] .brand-kicker,
            .shell[data-sidebar-state="collapsed"] .brand-mark__copy,
            .shell[data-sidebar-state="collapsed"] .sidebar-overview,
            .shell[data-sidebar-state="collapsed"] .nav-title,
            .shell[data-sidebar-state="collapsed"] .nav-link strong,
            .shell[data-sidebar-state="collapsed"] .nav-link small {
                display: none !important;
            }
            .shell[data-sidebar-state="collapsed"] .brand-mark {
                justify-content: center;
            }
            .shell[data-sidebar-state="collapsed"] .brand-mark__glyph {
                width: 38px;
                height: 38px;
                font-size: 20px;
            }
            .shell[data-sidebar-state="collapsed"] .sidebar-toggle-desktop {
                margin-right: auto;
            }
            .shell[data-sidebar-state="collapsed"] .nav-link {
                justify-content: center;
                width: 46px;
                min-height: 46px;
                padding: 8px;
                margin: 0 auto 8px;
                gap: 0;
                border-radius: 15px;
            }
            .shell[data-sidebar-state="collapsed"] .nav-link::before {
                display: none;
            }
            .shell[data-sidebar-state="collapsed"] .nav-link > span:first-child {
                width: 32px !important;
                height: 32px !important;
            }
            .shell[data-sidebar-state="collapsed"] .nav-section {
                margin: 0 0 12px;
            }
            .shell[data-layout-mode="compact"] .topbar,
            .shell[data-focus-active="true"] .topbar {
                min-height: 56px;
                grid-template-columns: minmax(0, 1fr) auto;
                align-items: center;
                gap: 12px;
                margin-bottom: 14px;
                padding: 8px 12px;
                border-radius: 18px;
            }
            .shell[data-layout-mode="compact"] .topbar-leading,
            .shell[data-focus-active="true"] .topbar-leading {
                display: flex;
                align-items: center;
                gap: 10px;
                min-width: 0;
            }
            .shell[data-layout-mode="compact"] .topbar-label,
            .shell[data-layout-mode="compact"] .workspace,
            .shell[data-layout-mode="compact"] .topbar-leading > div[style*="flex-wrap"],
            .shell[data-focus-active="true"] .topbar-label,
            .shell[data-focus-active="true"] .workspace,
            .shell[data-focus-active="true"] .topbar-leading > div[style*="flex-wrap"] {
                display: none !important;
            }
            .shell[data-layout-mode="compact"] .topbar-title,
            .shell[data-focus-active="true"] .topbar-title {
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                font-size: 18px;
                line-height: 1.1;
            }
            .shell[data-layout-mode="compact"] .topbar-actions,
            .shell[data-focus-active="true"] .topbar-actions {
                gap: 8px;
                flex-wrap: nowrap;
            }
            .shell[data-layout-mode="compact"] .topbar-search,
            .shell[data-focus-active="true"] .topbar-search {
                display: none;
            }
            .shell[data-layout-mode="compact"] .identity-card,
            .shell[data-focus-active="true"] .identity-card {
                min-width: auto;
                padding: 8px 10px;
            }
            .shell[data-layout-mode="compact"] .identity-card span,
            .shell[data-focus-active="true"] .identity-card span,
            .shell[data-layout-mode="compact"] .notification-pill span:first-child,
            .shell[data-focus-active="true"] .notification-pill span:first-child {
                display: none;
            }
            .shell[data-focus-active="true"] {
                grid-template-columns: 64px minmax(0, 1fr);
                gap: 10px;
                padding: 10px;
            }
            .shell[data-focus-active="true"] .main {
                padding-right: 0;
            }
            .shell[data-focus-active="true"][data-sidebar-state="hidden"] {
                grid-template-columns: minmax(0, 1fr);
            }
            .shell[data-focus-active="true"][data-sidebar-state="hidden"] .sidebar {
                display: none;
            }
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
        @media print {
            @page { size: A4; margin: 12mm; }
            body { background: #fff !important; color: #111 !important; }
            .sidebar,
            .shell-backdrop,
            .topbar,
            .erp-module-bar,
            .alert,
            .premium-anchor-grid,
            .actions,
            .button,
            button,
            form,
            [data-sidebar-toggle],
            [data-sidebar-collapse-toggle] {
                display: none !important;
            }
            .shell,
            .main,
            .premium-detail-page,
            .pos-session {
                display: block !important;
                width: 100% !important;
                max-width: none !important;
                min-width: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: visible !important;
            }
            .card,
            .premium-detail-hero,
            .premium-detail-panel,
            .premium-stat-card,
            .pos-session-hero,
            .pos-session-panel,
            .pos-stat-card,
            .pos-session-quick-card {
                color: #111 !important;
                background: #fff !important;
                box-shadow: none !important;
                border-color: #bbb !important;
                break-inside: avoid;
            }
            .muted,
            .help,
            .hint,
            .pos-history-meta { color: #444 !important; }
            .table-wrap { overflow: visible !important; }
            table { width: 100% !important; font-size: 10pt; }
            a { color: #111 !important; text-decoration: none !important; }
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
        .shell {
            grid-template-columns: 260px minmax(0, 1fr);
            transition: grid-template-columns .18s ease, gap .18s ease, padding .18s ease;
        }
        .sidebar,
        .topbar {
            transition: width .18s ease, min-height .18s ease, padding .18s ease, transform .2s ease;
        }
        .layout-control-row {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex-wrap: nowrap;
        }
        .layout-icon-button {
            width: 42px;
            min-width: 42px;
            height: 42px;
            padding: 0;
            justify-content: center;
            border-radius: 14px;
        }
        .layout-menu-glyph {
            display: inline-grid;
            gap: 4px;
        }
        .layout-menu-glyph span {
            display: block;
            width: 17px;
            height: 2px;
            border-radius: 999px;
            background: currentColor;
        }
        .layout-focus-button[data-focus-active="true"] {
            background: rgba(14, 115, 115, 0.14);
            border-color: rgba(14, 115, 115, 0.28);
            color: #075e5e;
        }
        .sidebar-toggle-desktop {
            position: absolute;
            top: 14px;
            right: 14px;
            z-index: 2;
        }
        .shell[data-sidebar-state="collapsed"] {
            grid-template-columns: 64px minmax(0, 1fr);
            gap: 14px;
        }
        .shell[data-sidebar-state="collapsed"] .sidebar {
            width: 64px;
            min-width: 64px;
            padding: 14px 8px;
        }
        .shell[data-sidebar-state="collapsed"] .brand {
            padding: 0;
            min-height: 50px;
            align-items: center;
        }
        .shell[data-sidebar-state="collapsed"] .brand-kicker,
        .shell[data-sidebar-state="collapsed"] .brand-mark__copy,
        .shell[data-sidebar-state="collapsed"] .sidebar-overview,
        .shell[data-sidebar-state="collapsed"] .nav-title,
        .shell[data-sidebar-state="collapsed"] .nav-link strong,
        .shell[data-sidebar-state="collapsed"] .nav-link small {
            display: none !important;
        }
        .shell[data-sidebar-state="collapsed"] .brand-mark {
            justify-content: center;
        }
        .shell[data-sidebar-state="collapsed"] .brand-mark__glyph {
            width: 42px;
            height: 42px;
            font-size: 18px;
        }
        .shell[data-sidebar-state="collapsed"] .sidebar-toggle-desktop {
            position: static;
            margin: 0 auto 10px;
        }
        .shell[data-sidebar-state="collapsed"] .nav-link {
            width: 46px;
            height: 46px;
            min-height: 46px;
            padding: 0;
            justify-content: center;
            gap: 0;
        }
        .shell[data-sidebar-state="collapsed"] .nav-link::before {
            display: none;
        }
        .shell[data-sidebar-state="collapsed"] .nav-link > span:first-child {
            width: 18px;
            text-align: center;
        }
        .shell[data-sidebar-state="collapsed"] .nav-section {
            gap: 8px;
        }
        .shell[data-layout-mode="compact"] .topbar,
        .shell[data-focus-active="true"] .topbar {
            min-height: 50px;
            padding: 5px 10px;
            border-radius: 14px;
            align-items: center;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 8px;
            margin-bottom: 8px;
        }
        .shell[data-layout-mode="compact"] .topbar-leading,
        .shell[data-focus-active="true"] .topbar-leading {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 0;
        }
        .shell[data-layout-mode="compact"] .layout-icon-button,
        .shell[data-focus-active="true"] .layout-icon-button {
            width: 34px;
            min-width: 34px;
            height: 34px;
            border-radius: 11px;
        }
        .shell[data-layout-mode="compact"] .layout-menu-glyph,
        .shell[data-focus-active="true"] .layout-menu-glyph {
            gap: 3px;
        }
        .shell[data-layout-mode="compact"] .layout-menu-glyph span,
        .shell[data-focus-active="true"] .layout-menu-glyph span {
            width: 15px;
        }
        .shell[data-layout-mode="compact"] .topbar-label,
        .shell[data-layout-mode="compact"] .workspace,
        .shell[data-layout-mode="compact"] .topbar-leading > div[style*="flex-wrap"],
        .shell[data-focus-active="true"] .topbar-label,
        .shell[data-focus-active="true"] .workspace,
        .shell[data-focus-active="true"] .topbar-leading > div[style*="flex-wrap"] {
            display: none !important;
        }
        .shell[data-layout-mode="compact"] .topbar-title,
        .shell[data-focus-active="true"] .topbar-title {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 16px;
            line-height: 1.1;
        }
        .shell[data-layout-mode="compact"] .topbar-actions,
        .shell[data-focus-active="true"] .topbar-actions {
            gap: 6px;
            flex-wrap: nowrap;
        }
        .shell[data-layout-mode="compact"] .topbar-actions .button,
        .shell[data-focus-active="true"] .topbar-actions .button,
        .shell[data-layout-mode="compact"] .notification-pill,
        .shell[data-focus-active="true"] .notification-pill,
        .shell[data-layout-mode="compact"] .identity-card,
        .shell[data-focus-active="true"] .identity-card {
            min-height: 34px;
            padding: 6px 9px;
            border-radius: 12px;
            font-size: 12px;
        }
        .shell[data-focus-active="true"] .topbar-actions {
            display: none;
        }
        .shell[data-layout-mode="compact"] .layout-mode-switch,
        .shell[data-layout-mode="compact"] .identity-card {
            display: none;
        }
        .shell[data-layout-mode="compact"] .topbar-search,
        .shell[data-focus-active="true"] .topbar-search {
            display: none;
        }
        .shell[data-layout-mode="compact"] .identity-card,
        .shell[data-focus-active="true"] .identity-card {
            min-width: auto;
            padding: 8px 10px;
        }
        .shell[data-layout-mode="compact"] .identity-card span,
        .shell[data-focus-active="true"] .identity-card span,
        .shell[data-layout-mode="compact"] .notification-pill span:first-child,
        .shell[data-focus-active="true"] .notification-pill span:first-child {
            display: none;
        }
        .shell[data-focus-active="true"] {
            grid-template-columns: 64px minmax(0, 1fr);
            gap: 10px;
            padding: 10px;
        }
        .shell[data-focus-active="true"] .main {
            padding-right: 0;
        }
        .shell[data-focus-active="true"][data-sidebar-state="hidden"] {
            grid-template-columns: minmax(0, 1fr);
        }
        .shell[data-focus-active="true"][data-sidebar-state="hidden"] .sidebar {
            display: none;
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
            .sidebar-toggle-desktop {
                display: none;
            }
            .shell,
            .shell[data-sidebar-state="collapsed"],
            .shell[data-focus-active="true"],
            .shell[data-focus-active="true"][data-sidebar-state="hidden"] {
                grid-template-columns: minmax(0, 1fr) !important;
                width: 100%;
                padding: 12px;
                gap: 14px;
            }
            .main {
                width: 100%;
                min-width: 0;
            }
            .shell[data-sidebar-state="collapsed"] .sidebar,
            .shell[data-focus-active="true"] .sidebar,
            .shell[data-focus-active="true"][data-sidebar-state="hidden"] .sidebar {
                display: block;
                width: min(330px, calc(100vw - 24px));
                min-width: 0;
                padding: 18px;
            }
            .shell[data-sidebar-state="collapsed"] .brand-kicker,
            .shell[data-sidebar-state="collapsed"] .brand-mark__copy,
            .shell[data-sidebar-state="collapsed"] .sidebar-overview,
            .shell[data-sidebar-state="collapsed"] .nav-title,
            .shell[data-sidebar-state="collapsed"] .nav-link strong,
            .shell[data-sidebar-state="collapsed"] .nav-link small {
                display: initial !important;
            }
            .shell[data-sidebar-state="collapsed"] .nav-title,
            .shell[data-sidebar-state="collapsed"] .nav-link strong,
            .shell[data-sidebar-state="collapsed"] .nav-link small,
            .shell[data-sidebar-state="collapsed"] .brand-kicker {
                display: block !important;
            }
            .shell[data-sidebar-state="collapsed"] .brand-mark__copy,
            .shell[data-sidebar-state="collapsed"] .sidebar-overview {
                display: grid !important;
            }
            .shell[data-sidebar-state="collapsed"] .nav-link {
                width: auto;
                height: auto;
                min-height: 0;
                padding: 12px 14px;
                justify-content: flex-start;
                gap: 12px;
            }
            .shell[data-layout-mode="compact"] .topbar,
            .shell[data-focus-active="true"] .topbar {
                grid-template-columns: 1fr;
                align-items: start;
            }
            .shell[data-layout-mode="compact"] .topbar-leading,
            .shell[data-focus-active="true"] .topbar-leading {
                width: 100%;
                flex-wrap: wrap;
            }
            .shell[data-layout-mode="compact"] .topbar-actions,
            .shell[data-focus-active="true"] .topbar-actions {
                width: 100%;
                justify-content: flex-start;
                flex-wrap: wrap;
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
            body.js-ready.sidebar-overlay-open {
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
    <style>
        .brand-mark__logo {
            object-fit: contain;
            padding: 6px;
            background: #ffffff;
        }
    </style>
    @stack('page-styles')
    <link rel="stylesheet" href="{{ asset('css/product-picker.css') }}">
    <link rel="stylesheet" href="{{ asset('css/erp-compact.css') }}?v=20260721-7">
</head>
<body>
@php
    $layoutUser = auth()->user();
    $sectorProfileService = app(\App\Modules\Core\Company\Services\SectorProfileService::class);
    $sectorProfile = $workspace->companyId() ? $sectorProfileService->profileForCompany($workspace->companyId()) : $sectorProfileService->defaultProfile();
    $uiMode = session('ui_mode', 'full');
    $isMerchantMode = $uiMode === 'merchant';
    $hideGlobalShortcuts = trim($__env->yieldContent('hide-global-shortcuts')) === '1';
    $pageTitle = trim($__env->yieldContent('page-title', 'Tableau de bord'));
    $declaredLayoutMode = trim($__env->yieldContent('layout-mode', ''));
    $focusRoutePatterns = [
        'pos.*',
        'orders.*',
        'stock.*',
        'stock-counts.*',
        'warehouse-transfers.*',
        'inventory.*',
        'purchases.*',
        'purchase-requests.*',
        'purchase-orders.*',
        'sales.*',
        'reports.*',
    ];
    $layoutMode = in_array($declaredLayoutMode, ['normal', 'compact', 'focus'], true)
        ? $declaredLayoutMode
        : (request()->routeIs(...$focusRoutePatterns) ? 'compact' : 'normal');
    $focusAvailable = $layoutMode !== 'normal' || request()->routeIs(...$focusRoutePatterns);
    $globalSearchPlaceholder = $isMerchantMode
        ? 'Recherche simple : produit, client, ticket, paiement...'
        : 'Recherche globale : client, produit, vente, achat, paiement...';
    $layoutBreadcrumbs = $erpNavigation['breadcrumbs'] ?? [];
    $printableDetail = request()->routeIs(
        'sales.show',
        'quotes.show',
        'orders.show',
        'delivery-notes.show',
        'credit-notes.show',
        'purchases.show',
        'purchase-credit-notes.show',
        'purchase-orders.show',
        'goods-receipts.show',
        'payments.show',
        'pos.show'
    );
    if ($pageTitle !== '' && ($layoutBreadcrumbs === [] || ($layoutBreadcrumbs[array_key_last($layoutBreadcrumbs)]['label'] ?? null) !== $pageTitle)) {
        $layoutBreadcrumbs[] = ['label' => $pageTitle, 'url' => null];
    }
@endphp
<div
    class="shell"
    data-layout-shell
    data-layout-mode="{{ $layoutMode }}"
    data-focus-available="{{ $focusAvailable ? 'true' : 'false' }}"
    data-focus-active="{{ $layoutMode === 'focus' ? 'true' : 'false' }}"
    data-sidebar-state="expanded"
    data-sidebar-open="false"
>
    <div class="shell-backdrop" data-sidebar-backdrop hidden></div>
    <aside class="sidebar" id="app-sidebar" aria-label="Navigation principale">
        <button
            type="button"
            class="button button-secondary layout-icon-button sidebar-toggle-desktop"
            data-sidebar-collapse-toggle
            aria-controls="app-sidebar"
            aria-label="Reduire le menu lateral"
            title="Reduire le menu lateral"
        >
            <span class="layout-menu-glyph" aria-hidden="true">
                <span></span>
                <span></span>
                <span></span>
            </span>
        </button>
        <div class="brand">
            <div class="brand-kicker">Nema Suite</div>
            <div class="brand-mark">
                <img class="brand-mark__glyph brand-mark__logo" src="{{ asset('images/nema-technologies-mark.png') }}" alt="Nema Technologies">
                <div class="brand-mark__copy">
                    <h1>Nema ERP</h1>
                    <small>Socle ERP PME maliennes</small>
                </div>
            </div>
        </div>
        <section class="sidebar-overview">
            <div class="sidebar-badge-row">
                <span class="sidebar-badge">{{ $isMerchantMode ? 'Mode commercant' : 'Mode complet' }}</span>
                <span class="sidebar-badge sidebar-badge--soft">{{ $sectorProfile['label'] ?? 'Profil metier' }}</span>
            </div>
            <div class="sidebar-overview__grid">
                <div class="sidebar-overview__item">
                    <span>Entreprise</span>
                    <strong>{{ $workspace->company()?->name ?? 'Non definie' }}</strong>
                </div>
                @if ($workspace->branch())
                    <div class="sidebar-overview__item">
                        <span>Agence</span>
                        <strong>{{ $workspace->branch()?->name }}</strong>
                    </div>
                @endif
                <div class="sidebar-overview__item">
                    <span>Compte</span>
                    <strong>{{ $layoutUser?->name ?? 'Utilisateur' }}</strong>
                </div>
            </div>
        </section>
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
                <div class="layout-control-row">
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
                    <button
                        type="button"
                        class="button button-secondary layout-icon-button"
                        data-sidebar-collapse-toggle
                        aria-controls="app-sidebar"
                        aria-label="Reduire le menu lateral"
                        title="Menu"
                    >
                        <span class="layout-menu-glyph" aria-hidden="true">
                            <span></span>
                            <span></span>
                            <span></span>
                        </span>
                    </button>
                    @if ($focusAvailable)
                        <button
                            type="button"
                            class="button button-secondary layout-icon-button layout-focus-button"
                            data-focus-toggle
                            data-focus-active="{{ $layoutMode === 'focus' ? 'true' : 'false' }}"
                            aria-pressed="{{ $layoutMode === 'focus' ? 'true' : 'false' }}"
                            aria-label="Activer le mode focus"
                            title="Mode focus"
                        >
                            F
                        </button>
                    @endif
                </div>
                <div class="topbar-label">{{ $isMerchantMode ? 'Routine commerce' : 'Pilotage en temps reel' }}</div>
                @if (! $isMerchantMode && ! empty($layoutBreadcrumbs))
                    <div style="display:flex; flex-wrap:wrap; gap:8px; align-items:center; color:var(--muted); font-size:12px;">
                        @foreach ($layoutBreadcrumbs as $breadcrumb)
                            @if (! $loop->first)
                                <span aria-hidden="true">/</span>
                            @endif
                            @if (! empty($breadcrumb['url']) && ! $loop->last)
                                <a href="{{ $breadcrumb['url'] }}" style="font-weight:700;">{{ $breadcrumb['label'] }}</a>
                            @else
                                <span style="{{ $loop->last ? 'font-weight:800; color:var(--brand-deep);' : 'font-weight:700;' }}">{{ $breadcrumb['label'] }}</span>
                            @endif
                        @endforeach
                    </div>
                @endif
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
                @if ($printableDetail)
                    <button type="button" class="button button-secondary" onclick="window.print()">Imprimer</button>
                @endif
                @allowed('dashboard.view')
                    <div class="topbar-search">
                        <form method="GET" action="{{ route('search.index') }}" class="global-search-form">
                            <input id="global_search" type="search" name="q" value="{{ request()->routeIs('search.index') ? request('q') : '' }}" placeholder="{{ $globalSearchPlaceholder }}" aria-label="Recherche globale dans l ERP">
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
                <form method="POST" action="{{ route('ui-mode.update') }}" class="layout-mode-switch">
                    @csrf
                    <input type="hidden" name="mode" value="{{ $isMerchantMode ? 'full' : 'merchant' }}">
                    <button class="button button-secondary" type="submit">{{ $isMerchantMode ? 'Mode complet' : 'Mode commercant' }}</button>
                </form>
                <div class="identity-card">
                    <strong>{{ auth()->user()?->name }}</strong>
                    <span>{{ auth()->user()?->email }}</span>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="layout-logout">
                    @csrf
                    <button class="button button-secondary" type="submit">Deconnexion</button>
                </form>
            </div>
        </div>

        @include('partials.flash')

        @if (! $hideGlobalShortcuts && ! $isMerchantMode && ! empty($erpNavigation['modules'] ?? []))
            <nav class="erp-module-bar" aria-label="Applications ERP">
                @foreach (($erpNavigation['modules'] ?? []) as $module)
                    <a
                        href="{{ $module['url'] }}"
                        class="erp-module-bar__link {{ $module['active'] ? 'is-active' : '' }}"
                        aria-current="{{ $module['active'] ? 'page' : 'false' }}"
                    >
                        @include('dashboard.partials.icon', ['name' => $module['icon'] ?? 'grid', 'size' => 15])
                        <span>{{ $module['label'] }}</span>
                    </a>
                @endforeach
            </nav>
        @endif

        @if (! $hideGlobalShortcuts)
            @include('layouts.partials.merchant-quick-actions')
        @endif

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
        const mobileToggle = document.querySelector('[data-sidebar-toggle]');
        const collapseToggles = Array.from(document.querySelectorAll('[data-sidebar-collapse-toggle]'));
        const focusToggle = document.querySelector('[data-focus-toggle]');
        const backdrop = document.querySelector('[data-sidebar-backdrop]');

        if (!shell || !sidebar || !mobileToggle || !backdrop) {
            return;
        }

        const mobileQuery = window.matchMedia('(max-width: 980px)');
        const sidebarStorageKey = 'nema.erp.sidebar.state';
        const focusStorageKey = 'nema.erp.layout.focus';
        const layoutMode = shell.dataset.layoutMode || 'normal';
        const focusAvailable = shell.dataset.focusAvailable === 'true';
        const storedSidebarState = (() => {
            try {
                const value = window.localStorage.getItem(sidebarStorageKey);
                return ['expanded', 'collapsed', 'hidden'].includes(value) ? value : null;
            } catch (error) {
                return null;
            }
        })();

        let sidebarState = storedSidebarState || (layoutMode === 'normal' ? 'expanded' : 'collapsed');
        let focusActive = shell.dataset.focusActive === 'true';

        try {
            const storedFocus = window.localStorage.getItem(focusStorageKey);
            if (focusAvailable && storedFocus !== null && layoutMode !== 'focus') {
                focusActive = storedFocus === 'true';
            }
        } catch (error) {
            focusActive = shell.dataset.focusActive === 'true';
        }

        if (focusActive && sidebarState === 'expanded') {
            sidebarState = 'collapsed';
        } else if (!focusActive && sidebarState === 'hidden') {
            sidebarState = 'collapsed';
        }

        const saveSidebarState = () => {
            try {
                window.localStorage.setItem(sidebarStorageKey, sidebarState);
            } catch (error) {
                // localStorage can be unavailable in private or restricted contexts.
            }
        };

        const saveFocusState = () => {
            try {
                window.localStorage.setItem(focusStorageKey, focusActive ? 'true' : 'false');
            } catch (error) {
                // localStorage can be unavailable in private or restricted contexts.
            }
        };

        const updateControls = () => {
            const isMobile = mobileQuery.matches;
            const visibleSidebarState = isMobile ? 'expanded' : sidebarState;

            shell.dataset.sidebarState = visibleSidebarState;
            shell.dataset.focusActive = focusActive && focusAvailable ? 'true' : 'false';

            collapseToggles.forEach((button) => {
                const opensHiddenSidebar = focusActive && sidebarState === 'hidden';
                button.setAttribute('aria-expanded', visibleSidebarState === 'expanded' ? 'true' : 'false');
                button.setAttribute(
                    'aria-label',
                    isMobile || opensHiddenSidebar ? 'Ouvrir le menu lateral' : (visibleSidebarState === 'expanded' ? 'Reduire le menu lateral' : 'Ouvrir le menu lateral')
                );
                button.title = isMobile || opensHiddenSidebar ? 'Menu' : (visibleSidebarState === 'expanded' ? 'Reduire le menu' : 'Ouvrir le menu');
            });

            if (focusToggle) {
                focusToggle.dataset.focusActive = focusActive && focusAvailable ? 'true' : 'false';
                focusToggle.setAttribute('aria-pressed', focusActive && focusAvailable ? 'true' : 'false');
                focusToggle.setAttribute('aria-label', focusActive && focusAvailable ? 'Desactiver le mode focus' : 'Activer le mode focus');
                focusToggle.title = focusActive && focusAvailable ? 'Quitter le mode focus' : 'Mode focus';
            }
        };

        const setOpen = (open) => {
            if (!mobileQuery.matches) {
                shell.dataset.sidebarOpen = 'false';
                mobileToggle.setAttribute('aria-expanded', 'false');
                mobileToggle.setAttribute('aria-label', 'Ouvrir le menu principal');
                document.body.classList.remove('js-ready');
                document.body.classList.remove('sidebar-overlay-open');
                backdrop.hidden = true;
                updateControls();
                return;
            }

            document.body.classList.add('js-ready');
            document.body.classList.toggle('sidebar-overlay-open', open);
            shell.dataset.sidebarOpen = open ? 'true' : 'false';
            mobileToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            mobileToggle.setAttribute('aria-label', open ? 'Fermer le menu principal' : 'Ouvrir le menu principal');
            backdrop.hidden = !open;
            updateControls();
        };

        const toggleSidebar = () => setOpen(shell.dataset.sidebarOpen !== 'true');
        const closeSidebar = () => setOpen(false);
        const cycleDesktopSidebar = () => {
            if (mobileQuery.matches) {
                toggleSidebar();
                return;
            }

            if (focusActive && sidebarState === 'collapsed') {
                sidebarState = 'hidden';
            } else if (sidebarState === 'hidden') {
                sidebarState = 'expanded';
            } else if (sidebarState === 'expanded') {
                sidebarState = 'collapsed';
            } else {
                sidebarState = 'expanded';
            }

            saveSidebarState();
            closeSidebar();
            updateControls();
        };

        mobileToggle.addEventListener('click', toggleSidebar);
        collapseToggles.forEach((button) => button.addEventListener('click', cycleDesktopSidebar));
        if (focusToggle) {
            focusToggle.addEventListener('click', () => {
                focusActive = !focusActive;
                if (focusActive && sidebarState === 'expanded') {
                    sidebarState = 'collapsed';
                    saveSidebarState();
                }

                saveFocusState();
                updateControls();
            });
        }
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
            updateControls();
        };

        if (typeof mobileQuery.addEventListener === 'function') {
            mobileQuery.addEventListener('change', handleViewportChange);
        } else if (typeof mobileQuery.addListener === 'function') {
            mobileQuery.addListener(handleViewportChange);
        }

        handleViewportChange();
        updateControls();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
})();
</script>
<script src="{{ asset('js/product-picker.js') }}" defer></script>
<script src="{{ asset('js/form-safety.js') }}" defer></script>
</body>
</html>
