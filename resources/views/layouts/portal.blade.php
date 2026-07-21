<!DOCTYPE html>
<html lang="fr">
<head>
    @include('partials.security-csp-meta')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light">
    <meta name="theme-color" content="#f4ede2">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/pos-192.png') }}">
    <link rel="manifest" href="{{ asset('app-manifest.webmanifest') }}">
    <title>@yield('title', 'Portail client - Nema ERP')</title>
    <style>
        :root {
            --bg: #f4ede2;
            --paper: rgba(255, 252, 246, 0.95);
            --paper-strong: #fffdfa;
            --line: rgba(102, 82, 56, 0.16);
            --text: #241b13;
            --muted: #6e6154;
            --brand: #0f766e;
            --brand-deep: #0b4f56;
            --accent: #c56a18;
            --success: #176b4d;
            --warning: #9a5b00;
            --danger: #b42318;
            --shadow-soft: 0 18px 40px rgba(42, 28, 18, 0.08);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, Helvetica, sans-serif;
            background:
                radial-gradient(circle at top right, rgba(15, 118, 110, 0.14), transparent 34%),
                radial-gradient(circle at bottom left, rgba(197, 106, 24, 0.16), transparent 30%),
                linear-gradient(180deg, #f7f1e7 0%, #f2ebdf 100%);
            color: var(--text);
        }
        a { color: inherit; }
        .shell {
            max-width: 1180px;
            margin: 0 auto;
            padding: 32px 18px 48px;
            display: grid;
            gap: 18px;
        }
        .card {
            border: 1px solid var(--line);
            border-radius: 24px;
            background: var(--paper);
            box-shadow: var(--shadow-soft);
            padding: 22px;
        }
        .hero {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, rgba(247, 251, 252, 0.98) 0%, rgba(235, 244, 242, 0.96) 55%, rgba(255, 244, 227, 0.9) 100%);
        }
        .hero::after {
            content: '';
            position: absolute;
            width: 220px;
            height: 220px;
            right: -70px;
            top: -50px;
            border-radius: 999px;
            background: rgba(15, 118, 110, 0.12);
            filter: blur(10px);
        }
        .hero-grid {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: minmax(0, 1.45fr) minmax(280px, 0.9fr);
            gap: 18px;
            align-items: start;
        }
        .hero h1 {
            margin: 8px 0 10px;
            font-size: clamp(30px, 4vw, 44px);
            line-height: 1.02;
            letter-spacing: -.04em;
        }
        .kicker {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.78);
            border: 1px solid rgba(15, 118, 110, 0.14);
            color: var(--brand-deep);
            font-weight: 700;
            font-size: 13px;
        }
        .muted { color: var(--muted); }
        .summary-grid {
            display: grid;
            gap: 14px;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        }
        .summary-box {
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 16px;
            background: var(--paper-strong);
        }
        .summary-box .label {
            color: var(--muted);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: .08em;
            font-weight: 700;
        }
        .summary-box .value {
            margin-top: 8px;
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -.03em;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
            background: #eee5da;
            color: var(--text);
        }
        .badge-success { background: rgba(23, 107, 77, 0.14); color: var(--success); }
        .badge-warning { background: rgba(154, 91, 0, 0.14); color: var(--warning); }
        .badge-danger { background: rgba(180, 35, 24, 0.14); color: var(--danger); }
        .badge-muted { background: rgba(110, 97, 84, 0.12); color: var(--muted); }
        .layout {
            display: grid;
            gap: 18px;
            grid-template-columns: minmax(0, 1.25fr) minmax(300px, 0.88fr);
            align-items: start;
        }
        .button {
            touch-action: manipulation;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border-radius: 14px;
            border: 1px solid transparent;
            padding: 12px 16px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }
        .button-primary {
            background: var(--brand);
            color: #fff;
        }
        .button-secondary {
            background: rgba(255, 255, 255, 0.82);
            border-color: var(--line);
            color: var(--text);
        }
        .button-whatsapp {
            background: #1fa55b;
            color: #fff;
        }
        .stack {
            display: grid;
            gap: 12px;
        }
        .notice {
            border-radius: 18px;
            padding: 14px 16px;
            border: 1px solid var(--line);
            background: rgba(255, 255, 255, 0.7);
        }
        .notice-success { border-color: rgba(23, 107, 77, 0.24); background: rgba(23, 107, 77, 0.08); }
        .notice-warning { border-color: rgba(154, 91, 0, 0.24); background: rgba(154, 91, 0, 0.08); }
        label {
            display: block;
            margin-bottom: 6px;
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        input,
        button,
        select,
        textarea {
            width: 100%;
            border-radius: 14px;
            border: 1px solid rgba(102, 82, 56, 0.18);
            background: rgba(255, 255, 255, 0.92);
            color: var(--text);
            font: inherit;
            padding: 12px 14px;
        }
        textarea {
            min-height: 92px;
            resize: vertical;
        }
        .form-grid {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        }
        .field-error {
            color: var(--danger);
            font-size: 13px;
            margin-top: 6px;
        }
        .signature-card {
            display: grid;
            gap: 12px;
        }
        .signature-pad {
            display: grid;
            gap: 10px;
        }
        .signature-pad canvas {
            width: 100%;
            min-height: 180px;
            border-radius: 18px;
            border: 1px dashed rgba(15, 118, 110, 0.28);
            background: #fffdfa;
            touch-action: none;
        }
        .signature-toolbar {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }
        .signature-preview {
            margin-top: 14px;
            padding: 14px;
            border-radius: 18px;
            border: 1px solid var(--line);
            background: rgba(255, 255, 255, 0.72);
        }
        .signature-preview img {
            display: block;
            width: 100%;
            max-width: 360px;
            border-radius: 14px;
            border: 1px solid rgba(102, 82, 56, 0.16);
            background: #fffdfa;
            margin-top: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px 10px;
            border-bottom: 1px solid rgba(102, 82, 56, 0.12);
            text-align: left;
            vertical-align: top;
        }
        th {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--muted);
        }
        .toolbar {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
        }
        @media print {
            body { background: #fff; }
            .toolbar, .portal-actions { display: none !important; }
            .shell { padding: 0; max-width: none; }
            .card { box-shadow: none; }
        }
        @media (max-width: 960px) {
            .hero-grid,
            .layout {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <main class="shell">
        <noscript>
            <div class="notice notice-warning">Le portail fonctionne mieux avec JavaScript active, surtout pour la copie rapide et la signature graphique. La signature textuelle reste disponible.</div>
        </noscript>
        <div class="toolbar">
            <button type="button" class="button button-secondary" onclick="window.print()">Imprimer</button>
        </div>
        @yield('content')
    </main>
    <script>
    document.addEventListener('click', async function (event) {
        const trigger = event.target.closest('[data-copy-text]');
        if (!trigger) {
            return;
        }

        const text = trigger.getAttribute('data-copy-text') || '';
        if (!text) {
            return;
        }

        const initialLabel = trigger.dataset.copyLabel || trigger.textContent.trim();
        const successLabel = trigger.dataset.copySuccess || 'Copie';

        try {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                await navigator.clipboard.writeText(text);
            } else {
                const helper = document.createElement('textarea');
                helper.value = text;
                document.body.appendChild(helper);
                helper.select();
                document.execCommand('copy');
                document.body.removeChild(helper);
            }
            trigger.textContent = successLabel;
            window.setTimeout(() => {
                trigger.textContent = initialLabel;
            }, 1600);
        } catch (error) {
            console.error(error);
        }
    });
    </script>
    @stack('scripts')
</body>
</html>
