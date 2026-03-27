@extends('layouts.app')

@section('title', 'Point de vente - Caisse detail')
@section('page-title', 'Point de vente')

@section('content')
    <style>
        html, body {
            min-height: 100%;
            overflow: auto;
        }
        body { margin: 0; background: #14162a; overflow: auto; color: #f8f9ff; }
        .sidebar, .topbar { display: none; }
        .shell {
            grid-template-columns: 1fr !important;
            min-height: 100dvh;
            overflow: visible;
        }
        .main {
            padding: 0 !important;
            min-height: 100dvh;
            overflow: visible;
        }

        .pos-shell {
            min-height: 100dvh;
            display: grid;
            grid-template-rows: auto auto;
            overflow: visible;
            background:
                radial-gradient(circle at top left, rgba(212, 72, 173, 0.16), transparent 28%),
                linear-gradient(180deg, #181b30 0%, #131528 100%);
        }
        .pos-kicker {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
            align-items: center;
            padding: 10px 16px 12px;
            background: linear-gradient(180deg, #242846 0%, #1f223c 100%);
            color: #fff;
            border-top: 5px solid #d448ad;
            border-bottom: 1px solid #393f67;
        }
        .pos-kicker h2 {
            margin: 0;
            font-size: 14px;
            letter-spacing: .10em;
            text-transform: uppercase;
        }
        .pos-kicker .muted {
            max-width: none;
            color: #bcc4ea;
            font-size: 12px;
        }
        .pos-kicker .button { border-radius: 12px; }
        .pos-kicker .button-secondary {
            background: #2c3152;
            color: #fff;
            border: 1px solid #454c76;
        }

        .pos-workspace {
            display: grid;
            grid-template-columns: minmax(400px, 455px) minmax(0, 1fr);
            overflow: visible;
            background: #191b31;
        }
        .pos-browser {
            grid-column: 2;
            padding: 10px 12px 12px;
            display: grid;
            gap: 8px;
            grid-template-rows: auto auto auto auto auto auto;
            align-content: start;
            min-width: 0;
            overflow: visible;
            background: #262846;
        }
        .pos-cart {
            grid-column: 1;
            grid-row: 1;
            display: grid;
            grid-template-rows: auto auto;
            border-left: none;
            border-right: 1px solid #373c63;
            background: linear-gradient(180deg, #1d2138 0%, #1b1f35 100%);
            min-width: 0;
            overflow: visible;
        }

        .pos-toolbar {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 10px;
            align-items: center;
        }
        .pos-search { display: grid; gap: 6px; }
        .pos-search input {
            width: 100%;
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid #3a4065;
            font-size: 15px;
            background: #161a30;
            color: #f5f7ff;
            box-shadow: none;
        }
        .pos-search .help,
        .pos-kicker .help,
        .pos-browser .help,
        .pos-cart .help {
            color: #95a1d5;
            font-size: 12px;
        }
        .pos-summary-card,
        .summary-box {
            min-width: 150px;
            margin: 0;
            padding: 10px 12px;
            border-radius: 12px;
            border: 1px solid #40466f;
            background: #2a2f4d;
            box-shadow: none;
        }
        .pos-summary-card strong,
        .summary-box strong {
            color: #b6bee4;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .08em;
        }
        .pos-summary-card .value,
        .summary-box .value {
            color: #fff;
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -0.03em;
        }

        .pos-touch-strip,
        .pos-chip-row,
        .pos-session-strip,
        .pos-cart-context {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .pos-shortcuts { display: none; }
        .pos-touch-strip {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
        }
        .pos-touch-btn,
        .pos-chip {
            border: 1px solid #454c76;
            border-radius: 10px;
            background: #32365d;
            color: #eef1ff;
            padding: 10px 12px;
            font-weight: 800;
            cursor: pointer;
            transition: all .14s ease;
        }
        .pos-touch-btn:hover,
        .pos-chip:hover {
            background: #3b406c;
            border-color: #5a6294;
        }
        .pos-touch-btn.is-active,
        .pos-chip.is-active {
            background: #1d8ec0;
            border-color: #43c7ff;
            color: #fff;
            box-shadow: none;
        }
        .pos-meta-chip,
        .pos-cart-context-chip,
        .pos-shortcuts span {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            border-radius: 999px;
            background: #2a2f4d;
            border: 1px solid #434974;
            color: #d8ddf7;
            font-size: 12px;
            font-weight: 700;
            box-shadow: none;
        }
        .pos-meta-chip strong,
        .pos-cart-context-chip strong { color: #fff; }

        #pos-product-grid {
            padding: 8px;
            background: #202341;
            border: 1px solid #373c63;
            border-radius: 10px;
            box-shadow: none;
            overflow: visible;
        }
        .pos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(118px, 1fr));
            gap: 8px;
            align-content: start;
        }
        .pos-product {
            border: 1px solid #4b5180;
            border-radius: 8px;
            background: linear-gradient(180deg, #32365d 0%, #2a2e4f 100%);
            padding: 6px;
            text-align: left;
            cursor: pointer;
            min-height: 140px;
            display: grid;
            grid-template-rows: auto 1fr auto;
            gap: 6px;
            transition: transform .12s ease, border-color .12s ease;
        }
        .pos-product:hover {
            transform: translateY(-1px);
            border-color: #6d75ad;
            box-shadow: none;
        }
        .pos-product-top {
            position: relative;
            display: block;
        }
        .pos-product-thumb {
            width: 100%;
            height: 88px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: 12px;
            letter-spacing: .04em;
            color: #17304f;
            overflow: hidden;
            border: 1px solid transparent;
        }
        .pos-product-thumb.tone-1 { background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); }
        .pos-product-thumb.tone-2 { background: linear-gradient(135deg, #fae8ff 0%, #e9d5ff 100%); }
        .pos-product-thumb.tone-3 { background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%); }
        .pos-product-thumb.tone-4 { background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); }
        .pos-product-thumb.tone-5 { background: linear-gradient(135deg, #ffe4e6 0%, #fecdd3 100%); }
        .pos-product-thumb.has-image {
            background: #fff;
            border-color: #dbe3ef;
        }
        .pos-product-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .pos-product strong {
            display: block;
            font-size: 12px;
            line-height: 1.25;
            margin-bottom: 2px;
            color: #fff;
        }
        .pos-product .meta {
            font-size: 10px;
            line-height: 1.2;
            color: #b7bde1;
        }
        .pos-product .price {
            font-size: 13px;
            font-weight: 900;
            color: #fff;
            letter-spacing: 0;
            margin-top: 2px;
        }
        .pos-product .badge {
            position: absolute;
            top: 6px;
            right: 6px;
            margin-top: 0;
            border-radius: 999px;
            padding: 4px 6px;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: .05em;
            text-transform: uppercase;
        }
        .badge-success { background: rgba(22, 163, 74, .85); color: #f0fdf4; }
        .badge-warning { background: rgba(217, 119, 6, .9); color: #fffbeb; }
        .pos-empty {
            padding: 30px 22px;
            text-align: center;
            border: 1px dashed #485079;
            border-radius: 12px;
            color: #aab3dc;
            background: #252a44;
        }

        .pos-cart-head {
            padding: 10px 12px;
            border-bottom: 1px solid #373c63;
            background: linear-gradient(180deg, #242846 0%, #1f223c 100%);
        }
        .pos-nav {
            display: flex;
            gap: 6px;
            align-items: center;
            margin-bottom: 10px;
        }
        .pos-nav-tab,
        .pos-nav-counter {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 30px;
            padding: 0 12px;
            border-radius: 8px 8px 0 0;
            background: #31365c;
            color: #f6f7ff;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .03em;
            border: 1px solid #464d78;
            border-bottom: none;
        }
        .pos-nav-tab.is-active {
            background: #1c2038;
            color: #fff;
        }
        .pos-nav-counter {
            border-radius: 8px;
            min-width: 48px;
            background: #38406b;
        }
        .pos-order-switcher {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            align-items: center;
            margin-bottom: 10px;
        }
        .pos-order-tabs {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding-bottom: 2px;
            scrollbar-width: thin;
        }
        .pos-order-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            flex: 0 0 auto;
        }
        .pos-order-tab {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            border: 1px solid #4a5180;
            background: #2a3050;
            color: #eef1ff;
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
            cursor: pointer;
        }
        .pos-order-tab.is-active {
            background: #d448ad;
            border-color: #ef7fd3;
            color: #fff;
        }
        .pos-order-tab small {
            color: rgba(255,255,255,.76);
            font-size: 10px;
            font-weight: 700;
        }
        .pos-order-tab.is-active small {
            color: rgba(255,255,255,.88);
        }
        .pos-order-tab-remove {
            width: 28px;
            height: 28px;
            border: 1px solid #5a618f;
            border-radius: 999px;
            background: #202744;
            color: #ffbfdc;
            font-size: 14px;
            font-weight: 900;
            line-height: 1;
            cursor: pointer;
            flex: 0 0 auto;
        }
        .pos-order-pill.is-active .pos-order-tab-remove {
            background: #7c2d63;
            border-color: #ef7fd3;
            color: #fff;
        }
        .pos-order-tab-remove:hover {
            background: #8c355f;
            border-color: #ff9ed8;
            color: #fff;
        }
        .pos-order-add {
            border: 1px solid #4fd8ff;
            border-radius: 999px;
            background: #18233f;
            color: #9fe8ff;
            padding: 8px 12px;
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
            cursor: pointer;
        }
        .pos-cart-title-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        .pos-cart-title-row h3 {
            margin: 0;
            font-size: 16px;
            letter-spacing: 0;
            color: #fff;
        }
        .doc-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 5px 8px;
            border-radius: 999px;
            background: rgba(212, 72, 173, .18);
            color: #ffb0ea;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .10em;
            margin-bottom: 8px;
            border: 1px solid rgba(212, 72, 173, .35);
        }
        .pos-cart-head .summary-box {
            min-width: 132px;
            background: #2b3051;
            color: #fff;
            border: 1px solid #454c76;
            box-shadow: none;
        }
        .pos-cart-head .summary-box strong { color: #c6ceef; }
        .pos-cart-head .summary-box .value { color: #fff; }
        .pos-cart-head-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
            margin-top: 8px;
        }
        .pos-cart-head-grid label,
        .pos-cart-body label {
            display: block;
            margin-bottom: 5px;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .09em;
            color: #aeb7dd;
            font-weight: 800;
        }
        .pos-cart-head-grid input,
        .pos-cart-head-grid select,
        .pos-cart-body input,
        .pos-cart-body textarea,
        .pos-cart-body select {
            width: 100%;
            background: #151a30;
            border: 1px solid #424971;
            border-radius: 10px;
            padding: 9px 10px;
            color: #f5f7ff;
        }
        .field-error {
            margin-top: 4px;
            color: #ff9aa5;
            font-size: 12px;
        }
        .alert-error {
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid #86445d;
            background: #3f2430;
            color: #ffd7e0;
            display: grid;
            gap: 6px;
        }

        .pos-sale-form {
            display: grid;
            overflow: visible;
        }
        .pos-cart-body {
            padding: 10px 12px 12px;
            display: grid;
            gap: 10px;
            grid-template-rows: auto auto auto auto auto;
            overflow: visible;
            background: #1b1f35;
        }
        .pos-lines {
            display: grid;
            gap: 6px;
            max-height: none;
            overflow: visible;
            padding-right: 2px;
        }
        .pos-line {
            border: 1px solid #3c436c;
            border-radius: 8px;
            background: #262b47;
            padding: 8px 10px;
            display: grid;
            gap: 6px;
            cursor: pointer;
            transition: border-color .12s ease, background .12s ease;
            color: #fff;
        }
        .pos-line:hover { background: #2a3050; }
        .pos-line.is-selected {
            border-color: #4fd8ff;
            box-shadow: inset 0 0 0 1px rgba(79, 216, 255, .35);
            background: #2b3153;
        }
        .pos-line-top {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr) auto auto;
            gap: 8px;
            align-items: start;
        }
        .pos-line-qty {
            min-width: 24px;
            height: 24px;
            border-radius: 4px;
            background: #1976d2;
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 900;
        }
        .pos-line-main {
            min-width: 0;
            display: grid;
            gap: 2px;
        }
        .pos-line-title {
            font-weight: 800;
            font-size: 12px;
            line-height: 1.25;
            color: #fff;
        }
        .pos-line-meta {
            color: #b6bee3;
            font-size: 10px;
            line-height: 1.25;
        }
        .pos-line-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 2px 6px;
            border-radius: 999px;
            background: #202c52;
            color: #9fdcff;
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .06em;
            margin-top: 2px;
            justify-self: start;
        }
        .pos-line-amount {
            color: #fff;
            font-size: 13px;
            font-weight: 900;
            white-space: nowrap;
            align-self: center;
        }
        .pos-line-grid {
            display: none;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
            align-items: end;
            padding-top: 4px;
            border-top: 1px dashed rgba(178, 190, 231, .18);
        }
        .pos-line.is-selected .pos-line-grid {
            display: grid;
        }
        .pos-line-grid > div {
            min-width: 0;
        }
        .pos-line-total {
            grid-column: 1 / -1;
        }
        .pos-qty-box {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .pos-qty-box button,
        .pos-remove {
            border: none;
            border-radius: 8px;
            background: #3b4168;
            color: #fff;
            font-weight: 900;
            min-width: 32px;
            height: 32px;
            cursor: pointer;
        }
        .pos-remove {
            background: #6f2f4d;
            color: #ffe5f0;
            padding: 0 10px;
        }

        .pos-keypad {
            border: 1px solid #3f456e;
            border-radius: 8px;
            background: #242945;
            padding: 8px;
            display: grid;
            gap: 8px;
        }
        .pos-keypad-head {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }
        .pos-keypad-head strong {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #d7ddfb;
        }
        .pos-keypad-target {
            color: #85f0ff;
            font-weight: 800;
            font-size: 12px;
        }
        .pos-keypad-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 4px;
        }
        .pos-key {
            border: none;
            border-radius: 2px;
            min-height: 44px;
            font-size: 17px;
            font-weight: 900;
            cursor: pointer;
            background: #43486c;
            color: #fff;
            box-shadow: none;
        }
        .pos-key:hover { background: #505684; }
        .pos-key.is-wide { grid-column: span 2; }
        .pos-key.is-action { background: #2e365d; color: #fff; }
        .pos-key.is-danger { background: #894a62; color: #fff; }

        .pos-summary {
            display: grid;
            gap: 6px;
            padding: 10px 12px;
            border-radius: 8px;
            background: #242945;
            border: 1px solid #41466f;
        }
        .pos-summary-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            font-size: 12px;
            color: #cfd4f3;
        }
        .pos-summary-row strong {
            font-size: 17px;
            color: #fff;
        }
        .pos-summary-controls {
            display: grid;
            gap: 8px;
            grid-template-columns: minmax(0, .95fr) minmax(0, 1.05fr);
        }
        .pos-inline-grid {
            display: grid;
            gap: 8px;
            grid-template-columns: 1fr 110px;
        }
        .pos-note {
            min-height: 42px;
            resize: vertical;
        }
        .pos-actions {
            display: grid;
            grid-template-columns: 1fr 1fr 1.3fr;
            gap: 8px;
            align-items: center;
            border-top: none;
            padding-top: 0;
        }
        .pos-actions .button {
            width: 100%;
            border-radius: 8px;
            padding: 13px 14px;
            font-size: 14px;
        }
        .pos-actions .button-primary {
            background: linear-gradient(180deg, #d44ab2 0%, #b52b90 100%);
            color: #fff;
            box-shadow: none;
            border: 1px solid #d44ab2;
        }
        .pos-actions .button-secondary {
            background: #383d63;
            color: #eef0ff;
            border: 1px solid #4a5180;
        }
        .pos-payment-panel {
            display: grid;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 8px;
            background: #242945;
            border: 1px solid #41466f;
        }
        .pos-payment-head {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }
        .pos-payment-lines {
            display: grid;
            gap: 8px;
        }
        .pos-payment-line {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) minmax(120px, .9fr) auto;
            gap: 8px;
            align-items: end;
            padding: 8px;
            border-radius: 8px;
            border: 1px solid #41476f;
            background: #1b2038;
        }
        .pos-payment-line.is-cash {
            border-color: #3f7a92;
            background: #1a2637;
        }
        .pos-payment-remove {
            width: 36px;
            height: 36px;
            border: none;
            border-radius: 8px;
            background: #6f2f4d;
            color: #ffe6f1;
            font-weight: 900;
            cursor: pointer;
        }
        .pos-payment-remove:disabled {
            opacity: .35;
            cursor: not-allowed;
        }
        .pos-payment-grid {
            display: grid;
            gap: 10px;
            grid-template-columns: minmax(0, .95fr) minmax(0, 1.05fr);
            align-items: start;
        }
        .pos-payment-totals {
            display: grid;
            gap: 6px;
        }
        .pos-payment-total-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            font-size: 12px;
            color: #d6ddfb;
            padding: 6px 0;
            border-bottom: 1px dashed rgba(215, 221, 251, .12);
        }
        .pos-payment-total-row:last-child {
            border-bottom: none;
        }
        .pos-payment-total-row strong {
            color: #fff;
            font-size: 13px;
        }
        .pos-payment-total-row.is-warning strong {
            color: #ffcf8b;
        }
        .pos-payment-total-row.is-success strong {
            color: #9ef0c0;
        }
        .pos-payment-help {
            color: #95a1d5;
            font-size: 11px;
        }
        @media (max-width: 920px) {
            .pos-workspace { grid-template-columns: minmax(350px, 410px) minmax(0, 1fr); }
            .pos-grid { grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); }
            .pos-touch-strip { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        }
        @media (max-width: 760px) {
            .pos-workspace { grid-template-columns: 1fr; }
            .pos-browser { grid-column: auto; padding: 12px; }
            .pos-cart {
                grid-column: auto;
                grid-row: auto;
                border-right: none;
                border-top: 1px solid #373c63;
            }
            .pos-lines { max-height: none; }
            .pos-kicker { padding: 10px 12px 12px; }
            .pos-browser { padding: 12px; }
            .pos-cart-head, .pos-cart-body { padding-left: 12px; padding-right: 12px; }
            .pos-toolbar,
            .pos-cart-head-grid,
            .pos-line-grid,
            .pos-summary-controls,
            .pos-inline-grid,
            .pos-actions,
            .pos-touch-strip,
            .pos-payment-grid,
            .pos-payment-line {
                grid-template-columns: 1fr;
            }
            .pos-grid { grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); }
            .pos-line-top { grid-template-columns: auto minmax(0, 1fr) auto; }
            .pos-remove { grid-column: 2 / -1; justify-self: end; }
        }
    </style>

    <div class="pos-shell">
        <div class="pos-kicker">
            <div>
                <h2>CAISSE {{ $session->id }} - ERP POS</h2>
                <div class="muted">Session {{ $session->session_number }} / {{ $session->warehouse?->name }} / {{ $session->cashAccount?->name }}. Ecran caisse detail optimise pour vente rapide, tactile et scan code-barres.</div>
            </div>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <a href="{{ route('pos.show', $session) }}" class="button button-secondary">Retour session</a>
                <a href="{{ route('pos.report', ['date' => $session->opened_at?->toDateString(), 'warehouse_id' => $session->warehouse_id, 'cash_account_id' => $session->cash_account_id]) }}" class="button button-secondary">Rapport du jour</a>
            </div>
        </div>

        <div class="pos-workspace">
            <section class="pos-browser">
                <div class="pos-toolbar">
                    <div class="pos-search">
                        <input id="pos-search" type="text" placeholder="Scanner ou rechercher un article : code-barres, SKU, nom" autofocus>
                        <div id="pos-feedback" class="help">Scanne un code-barres ou clique sur un article pour l ajouter au panier.</div>
                    </div>
                    <div class="pos-summary-card">
                        <strong>Tickets session</strong>
                        <div class="value">{{ number_format($summary['sales_count'], 0, ',', ' ') }}</div>
                        <div class="help">tickets deja saisis</div>
                    </div>
                </div>

                <div class="pos-touch-strip">
                    <button type="button" class="pos-touch-btn" data-pos-action="focus-customer">Client</button>
                    <button type="button" class="pos-touch-btn" data-pos-action="focus-note">Note</button>
                    <button type="button" class="pos-touch-btn" data-pos-action="focus-search">Recherche</button>
                    <button type="button" class="pos-touch-btn" data-pos-action="target-qty">Qte</button>
                    <button type="button" class="pos-touch-btn" data-pos-action="target-line-discount">%</button>
                    <button type="button" class="pos-touch-btn" data-pos-action="target-price">Prix</button>
                    <button type="button" class="pos-touch-btn" data-pos-action="clear-cart">Vider</button>
                </div>

                <div class="pos-session-strip">
                    <div class="pos-meta-chip">Session <strong>{{ $session->session_number }}</strong></div>
                    <div class="pos-meta-chip">Entrepot <strong>{{ $session->warehouse?->name }}</strong></div>
                    <div class="pos-meta-chip">Caisse <strong>{{ $session->cashAccount?->name }}</strong></div>
                    <div class="pos-meta-chip" id="pos-filter-count">{{ count($productCatalog) }} / {{ count($productCatalog) }} articles</div>
                </div>

                <div id="pos-category-row" class="pos-chip-row">
                    <button type="button" class="pos-chip is-active" data-category="">Tous</button>
                    @foreach ($categories as $category)
                        <button type="button" class="pos-chip" data-category="{{ $category['id'] }}">{{ $category['name'] }}</button>
                    @endforeach
                </div>

                <div class="pos-shortcuts">
                    <span>F2 Recherche</span>
                    <span>F4 Client</span>
                    <span>F6 Prix</span>
                    <span>F7 Remise ligne</span>
                    <span>F8 Remise ticket</span>
                    <span>Ctrl+Enter Encaisser</span>
                </div>

                <div id="pos-product-grid"></div>
            </section>

            <aside class="pos-cart">
                <div class="pos-cart-head">
                    <div class="pos-nav">
                        <a href="{{ route('pos.sales.create') }}" class="pos-nav-tab is-active">Caisse</a>
                        <a href="{{ route('pos.show', $session) }}#tickets-session" class="pos-nav-tab">Commandes</a>
                        <div class="pos-nav-counter">{{ number_format($summary['sales_count'], 0, ',', ' ') }}</div>
                    </div>
                    <div class="pos-order-switcher">
                        <div id="pos-order-tabs" class="pos-order-tabs"></div>
                        <button type="button" id="pos-new-order" class="pos-order-add">+ Nouvelle commande</button>
                    </div>
                    <div class="doc-chip">Commande en cours</div>
                    <div class="pos-cart-title-row">
                        <div>
                            <h3>Produits selectionnes</h3>
                            <div class="help">Tous les produits ajoutes restent visibles a gauche pendant la commande, comme sur une vraie caisse detail.</div>
                        </div>
                        <div class="summary-box">
                            <strong>Total ticket</strong>
                            <div class="value" id="summary-total-head">0 XOF</div>
                        </div>
                    </div>

                    <div class="pos-cart-head-grid">
                        <div>
                            <label for="customer_id">Client</label>
                            <select id="customer_id" name="customer_id" form="pos-sale-form">
                                <option value="">Client comptoir</option>
                                @foreach ($customers as $customer)
                                    <option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>{{ $customer->name }}</option>
                                @endforeach
                            </select>
                            @error('customer_id')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label for="sale_date">Date de vente</label>
                            <input id="sale_date" name="sale_date" type="date" form="pos-sale-form" value="{{ old('sale_date', now()->toDateString()) }}" required>
                            @error('sale_date')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label for="method">Mode de paiement</label>
                            <select id="method" name="method" form="pos-sale-form" required>
                                @foreach ($methods as $key => $label)
                                    <option value="{{ $key }}" @selected(old('method', 'cash') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('method')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label for="reference">Reference</label>
                            <input id="reference" name="reference" type="text" form="pos-sale-form" value="{{ old('reference') }}" placeholder="Reference ticket ou paiement">
                            @error('reference')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="pos-cart-context">
                        <div class="pos-cart-context-chip">Client <strong id="pos-customer-chip">Client comptoir</strong></div>
                        <div class="pos-cart-context-chip">Paiement <strong id="pos-method-chip">{{ $methods[old('method', 'cash')] ?? reset($methods) }}</strong></div>
                        <div class="pos-cart-context-chip">Date <strong id="pos-date-chip">{{ now()->format('d/m/Y') }}</strong></div>
                        <div class="pos-cart-context-chip">Lignes <strong id="pos-lines-chip">0 ligne</strong></div>
                    </div>
                </div>
                <form id="pos-sale-form" class="pos-sale-form" method="POST" action="{{ route('pos.sales.store') }}">
                    @csrf
                    <div class="pos-cart-body">
                        @if ($errors->any())
                            <div class="alert-error">
                                <strong>Le ticket ne peut pas etre valide pour le moment.</strong>
                                @foreach ($errors->all() as $error)
                                    <div>{{ $error }}</div>
                                @endforeach
                            </div>
                        @endif

                        <div>
                            <div id="pos-empty" class="pos-empty">Le panier est vide. Scanne un article ou clique sur une carte produit pour demarrer la vente.</div>
                            <div id="pos-lines" class="pos-lines"></div>
                        </div>

                        <div class="pos-keypad">
                            <div class="pos-keypad-head">
                                <strong>Pave numerique</strong>
                                <div class="pos-keypad-target" id="pos-keypad-target">quantite</div>
                            </div>
                            <div class="pos-keypad-grid">
                                <button type="button" class="pos-key" data-keypad="7">7</button>
                                <button type="button" class="pos-key" data-keypad="8">8</button>
                                <button type="button" class="pos-key" data-keypad="9">9</button>
                                <button type="button" class="pos-key is-danger" data-keypad-action="backspace">Eff</button>
                                <button type="button" class="pos-key" data-keypad="4">4</button>
                                <button type="button" class="pos-key" data-keypad="5">5</button>
                                <button type="button" class="pos-key" data-keypad="6">6</button>
                                <button type="button" class="pos-key is-action" data-keypad-action="plus">+1</button>
                                <button type="button" class="pos-key" data-keypad="1">1</button>
                                <button type="button" class="pos-key" data-keypad="2">2</button>
                                <button type="button" class="pos-key" data-keypad="3">3</button>
                                <button type="button" class="pos-key is-action" data-keypad-action="minus">-1</button>
                                <button type="button" class="pos-key is-wide" data-keypad="0">0</button>
                                <button type="button" class="pos-key" data-keypad=".">.</button>
                                <button type="button" class="pos-key is-danger" data-keypad-action="clear">C</button>
                            </div>
                        </div>

                        <div class="pos-summary">
                            <div class="pos-summary-row"><span>Sous-total</span><strong id="summary-subtotal">0 XOF</strong></div>
                            <div class="pos-summary-row"><span>Remises lignes</span><strong id="summary-line-discount">0 XOF</strong></div>
                            <div class="pos-summary-row"><span>Remise ticket</span><strong id="summary-ticket-discount">0 XOF</strong></div>
                            <div class="pos-summary-row"><span>Total a encaisser</span><strong id="summary-total">0 XOF</strong></div>
                        </div>

                        <div class="pos-payment-panel">
                            <div class="pos-payment-head">
                                <div>
                                    <label style="margin-bottom:4px;">Encaissement</label>
                                    <div class="pos-payment-help">Plusieurs modes de paiement sur le meme ticket, avec montant recu et monnaie a rendre pour la partie cash.</div>
                                </div>
                                <button type="button" id="pos-add-payment-line" class="button button-secondary">+ Ligne de reglement</button>
                            </div>
                            <div id="pos-payment-lines" class="pos-payment-lines"></div>
                            <div class="pos-payment-grid">
                                <div>
                                    <label for="cash_received_amount">Montant recu en especes</label>
                                    <input id="cash_received_amount" name="cash_received_amount" type="number" min="0" step="0.01" value="{{ old('cash_received_amount', $initialCashReceivedAmount ?? 0) }}">
                                    <div class="pos-payment-help">Renseigne seulement ce que le client remet en cash. La monnaie a rendre se calcule automatiquement.</div>
                                    @error('cash_received_amount')<div class="field-error">{{ $message }}</div>@enderror
                                </div>
                                <div class="pos-payment-totals">
                                    <div class="pos-payment-total-row"><span>Reglements saisis</span><strong id="summary-paid">0 XOF</strong></div>
                                    <div class="pos-payment-total-row is-warning"><span>Reste a couvrir</span><strong id="summary-remaining">0 XOF</strong></div>
                                    <div class="pos-payment-total-row is-success"><span>Monnaie a rendre</span><strong id="summary-change">0 XOF</strong></div>
                                </div>
                            </div>
                            @error('payments')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="pos-summary-controls">
                            <div>
                                <label for="discount_type">Remise ticket</label>
                                <div class="pos-inline-grid">
                                    <select id="discount_type" name="discount_type">
                                        <option value="none" @selected(old('discount_type', 'none') === 'none')>Aucune</option>
                                        <option value="fixed" @selected(old('discount_type') === 'fixed')>Montant</option>
                                        <option value="percent" @selected(old('discount_type') === 'percent')>%</option>
                                    </select>
                                    <input id="discount_value" name="discount_value" type="number" min="0" step="0.01" value="{{ old('discount_value', 0) }}">
                                </div>
                                @error('discount_type')<div class="field-error">{{ $message }}</div>@enderror
                                @error('discount_value')<div class="field-error">{{ $message }}</div>@enderror
                            </div>
                            <div>
                                <label for="notes">Notes</label>
                                <textarea id="notes" name="notes" class="pos-note" placeholder="Commentaire operateur, precisions ticket, remarque caisse">{{ old('notes') }}</textarea>
                                @error('notes')<div class="field-error">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <input type="hidden" id="source_draft_id" name="source_draft_id" value="{{ old('source_draft_id', $activeDraftId) }}">
                        <div id="pos-hidden-inputs"></div>

                        <div class="pos-actions">
                            <a href="{{ route('pos.show', $session) }}" class="button button-secondary">Retour session</a>
                            <button type="button" id="pos-save-draft" class="button button-secondary">Mettre en attente</button>
                            <button type="submit" class="button button-primary">Valider et encaisser</button>
                        </div>
                    </div>
                </form>
            </aside>
        </div>
    </div>

    <script>
        const productCatalog = @json($productCatalog);
        const methods = @json($methods);
        const paymentAccounts = @json($paymentAccounts);
        const sessionCashAccountId = @json($sessionCashAccountId);
        const initialItems = @json($initialItems);
        const initialPayments = @json($initialPayments);
        const initialCashReceivedAmount = Number(@json($initialCashReceivedAmount ?? 0));
        const savedDrafts = @json(($savedDrafts ?? collect())->values()->all());
        const initialDraftId = @json($activeDraftId);
        const hasOldPosForm = @json($hasOldPosForm ?? false);
        const money = (value) => new Intl.NumberFormat('fr-FR').format(Math.round(Number(value) || 0)) + ' XOF';
        const searchInput = document.getElementById('pos-search');
        const feedback = document.getElementById('pos-feedback');
        const productGrid = document.getElementById('pos-product-grid');
        const categoryRow = document.getElementById('pos-category-row');
        const customerInput = document.getElementById('customer_id');
        const saleDateInput = document.getElementById('sale_date');
        const methodInput = document.getElementById('method');
        const linesWrap = document.getElementById('pos-lines');
        const paymentLinesWrap = document.getElementById('pos-payment-lines');
        const addPaymentLineButton = document.getElementById('pos-add-payment-line');
        const cashReceivedInput = document.getElementById('cash_received_amount');
        const hiddenInputs = document.getElementById('pos-hidden-inputs');
        const emptyState = document.getElementById('pos-empty');
        const discountTypeInput = document.getElementById('discount_type');
        const discountValueInput = document.getElementById('discount_value');
        const subtotalOutput = document.getElementById('summary-subtotal');
        const lineDiscountOutput = document.getElementById('summary-line-discount');
        const ticketDiscountOutput = document.getElementById('summary-ticket-discount');
        const totalOutput = document.getElementById('summary-total');
        const totalHeadOutput = document.getElementById('summary-total-head');
        const paidOutput = document.getElementById('summary-paid');
        const remainingOutput = document.getElementById('summary-remaining');
        const changeOutput = document.getElementById('summary-change');
        const saleForm = document.getElementById('pos-sale-form');
        const keypadTargetOutput = document.getElementById('pos-keypad-target');
        const filterCountOutput = document.getElementById('pos-filter-count');
        const customerChip = document.getElementById('pos-customer-chip');
        const methodChip = document.getElementById('pos-method-chip');
        const dateChip = document.getElementById('pos-date-chip');
        const linesChip = document.getElementById('pos-lines-chip');
        const touchButtons = Array.from(document.querySelectorAll('[data-pos-action]'));
        const referenceInput = document.getElementById('reference');
        const notesInput = document.getElementById('notes');
        const orderTabsWrap = document.getElementById('pos-order-tabs');
        const newOrderButton = document.getElementById('pos-new-order');
        const saveDraftButton = document.getElementById('pos-save-draft');
        const sourceDraftInput = document.getElementById('source_draft_id');
        const csrfToken = saleForm.querySelector('input[name="_token"]').value;
        const draftStoreUrl = @json(route('pos.drafts.store'));
        const draftDestroyBaseUrl = @json(url('/point-de-vente/brouillons'));

        const byId = Object.fromEntries(productCatalog.map((product) => [String(product.id), product]));
        const accountsById = Object.fromEntries(paymentAccounts.map((account) => [String(account.id), account]));
        const defaultMethod = methodInput.options[0]?.value || 'cash';
        const methodLabels = methods;
        const touchTargets = {
            'target-qty': 'qty',
            'target-price': 'price',
            'target-line-discount': 'line_discount',
            'target-ticket-discount': 'ticket_discount',
        };
        const labels = {
            qty: 'quantite',
            price: 'prix',
            line_discount: 'remise ligne',
            ticket_discount: 'remise ticket',
        };
        const n = (value, fallback = 0) => {
            const parsed = Number(value);
            return Number.isFinite(parsed) ? parsed : fallback;
        };
        const todayValue = saleDateInput.value || new Date().toISOString().slice(0, 10);
        const state = {
            search: '',
            category: '',
            keypadTarget: 'qty',
            buffer: '',
            orders: [],
            activeOrderUid: null,
        };
        const nextOrderNumber = () => state.orders.reduce((max, order) => {
            const match = String(order.label || '').match(/(\d+)$/);
            return Math.max(max, match ? Number(match[1]) : 0);
        }, 0) + 1;
        const accountMatchesMethod = (account, method) => {
            if (!account) {
                return false;
            }
            if (method === 'cash') {
                return Number(account.id) === Number(sessionCashAccountId) || account.type === 'cash';
            }
            if (method === 'wave') {
                return account.type === 'mobile_money' && String(account.name || '').toLowerCase().includes('wave');
            }
            if (method === 'moov_money') {
                return account.type === 'mobile_money' && String(account.name || '').toLowerCase().includes('moov');
            }
            if (method === 'mobile_money') {
                return account.type === 'mobile_money';
            }
            if (method === 'bank_transfer' || method === 'cheque') {
                return account.type === 'bank';
            }
            return true;
        };
        const defaultAccountIdForMethod = (method) => {
            if (method === 'cash') {
                return sessionCashAccountId ? Number(sessionCashAccountId) : null;
            }
            const preferred = paymentAccounts.find((account) => accountMatchesMethod(account, method));
            return preferred ? Number(preferred.id) : null;
        };
        const mapOrderItems = (items = []) => (Array.isArray(items) ? items : []).map((item, index) => ({
            uid: item.uid || `${Date.now()}-${index}-${Math.random()}`,
            product_id: String(item.product_id || ''),
            description: item.description || byId[String(item.product_id || '')]?.name || '',
            qty: Number(item.qty || 1),
            unit_price: Number(item.unit_price || byId[String(item.product_id || '')]?.price || 0),
            discount_type: item.discount_type || 'none',
            discount_value: Number(item.discount_value || 0),
        }));
        const createPaymentLine = (seed = {}, fallbackMethod = defaultMethod) => {
            const method = seed.method || fallbackMethod;
            return {
                uid: seed.uid || `payment-${Date.now()}-${Math.random()}`,
                method,
                amount: Number(seed.amount || 0),
                cash_account_id: seed.cash_account_id ? Number(seed.cash_account_id) : defaultAccountIdForMethod(method),
                label: seed.label || '',
            };
        };
        const mapOrderPayments = (payments = [], fallbackMethod = defaultMethod) => {
            const mapped = (Array.isArray(payments) ? payments : [])
                .map((payment, index) => createPaymentLine({ ...payment, uid: payment.uid || `payment-${Date.now()}-${index}-${Math.random()}` }, fallbackMethod))
                .filter((payment) => payment.method || payment.amount || payment.cash_account_id);
            return mapped.length ? mapped : [createPaymentLine({}, fallbackMethod)];
        };
        const createDraftOrder = (seed = {}, forcedIndex = null) => {
            const items = mapOrderItems(seed.items || []);
            const method = seed.method || defaultMethod;
            return {
                uid: seed.uid || `order-${Date.now()}-${Math.random()}`,
                draft_id: seed.draft_id ? Number(seed.draft_id) : null,
                label: seed.label || `Commande ${forcedIndex ?? nextOrderNumber()}`,
                customer_id: seed.customer_id ?? '',
                sale_date: seed.sale_date || todayValue,
                method,
                reference: seed.reference || '',
                notes: seed.notes || '',
                discount_type: seed.discount_type || 'none',
                discount_value: Number(seed.discount_value || 0),
                cash_received_amount: Number(seed.cash_received_amount || 0),
                items,
                payments: mapOrderPayments(seed.payments || [], method),
                selectedLine: seed.selectedLine || (items[0]?.uid ?? null),
            };
        };
        const activeOrder = () => state.orders.find((order) => order.uid === state.activeOrderUid) || state.orders[0] || null;
        const ensureActiveOrder = () => {
            if (!state.orders.length) {
                const fallback = createDraftOrder({}, 1);
                state.orders = [fallback];
                state.activeOrderUid = fallback.uid;
            }
            return activeOrder();
        };
        Object.defineProperty(state, 'items', {
            get() {
                return ensureActiveOrder().items;
            },
            set(value) {
                ensureActiveOrder().items = Array.isArray(value) ? value : [];
            },
        });
        Object.defineProperty(state, 'selectedLine', {
            get() {
                return ensureActiveOrder().selectedLine;
            },
            set(value) {
                ensureActiveOrder().selectedLine = value;
            },
        });

        const serverDraftOrders = savedDrafts.map((draft, index) => createDraftOrder({
            draft_id: draft.id,
            label: draft.label,
            customer_id: draft.customer_id ?? '',
            sale_date: draft.sale_date || todayValue,
            method: draft.method || defaultMethod,
            reference: draft.reference || '',
            notes: draft.notes || '',
            discount_type: draft.discount_type || 'none',
            discount_value: n(draft.discount_value, 0),
            cash_received_amount: n(draft.cash_received_amount, 0),
            payments: draft.payments || [],
            items: draft.items || [],
        }, index + 1));
        const activeDraftNumber = initialDraftId ? Number(initialDraftId) : null;
        const bootOrder = createDraftOrder({
            draft_id: activeDraftNumber,
            customer_id: customerInput.value || '',
            sale_date: saleDateInput.value || todayValue,
            method: methodInput.value || defaultMethod,
            reference: referenceInput?.value || '',
            notes: notesInput?.value || '',
            discount_type: discountTypeInput.value || 'none',
            discount_value: n(discountValueInput.value, 0),
            cash_received_amount: initialCashReceivedAmount,
            payments: initialPayments,
            items: initialItems,
        }, 1);
        let bootOrders = serverDraftOrders.slice();
        if (hasOldPosForm || initialItems.length || initialPayments.length) {
            if (activeDraftNumber) {
                const existingIndex = bootOrders.findIndex((order) => order.draft_id === activeDraftNumber);
                if (existingIndex !== -1) {
                    bootOrders[existingIndex] = createDraftOrder({
                        ...bootOrders[existingIndex],
                        draft_id: bootOrders[existingIndex].draft_id,
                        label: bootOrders[existingIndex].label,
                        customer_id: bootOrder.customer_id,
                        sale_date: bootOrder.sale_date,
                        method: bootOrder.method,
                        reference: bootOrder.reference,
                        notes: bootOrder.notes,
                        discount_type: bootOrder.discount_type,
                        discount_value: bootOrder.discount_value,
                        cash_received_amount: bootOrder.cash_received_amount,
                        payments: bootOrder.payments,
                        items: bootOrder.items,
                        selectedLine: bootOrder.selectedLine,
                    }, existingIndex + 1);
                } else {
                    bootOrders.unshift(bootOrder);
                }
            } else {
                bootOrders.unshift(bootOrder);
            }
        }
        if (!bootOrders.length) {
            bootOrders = [createDraftOrder({}, 1)];
        }
        const initialActiveOrder = activeDraftNumber
            ? bootOrders.find((order) => order.draft_id === activeDraftNumber) || bootOrders[0]
            : bootOrders[0];
        state.orders = bootOrders;
        state.activeOrderUid = initialActiveOrder.uid;
        const norm = (value) => (value || '').toString().trim().toLowerCase();
        const esc = (value) => String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\"/g, '&quot;')
            .replace(/'/g, '&#039;');
        const initials = (name) => {
            const parts = String(name || '').trim().split(/\s+/).filter(Boolean).slice(0, 2);
            return parts.map((part) => part.charAt(0).toUpperCase()).join('') || 'PR';
        };
        const tone = (product) => ((Number(product.category_id || product.id || 0) % 5) + 1);
        const thumbHtml = (product) => product.image_url
            ? `<img src="${esc(product.image_url)}" alt="${esc(product.name)}">`
            : esc(initials(product.name));
        const paymentMethodLabel = (method) => methodLabels[method] || method || 'Mode';
        const primaryMethod = (order) => order?.payments?.[0]?.method || order?.method || defaultMethod;
        const paymentSummary = (order, total) => {
            if (!Array.isArray(order.payments) || !order.payments.length) {
                order.payments = [createPaymentLine({ amount: total }, order.method || defaultMethod)];
            }

            const payments = order.payments
                .map((payment) => ({
                    ...payment,
                    method: payment.method || order.method || defaultMethod,
                    amount: Math.max(n(payment.amount, 0), 0),
                    cash_account_id: payment.cash_account_id ? Number(payment.cash_account_id) : defaultAccountIdForMethod(payment.method || order.method || defaultMethod),
                }))
                .filter((payment) => payment.method || payment.amount || payment.cash_account_id);

            if (!payments.length) {
                payments.push(createPaymentLine({ amount: total }, order.method || defaultMethod));
            }

            if (payments.length === 1) {
                payments[0].method = order.method || payments[0].method || defaultMethod;
                payments[0].cash_account_id = payments[0].cash_account_id || defaultAccountIdForMethod(payments[0].method);
                payments[0].amount = total;
            }

            const paid = payments.reduce((carry, payment) => carry + n(payment.amount, 0), 0);
            const cashAllocated = payments.filter((payment) => payment.method === 'cash').reduce((carry, payment) => carry + n(payment.amount, 0), 0);
            const hasCashLine = payments.some((payment) => payment.method === 'cash');
            let cashReceived = Math.max(n(order.cash_received_amount, 0), 0);
            if (hasCashLine && cashAllocated > 0 && cashReceived <= 0) {
                cashReceived = cashAllocated;
            }
            if (!hasCashLine) {
                cashReceived = 0;
            }

            order.payments = payments;
            order.method = payments[0]?.method || order.method || defaultMethod;
            order.cash_received_amount = cashReceived;

            return {
                payments,
                paid,
                remaining: Math.max(total - paid, 0),
                overpaid: Math.max(paid - total, 0),
                cashAllocated,
                hasCashLine,
                cashReceived,
                changeDue: Math.max(cashReceived - cashAllocated, 0),
                isMixed: payments.length > 1 || new Set(payments.map((payment) => payment.method)).size > 1,
            };
        };
        const syncActiveOrderFromForm = () => {
            const order = ensureActiveOrder();
            order.customer_id = customerInput.value || '';
            order.sale_date = saleDateInput.value || todayValue;
            order.method = methodInput.value || defaultMethod;
            order.reference = referenceInput?.value || '';
            order.notes = notesInput?.value || '';
            order.discount_type = discountTypeInput.value || 'none';
            order.discount_value = n(discountValueInput.value, 0);
            order.cash_received_amount = Math.max(n(cashReceivedInput?.value, order.cash_received_amount || 0), 0);
            if (!Array.isArray(order.payments) || !order.payments.length) {
                order.payments = [createPaymentLine({}, order.method)];
            }
            if (order.payments.length === 1) {
                order.payments[0].method = order.method;
                order.payments[0].cash_account_id = order.payments[0].cash_account_id || defaultAccountIdForMethod(order.method);
            }
            return order;
        };
        const loadOrderIntoForm = (order) => {
            customerInput.value = order.customer_id || '';
            saleDateInput.value = order.sale_date || todayValue;
            methodInput.value = order.method || defaultMethod;
            if (referenceInput) {
                referenceInput.value = order.reference || '';
            }
            if (notesInput) {
                notesInput.value = order.notes || '';
            }
            if (cashReceivedInput) {
                cashReceivedInput.value = n(order.cash_received_amount, 0);
            }
            discountTypeInput.value = order.discount_type || 'none';
            discountValueInput.value = n(order.discount_value, 0);
            sourceDraftInput.value = order.draft_id ? String(order.draft_id) : '';
        };
        const orderTicketDiscount = (base, order) => (order?.discount_type ?? 'none') === 'fixed'
            ? Math.min(base, n(order?.discount_value, 0))
            : (order?.discount_type ?? 'none') === 'percent'
                ? base * Math.min(n(order?.discount_value, 0), 100) / 100
                : 0;
        const orderSnapshot = (order) => {
            const subtotal = order.items.reduce((carry, item) => carry + lineSubtotal(item), 0);
            const lineDiscounts = order.items.reduce((carry, item) => carry + lineDiscount(item), 0);
            const base = Math.max(subtotal - lineDiscounts, 0);
            const ticket = orderTicketDiscount(base, order);
            const total = Math.max(base - ticket, 0);
            return {
                subtotal,
                lineDiscounts,
                total,
                payment: paymentSummary(order, total),
            };
        };
        const draftPayload = (order) => {
            const snapshot = orderSnapshot(order);
            return {
                draft_id: order.draft_id || null,
                label: order.label,
                customer_id: order.customer_id || null,
                sale_date: order.sale_date || todayValue,
                method: primaryMethod(order),
                reference: order.reference || '',
                notes: order.notes || '',
                discount_type: order.discount_type || 'none',
                discount_value: n(order.discount_value, 0),
                cash_received_amount: n(order.cash_received_amount, 0),
                payments: snapshot.payment.payments.map((payment) => ({
                    method: payment.method,
                    amount: n(payment.amount, 0),
                    cash_account_id: payment.cash_account_id || '',
                    label: payment.label || '',
                })),
                items: order.items.map((item) => ({
                    product_id: item.product_id,
                    description: item.description || byId[String(item.product_id)]?.name || '',
                    qty: n(item.qty, 0),
                    unit_price: n(item.unit_price, 0),
                    discount_type: item.discount_type || 'none',
                    discount_value: n(item.discount_value, 0),
                })),
            };
        };        const paymentMethodOptionsHtml = (selectedMethod) => Object.entries(methods).map(([value, label]) => `<option value="${value}" ${value === selectedMethod ? 'selected' : ''}>${esc(label)}</option>`).join('');
        const paymentAccountOptionsHtml = (method, selectedId) => {
            let options = paymentAccounts.filter((account) => accountMatchesMethod(account, method));
            if (!options.length) {
                options = paymentAccounts.slice();
            }
            if (selectedId && accountsById[String(selectedId)] && !options.some((account) => Number(account.id) === Number(selectedId))) {
                options = [accountsById[String(selectedId)], ...options];
            }
            return ['<option value="">Compte auto</option>'].concat(options.map((account) => `<option value="${account.id}" ${Number(selectedId) === Number(account.id) ? 'selected' : ''}>${esc(account.name)}</option>`)).join('');
        };
        const renderOrderTabs = () => {
            if (!orderTabsWrap) {
                return;
            }
            orderTabsWrap.innerHTML = state.orders.map((order) => {
                const snapshot = orderSnapshot(order);
                const active = order.uid === state.activeOrderUid;
                return `
                    <div class="pos-order-pill ${active ? 'is-active' : ''}">
                        <button type="button" class="pos-order-tab ${active ? 'is-active' : ''}" data-order-switch="${order.uid}">
                            <span>${esc(order.label)}</span>
                            <small>${money(snapshot.total)} · ${order.items.length} art.${order.draft_id ? ' · attente' : ''}</small>
                        </button>
                        <button type="button" class="pos-order-tab-remove" data-order-remove="${order.uid}" title="Supprimer la commande">×</button>
                    </div>
                `;
            }).join('');
        };
        const renderPaymentLines = (order, snapshot) => {
            const payment = snapshot.payment;
            paymentLinesWrap.innerHTML = payment.payments.map((entry, index) => `
                <div class="pos-payment-line ${entry.method === 'cash' ? 'is-cash' : ''}">
                    <div>
                        <label>Mode</label>
                        <select data-payment-input="method" data-payment-index="${index}">${paymentMethodOptionsHtml(entry.method)}</select>
                    </div>
                    <div>
                        <label>Compte de tresorerie</label>
                        <select data-payment-input="cash_account_id" data-payment-index="${index}">${paymentAccountOptionsHtml(entry.method, entry.cash_account_id)}</select>
                    </div>
                    <div>
                        <label>Montant</label>
                        <input type="number" min="0" step="0.01" value="${n(entry.amount)}" data-payment-input="amount" data-payment-index="${index}">
                    </div>
                    <button type="button" class="pos-payment-remove" data-remove-payment="${index}" ${payment.payments.length === 1 ? 'disabled' : ''}>×</button>
                </div>
            `).join('');
            cashReceivedInput.disabled = !payment.hasCashLine;
            cashReceivedInput.value = payment.hasCashLine ? n(payment.cashReceived) : 0;
            paidOutput.textContent = money(payment.paid);
            remainingOutput.textContent = money(payment.remaining);
            changeOutput.textContent = money(payment.changeDue);
        };
        const deletePersistedDraft = async (order) => {
            if (!order?.draft_id) {
                return;
            }
            const response = await fetch(`${draftDestroyBaseUrl}/${order.draft_id}`, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error(data.message || 'Impossible de supprimer cette commande en attente.');
            }
        };
        const switchOrder = (uid) => {
            const current = syncActiveOrderFromForm();
            current.selectedLine = state.selectedLine;
            const target = state.orders.find((order) => order.uid === uid);
            if (!target) {
                return;
            }
            state.activeOrderUid = uid;
            loadOrderIntoForm(target);
            resetBuffer();
            renderCart();
            feedback.textContent = `${target.label} active.`;
        };
        const createNewOrder = () => {
            syncActiveOrderFromForm();
            const order = createDraftOrder({
                sale_date: saleDateInput.value || todayValue,
                method: methodInput.value || defaultMethod,
            }, nextOrderNumber());
            state.orders.push(order);
            state.activeOrderUid = order.uid;
            loadOrderIntoForm(order);
            resetBuffer();
            renderCart();
            feedback.textContent = `${order.label} est prete.`;
            searchInput.focus();
        };
        const saveCurrentOrder = async () => {
            const order = syncActiveOrderFromForm();
            if (!order.items.length) {
                feedback.textContent = 'Ajoute au moins un article avant de mettre la commande en attente.';
                searchInput.focus();
                return;
            }
            if (saveDraftButton) {
                saveDraftButton.disabled = true;
            }
            try {
                const response = await fetch(draftStoreUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(draftPayload(order)),
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    throw new Error(data.message || 'Impossible de mettre cette commande en attente.');
                }
                if (data.draft) {
                    const index = state.orders.findIndex((entry) => entry.uid === order.uid);
                    const refreshed = createDraftOrder({ ...data.draft, uid: order.uid, selectedLine: order.selectedLine }, index + 1);
                    state.orders[index] = refreshed;
                    state.activeOrderUid = refreshed.uid;
                    loadOrderIntoForm(refreshed);
                }
                renderCart();
                feedback.textContent = data.message || `${order.label} mise en attente.`;
            } catch (error) {
                feedback.textContent = error?.message || 'Une erreur est survenue pendant la mise en attente.';
            } finally {
                if (saveDraftButton) {
                    saveDraftButton.disabled = false;
                }
            }
        };
        const removeOrder = async (uid) => {
            const order = state.orders.find((entry) => entry.uid === uid);
            if (!order) {
                return;
            }
            try {
                await deletePersistedDraft(order);
            } catch (error) {
                feedback.textContent = error?.message || 'Impossible de supprimer cette commande en attente.';
                return;
            }
            if (state.orders.length === 1) {
                state.orders = [createDraftOrder({}, 1)];
                state.activeOrderUid = state.orders[0].uid;
                loadOrderIntoForm(state.orders[0]);
                resetBuffer();
                renderCart();
                feedback.textContent = order.draft_id ? `${order.label} a ete retiree des commandes en attente.` : 'La commande en cours a ete reinitialisee.';
                searchInput.focus();
                return;
            }
            const currentIndex = state.orders.findIndex((entry) => entry.uid === uid);
            state.orders = state.orders.filter((entry) => entry.uid !== uid);
            if (state.activeOrderUid === uid) {
                const fallback = state.orders[Math.max(0, currentIndex - 1)] || state.orders[0];
                state.activeOrderUid = fallback.uid;
                loadOrderIntoForm(fallback);
            }
            resetBuffer();
            renderCart();
            feedback.textContent = order.draft_id ? `${order.label} a ete retiree des commandes en attente.` : `${order.label} a ete supprimee.`;
            searchInput.focus();
        };
        const addPaymentLine = () => {
            const order = syncActiveOrderFromForm();
            const snapshot = orderSnapshot(order);
            order.payments.push(createPaymentLine({ amount: snapshot.payment.remaining > 0 ? snapshot.payment.remaining : 0 }, order.method || defaultMethod));
            renderCart();
        };
        const updatePaymentLine = (index, key, value) => {
            const order = syncActiveOrderFromForm();
            const payment = order.payments[index];
            if (!payment) {
                return;
            }
            if (key === 'method') {
                payment.method = methods[value] ? value : defaultMethod;
                if (!payment.cash_account_id || !accountMatchesMethod(accountsById[String(payment.cash_account_id)], payment.method)) {
                    payment.cash_account_id = defaultAccountIdForMethod(payment.method);
                }
                if (order.payments.length === 1) {
                    order.method = payment.method;
                    methodInput.value = payment.method;
                }
            } else if (key === 'amount') {
                payment.amount = n(value, 0);
            } else if (key === 'cash_account_id') {
                payment.cash_account_id = value ? Number(value) : null;
            }
            renderCart();
        };
        const removePaymentLine = (index) => {
            const order = syncActiveOrderFromForm();
            if (order.payments.length === 1) {
                return;
            }
            order.payments.splice(index, 1);
            renderCart();
        };
        const lineSubtotal = (item) => Math.max(n(item.qty) * n(item.unit_price), 0);
        const lineDiscount = (item) => item.discount_type === 'fixed'
            ? Math.min(lineSubtotal(item), n(item.discount_value))
            : item.discount_type === 'percent'
                ? lineSubtotal(item) * Math.min(n(item.discount_value), 100) / 100
                : 0;
        const lineTotal = (item) => Math.max(lineSubtotal(item) - lineDiscount(item), 0);
        const ticketDiscount = (base) => orderTicketDiscount(base, ensureActiveOrder());

        const currentLine = () => {
            if (!state.items.length) {
                state.selectedLine = null;
                return null;
            }
            const selected = state.items.find((item) => item.uid === state.selectedLine);
            if (selected) {
                return selected;
            }
            state.selectedLine = state.items[0].uid;
            return state.items[0];
        };

        const updateTouchUi = () => {
            if (keypadTargetOutput) {
                const label = labels[state.keypadTarget] || 'quantite';
                keypadTargetOutput.textContent = state.buffer ? `${label} : ${state.buffer}` : label;
            }
            touchButtons.forEach((button) => {
                const target = touchTargets[button.dataset.posAction] || null;
                button.classList.toggle('is-active', !!target && target === state.keypadTarget);
            });
        };

        const resetBuffer = () => {
            state.buffer = '';
            updateTouchUi();
        };

        const focusField = () => {
            const line = currentLine();
            if (!line) {
                return;
            }
            const field = {
                qty: 'qty',
                price: 'unit_price',
                line_discount: 'discount_value',
            }[state.keypadTarget];
            if (!field) {
                return;
            }
            const input = linesWrap.querySelector(`[data-line-input="${field}"][data-line="${line.uid}"]`);
            if (!input) {
                return;
            }
            requestAnimationFrame(() => {
                input.focus();
                if (typeof input.select === 'function') {
                    input.select();
                }
            });
        };

        const setTarget = (target, shouldFocus = true) => {
            state.keypadTarget = target;
            state.buffer = '';
            const line = currentLine();
            if (target === 'line_discount' && line && line.discount_type === 'none') {
                line.discount_type = 'fixed';
            }
            if (target === 'ticket_discount' && discountTypeInput.value === 'none') {
                discountTypeInput.value = 'fixed';
            }
            updateTouchUi();
            renderCart();
            if (shouldFocus) {
                if (target === 'ticket_discount') {
                    discountValueInput.focus();
                    discountValueInput.select();
                } else {
                    focusField();
                }
            }
        };

        const formatDateInput = (value) => {
            if (!value) {
                return 'Date du jour';
            }
            const parts = String(value).split('-');
            return parts.length === 3 ? `${parts[2]}/${parts[1]}/${parts[0]}` : value;
        };

        const updateContext = () => {
            if (filterCountOutput) {
                const visible = productGrid.querySelectorAll('[data-product-id]').length;
                filterCountOutput.textContent = `${visible} / ${productCatalog.length} articles`;
            }
            if (customerChip) {
                customerChip.textContent = customerInput.value
                    ? (customerInput.selectedOptions[0]?.textContent.trim() || 'Client')
                    : 'Client comptoir';
            }
            if (methodChip) {
                const order = activeOrder();
                const snapshot = order ? orderSnapshot(order) : null;
                methodChip.textContent = snapshot?.payment?.isMixed ? `Mixte (${snapshot.payment.payments.length})` : (methodLabels[methodInput.value] || 'Mode');
            }
            if (dateChip) {
                dateChip.textContent = formatDateInput(saleDateInput.value);
            }
            if (linesChip) {
                const order = activeOrder();
                linesChip.textContent = `${order?.label || 'Commande'} · ${state.items.length} ligne${state.items.length > 1 ? 's' : ''}`;
            }
        };

        const addProduct = (product) => {
            const existing = state.items.find((item) => item.product_id === String(product.id) && item.discount_type === 'none' && n(item.discount_value) === 0);
            if (existing) {
                existing.qty = n(existing.qty, 0) + 1;
                state.selectedLine = existing.uid;
                feedback.textContent = `${product.name} ajoute a nouveau au panier.`;
            } else {
                const line = {
                    uid: `${Date.now()}-${Math.random()}`,
                    product_id: String(product.id),
                    description: product.name,
                    qty: 1,
                    unit_price: n(product.price),
                    discount_type: 'none',
                    discount_value: 0,
                };
                state.items.push(line);
                state.selectedLine = line.uid;
                feedback.textContent = `${product.name} ajoute au panier.`;
            }
            resetBuffer();
            renderCart();
            renderProducts();
            searchInput.value = '';
            searchInput.focus();
        };

        const updateLine = (uid, key, value) => {
            const line = state.items.find((item) => item.uid === uid);
            if (!line) {
                return;
            }
            state.selectedLine = uid;
            line[key] = ['qty', 'unit_price', 'discount_value'].includes(key) ? n(value, 0) : value;
            if (key === 'discount_type' && value === 'none') {
                line.discount_value = 0;
            }
            resetBuffer();
            renderCart();
        };

        const removeLine = (uid) => {
            state.items = state.items.filter((item) => item.uid !== uid);
            currentLine();
            resetBuffer();
            renderCart();
        };

        const moveSelection = (step) => {
            if (!state.items.length) {
                return;
            }
            const index = state.items.findIndex((item) => item.uid === state.selectedLine);
            const next = Math.min(Math.max((index === -1 ? 0 : index) + step, 0), state.items.length - 1);
            state.selectedLine = state.items[next].uid;
            resetBuffer();
            renderCart();
        };

        const adjustQty = (delta) => {
            const line = currentLine();
            if (!line) {
                feedback.textContent = 'Selectionne une ligne du panier pour ajuster la quantite.';
                return;
            }
            line.qty = Math.max(0.001, n(line.qty, 0) + delta);
            resetBuffer();
            renderCart();
        };
        const targetRaw = () => {
            if (state.keypadTarget === 'ticket_discount') {
                return String(discountValueInput.value || '');
            }
            if (state.keypadTarget === 'qty') {
                return String(currentLine()?.qty ?? '');
            }
            if (state.keypadTarget === 'price') {
                return String(currentLine()?.unit_price ?? '');
            }
            return String(currentLine()?.discount_value ?? '');
        };

        const bufferNumber = (target) => {
            if (state.buffer === '') {
                return target === 'qty' ? 0.001 : 0;
            }
            const parsed = Number(state.buffer);
            if (!Number.isFinite(parsed)) {
                return target === 'qty' ? 0.001 : 0;
            }
            return target === 'qty' ? Math.max(parsed, 0.001) : Math.max(parsed, 0);
        };

        const applyBuffer = () => {
            if (state.keypadTarget === 'ticket_discount') {
                if (discountTypeInput.value === 'none') {
                    discountTypeInput.value = 'fixed';
                }
                const order = ensureActiveOrder();
                order.discount_type = discountTypeInput.value || 'fixed';
                order.discount_value = bufferNumber('ticket_discount');
                discountValueInput.value = order.discount_value;
                renderCart();
                return;
            }

            const line = currentLine();
            if (!line) {
                feedback.textContent = 'Selectionne une ligne du panier avant d utiliser le pave numerique.';
                return;
            }

            if (state.keypadTarget === 'line_discount' && line.discount_type === 'none') {
                line.discount_type = 'fixed';
            }
            if (state.keypadTarget === 'qty') {
                line.qty = bufferNumber('qty');
            }
            if (state.keypadTarget === 'price') {
                line.unit_price = bufferNumber('price');
            }
            if (state.keypadTarget === 'line_discount') {
                line.discount_value = bufferNumber('line_discount');
            }
            renderCart();
        };

        const applyToken = (token) => {
            if (token === '.') {
                if (state.buffer.includes('.')) {
                    return;
                }
                state.buffer = state.buffer ? `${state.buffer}.` : '0.';
            } else {
                state.buffer = state.buffer === '0' ? token : `${state.buffer}${token}`;
            }
            applyBuffer();
            updateTouchUi();
        };

        const applyAction = (action) => {
            if (action === 'plus') {
                adjustQty(1);
                return;
            }
            if (action === 'minus') {
                adjustQty(-1);
                return;
            }
            if (action === 'clear') {
                state.buffer = '';
                applyBuffer();
                updateTouchUi();
                return;
            }
            if (action === 'backspace') {
                if (!state.buffer) {
                    state.buffer = targetRaw();
                }
                state.buffer = state.buffer.slice(0, -1);
                applyBuffer();
                updateTouchUi();
            }
        };

        const renderProducts = () => {
            const term = norm(state.search);
            const filtered = productCatalog.filter((product) => {
                const categoryMatch = !state.category || String(product.category_id || '') === state.category;
                const searchMatch = !term || [product.name, product.sku, product.barcode, product.category_name].some((field) => norm(field).includes(term));
                return categoryMatch && searchMatch;
            });

            if (!filtered.length) {
                productGrid.innerHTML = '<div class="pos-empty" style="grid-column:1 / -1;">Aucun article ne correspond a cette recherche.</div>';
                updateContext();
                return;
            }

            productGrid.innerHTML = `<div class="pos-grid">${filtered.map((product) => `
                <button type="button" class="pos-product" data-product-id="${product.id}">
                    <div class="pos-product-top">
                        <div class="pos-product-thumb ${product.image_url ? 'has-image' : `tone-${tone(product)}`}">${thumbHtml(product)}</div>
                        <span class="badge ${product.type === 'service' ? 'badge-success' : 'badge-warning'}">${product.type === 'service' ? 'Service' : 'Stock'}</span>
                    </div>
                    <div>
                        <strong>${esc(product.name)}</strong>
                        <div class="meta">${esc(product.category_name || 'Sans categorie')} / ${esc(product.barcode || product.sku || 'Reference libre')}</div>
                        <div class="meta">${esc(product.unit || '')}</div>
                    </div>
                    <div class="price">${money(product.price)}</div>
                </button>
            `).join('')}</div>`;
            updateContext();
        };

        const renderCart = () => {
            const order = syncActiveOrderFromForm();
            const active = currentLine();
            const snapshot = orderSnapshot(order);
            const subtotal = snapshot.subtotal;
            const lineDiscounts = snapshot.lineDiscounts;
            const globalDiscount = orderTicketDiscount(Math.max(subtotal - lineDiscounts, 0), order);

            emptyState.style.display = state.items.length ? 'none' : 'block';
            linesWrap.innerHTML = state.items.map((item) => {
                const product = byId[item.product_id] || {};
                const activeClass = item.uid === active?.uid ? 'is-selected' : '';
                const tag = item.uid === active?.uid ? 'Ligne active' : 'Toucher pour selectionner';
                return `
                    <div class="pos-line ${activeClass}" data-line-card="${item.uid}">
                        <div class="pos-line-top">
                            <div class="pos-line-qty">${n(item.qty)}</div>
                            <div class="pos-line-main">
                                <div class="pos-line-title">${esc(item.description || product.name || 'Article')}</div>
                                <div class="pos-line-meta">${esc(product.barcode || product.sku || 'Sans code')} / ${esc(product.category_name || 'Sans categorie')}</div>
                                <div class="pos-line-tag">${tag}</div>
                            </div>
                            <div class="pos-line-amount">${money(lineTotal(item))}</div>
                            <button type="button" class="pos-remove" data-remove-line="${item.uid}">×</button>
                        </div>
                        <div class="pos-line-grid">
                            <div>
                                <label>Quantite</label>
                                <div class="pos-qty-box">
                                    <button type="button" data-qty-step="-1" data-line="${item.uid}">-</button>
                                    <input type="number" min="0.001" step="0.001" value="${n(item.qty)}" data-line-input="qty" data-line="${item.uid}">
                                    <button type="button" data-qty-step="1" data-line="${item.uid}">+</button>
                                </div>
                            </div>
                            <div>
                                <label>Prix unitaire</label>
                                <input type="number" min="0" step="0.01" value="${n(item.unit_price)}" data-line-input="unit_price" data-line="${item.uid}">
                            </div>
                            <div>
                                <label>Remise ligne</label>
                                <select data-line-input="discount_type" data-line="${item.uid}">
                                    <option value="none" ${item.discount_type === 'none' ? 'selected' : ''}>Aucune</option>
                                    <option value="fixed" ${item.discount_type === 'fixed' ? 'selected' : ''}>Montant</option>
                                    <option value="percent" ${item.discount_type === 'percent' ? 'selected' : ''}>%</option>
                                </select>
                            </div>
                            <div>
                                <label>Valeur</label>
                                <input type="number" min="0" step="0.01" value="${n(item.discount_value)}" data-line-input="discount_value" data-line="${item.uid}">
                            </div>
                            <div class="pos-line-total">
                                <label>Total ligne</label>
                                <div class="summary-box" style="margin:0; min-height:44px; width:100%; display:flex; align-items:center; justify-content:center;">${money(lineTotal(item))}</div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');

            const paymentInputs = snapshot.payment.payments.map((payment, index) => `
                <input type="hidden" name="payments[${index}][method]" value="${esc(payment.method)}">
                <input type="hidden" name="payments[${index}][amount]" value="${n(payment.amount)}">
                <input type="hidden" name="payments[${index}][cash_account_id]" value="${payment.cash_account_id ? esc(payment.cash_account_id) : ''}">
                <input type="hidden" name="payments[${index}][label]" value="${esc(payment.label || '')}">
            `).join('');

            hiddenInputs.innerHTML = state.items.map((item, index) => `
                <input type="hidden" name="items[${index}][product_id]" value="${item.product_id}">
                <input type="hidden" name="items[${index}][description]" value="${esc(item.description || '')}">
                <input type="hidden" name="items[${index}][qty]" value="${n(item.qty)}">
                <input type="hidden" name="items[${index}][unit_price]" value="${n(item.unit_price)}">
                <input type="hidden" name="items[${index}][discount_type]" value="${item.discount_type}">
                <input type="hidden" name="items[${index}][discount_value]" value="${n(item.discount_value)}">
            `).join('') + paymentInputs;

            subtotalOutput.textContent = money(subtotal);
            lineDiscountOutput.textContent = money(lineDiscounts);
            ticketDiscountOutput.textContent = money(globalDiscount);
            totalOutput.textContent = money(snapshot.total);
            if (totalHeadOutput) {
                totalHeadOutput.textContent = money(snapshot.total);
            }
            sourceDraftInput.value = order.draft_id ? String(order.draft_id) : '';
            renderPaymentLines(order, snapshot);
            renderOrderTabs();
            updateContext();
            updateTouchUi();
        };
        const handleTouch = (action) => {
            if (action === 'focus-search') {
                searchInput.focus();
                searchInput.select();
                return;
            }
            if (action === 'focus-customer') {
                customerInput.focus();
                return;
            }
            if (action === 'focus-note') {
                notesInput.focus();
                return;
            }
            if (action === 'counter-customer') {
                customerInput.value = '';
                updateContext();
                feedback.textContent = 'Client comptoir active pour ce ticket.';
                return;
            }
            if (action === 'clear-cart') {
                const order = ensureActiveOrder();
                order.items = [];
                order.selectedLine = null;
                order.payments = [createPaymentLine({}, order.method || defaultMethod)];
                order.cash_received_amount = 0;
                resetBuffer();
                renderCart();
                feedback.textContent = 'Panier vide.';
                return;
            }
            if (action === 'target-qty') { setTarget('qty'); }
            if (action === 'target-price') { setTarget('price'); }
            if (action === 'target-line-discount') { setTarget('line_discount'); }
            if (action === 'target-ticket-discount') { setTarget('ticket_discount'); }
        };

        productGrid.addEventListener('click', (event) => {
            const button = event.target.closest('[data-product-id]');
            if (!button) {
                return;
            }
            const product = byId[String(button.dataset.productId)];
            if (product) {
                addProduct(product);
            }
        });

        categoryRow.addEventListener('click', (event) => {
            const button = event.target.closest('[data-category]');
            if (!button) {
                return;
            }
            state.category = String(button.dataset.category || '');
            categoryRow.querySelectorAll('[data-category]').forEach((chip) => chip.classList.toggle('is-active', chip === button));
            renderProducts();
        });

        if (orderTabsWrap) {
            orderTabsWrap.addEventListener('click', (event) => {
                const removeButton = event.target.closest('[data-order-remove]');
                if (removeButton) {
                    removeOrder(removeButton.dataset.orderRemove);
                    return;
                }
                const button = event.target.closest('[data-order-switch]');
                if (!button) {
                    return;
                }
                switchOrder(button.dataset.orderSwitch);
            });
        }

        if (newOrderButton) {
            newOrderButton.addEventListener('click', () => {
                createNewOrder();
            });
        }

        if (saveDraftButton) {
            saveDraftButton.addEventListener('click', async () => {
                await saveCurrentOrder();
            });
        }
        if (addPaymentLineButton) {
            addPaymentLineButton.addEventListener('click', () => {
                addPaymentLine();
            });
        }

        const onLineField = (event) => {
            const field = event.target.dataset.lineInput;
            const uid = event.target.dataset.line;
            if (!field || !uid) {
                return;
            }
            updateLine(uid, field, event.target.value);
            state.keypadTarget = field === 'unit_price' ? 'price' : field === 'discount_value' ? 'line_discount' : 'qty';
            updateTouchUi();
        };

        linesWrap.addEventListener('input', onLineField);
        linesWrap.addEventListener('change', onLineField);
        linesWrap.addEventListener('focusin', (event) => {
            const field = event.target.dataset.lineInput;
            const uid = event.target.dataset.line;
            if (!field || !uid) {
                return;
            }
            state.selectedLine = uid;
            state.keypadTarget = field === 'unit_price' ? 'price' : field === 'discount_value' ? 'line_discount' : 'qty';
            resetBuffer();
            renderCart();
        });
        linesWrap.addEventListener('click', (event) => {
            const removeButton = event.target.closest('[data-remove-line]');
            if (removeButton) {
                removeLine(removeButton.dataset.removeLine);
                return;
            }
            const qtyButton = event.target.closest('[data-qty-step]');
            if (qtyButton) {
                state.selectedLine = qtyButton.dataset.line;
                adjustQty(Number(qtyButton.dataset.qtyStep || 0));
                return;
            }
            const lineCard = event.target.closest('[data-line-card]');
            if (lineCard) {
                state.selectedLine = lineCard.dataset.lineCard;
                resetBuffer();
                renderCart();
            }
        });
        paymentLinesWrap.addEventListener('change', (event) => {
            const field = event.target.dataset.paymentInput;
            const index = Number(event.target.dataset.paymentIndex);
            if (!field || Number.isNaN(index)) {
                return;
            }
            updatePaymentLine(index, field, event.target.value);
        });
        paymentLinesWrap.addEventListener('input', (event) => {
            const field = event.target.dataset.paymentInput;
            const index = Number(event.target.dataset.paymentIndex);
            if (field === 'amount' && !Number.isNaN(index)) {
                updatePaymentLine(index, field, event.target.value);
            }
        });
        paymentLinesWrap.addEventListener('click', (event) => {
            const removeButton = event.target.closest('[data-remove-payment]');
            if (removeButton) {
                removePaymentLine(Number(removeButton.dataset.removePayment));
            }
        });
        cashReceivedInput.addEventListener('input', () => {
            const order = ensureActiveOrder();
            order.cash_received_amount = n(cashReceivedInput.value, 0);
            renderCart();
        });

        searchInput.addEventListener('input', (event) => {
            state.search = event.target.value;
            feedback.textContent = state.search ? 'Resultats filtres en direct.' : 'Scanne un code-barres ou clique sur un article pour l ajouter au panier.';
            renderProducts();
        });
        searchInput.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter') {
                return;
            }
            event.preventDefault();
            const query = norm(searchInput.value);
            if (!query) {
                return;
            }
            const exact = productCatalog.find((product) => [product.barcode, product.sku].some((field) => norm(field) === query));
            if (exact) {
                addProduct(exact);
                return;
            }
            const match = productCatalog.find((product) => norm(product.name).includes(query));
            if (match) {
                addProduct(match);
                return;
            }
            feedback.textContent = 'Aucun article trouve pour ce scan ou cette recherche.';
        });

        document.addEventListener('click', (event) => {
            const touchButton = event.target.closest('[data-pos-action]');
            if (touchButton) {
                handleTouch(touchButton.dataset.posAction);
                return;
            }
            const keypadButton = event.target.closest('[data-keypad]');
            if (keypadButton) {
                applyToken(keypadButton.dataset.keypad);
                return;
            }
            const keypadActionButton = event.target.closest('[data-keypad-action]');
            if (keypadActionButton) {
                applyAction(keypadActionButton.dataset.keypadAction);
            }
        });

        [discountTypeInput, discountValueInput].forEach((element) => element.addEventListener('input', () => {
            if (document.activeElement === discountValueInput) {
                state.keypadTarget = 'ticket_discount';
                resetBuffer();
            }
            syncActiveOrderFromForm();
            renderCart();
        }));
        [customerInput, saleDateInput, referenceInput].forEach((element) => {
            if (!element) {
                return;
            }
            element.addEventListener('change', () => {
                syncActiveOrderFromForm();
                renderCart();
            });
        });
        methodInput.addEventListener('change', () => {
            const order = syncActiveOrderFromForm();
            if (order.payments.length === 1) {
                order.payments[0].method = order.method;
                order.payments[0].cash_account_id = defaultAccountIdForMethod(order.method);
            }
            renderCart();
        });
        if (notesInput) {
            notesInput.addEventListener('input', () => {
                syncActiveOrderFromForm();
                renderOrderTabs();
                updateContext();
            });
        }

        document.addEventListener('keydown', (event) => {
            const typing = ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName || '');
            const allowed = ['F2', 'F4', 'F6', 'F7', 'F8', 'Escape'];
            if (typing && !allowed.includes(event.key) && !(event.ctrlKey && event.key === 'Enter')) {
                return;
            }
            if (event.ctrlKey && event.key === 'Enter') {
                event.preventDefault();
                saleForm.requestSubmit();
                return;
            }
            if (event.key === 'F2') {
                event.preventDefault();
                searchInput.focus();
                searchInput.select();
                return;
            }
            if (event.key === 'F4') {
                event.preventDefault();
                customerInput.focus();
                return;
            }
            if (event.key === 'F6') {
                event.preventDefault();
                setTarget('price');
                return;
            }
            if (event.key === 'F7') {
                event.preventDefault();
                setTarget('line_discount');
                return;
            }
            if (event.key === 'F8') {
                event.preventDefault();
                setTarget('ticket_discount');
                return;
            }
            if (event.key === 'Escape') {
                event.preventDefault();
                state.search = '';
                searchInput.value = '';
                renderProducts();
                feedback.textContent = 'Recherche reinitialisee.';
                return;
            }
            if (typing) {
                return;
            }
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                moveSelection(1);
                return;
            }
            if (event.key === 'ArrowUp') {
                event.preventDefault();
                moveSelection(-1);
                return;
            }
            if (event.key === 'Delete') {
                event.preventDefault();
                if (state.selectedLine) {
                    removeLine(state.selectedLine);
                }
                return;
            }
            if (event.key === '+') {
                event.preventDefault();
                adjustQty(1);
                return;
            }
            if (event.key === '-') {
                event.preventDefault();
                adjustQty(-1);
            }
        });

        saleForm.addEventListener('submit', (event) => {
            const order = syncActiveOrderFromForm();
            const snapshot = orderSnapshot(order);
            sourceDraftInput.value = order.draft_id ? String(order.draft_id) : '';
            if (!state.items.length) {
                event.preventDefault();
                feedback.textContent = 'Ajoute au moins un article avant de valider le ticket.';
                searchInput.focus();
                return;
            }
            if (Math.abs(snapshot.payment.paid - snapshot.total) > 0.01) {
                event.preventDefault();
                feedback.textContent = 'Le total des reglements doit couvrir exactement le ticket.';
                return;
            }
            if (snapshot.payment.cashAllocated > 0 && snapshot.payment.cashReceived + 0.009 < snapshot.payment.cashAllocated) {
                event.preventDefault();
                feedback.textContent = 'Le montant recu en especes doit couvrir la part cash du ticket.';
            }
        });

        loadOrderIntoForm(ensureActiveOrder());
        renderProducts();
        renderCart();
        updateContext();
        updateTouchUi();
        searchInput.focus();
    </script>
@endsection



