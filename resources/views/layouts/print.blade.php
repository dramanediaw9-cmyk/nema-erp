<!DOCTYPE html>
<html lang="fr">
<head>
    @include('partials.security-csp-meta')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Document - Nema ERP')</title>
    <style>
        :root {
            --ink: #17212b;
            --muted: #667085;
            --line: #d0d5dd;
            --accent: #0b5d6b;
            --accent-soft: #e6f0f2;
            --paper: #ffffff;
            --bg: #eef2f6;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: @if($pdfMode ?? false) #ffffff @else var(--bg) @endif;
            color: var(--ink);
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.45;
        }
        .page {
            max-width: @if($pdfMode ?? false) none @else 960px @endif;
            margin: @if($pdfMode ?? false) 0 @else 24px auto @endif;
            background: var(--paper);
            padding: 36px 42px;
            box-shadow: @if($pdfMode ?? false) none @else 0 12px 30px rgba(15, 23, 42, 0.08) @endif;
            position: relative;
        }
        .page::before {
            content: '';
            position: absolute;
            inset: 0 0 auto 0;
            height: 8px;
            background: linear-gradient(90deg, var(--accent) 0%, #0f766e 100%);
        }
        .toolbar {
            max-width: 960px;
            margin: 20px auto 0;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
        .button {
            display: inline-block;
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 10px 14px;
            text-decoration: none;
            color: var(--ink);
            background: #fff;
            font-weight: 700;
        }
        .button-primary {
            background: var(--accent);
            color: #fff;
            border-color: var(--accent);
        }
        .doc-header {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            margin-bottom: 28px;
            padding: 8px 0 20px;
            border-bottom: 2px solid var(--line);
        }
        .doc-header h1 {
            margin: 0 0 10px;
            font-size: 28px;
        }
        .company-brand {
            display: flex;
            gap: 14px;
            align-items: flex-start;
        }
        .company-brand-logo {
            width: 74px;
            max-height: 74px;
            object-fit: contain;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 6px;
            background: #fff;
        }
        .meta, .muted {
            color: var(--muted);
        }
        .doc-chip {
            display: inline-flex;
            padding: 6px 10px;
            border-radius: 999px;
            background: var(--accent-soft);
            color: var(--accent);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
            margin-bottom: 10px;
        }
        .grid {
            display: grid;
            gap: 18px;
        }
        .grid-2 {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .panel {
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 16px;
        }
        .panel h2 {
            margin: 0 0 12px;
            font-size: 16px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 18px;
        }
        th, td {
            padding: 10px 8px;
            border-bottom: 1px solid var(--line);
            text-align: left;
            vertical-align: top;
        }
        th {
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: .04em;
            color: var(--muted);
            background: #f8fafc;
        }
        .right { text-align: right; }
        .totals {
            width: 340px;
            margin-left: auto;
            margin-top: 20px;
        }
        .totals td {
            padding: 8px 0;
        }
        .totals .grand-total td {
            border-top: 2px solid var(--line);
            font-size: 18px;
            font-weight: 700;
        }
        .footer {
            margin-top: 28px;
            padding-top: 18px;
            border-top: 1px solid var(--line);
            color: var(--muted);
            font-size: 13px;
        }
        .signatures {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 30px;
            margin-top: 36px;
        }
        .signature-box {
            border-top: 1px solid var(--line);
            padding-top: 10px;
            color: var(--muted);
            font-size: 13px;
        }
        @media print {
            body {
                background: #fff;
            }
            .toolbar {
                display: none;
            }
            .page {
                margin: 0;
                max-width: none;
                box-shadow: none;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    @unless($pdfMode ?? false)
        <div class="toolbar">
            <button type="button" class="button button-primary" onclick="window.print()">Imprimer</button>
            <a href="{{ url()->previous() }}" class="button">Retour</a>
        </div>
    @endunless

    <main class="page">
        @yield('content')
    </main>
</body>
</html>
