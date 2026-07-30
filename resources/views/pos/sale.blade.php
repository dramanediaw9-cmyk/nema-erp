@extends('layouts.app')

@section('title', 'Point de vente - Caisse detail')
@section('page-title', 'Point de vente')

@section('content')
    @php
        $customerLabel = $businessVocabulary['client'] ?? 'Client';
        $productLabel = $businessVocabulary['product'] ?? 'Produit';
        $productsLabel = $businessVocabulary['products'] ?? 'Produits';
        $saleLabel = $businessVocabulary['sale'] ?? 'Vente';
        $salesLabel = $businessVocabulary['sales'] ?? 'Ventes';
        $stockLabel = $businessVocabulary['stock'] ?? 'Stock';
        $counterCustomerLabel = in_array($businessVocabulary['profile_key'] ?? '', ['food_store', 'general_trade', 'pharmacy_parapharmacy'], true)
            ? 'Client comptoir'
            : $customerLabel.' comptoir';
    @endphp
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
        .pos-sync-strip {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
            padding: 10px 12px;
            border-radius: 12px;
            border: 1px solid #3b4169;
            background: linear-gradient(180deg, rgba(27, 31, 54, 0.96) 0%, rgba(18, 21, 38, 0.98) 100%);
        }
        .pos-sync-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 116px;
            padding: 8px 12px;
            border-radius: 999px;
            border: 1px solid #46507f;
            background: #2a3050;
            color: #eef2ff;
            font-size: 12px;
            font-weight: 900;
            letter-spacing: .05em;
            text-transform: uppercase;
        }
        .pos-sync-badge.is-online {
            border-color: rgba(74, 222, 128, 0.35);
            background: rgba(20, 83, 45, 0.72);
            color: #dcfce7;
        }
        .pos-sync-badge.is-offline {
            border-color: rgba(251, 191, 36, 0.34);
            background: rgba(120, 53, 15, 0.78);
            color: #fef3c7;
        }
        .pos-sync-badge.is-syncing {
            border-color: rgba(96, 165, 250, 0.35);
            background: rgba(30, 64, 175, 0.7);
            color: #dbeafe;
        }
        .pos-sync-copy {
            display: grid;
            gap: 2px;
            flex: 1 1 240px;
            min-width: 0;
        }
        .pos-sync-summary {
            color: #f8faff;
            font-size: 13px;
            font-weight: 800;
        }
        .pos-sync-last {
            color: #aab3dc;
            font-size: 11px;
        }
        .pos-sync-queue {
            display: grid;
            gap: 8px;
        }
        .pos-sync-item {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            padding: 10px 12px;
            border-radius: 12px;
            border: 1px solid rgba(62, 70, 112, 0.9);
            background: rgba(21, 25, 45, 0.92);
        }
        .pos-sync-item strong {
            color: #fff;
            font-size: 12px;
        }
        .pos-sync-item-meta {
            color: #aab3dc;
            font-size: 11px;
            margin-top: 3px;
        }
        .pos-sync-item-error {
            margin-top: 6px;
            color: #ffd3dd;
            font-size: 11px;
        }
        .pos-sync-item-actions {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .pos-sync-item-actions button {
            border: 1px solid #48517e;
            border-radius: 999px;
            background: #252b48;
            color: #eef1ff;
            padding: 7px 11px;
            font-size: 11px;
            font-weight: 800;
            cursor: pointer;
        }
        .pos-sync-item-actions button:hover {
            background: #31385d;
        }

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
        .pos-product.is-unavailable,
        .pos-product:disabled {
            opacity: .52;
            cursor: not-allowed;
            transform: none;
            border-color: #5a607f;
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
        .badge-danger { background: rgba(220, 38, 38, .88); color: #fff1f2; }
        .pos-stock-line.is-empty {
            color: #fecaca;
            font-weight: 700;
        }
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
        .pos-line-tag.is-danger {
            background: rgba(127, 29, 29, 0.7);
            color: #fee2e2;
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
        .pos-actions .button-primary:disabled {
            background: #585d7f;
            border-color: #6f7495;
            color: #dadcf0;
            cursor: not-allowed;
            opacity: .6;
        }
        .pos-actions .button-primary.is-blocked {
            background: linear-gradient(180deg, #a96d9b 0%, #83537f 100%);
            border-color: #ba8cb2;
            color: #fff6fd;
            box-shadow: 0 0 0 1px rgba(255, 222, 248, 0.12);
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
        .pos-payment-panel.is-invalid {
            border-color: #cc6b83;
            box-shadow: inset 0 0 0 1px rgba(204, 107, 131, .18);
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
        .pos-payment-inline-error {
            display: none;
            padding: 9px 10px;
            border-radius: 8px;
            border: 1px solid #9a4f65;
            background: rgba(154, 79, 101, .16);
            color: #ffd7e1;
            font-size: 12px;
            font-weight: 700;
        }
        .pos-payment-inline-error.is-visible {
            display: block;
        }
        #cash_received_amount.is-invalid {
            border-color: #d97c94;
            background: #2f2130;
            color: #fff5f8;
        }
        .pos-payment-total-row.is-warning.is-invalid strong {
            color: #ff9ab1;
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
        :root {
            --pos-bg: #0b1220;
            --pos-bg-soft: #111a2a;
            --pos-panel: #162133;
            --pos-panel-soft: #1b2940;
            --pos-panel-strong: #0f1828;
            --pos-line: #1e2b42;
            --pos-line-border: #32445f;
            --pos-stroke: #3a4a63;
            --pos-text: #f7f5ef;
            --pos-muted: #9fb0c9;
            --pos-accent: #1f8b8a;
            --pos-accent-strong: #14696c;
            --pos-accent-soft: #b5f0e8;
            --pos-warm: #d09a45;
            --pos-warm-soft: #ffe4b7;
            --pos-danger: #b95a6f;
            --pos-shadow: 0 20px 44px rgba(3, 9, 18, 0.34);
        }
        body {
            background:
                radial-gradient(circle at top left, rgba(31, 139, 138, 0.14), transparent 26%),
                radial-gradient(circle at 82% 12%, rgba(208, 154, 69, 0.12), transparent 24%),
                linear-gradient(180deg, #0b1220 0%, #08101c 100%);
            color: var(--pos-text);
        }
        .pos-shell {
            background:
                radial-gradient(circle at top left, rgba(31, 139, 138, 0.16), transparent 24%),
                radial-gradient(circle at 78% 16%, rgba(208, 154, 69, 0.14), transparent 20%),
                linear-gradient(180deg, #101a2b 0%, #09111d 100%);
        }
        .pos-kicker {
            padding: 14px 18px 16px;
            background: linear-gradient(135deg, rgba(20, 32, 51, 0.98) 0%, rgba(14, 24, 39, 0.98) 100%);
            border-top: 4px solid var(--pos-warm);
            border-bottom: 1px solid rgba(181, 240, 232, 0.08);
            box-shadow: 0 18px 36px rgba(3, 9, 18, 0.28);
        }
        .pos-kicker h2 {
            font-size: 15px;
            letter-spacing: .16em;
        }
        .pos-kicker .muted,
        .pos-search .help,
        .pos-browser .help,
        .pos-cart .help,
        .pos-payment-help {
            color: var(--pos-muted);
        }
        .pos-kicker .button-secondary,
        .pos-actions .button-secondary {
            background: linear-gradient(180deg, #22314a 0%, #182439 100%);
            color: #f3f7ff;
            border: 1px solid rgba(181, 240, 232, 0.14);
            box-shadow: none;
        }
        .pos-workspace {
            background: transparent;
        }
        .pos-browser {
            background: linear-gradient(180deg, rgba(20, 30, 47, 0.96) 0%, rgba(15, 23, 37, 0.96) 100%);
            padding: 14px 14px 16px;
            gap: 10px;
        }
        .pos-cart {
            background: linear-gradient(180deg, rgba(18, 27, 43, 0.98) 0%, rgba(12, 19, 31, 0.98) 100%);
            border-right: 1px solid rgba(181, 240, 232, 0.08);
            box-shadow: inset -1px 0 0 rgba(255, 255, 255, 0.03);
        }
        .pos-toolbar {
            gap: 12px;
        }
        .pos-search input,
        .pos-cart-head-grid input,
        .pos-cart-head-grid select,
        .pos-cart-body input,
        .pos-cart-body textarea,
        .pos-cart-body select {
            background: linear-gradient(180deg, rgba(13, 22, 36, 0.98) 0%, rgba(10, 18, 29, 0.98) 100%);
            border: 1px solid rgba(181, 240, 232, 0.12);
            border-radius: 12px;
            color: var(--pos-text);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.03);
        }
        .pos-search input:focus,
        .pos-cart-head-grid input:focus,
        .pos-cart-head-grid select:focus,
        .pos-cart-body input:focus,
        .pos-cart-body textarea:focus,
        .pos-cart-body select:focus {
            outline: none;
            border-color: rgba(181, 240, 232, 0.34);
            box-shadow: 0 0 0 3px rgba(31, 139, 138, 0.16);
        }
        .pos-summary-card,
        .summary-box {
            border-radius: 16px;
            border: 1px solid rgba(181, 240, 232, 0.12);
            background: linear-gradient(180deg, rgba(31, 45, 68, 0.98) 0%, rgba(22, 33, 51, 0.98) 100%);
            box-shadow: var(--pos-shadow);
        }
        .pos-summary-card strong,
        .summary-box strong {
            color: #c9d7e6;
        }
        .pos-touch-btn,
        .pos-chip {
            border: 1px solid rgba(181, 240, 232, 0.12);
            border-radius: 12px;
            background: linear-gradient(180deg, rgba(29, 43, 64, 0.98) 0%, rgba(21, 31, 47, 0.98) 100%);
            color: #eff5ff;
            box-shadow: 0 10px 18px rgba(3, 9, 18, 0.2);
        }
        .pos-touch-btn:hover,
        .pos-chip:hover {
            background: linear-gradient(180deg, rgba(37, 55, 81, 0.98) 0%, rgba(26, 39, 60, 0.98) 100%);
            border-color: rgba(181, 240, 232, 0.22);
        }
        .pos-touch-btn.is-active,
        .pos-chip.is-active,
        .pos-order-tab.is-active,
        .pos-actions .button-primary {
            background: linear-gradient(135deg, var(--pos-accent) 0%, var(--pos-accent-strong) 100%);
            border-color: rgba(181, 240, 232, 0.42);
            color: #fff;
        }
        .pos-meta-chip,
        .pos-cart-context-chip,
        .pos-shortcuts span {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(181, 240, 232, 0.1);
            color: #dce7f4;
            border-radius: 999px;
        }
        #pos-product-grid {
            padding: 10px;
            background: linear-gradient(180deg, rgba(17, 25, 40, 0.98) 0%, rgba(12, 18, 30, 0.98) 100%);
            border: 1px solid rgba(181, 240, 232, 0.1);
            border-radius: 18px;
            box-shadow: var(--pos-shadow);
        }
        .pos-product {
            border: 1px solid rgba(181, 240, 232, 0.1);
            border-radius: 16px;
            background: linear-gradient(180deg, rgba(31, 43, 65, 0.98) 0%, rgba(19, 28, 43, 0.98) 100%);
            min-height: 150px;
            padding: 8px;
            box-shadow: 0 16px 26px rgba(3, 9, 18, 0.22);
        }
        .pos-product:hover {
            transform: translateY(-3px);
            border-color: rgba(181, 240, 232, 0.24);
            box-shadow: 0 20px 30px rgba(3, 9, 18, 0.28);
        }
        .pos-product.is-unavailable,
        .pos-product:disabled {
            transform: none;
            box-shadow: none;
        }
        .pos-product-thumb {
            border-radius: 12px;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.28);
        }
        .pos-product-thumb.tone-1 { background: linear-gradient(135deg, #f7ead8 0%, #f0cf9f 100%); }
        .pos-product-thumb.tone-2 { background: linear-gradient(135deg, #d8f3ef 0%, #9fdfd6 100%); }
        .pos-product-thumb.tone-3 { background: linear-gradient(135deg, #fce8d7 0%, #f4bb8a 100%); }
        .pos-product-thumb.tone-4 { background: linear-gradient(135deg, #fff1cb 0%, #f4d58d 100%); }
        .pos-product-thumb.tone-5 { background: linear-gradient(135deg, #e2edf9 0%, #b8cadf 100%); }
        .pos-cart-head {
            padding: 14px 14px 12px;
            border-bottom: 1px solid rgba(181, 240, 232, 0.1);
            background: linear-gradient(180deg, rgba(21, 32, 50, 0.98) 0%, rgba(14, 23, 37, 0.98) 100%);
        }
        .pos-nav-tab,
        .pos-nav-counter {
            background: linear-gradient(180deg, rgba(31, 43, 65, 0.98) 0%, rgba(20, 31, 47, 0.98) 100%);
            border: 1px solid rgba(181, 240, 232, 0.1);
            border-bottom: none;
            color: #f6f8fd;
        }
        .pos-nav-tab.is-active {
            background: linear-gradient(180deg, rgba(15, 24, 38, 0.98) 0%, rgba(11, 18, 29, 0.98) 100%);
        }
        .pos-nav-counter {
            background: linear-gradient(135deg, rgba(31, 139, 138, 0.18) 0%, rgba(20, 105, 108, 0.16) 100%);
        }
        .pos-order-tab {
            border: 1px solid rgba(181, 240, 232, 0.1);
            background: linear-gradient(180deg, rgba(31, 43, 65, 0.98) 0%, rgba(20, 31, 47, 0.98) 100%);
            color: #eef5ff;
        }
        .pos-order-tab small,
        .pos-order-tab.is-active small {
            color: rgba(255, 255, 255, 0.82);
        }
        .pos-order-tab-remove,
        .pos-payment-remove,
        .pos-remove {
            background: rgba(185, 90, 111, 0.16);
            border: 1px solid rgba(185, 90, 111, 0.22);
            color: #ffe2e8;
        }
        .pos-order-add {
            border: 1px solid rgba(181, 240, 232, 0.24);
            background: rgba(31, 139, 138, 0.12);
            color: #d9fff3;
        }
        .doc-chip {
            background: rgba(208, 154, 69, 0.16);
            color: #ffe2b5;
            border: 1px solid rgba(208, 154, 69, 0.28);
        }
        .pos-cart-head .summary-box {
            background: linear-gradient(180deg, rgba(31, 45, 68, 0.98) 0%, rgba(19, 29, 45, 0.98) 100%);
            border: 1px solid rgba(181, 240, 232, 0.12);
        }
        .pos-cart-body {
            padding: 12px 14px 16px;
            gap: 12px;
            background: linear-gradient(180deg, rgba(16, 24, 38, 0.98) 0%, rgba(11, 18, 29, 0.98) 100%);
        }
        .alert-error {
            border: 1px solid rgba(185, 90, 111, 0.28);
            background: rgba(185, 90, 111, 0.12);
            color: #ffe2e8;
        }
        .pos-line {
            border: 1px solid rgba(181, 240, 232, 0.1);
            border-radius: 14px;
            background: linear-gradient(180deg, rgba(31, 43, 65, 0.96) 0%, rgba(20, 31, 47, 0.96) 100%);
            box-shadow: 0 14px 24px rgba(3, 9, 18, 0.18);
        }
        .pos-line:hover {
            background: linear-gradient(180deg, rgba(37, 53, 78, 0.98) 0%, rgba(24, 36, 54, 0.98) 100%);
        }
        .pos-line.is-selected {
            border-color: rgba(181, 240, 232, 0.34);
            box-shadow: inset 0 0 0 1px rgba(31, 139, 138, 0.26), 0 18px 28px rgba(3, 9, 18, 0.22);
            background: linear-gradient(180deg, rgba(24, 43, 59, 0.98) 0%, rgba(17, 33, 46, 0.98) 100%);
        }
        .pos-line-qty {
            border-radius: 8px;
            background: linear-gradient(135deg, var(--pos-warm) 0%, #ad7930 100%);
        }
        .pos-line-tag {
            background: rgba(31, 139, 138, 0.12);
            color: #bff5ee;
        }
        .pos-qty-box button {
            background: linear-gradient(180deg, rgba(33, 47, 71, 0.98) 0%, rgba(22, 32, 50, 0.98) 100%);
            border: 1px solid rgba(181, 240, 232, 0.1);
        }
        .pos-keypad,
        .pos-summary,
        .pos-payment-panel {
            border: 1px solid rgba(181, 240, 232, 0.1);
            border-radius: 16px;
            background: linear-gradient(180deg, rgba(20, 30, 47, 0.98) 0%, rgba(13, 22, 35, 0.98) 100%);
            box-shadow: var(--pos-shadow);
        }
        .pos-keypad-head strong,
        .pos-payment-total-row strong,
        .pos-summary-row strong {
            color: #fff;
        }
        .pos-keypad-target {
            color: var(--pos-accent-soft);
        }
        .pos-key {
            border-radius: 12px;
            background: linear-gradient(180deg, rgba(37, 53, 78, 0.98) 0%, rgba(25, 37, 56, 0.98) 100%);
            color: #fff;
            box-shadow: 0 10px 18px rgba(3, 9, 18, 0.22);
        }
        .pos-key:hover {
            background: linear-gradient(180deg, rgba(46, 65, 95, 0.98) 0%, rgba(31, 46, 69, 0.98) 100%);
        }
        .pos-key.is-action {
            background: linear-gradient(135deg, rgba(31, 139, 138, 0.24) 0%, rgba(20, 105, 108, 0.24) 100%);
        }
        .pos-key.is-danger {
            background: linear-gradient(135deg, rgba(185, 90, 111, 0.28) 0%, rgba(138, 63, 83, 0.28) 100%);
        }
        .pos-summary-row,
        .pos-payment-total-row {
            color: #d0d9e7;
        }
        .pos-actions .button-primary {
            box-shadow: 0 18px 28px rgba(20, 105, 108, 0.28);
        }
        .pos-actions .button-primary:disabled {
            background: linear-gradient(180deg, rgba(86, 96, 121, 0.8) 0%, rgba(71, 80, 102, 0.8) 100%);
            border-color: rgba(181, 240, 232, 0.08);
            color: #d7deec;
        }
        .pos-payment-panel.is-invalid {
            border-color: rgba(185, 90, 111, 0.38);
            box-shadow: inset 0 0 0 1px rgba(185, 90, 111, 0.2), var(--pos-shadow);
        }
        .pos-payment-line {
            border-radius: 14px;
            border: 1px solid rgba(181, 240, 232, 0.1);
            background: rgba(16, 24, 39, 0.88);
        }
        .pos-payment-line.is-cash {
            border-color: rgba(31, 139, 138, 0.3);
            background: rgba(15, 37, 45, 0.92);
        }
        .pos-payment-total-row.is-warning strong {
            color: #f3d08a;
        }
        .pos-payment-total-row.is-success strong {
            color: #9fe3cb;
        }
        .pos-payment-inline-error {
            border-radius: 12px;
            border: 1px solid rgba(185, 90, 111, 0.28);
            background: rgba(185, 90, 111, 0.14);
            color: #ffe2e8;
        }
        #cash_received_amount.is-invalid {
            border-color: rgba(185, 90, 111, 0.42);
            background: rgba(55, 23, 31, 0.92);
            color: #fff5f8;
        }
        .pos-quick-actions {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        .pos-quick-btn {
            min-height: 52px;
            border-radius: 14px;
            border: 1px solid rgba(255, 255, 255, 0.14);
            background: #2b3153;
            color: #f8f9ff;
            font-weight: 800;
            font-size: 15px;
            letter-spacing: .01em;
            cursor: pointer;
        }
        .pos-quick-btn.is-primary {
            background: linear-gradient(180deg, #2f8a7d 0%, #23695f 100%);
            border-color: rgba(74, 222, 128, 0.45);
        }
        .pos-quick-btn.is-accent {
            background: linear-gradient(180deg, #3b5aa8 0%, #30488a 100%);
            border-color: rgba(147, 197, 253, 0.45);
        }
        .pos-quick-btn.is-warm {
            background: linear-gradient(180deg, #9a6a2f 0%, #7a5121 100%);
            border-color: rgba(251, 191, 36, 0.45);
        }
        .pos-help-chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 18px;
            height: 18px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 800;
            border: 1px solid rgba(255, 255, 255, 0.35);
            color: #f8f9ff;
            margin-left: 6px;
            cursor: help;
        }
        .pos-kicker {
            padding: 8px 12px !important;
            min-height: 0;
        }
        .pos-kicker .muted {
            display: none;
        }
        .pos-kicker-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }
        .pos-kicker-actions .button,
        .pos-mode-button {
            min-height: 38px;
            padding: 8px 12px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 800;
        }
        .pos-mode-button {
            border: 1px solid rgba(181, 240, 232, 0.18);
            background: linear-gradient(180deg, #1f8b8a 0%, #14696c 100%);
            color: #ffffff;
            cursor: pointer;
        }
        .pos-status-strip {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 8px;
        }
        .pos-status-item {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            column-gap: 8px;
            row-gap: 2px;
            align-items: center;
            min-height: 44px;
            padding: 8px 10px;
            border: 1px solid rgba(181, 240, 232, 0.12);
            border-radius: 10px;
            background: rgba(9, 17, 29, 0.72);
        }
        .pos-status-item strong {
            color: #ffffff;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .08em;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .pos-status-item small {
            grid-column: 2;
            color: var(--pos-muted);
            font-size: 11px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .pos-status-dot {
            width: 10px;
            height: 10px;
            border-radius: 999px;
            background: #9ef0c0;
            box-shadow: 0 0 0 4px rgba(158, 240, 192, 0.10);
        }
        .pos-status-item.is-warning .pos-status-dot {
            background: #ffcf8b;
            box-shadow: 0 0 0 4px rgba(255, 207, 139, 0.10);
        }
        .pos-status-item.is-muted .pos-status-dot {
            background: #95a1d5;
            box-shadow: 0 0 0 4px rgba(149, 161, 213, 0.10);
        }
        body.pos-fullscreen-mode .pos-kicker {
            position: sticky;
            top: 0;
            z-index: 60;
            box-shadow: 0 12px 28px rgba(3, 9, 18, 0.35);
        }
        body.pos-fullscreen-mode .pos-shell {
            min-height: 100vh;
        }
        body.pos-fullscreen-mode .pos-browser {
            padding-top: 8px;
        }
        @media (max-width: 920px) {
            .pos-status-strip {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        @media (max-width: 760px) {
            .pos-status-strip {
                grid-template-columns: 1fr;
            }
        }
        /* Desktop caisse: toutes les commandes essentielles restent dans le viewport. */
        @media (min-width: 921px) {
            html,
            body {
                width: 100%;
                height: 100%;
                min-height: 0;
                overflow: hidden !important;
                overscroll-behavior: none;
            }
            .shell,
            .main {
                width: 100%;
                height: 100dvh;
                min-height: 0 !important;
                max-height: 100dvh;
                overflow: hidden !important;
            }
            .pos-shell {
                width: 100%;
                height: 100dvh;
                min-height: 0;
                grid-template-rows: auto minmax(0, 1fr);
                overflow: hidden;
            }
            .pos-kicker {
                min-height: 50px;
                padding: 6px 10px !important;
                flex-wrap: nowrap;
            }
            .pos-kicker-actions {
                flex-wrap: nowrap;
            }
            .pos-kicker-actions .button,
            .pos-mode-button {
                min-height: 34px;
                padding: 6px 10px;
                white-space: nowrap;
            }
            .pos-workspace {
                width: 100%;
                height: 100%;
                min-height: 0;
                grid-template-columns: minmax(500px, 580px) minmax(0, 1fr);
                overflow: hidden;
            }
            .pos-browser {
                height: 100%;
                min-height: 0;
                padding: 6px 8px 8px;
                gap: 3px;
                grid-template-rows: auto auto auto auto auto auto auto auto auto minmax(0, 1fr);
                overflow: hidden;
            }
            .pos-quick-actions {
                display: grid !important;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 4px;
                margin: 0;
            }
            .pos-quick-btn {
                min-height: 32px;
                padding: 5px 7px;
                border-radius: 8px;
                font-size: 11px;
            }
            .pos-session-strip {
                display: flex !important;
                gap: 4px;
                flex-wrap: nowrap;
                overflow: hidden;
            }
            .pos-meta-chip {
                min-width: 0;
                padding: 3px 6px;
                font-size: 9px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .pos-status-strip {
                display: grid !important;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 4px;
            }
            .pos-status-item {
                min-height: 30px;
                padding: 3px 5px;
                column-gap: 5px;
            }
            .pos-status-item strong {
                font-size: 9px;
                letter-spacing: .03em;
            }
            .pos-status-item small {
                font-size: 8px;
            }
            .pos-status-dot {
                width: 7px;
                height: 7px;
                box-shadow: none;
            }
            .pos-sync-strip {
                display: flex !important;
                min-height: 32px;
                gap: 6px;
                padding: 3px 6px;
                flex-wrap: nowrap;
            }
            .pos-sync-badge {
                min-width: 72px;
                padding: 3px 6px;
                font-size: 9px;
            }
            .pos-sync-copy {
                gap: 0;
            }
            .pos-sync-summary {
                font-size: 10px;
            }
            .pos-sync-last {
                font-size: 8px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .pos-sync-strip .button {
                min-height: 28px;
                padding: 4px 7px;
                font-size: 9px;
                white-space: nowrap;
            }
            .pos-sync-queue {
                display: grid !important;
                max-height: 36px;
                gap: 3px;
                overflow: hidden;
            }
            .pos-sync-item {
                padding: 3px 6px;
                font-size: 9px;
            }
            .pos-shortcuts {
                display: flex !important;
                gap: 4px;
                flex-wrap: nowrap;
                overflow: hidden;
            }
            .pos-shortcuts span {
                padding: 2px 5px;
                font-size: 8px;
                white-space: nowrap;
            }
            .pos-toolbar {
                gap: 8px;
            }
            .pos-search {
                gap: 2px;
            }
            .pos-search input {
                padding: 9px 11px;
                font-size: 14px;
            }
            .pos-summary-card {
                min-width: 120px;
                padding: 7px 9px;
            }
            .pos-summary-card .value {
                font-size: 18px;
            }
            .pos-summary-card .help {
                display: none;
            }
            .pos-touch-strip {
                gap: 5px;
            }
            .pos-touch-btn,
            .pos-chip {
                padding: 7px 8px;
                min-height: 34px;
                font-size: 12px;
            }
            .pos-chip-row {
                gap: 5px;
                flex-wrap: nowrap;
                overflow: hidden;
            }
            #pos-product-grid {
                height: 100%;
                min-height: 190px;
                padding: 6px;
                overflow: hidden;
            }
            #pos-product-grid > .pos-grid + .pos-empty {
                display: none;
            }
            .pos-grid {
                height: 100%;
                min-height: 0;
                grid-template-columns: repeat(auto-fit, minmax(112px, 1fr));
                grid-auto-rows: minmax(0, 1fr);
                gap: 6px;
                overflow: hidden;
            }
            .pos-product {
                min-height: 0;
                height: 100%;
                padding: 6px;
                gap: 4px;
                overflow: hidden;
            }
            .pos-product-thumb {
                height: 54px;
            }
            .pos-product strong {
                font-size: 11px;
                line-height: 1.15;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }
            .pos-product .meta {
                display: none;
            }
            .pos-product .price {
                font-size: 12px;
            }
            .pos-cart {
                width: 100%;
                height: 100%;
                min-height: 0;
                grid-template-rows: auto minmax(0, 1fr);
                overflow: hidden;
            }
            .pos-cart-head {
                padding: 6px 9px 7px;
            }
            .pos-nav,
            .pos-order-switcher,
            .pos-cart-title-row {
                margin-bottom: 5px;
            }
            .pos-nav-tab,
            .pos-nav-counter {
                min-height: 26px;
                padding: 0 9px;
                font-size: 11px;
            }
            .pos-order-tab,
            .pos-order-add {
                padding: 6px 9px;
                font-size: 11px;
            }
            .doc-chip {
                display: inline-flex;
                margin: 0 0 4px;
                padding: 3px 6px;
                font-size: 8px;
            }
            .pos-cart-context {
                display: flex;
                gap: 3px;
                flex-wrap: nowrap;
                overflow: hidden;
            }
            .pos-cart-context-chip {
                min-width: 0;
                padding: 3px 5px;
                font-size: 8px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .pos-cart-title-row h3 {
                font-size: 14px;
            }
            .pos-cart-head .summary-box {
                min-width: 112px;
                padding: 6px 8px;
            }
            .pos-cart-head .summary-box .value {
                font-size: 17px;
            }
            .pos-cart-head-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 5px;
                margin-top: 4px;
            }
            .pos-cart-head-grid label,
            .pos-cart-body label {
                margin-bottom: 2px;
                font-size: 9px;
                letter-spacing: .05em;
            }
            .pos-cart-head-grid input,
            .pos-cart-head-grid select,
            .pos-cart-body input,
            .pos-cart-body textarea,
            .pos-cart-body select {
                min-height: 32px;
                padding: 6px 7px;
                border-radius: 8px;
                font-size: 11px;
            }
            .pos-sale-form {
                height: 100%;
                min-height: 0;
                overflow: hidden;
            }
            .pos-cart-body {
                position: relative;
                height: 100%;
                min-height: 0;
                padding: 6px 9px 8px;
                gap: 6px;
                grid-template-columns: minmax(0, 1.12fr) minmax(150px, .88fr);
                grid-template-rows: minmax(74px, 1fr) auto auto auto;
                overflow: hidden;
            }
            .pos-cart-body > .alert-error {
                grid-column: 1 / -1;
            }
            .pos-cart-body > div:has(#pos-lines) {
                grid-column: 1;
                grid-row: 1;
                min-height: 0;
                overflow: hidden;
            }
            .pos-lines {
                height: 100%;
                max-height: 100%;
                min-height: 0;
                overflow: auto;
                scrollbar-width: thin;
            }
            .pos-empty {
                padding: 12px 10px;
                font-size: 11px;
            }
            .pos-line {
                padding: 6px 7px;
                gap: 4px;
            }
            .pos-line-title {
                font-size: 11px;
            }
            .pos-keypad {
                grid-column: 2;
                grid-row: 1;
                min-height: 0;
                padding: 6px;
                gap: 5px;
                overflow: hidden;
            }
            .pos-keypad-head {
                gap: 6px;
            }
            .pos-keypad-grid {
                gap: 3px;
            }
            .pos-key {
                min-height: 28px;
                border-radius: 7px;
                font-size: 13px;
            }
            .pos-summary {
                grid-column: 1;
                grid-row: 2;
                padding: 6px 8px;
                gap: 3px;
            }
            .pos-summary-row {
                font-size: 10px;
            }
            .pos-summary-row strong {
                font-size: 13px;
            }
            .pos-summary-controls {
                grid-column: 2;
                grid-row: 2;
                grid-template-columns: 1fr;
                gap: 4px;
            }
            .pos-inline-grid {
                grid-template-columns: 1fr 74px;
                gap: 4px;
            }
            .pos-note {
                min-height: 32px;
                height: 32px;
                resize: none;
            }
            .pos-payment-panel {
                grid-column: 1 / -1;
                grid-row: 3;
                padding: 6px 8px;
                gap: 5px;
                overflow: hidden;
            }
            .pos-payment-head .pos-payment-help,
            .pos-payment-grid .pos-payment-help {
                display: block;
                font-size: 8px;
                line-height: 1.15;
            }
            .pos-payment-line {
                padding: 5px;
                gap: 5px;
            }
            .pos-payment-grid {
                gap: 6px;
            }
            .pos-payment-total-row {
                padding: 2px 0;
                font-size: 10px;
            }
            .pos-payment-total-row strong {
                font-size: 11px;
            }
            .pos-payment-remove {
                width: 32px;
                height: 32px;
            }
            .pos-actions {
                grid-column: 1 / -1;
                grid-row: 4;
                gap: 6px;
            }
            .pos-actions .button {
                min-height: 36px;
                padding: 8px 9px;
                font-size: 12px;
            }
        }
        @media (min-width: 921px) and (max-height: 700px) {
            .pos-keypad-head,
            .pos-search .help,
            .pos-payment-head .pos-payment-help {
                display: none;
            }
            .pos-key {
                min-height: 24px;
            }
            .pos-cart-head-grid label,
            .pos-cart-body label {
                font-size: 8px;
            }
        }
        /* Mode Odoo: panier fixe a gauche, catalogue prioritaire a droite. */
        @media (min-width: 921px) {
            .pos-workspace {
                grid-template-columns: minmax(430px, 36vw) minmax(0, 1fr);
            }
            .pos-browser {
                grid-template-rows: auto auto minmax(0, 1fr);
                gap: 6px;
            }
            .pos-browser:not(.show-tools) .pos-quick-actions,
            .pos-browser:not(.show-tools) .pos-touch-strip,
            .pos-browser:not(.show-tools) .pos-status-strip,
            .pos-browser:not(.show-tools) .pos-sync-strip,
            .pos-browser:not(.show-tools) .pos-sync-queue,
            .pos-browser:not(.show-tools) .pos-shortcuts {
                display: none !important;
            }
            .pos-browser.show-tools {
                grid-template-rows: auto auto auto auto auto auto auto auto auto minmax(190px, 1fr);
            }
            #pos-product-grid {
                min-height: 0;
            }
            .pos-grid {
                grid-template-columns: repeat(6, minmax(125px, 1fr));
                grid-template-rows: repeat(2, minmax(0, 1fr));
            }
            #pos-tools-toggle[aria-expanded="true"] {
                background: linear-gradient(135deg, var(--pos-accent) 0%, var(--pos-accent-strong) 100%);
                color: #fff;
            }
        }
        @media (min-width: 921px) and (max-width: 1450px) {
            .pos-workspace {
                grid-template-columns: minmax(410px, 42vw) minmax(0, 1fr);
            }
            .pos-grid {
                grid-template-columns: repeat(4, minmax(120px, 1fr));
            }
        }
    </style>
    <link rel="stylesheet" href="{{ asset('css/pos-odoo.css') }}?v=20260730-1">

    <div class="pos-shell">
        <div class="pos-kicker">
            <div>
                <h2>CAISSE {{ $session->id }} - ERP POS</h2>
                <div class="muted">Session {{ $session->session_number }} / {{ $session->warehouse?->name }} / {{ $session->cashAccount?->name }}. Ecran optimise pour {{ strtolower($saleLabel) }}, tactile et scan code-barres.</div>
            </div>
            <div class="pos-kicker-actions">
                <button type="button" id="pos-tools-toggle" class="pos-mode-button" aria-expanded="false">Outils</button>
                <button type="button" id="pos-fullscreen-toggle" class="pos-mode-button"><span id="pos-fullscreen-label">Plein ecran caisse</span></button>
                <a href="{{ route('pos.show', $session) }}" class="button button-secondary">Retour session</a>
                <a href="{{ route('pos.report', ['date' => $session->opened_at?->toDateString(), 'warehouse_id' => $session->warehouse_id, 'cash_account_id' => $session->cash_account_id]) }}" class="button button-secondary">Rapport du jour</a>
            </div>
        </div>

        <div class="pos-workspace">
            <section class="pos-browser">
                <div class="pos-toolbar">
                    <div class="pos-search">
                        <input id="pos-search" type="text" placeholder="Scanner code-barres puis Entrée, ou rechercher {{ strtolower($productLabel) }}/SKU" autocomplete="off" inputmode="search" autofocus>
                        <div id="pos-feedback" class="help">Douchette code-barres active : le scan ajoute directement au panier.</div>
                    </div>
                    <div class="pos-summary-card">
                        <strong>{{ $salesLabel }} session</strong>
                        <div class="value">{{ number_format($summary['sales_count'], 0, ',', ' ') }}</div>
                        <div class="help">document(s) deja saisi(s)</div>
                    </div>
                </div>
                <div class="pos-quick-actions">
                    <button type="button" id="pos-quick-sell" class="pos-quick-btn is-primary">{{ $saleLabel }}</button>
                    <button type="button" id="pos-quick-cash" class="pos-quick-btn is-accent">Encaisser</button>
                    <button type="button" id="pos-quick-receive" class="pos-quick-btn is-warm">Receptionner</button>
                </div>

                <div class="pos-touch-strip">
                    <button type="button" class="pos-touch-btn" data-pos-action="focus-customer">{{ $customerLabel }}</button>
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
                    @if ($activePosProfile)
                        <div class="pos-meta-chip">Profil <strong>{{ $activePosProfile['name'] }}</strong></div>
                    @endif
                    <div class="pos-meta-chip" id="pos-filter-count">{{ count($productCatalog) }} / {{ $productCatalogTotal }} {{ strtolower($productsLabel) }}</div>
                </div>
                <div class="pos-status-strip">
                    <div class="pos-status-item">
                        <span class="pos-status-dot"></span>
                        <strong>Session ouverte</strong>
                        <small>{{ $session->cashAccount?->name }} / {{ $session->warehouse?->name }}</small>
                    </div>
                    <div class="pos-status-item">
                        <span class="pos-status-dot"></span>
                        <strong>Scan pret</strong>
                        <small id="pos-scan-status">Douchette active</small>
                    </div>
                    <div class="pos-status-item is-muted">
                        <span class="pos-status-dot"></span>
                        <strong>Paiement</strong>
                        <small id="pos-cash-status">Aucun ticket en cours</small>
                    </div>
                    <div class="pos-status-item is-muted">
                        <span class="pos-status-dot"></span>
                        <strong>{{ $stockLabel }} controle</strong>
                        <small>{{ $stockPolicy === 'block' ? 'Rupture bloquee' : 'Alerte rupture' }}</small>
                    </div>
                </div>

                <div class="pos-sync-strip">
                    <div id="pos-network-badge" class="pos-sync-badge is-online">En ligne</div>
                    <div class="pos-sync-copy">
                        <div id="pos-sync-summary" class="pos-sync-summary">Aucun ticket hors ligne en attente.</div>
                        <div id="pos-sync-last" class="pos-sync-last">La caisse resynchronise automatiquement des que la connexion revient.</div>
                    </div>
                    <button type="button" id="pos-install-app" class="button button-secondary" hidden>Installer la caisse</button>
                    <button type="button" id="pos-sync-now" class="button button-secondary">Synchroniser la file</button>
                </div>
                <div id="pos-sync-queue" class="pos-sync-queue"></div>
                <div id="pos-category-row" class="pos-chip-row">
                    <button type="button" class="pos-chip is-active" data-category="">Tous</button>
                    @foreach ($categories as $category)
                        <button type="button" class="pos-chip" data-category="{{ $category['key'] }}" @if(!empty($category['color'])) style="border-color: {{ $category['color'] }}; color: {{ $category['color'] }};" @endif>{{ $category['name'] }}</button>
                    @endforeach
                </div>

                <div class="pos-shortcuts">
                    <span>F2 Recherche</span>
                    <span>F4 {{ $customerLabel }}</span>
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
                        <a href="{{ route('pos.sales.create', ['session' => $session->id]) }}" class="pos-nav-tab is-active">Caisse</a>
                        <a href="{{ route('pos.orders.index') }}" class="pos-nav-tab">Commandes</a>
                        <div class="pos-nav-counter">{{ number_format($summary['sales_count'], 0, ',', ' ') }}</div>
                    </div>
                    <div class="pos-order-switcher">
                        <div id="pos-order-tabs" class="pos-order-tabs"></div>
                        <button type="button" id="pos-new-order" class="pos-order-add">+ Nouvelle commande</button>
                    </div>
                    <div class="doc-chip">{{ $saleLabel }} en cours</div>
                    <div class="pos-cart-title-row">
                        <div>
                            <h3>Panier en cours</h3>
                        </div>
                        <div class="summary-box">
                            <strong>Total ticket</strong>
                            <div class="value" id="summary-total-head">0 XOF</div>
                        </div>
                    </div>

                    <div class="pos-cart-head-grid">
                        <div>
                            <label for="customer_id">{{ $customerLabel }} <span class="pos-help-chip" title="Laisse {{ $counterCustomerLabel }} si le dossier n est pas enregistre.">?</span></label>
                            <select id="customer_id" name="customer_id" form="pos-sale-form">
                                <option value="">{{ $counterCustomerLabel }}</option>
                                @foreach ($customers as $customer)
                                    <option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>{{ $customer->name }}</option>
                                @endforeach
                            </select>
                            @error('customer_id')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label for="sale_date">Date {{ strtolower($saleLabel) }}</label>
                            <input id="sale_date" name="sale_date" type="date" form="pos-sale-form" value="{{ old('sale_date', now()->toDateString()) }}" required>
                            @error('sale_date')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label for="method">Mode de paiement <span class="pos-help-chip" title="Choisis le mode reel utilise par le client pour eviter les ecarts de caisse.">?</span></label>
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
                        <div class="pos-cart-context-chip">{{ $customerLabel }} <strong id="pos-customer-chip">{{ $counterCustomerLabel }}</strong></div>
                        <div class="pos-cart-context-chip">Paiement <strong id="pos-method-chip">{{ $methods[old('method', 'cash')] ?? reset($methods) }}</strong></div>
                        <div class="pos-cart-context-chip">Date <strong id="pos-date-chip">{{ now()->format('d/m/Y') }}</strong></div>
                        <div class="pos-cart-context-chip">Lignes <strong id="pos-lines-chip">0 ligne</strong></div>
                        @if ($activePosProfile)
                            <div class="pos-cart-context-chip">Fidelite <strong>{{ $activePosProfile['loyalty_program_name'] ?: 'Aucune' }}</strong></div>
                        @endif
                    </div>
                </div>
                <form id="pos-sale-form" class="pos-sale-form" method="POST" action="{{ route('pos.sales.store', [], false) }}" novalidate>
                    @csrf
                    <div class="pos-cart-body">
                        @if ($errors->any())
                            <div class="alert-error">
                                <strong>Le ticket n est pas encore pret. Corrige les points ci-dessous.</strong>
                                @foreach ($errors->all() as $error)
                                    <div>{{ $error }}</div>
                                @endforeach
                            </div>
                        @endif

                        <div>
                            <div id="pos-empty" class="pos-empty">Le panier est vide. Scanne un {{ strtolower($productLabel) }} ou clique sur une carte {{ strtolower($productLabel) }} pour demarrer.</div>
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

                        <div id="pos-payment-panel" class="pos-payment-panel">
                            <div class="pos-payment-head">
                                <div>
                                    <label style="margin-bottom:4px;">Encaissement</label>
                                    <div class="pos-payment-help">Plusieurs modes de paiement sur le meme ticket, avec montant recu et monnaie a rendre pour la partie cash.</div>
                                </div>
                                <button type="button" id="pos-add-payment-line" class="button button-secondary">+ Ajouter un reglement</button>
                            </div>
                            <div id="pos-payment-lines" class="pos-payment-lines"></div>
                            <div class="pos-payment-grid">
                                <div>
                                    <label for="cash_received_amount">Montant recu en especes</label>
                                    <input id="cash_received_amount" name="cash_received_amount" type="text" inputmode="decimal" autocomplete="off" value="{{ old('cash_received_amount', $initialCashReceivedAmount ?? 0) }}">
                                    <div class="pos-payment-help">Renseigne seulement ce que le client remet en cash. La monnaie a rendre se calcule automatiquement.</div>
                                    @error('cash_received_amount')<div class="field-error">{{ $message }}</div>@enderror
                                </div>
                                <div class="pos-payment-totals">
                                    <div class="pos-payment-total-row"><span>Reglements saisis</span><strong id="summary-paid">0 XOF</strong></div>
                                    <div class="pos-payment-total-row is-warning"><span id="summary-remaining-label">Reste a couvrir</span><strong id="summary-remaining">0 XOF</strong></div>
                                    <div class="pos-payment-total-row is-success"><span>Monnaie a rendre</span><strong id="summary-change">0 XOF</strong></div>
                                </div>
                            </div>
                            <div id="pos-payment-error" class="pos-payment-inline-error">{{ $errors->first('payments') ?: $errors->first('cash_received_amount') }}</div>
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
                                <textarea id="notes" name="notes" class="pos-note" placeholder="Commentaire operateur, precisions ticket, remarque caisse">{{ $initialNote }}</textarea>
                                @error('notes')<div class="field-error">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <input type="hidden" id="source_draft_id" name="source_draft_id" value="{{ old('source_draft_id', $activeDraftId) }}">
                        <input type="hidden" id="pos_session_id" name="pos_session_id" value="{{ $session->id }}">
                        <input type="hidden" id="pos_sync_key" name="pos_sync_key" value="{{ old('pos_sync_key') }}">
                        <input type="hidden" name="print_thermal" value="{{ $autoPrintReceipt ? 1 : 0 }}">
                        <div id="pos-hidden-inputs"></div>

                        <div class="pos-actions">
                            <a href="{{ route('pos.show', $session) }}" class="button button-secondary">Retour session</a>
                            @if ($allowDraftOrders)
                                <button type="button" id="pos-save-draft" class="button button-secondary">Mettre en attente</button>
                            @endif
                            <button type="button" id="pos-submit-button" class="button button-primary">Valider et encaisser</button>
                        </div>
                    </div>
                </form>
            </aside>
        </div>
    </div>

    <script>
        let productCatalog = @json($productCatalog);
        const productCatalogTotal = Number(@json($productCatalogTotal));
        const productSearchUrl = @json(route('pos.sales.products', [], false));
        const methods = @json($methods);
        const paymentMethodConfigs = @json($paymentMethodConfigs);
        const activePosProfile = @json($activePosProfile);
        const stockPolicy = @json($stockPolicy);
        const showStockQuantity = @json($showStockQuantity);
        const quickCashPayment = @json($quickCashPayment);
        const cashRoundingEnabled = @json($cashRoundingEnabled);
        const cashRoundingPrecision = Number(@json($cashRoundingPrecision));
        const allowDraftOrders = @json($allowDraftOrders);
        const posSessionId = @json($session->id);
        const paymentAccounts = @json($paymentAccounts);
        const sessionCashAccountId = @json($sessionCashAccountId);
        const initialItems = @json($initialItems);
        const initialPayments = @json($initialPayments);
        const initialCashReceivedAmount = Number(@json($initialCashReceivedAmount ?? 0));
        const savedDrafts = @json(($savedDrafts ?? collect())->values()->all());
        const initialDraftId = @json($activeDraftId);
        const initialPosSyncKey = @json(old('pos_sync_key'));
        const hasOldPosForm = @json($hasOldPosForm ?? false);
        const sessionWarehouseName = @json($session->warehouse?->name ?? 'depot actif');
        const posVocabulary = {
            customer: @json($customerLabel),
            counterCustomer: @json($counterCustomerLabel),
            product: @json($productLabel),
            products: @json($productsLabel),
            sale: @json($saleLabel),
            sales: @json($salesLabel),
            stock: @json($stockLabel),
        };
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
        const remainingLabelOutput = document.getElementById('summary-remaining-label');
        const changeOutput = document.getElementById('summary-change');
        const paymentPanel = document.getElementById('pos-payment-panel');
        const paymentError = document.getElementById('pos-payment-error');
        const submitButton = document.getElementById('pos-submit-button');
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
        const posSyncKeyInput = document.getElementById('pos_sync_key');
        const networkBadge = document.getElementById('pos-network-badge');
        const syncSummary = document.getElementById('pos-sync-summary');
        const syncLast = document.getElementById('pos-sync-last');
        const syncQueueWrap = document.getElementById('pos-sync-queue');
        const syncNowButton = document.getElementById('pos-sync-now');
        const installAppButton = document.getElementById('pos-install-app');
        const quickSellButton = document.getElementById('pos-quick-sell');
        const quickCashButton = document.getElementById('pos-quick-cash');
        const quickReceiveButton = document.getElementById('pos-quick-receive');
        const toolsToggleButton = document.getElementById('pos-tools-toggle');
        const fullscreenButton = document.getElementById('pos-fullscreen-toggle');
        const fullscreenLabel = document.getElementById('pos-fullscreen-label');
        const scanStatusOutput = document.getElementById('pos-scan-status');
        const cashStatusOutput = document.getElementById('pos-cash-status');
        const csrfToken = saleForm.querySelector('input[name="_token"]').value;
        const queueStorageKey = `nema-erp-pos-offline:${@json($session->id)}:queue`;
        const offlineDbName = 'nema-erp-pos-offline';
        const offlineDbStore = 'queue_store';
        const maxRetryDelaySeconds = 5 * 60;
        const serviceWorkerUrl = @json(parse_url(asset('pos-sw.js'), PHP_URL_PATH) ?: '/pos-sw.js');
        let pendingQueue = [];
        let syncInFlight = false;
        let deferredInstallPrompt = null;
        let lastSyncMessage = '';
        const setScanStatus = (message) => {
            if (scanStatusOutput) {
                scanStatusOutput.textContent = message;
            }
        };
        const setFullscreenUi = () => {
            const active = Boolean(document.fullscreenElement);
            document.body.classList.toggle('pos-fullscreen-mode', active);
            if (fullscreenLabel) {
                fullscreenLabel.textContent = active ? 'Quitter plein ecran' : 'Plein ecran caisse';
            }
        };
        const focusCashSearch = () => {
            window.setTimeout(() => {
                searchInput?.focus();
                searchInput?.select();
            }, 80);
        };
        if (toolsToggleButton) {
            toolsToggleButton.addEventListener('click', () => {
                const expanded = document.querySelector('.pos-browser')?.classList.toggle('show-tools') || false;
                toolsToggleButton.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                toolsToggleButton.textContent = expanded ? 'Fermer outils' : 'Outils';
                window.requestAnimationFrame(() => renderProducts());
            });
        }
        if (fullscreenButton && document.fullscreenEnabled) {
            fullscreenButton.addEventListener('click', async () => {
                try {
                    if (document.fullscreenElement) {
                        await document.exitFullscreen();
                    } else {
                        await document.documentElement.requestFullscreen();
                    }
                } catch (error) {
                    feedback.textContent = 'Le navigateur a refuse le plein ecran. Reessaie depuis le bouton.';
                }
                setFullscreenUi();
                focusCashSearch();
            });
            document.addEventListener('fullscreenchange', () => {
                setFullscreenUi();
                focusCashSearch();
            });
        } else if (fullscreenButton) {
            fullscreenButton.hidden = true;
        }

        const byId = Object.fromEntries(productCatalog.map((product) => [String(product.id), product]));
        let remoteProductTotal = productCatalogTotal;
        let remoteProductPage = 1;
        let remoteProductPages = 1;
        let productSearchSequence = 0;
        const accountsById = Object.fromEntries(paymentAccounts.map((account) => [String(account.id), account]));
        const hardwareThreads = Number(window.navigator.hardwareConcurrency || 0);
        const deviceMemoryGb = Number(window.navigator.deviceMemory || 0);
        const isNarrowScreen = window.matchMedia('(max-width: 820px)').matches;
        const isLowPowerDevice = isNarrowScreen || (hardwareThreads > 0 && hardwareThreads <= 4) || (deviceMemoryGb > 0 && deviceMemoryGb <= 4);
        const isShortDesktop = !isNarrowScreen && window.innerHeight <= 700;
        const maxVisibleProducts = isNarrowScreen
            ? 8
            : (isShortDesktop
                ? (window.innerWidth >= 1600 ? 15 : (window.innerWidth <= 1320 ? 9 : 12))
                : (window.innerWidth >= 1600 ? 20 : 12));
        remoteProductPages = Math.max(Math.ceil(productCatalogTotal / maxVisibleProducts), 1);
        const loadRemoteProducts = async ({ focusFirst = false } = {}) => {
            const sequence = ++productSearchSequence;
            const url = new URL(productSearchUrl, window.location.origin);
            url.searchParams.set('session', String(posSessionId));
            url.searchParams.set('limit', String(maxVisibleProducts));
            url.searchParams.set('page', String(remoteProductPage));
            if (state.search.trim()) {
                url.searchParams.set('q', state.search.trim());
            }
            if (state.category) {
                url.searchParams.set('category', state.category);
            }

            productGrid.innerHTML = '<div class="pos-empty" style="grid-column:1 / -1;">Chargement des articles...</div>';
            try {
                const response = await fetch(url.toString(), {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                const payload = await response.json();
                if (sequence !== productSearchSequence) {
                    return [];
                }
                productCatalog = Array.isArray(payload.products) ? payload.products : [];
                remoteProductTotal = Number(payload.total || productCatalog.length);
                remoteProductPage = Number(payload.page || 1);
                remoteProductPages = Number(payload.pages || 1);
                productCatalog.forEach((product) => {
                    byId[String(product.id)] = product;
                });
                renderProducts();
                if (focusFirst) {
                    productGrid.querySelector('[data-product-id]')?.focus();
                }
                return productCatalog;
            } catch (error) {
                if (sequence === productSearchSequence) {
                    productGrid.innerHTML = '<div class="pos-empty" style="grid-column:1 / -1;">Le catalogue ne peut pas etre charge. Verifie la connexion puis reessaie.</div>';
                    feedback.textContent = 'Chargement des articles impossible. La caisse reste ouverte; reessaie la recherche.';
                }
                return [];
            }
        };
        const simplifyErrorMessage = (message) => {
            const text = String(message || '').trim();
            if (!text) {
                return 'Operation impossible pour le moment. Reessaie ou demande au responsable.';
            }
            if (/CSRF|419/i.test(text)) {
                return 'Session expirée. Recharge la page puis recommence.';
            }
            if (/network|fetch|connexion|timeout/i.test(text)) {
                return 'Connexion instable. Le ticket peut etre garde hors ligne puis synchronise.';
            }
            return text;
        };
        const methodConfigsByCode = Object.fromEntries(paymentMethodConfigs.map((config) => [config.method_code, config]));
        const defaultMethod = paymentMethodConfigs.find((config) => config.is_default)?.method_code || methodInput.options[0]?.value || 'cash';
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
        const formatQty = (value) => {
            const amount = n(value, 0);
            const rounded = Math.round(amount);
            const hasDecimals = Math.abs(amount - rounded) > 0.0001;

            return new Intl.NumberFormat('fr-FR', {
                minimumFractionDigits: hasDecimals ? 3 : 0,
                maximumFractionDigits: 3,
            }).format(amount);
        };
        const paymentAmount = (value, fallback = 0) => {
            const normalized = String(value ?? '').replace(/\s+/g, '').replace(',', '.');
            const parsed = Number(normalized);
            return Number.isFinite(parsed) ? parsed : fallback;
        };
        const toAppRelativeUrl = (value, fallback = '/') => {
            try {
                const url = new URL(value || fallback, window.location.href);
                return `${url.pathname}${url.search}${url.hash}` || fallback;
            } catch (error) {
                return fallback;
            }
        };
        const saleStoreUrl = toAppRelativeUrl(saleForm.getAttribute('action') || saleForm.action, '/point-de-vente/vente');
        const draftStoreUrl = toAppRelativeUrl(@json(route('pos.drafts.store', [], false)), '/point-de-vente/brouillons');
        const draftDestroyUrlTemplate = toAppRelativeUrl(@json(route('pos.drafts.destroy', ['draft' => '__DRAFT__'], false)), '/point-de-vente/brouillons/__DRAFT__');
        const todayValue = saleDateInput.value || new Date().toISOString().slice(0, 10);
        const generateSyncKey = () => window.crypto?.randomUUID
            ? `pos-${window.crypto.randomUUID()}`
            : `pos-${Date.now()}-${Math.random().toString(16).slice(2, 10)}`;
        const normalizeSyncKey = (value) => String(value || '').trim().slice(0, 80);
        const state = {
            search: '',
            category: '',
            keypadTarget: 'qty',
            buffer: '',
            orders: [],
            activeOrderUid: null,
        };
        const availableProductQty = (productId, currentLineUid = null) => {
            const product = byId[String(productId)];
            if (!product || product.type !== 'stockable') {
                return Number.POSITIVE_INFINITY;
            }

            const reservedInCart = state.items.reduce((total, item) => {
                if (String(item.product_id) !== String(productId) || item.uid === currentLineUid) {
                    return total;
                }

                return total + n(item.qty, 0);
            }, 0);

            return Math.max(0, n(product.available_qty, 0) - reservedInCart);
        };
        const stockMessage = (product, availableQty) => {
            const unit = product?.unit ? ` ${product.unit}` : '';
            return `${product?.name || 'Ce '+String(posVocabulary.product || 'article').toLowerCase()} dispose de ${formatQty(availableQty)}${unit} en ${String(posVocabulary.stock || 'stock').toLowerCase()} vendable dans ${sessionWarehouseName}.`;
        };
        const lineStockIssue = (line) => {
            const product = byId[String(line?.product_id || '')];
            if (!line || !product || product.type !== 'stockable') {
                return null;
            }

            const availableQty = availableProductQty(line.product_id, line.uid);
            const requestedQty = n(line.qty, 0);

            if (requestedQty <= availableQty + 0.0001 || stockPolicy === 'allow') {
                return null;
            }

            return {
                product,
                availableQty,
                requestedQty,
            };
        };
        const clampLineQuantity = (line, desiredQty) => {
            const product = byId[String(line?.product_id || '')];
            const sanitizedQty = Math.max(0.001, n(desiredQty, 0));

            if (!product || product.type !== 'stockable' || stockPolicy !== 'block') {
                return sanitizedQty;
            }

            const maxQty = availableProductQty(line.product_id, line.uid);
            if (sanitizedQty <= maxQty + 0.0001) {
                return sanitizedQty;
            }

            feedback.textContent = stockMessage(product, maxQty);

            return maxQty > 0.0001 ? maxQty : sanitizedQty;
        };
        const currentOrderStockIssue = () => state.items
            .map((line) => ({ line, issue: lineStockIssue(line) }))
            .find((entry) => entry.issue)?.issue || null;
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
            if (method === 'orange_money') {
                return account.type === 'mobile_money' && String(account.name || '').toLowerCase().includes('orange');
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
            const configured = methodConfigsByCode[method];
            if (configured?.cash_account_id) {
                return Number(configured.cash_account_id);
            }
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
            unit_price: Number(item.unit_price || comboPrice(byId[String(item.product_id || '')]) || 0),
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
            const cashReceivedAmount = Number(seed.cash_received_amount || 0);
            const cashReceivedRaw = seed.cash_received_raw ?? (cashReceivedAmount > 0 ? String(seed.cash_received_amount) : '');
            return {
                uid: seed.uid || `order-${Date.now()}-${Math.random()}`,
                draft_id: seed.draft_id ? Number(seed.draft_id) : null,
                label: seed.label || `Commande ${forcedIndex ?? nextOrderNumber()}`,
                sync_key: normalizeSyncKey(seed.sync_key || generateSyncKey()),
                customer_id: seed.customer_id ?? '',
                sale_date: seed.sale_date || todayValue,
                method,
                reference: seed.reference || '',
                notes: seed.notes || '',
                discount_type: seed.discount_type || 'none',
                discount_value: Number(seed.discount_value || 0),
                cash_received_amount: cashReceivedAmount,
                cash_received_raw: cashReceivedRaw,
                cash_received_touched: Boolean(seed.cash_received_touched),
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
            sync_key: initialPosSyncKey,
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
        const comboPrice = (product) => {
            if (!product?.combo) {
                return n(product?.price, 0);
            }

            if (product.combo.pricing_mode === 'fixed' && product.combo.price_override !== null) {
                return n(product.combo.price_override, n(product?.price, 0));
            }

            return n(product?.price, 0);
        };
        const primaryMethod = (order) => order?.payments?.[0]?.method || order?.method || defaultMethod;
        const paymentSummary = (order, total) => {
            if (!Array.isArray(order.payments) || !order.payments.length) {
                order.payments = [createPaymentLine({ amount: total }, order.method || defaultMethod)];
            }
            const payments = order.payments
                .map((payment) => {
                    const method = payment.method || order.method || defaultMethod;
                    const selectedAccount = payment.cash_account_id ? accountsById[String(payment.cash_account_id)] : null;
                    return {
                        ...payment,
                        method,
                        amount: Math.max(n(payment.amount, 0), 0),
                        cash_account_id: accountMatchesMethod(selectedAccount, method)
                            ? Number(selectedAccount.id)
                            : defaultAccountIdForMethod(method),
                    };
                })
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
            if (!hasCashLine) {
                cashReceived = 0;
                order.cash_received_raw = '';
                order.cash_received_touched = false;
            } else if (!order.cash_received_touched) {
                order.cash_received_raw = cashReceived > 0 ? String(cashReceived) : '';
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
                cashReceivedDisplay: hasCashLine
                    ? (order.cash_received_touched && String(order.cash_received_raw ?? '') === ''
                        ? ''
                        : String(order.cash_received_raw ?? (cashReceived > 0 ? cashReceived : '')))
                    : '',
                changeDue: Math.max(cashReceived - cashAllocated, 0),
                isMixed: payments.length > 1 || new Set(payments.map((payment) => payment.method)).size > 1,
            };
        };
        const syncActiveOrderFromForm = () => {
            const order = ensureActiveOrder();
            order.sync_key = normalizeSyncKey(posSyncKeyInput?.value || order.sync_key || generateSyncKey());
            if (posSyncKeyInput) {
                posSyncKeyInput.value = order.sync_key;
            }
            order.customer_id = customerInput.value || '';
            order.sale_date = saleDateInput.value || todayValue;
            order.method = methodInput.value || defaultMethod;
            order.reference = referenceInput?.value || '';
            order.notes = notesInput?.value || '';
            order.discount_type = discountTypeInput.value || 'none';
            order.discount_value = n(discountValueInput.value, 0);
            order.cash_received_raw = cashReceivedInput?.value ?? '';
            order.cash_received_amount = order.cash_received_raw === ''
                ? 0
                : Math.max(n(order.cash_received_raw, order.cash_received_amount || 0), 0);
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
            order.sync_key = normalizeSyncKey(order.sync_key || generateSyncKey());
            if (posSyncKeyInput) {
                posSyncKeyInput.value = order.sync_key;
            }
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
                cashReceivedInput.value = order.cash_received_raw ?? '';
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
        const paymentValidationState = (snapshot) => {
            if (snapshot.payment.overpaid > 0.01) {
                return {
                    valid: false,
                    message: `Les reglements depassent le ticket de ${money(snapshot.payment.overpaid)}.`,
                    cashInvalid: false,
                    remainingInvalid: true,
                };
            }
            if (snapshot.payment.remaining > 0.01) {
                return {
                    valid: false,
                    message: `Il reste ${money(snapshot.payment.remaining)} a regler sur ce ticket.`,
                    cashInvalid: false,
                    remainingInvalid: true,
                };
            }
            if (snapshot.payment.cashAllocated > 0 && snapshot.payment.cashReceived + 0.009 < snapshot.payment.cashAllocated) {
                return {
                    valid: false,
                    message: 'Le montant recu en especes doit couvrir la part cash du ticket.',
                    cashInvalid: true,
                    remainingInvalid: false,
                };
            }
            return {
                valid: true,
                message: '',
                cashInvalid: false,
                remainingInvalid: false,
            };
        };
        const applyPaymentValidation = (snapshot) => {
            const validation = paymentValidationState(snapshot);
            paymentPanel?.classList.toggle('is-invalid', Boolean(validation.message));
            paymentError.textContent = validation.message;
            paymentError.classList.toggle('is-visible', Boolean(validation.message));
            cashReceivedInput.classList.toggle('is-invalid', validation.cashInvalid);
            remainingOutput.closest('.pos-payment-total-row')?.classList.toggle('is-invalid', validation.remainingInvalid);
            const blocked = !state.items.length || !validation.valid;
            if (!syncInFlight) {
                submitButton.disabled = blocked;
            }
            submitButton.classList.toggle('is-blocked', blocked && !syncInFlight);
            submitButton.setAttribute('aria-disabled', blocked ? 'true' : 'false');
            submitButton.dataset.blocked = blocked ? 'true' : 'false';
            submitButton.title = !state.items.length
                ? `Ajoute au moins un ${String(posVocabulary.product || 'article').toLowerCase()} avant de valider.`
                : (validation.valid ? '' : validation.message);
            return validation;
        };
        const revealSubmitIssue = (options = {}) => {
            const { target = null, focus = null } = options;
            (target || paymentPanel || linesWrap)?.scrollIntoView({
                behavior: 'smooth',
                block: 'center',
            });
            if (focus && typeof focus.focus === 'function') {
                window.setTimeout(() => {
                    focus.focus({ preventScroll: true });
                    if (typeof focus.select === 'function') {
                        focus.select();
                    }
                }, 120);
            }
        };
        const syncHiddenInputs = (order, snapshot) => {
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
        };
        const refreshPaymentAmounts = (order) => {
            const snapshot = orderSnapshot(order);
            syncHiddenInputs(order, snapshot);
            renderPaymentSummary(snapshot);
            applyPaymentValidation(snapshot);
            return snapshot;
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
        };
        const openOfflineDatabase = () => new Promise((resolve) => {
            if (!('indexedDB' in window)) {
                resolve(null);
                return;
            }
            const request = window.indexedDB.open(offlineDbName, 1);
            request.onupgradeneeded = () => {
                const db = request.result;
                if (!db.objectStoreNames.contains(offlineDbStore)) {
                    db.createObjectStore(offlineDbStore, { keyPath: 'key' });
                }
            };
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => resolve(null);
        });
        const readQueueFromIndexedDb = async () => {
            const db = await openOfflineDatabase();
            if (!db) {
                return null;
            }
            return new Promise((resolve) => {
                const transaction = db.transaction(offlineDbStore, 'readonly');
                const request = transaction.objectStore(offlineDbStore).get(queueStorageKey);
                request.onsuccess = () => resolve(Array.isArray(request.result?.value) ? request.result.value : []);
                request.onerror = () => resolve(null);
                transaction.oncomplete = () => db.close();
            });
        };
        const writeQueueToIndexedDb = async (queue) => {
            const db = await openOfflineDatabase();
            if (!db) {
                return;
            }
            await new Promise((resolve) => {
                const transaction = db.transaction(offlineDbStore, 'readwrite');
                transaction.objectStore(offlineDbStore).put({ key: queueStorageKey, value: queue });
                transaction.oncomplete = () => resolve(true);
                transaction.onerror = () => resolve(false);
            });
            db.close();
        };
        const removeQueueFromIndexedDb = async () => {
            const db = await openOfflineDatabase();
            if (!db) {
                return;
            }
            await new Promise((resolve) => {
                const transaction = db.transaction(offlineDbStore, 'readwrite');
                transaction.objectStore(offlineDbStore).delete(queueStorageKey);
                transaction.oncomplete = () => resolve(true);
                transaction.onerror = () => resolve(false);
            });
            db.close();
        };
        const hydratePendingQueueFromIndexedDb = async () => {
            const indexedQueue = await readQueueFromIndexedDb();
            if (Array.isArray(indexedQueue)) {
                pendingQueue = indexedQueue.map((queued) => normalizeQueuedSale(queued));
                if (pendingQueue.length) {
                    localStorage.setItem(queueStorageKey, JSON.stringify(pendingQueue));
                } else {
                    localStorage.removeItem(queueStorageKey);
                }
                updateOfflineUi();
                return true;
            }
            if (pendingQueue.length) {
                await writeQueueToIndexedDb(pendingQueue);
            }
            return false;
        };
        const requestServiceWorkerSync = async () => {
            if (!('serviceWorker' in navigator)) {
                return;
            }
            try {
                const registration = await navigator.serviceWorker.ready;
                if ('sync' in registration) {
                    await registration.sync.register('nema-pos-sync');
                } else if (registration.active) {
                    registration.active.postMessage({ type: 'nema-pos-sync-now' });
                }
            } catch (error) {
                // Fallback silencieux, la page gardera la synchronisation manuelle/auto.
            }
        };
        const registerPosServiceWorker = async () => {
            if (!('serviceWorker' in navigator)) {
                return;
            }
            try {
                const registration = await navigator.serviceWorker.register(serviceWorkerUrl);
                await registration.update();
                if (registration.waiting) {
                    registration.waiting.postMessage({ type: 'SKIP_WAITING' });
                }
            } catch (error) {
                console.warn('POS service worker registration failed', error);
            }
        };
        const salePayload = (order, options = {}) => {
            const snapshot = orderSnapshot(order);
            order.sync_key = normalizeSyncKey(order.sync_key || generateSyncKey());

            return {
                customer_id: order.customer_id || null,
                sale_date: order.sale_date || todayValue,
                method: primaryMethod(order),
                reference: order.reference || '',
                notes: order.notes || '',
                discount_type: order.discount_type || 'none',
                discount_value: n(order.discount_value, 0),
                cash_received_amount: n(order.cash_received_amount, 0),
                pos_sync_key: order.sync_key,
                source_draft_id: options.includeDraftSource === false ? null : (order.draft_id || null),
                pos_session_id: posSessionId,
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
        };
        const extractResponseMessage = (data, fallback) => {
            if (data?.message) {
                return data.message;
            }
            if (data?.errors && typeof data.errors === 'object') {
                const first = Object.values(data.errors).flat().find(Boolean);
                if (first) {
                    return first;
                }
            }
            return fallback;
        };
        const queueTimestamp = (value) => {
            const date = value ? new Date(value) : null;
            return Number.isNaN(date?.getTime?.()) ? 'horodatage indisponible' : date.toLocaleString('fr-FR', {
                day: '2-digit',
                month: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
            });
        };
        const computeRetryDelaySeconds = (attemptCount) => {
            const safeAttempt = Math.max(1, Number.isFinite(attemptCount) ? attemptCount : 1);
            return Math.min(maxRetryDelaySeconds, Math.pow(2, Math.min(8, safeAttempt - 1)) * 5);
        };
        const shouldAttemptQueuedSale = (queued) => {
            if (!queued?.next_retry_at) {
                return true;
            }
            const retryAt = new Date(queued.next_retry_at).getTime();
            return Number.isNaN(retryAt) || retryAt <= Date.now();
        };
        const formatRetryHint = (queued) => {
            if (!queued?.next_retry_at) {
                return '';
            }

            const retryAt = new Date(queued.next_retry_at).getTime();
            if (Number.isNaN(retryAt)) {
                return '';
            }

            if (retryAt <= Date.now()) {
                return 'Pret pour une nouvelle tentative.';
            }

            return `Nouvelle tentative apres ${queueTimestamp(queued.next_retry_at)}.`;
        };
        const normalizeQueuedSale = (queued) => {
            const payload = queued?.payload && typeof queued.payload === 'object' ? queued.payload : {};
            const syncKey = normalizeSyncKey(queued?.sync_key || payload?.pos_sync_key || generateSyncKey());
            const parsedAttemptCount = Number(queued?.attempt_count);

            return {
                id: queued?.id || `queued-${Date.now()}-${Math.random().toString(16).slice(2, 8)}`,
                sync_key: syncKey,
                label: queued?.label || 'Ticket hors ligne',
                draft_id: queued?.draft_id ? Number(queued.draft_id) : null,
                total: n(queued?.total, 0),
                created_at: queued?.created_at || new Date().toISOString(),
                last_error: queued?.last_error || '',
                attempt_count: Number.isFinite(parsedAttemptCount) && parsedAttemptCount > 0 ? Math.floor(parsedAttemptCount) : 0,
                last_attempt_at: queued?.last_attempt_at || null,
                next_retry_at: queued?.next_retry_at || null,
                csrf_token: queued?.csrf_token || csrfToken,
                store_url: toAppRelativeUrl(queued?.store_url || saleStoreUrl, saleStoreUrl),
                payload: {
                    ...payload,
                    pos_sync_key: syncKey,
                    source_draft_id: null,
                },
            };
        };
        const loadPendingQueue = () => {
            try {
                const raw = localStorage.getItem(queueStorageKey);
                const parsed = raw ? JSON.parse(raw) : [];
                pendingQueue = Array.isArray(parsed) ? parsed.map((queued) => normalizeQueuedSale(queued)) : [];
            } catch (error) {
                pendingQueue = [];
            }
        };
        const persistPendingQueue = () => {
            localStorage.setItem(queueStorageKey, JSON.stringify(pendingQueue));
            if (pendingQueue.length) {
                void writeQueueToIndexedDb(pendingQueue);
            } else {
                void removeQueueFromIndexedDb();
            }
        };
        const replaceOrderAfterQueue = (uid, seed = {}) => {
            if (state.orders.length === 1) {
                const fresh = createDraftOrder(seed, 1);
                state.orders = [fresh];
                state.activeOrderUid = fresh.uid;
                loadOrderIntoForm(fresh);
                return;
            }
            const currentIndex = state.orders.findIndex((entry) => entry.uid === uid);
            state.orders = state.orders.filter((entry) => entry.uid !== uid);
            const fallback = state.orders[Math.max(0, currentIndex - 1)] || state.orders[0];
            state.activeOrderUid = fallback.uid;
            loadOrderIntoForm(fallback);
        };
        const renderPendingQueue = () => {
            if (!syncQueueWrap) {
                return;
            }
            syncQueueWrap.innerHTML = pendingQueue.length
                ? pendingQueue.map((queued) => `
                    <div class="pos-sync-item">
                        <div>
                            <strong>${esc(queued.label)}</strong>
                            <div class="pos-sync-item-meta">${money(queued.total)} · ${esc(queueTimestamp(queued.created_at))}</div>
                            <div class="pos-sync-item-meta">Cle sync ${esc(queued.sync_key.slice(0, 12))}...</div>
                            ${queued.last_error ? `<div class="pos-sync-item-error">${esc(queued.last_error)}</div>` : '<div class="pos-sync-item-meta">En attente de synchronisation.</div>'}
                            ${queued.attempt_count ? `<div class="pos-sync-item-meta">Tentatives: ${queued.attempt_count}${queued.last_attempt_at ? ` · derniere: ${esc(queueTimestamp(queued.last_attempt_at))}` : ''}</div>` : ''}
                            ${formatRetryHint(queued) ? `<div class="pos-sync-item-meta">${esc(formatRetryHint(queued))}</div>` : ''}
                        </div>
                        <div class="pos-sync-item-actions">
                            <button type="button" data-offline-restore="${esc(queued.sync_key)}">Reprendre</button>
                            <button type="button" data-offline-remove="${esc(queued.sync_key)}">Retirer</button>
                        </div>
                    </div>
                `).join('')
                : '';
        };
        const updateOfflineUi = () => {
            const online = window.navigator.onLine;
            if (networkBadge) {
                networkBadge.textContent = syncInFlight ? 'Synchronisation...' : (online ? 'En ligne' : 'Hors ligne');
                networkBadge.classList.toggle('is-online', online && !syncInFlight);
                networkBadge.classList.toggle('is-offline', !online && !syncInFlight);
                networkBadge.classList.toggle('is-syncing', syncInFlight);
            }
            if (syncSummary) {
                syncSummary.textContent = pendingQueue.length
                    ? `${pendingQueue.length} ticket${pendingQueue.length > 1 ? 's' : ''} hors ligne en attente.`
                    : (online ? 'Aucun ticket hors ligne en attente.' : 'Connexion faible ou coupee. Aucun ticket en file pour le moment.');
            }
            if (syncLast) {
                syncLast.textContent = lastSyncMessage || (online
                    ? 'La caisse resynchronise automatiquement des que la connexion revient.'
                    : 'Les tickets valides seront stockes sur cet appareil puis envoyes plus tard.');
            }
            if (syncNowButton) {
                syncNowButton.disabled = syncInFlight || !online || !pendingQueue.length;
                syncNowButton.textContent = syncInFlight ? 'Synchronisation...' : 'Synchroniser la file';
            }
            if (installAppButton) {
                installAppButton.hidden = !deferredInstallPrompt;
                installAppButton.disabled = !deferredInstallPrompt || syncInFlight;
            }
            if (saveDraftButton) {
                saveDraftButton.disabled = syncInFlight || !online;
                saveDraftButton.title = online ? '' : 'La mise en attente serveur n est pas disponible hors ligne.';
            }
            if (submitButton && !syncInFlight) {
                submitButton.textContent = online ? 'Valider et encaisser' : 'Enregistrer hors ligne';
            }
            renderPendingQueue();
        };
        const savePendingQueue = () => {
            persistPendingQueue();
            updateOfflineUi();
        };
        const dropQueuedSale = (syncKey) => {
            pendingQueue = pendingQueue.filter((queued) => queued.sync_key !== syncKey);
            savePendingQueue();
        };
        const restoreQueuedSale = (syncKey) => {
            const queued = pendingQueue.find((entry) => entry.sync_key === syncKey);
            if (!queued) {
                return;
            }
            syncActiveOrderFromForm();
            const restored = createDraftOrder({
                ...queued.payload,
                label: queued.label,
                draft_id: queued.draft_id || null,
                sync_key: queued.sync_key,
                cash_received_amount: queued.payload.cash_received_amount || 0,
                payments: queued.payload.payments || [],
                items: queued.payload.items || [],
            }, nextOrderNumber());
            const canReuseCurrent = state.orders.length === 1 && !ensureActiveOrder().items.length;
            if (canReuseCurrent) {
                state.orders = [restored];
            } else {
                state.orders.push(restored);
            }
            state.activeOrderUid = restored.uid;
            loadOrderIntoForm(restored);
            dropQueuedSale(syncKey);
            resetBuffer();
            renderCart();
            feedback.textContent = `${restored.label} a ete reouverte depuis la file hors ligne.`;
            searchInput.focus();
        };
        const queueCurrentSale = (order, snapshot) => {
            const queued = normalizeQueuedSale({
                id: `queued-${Date.now()}-${Math.random().toString(16).slice(2, 8)}`,
                sync_key: order.sync_key,
                label: order.label,
                draft_id: order.draft_id || null,
                total: snapshot.total,
                created_at: new Date().toISOString(),
                csrf_token: csrfToken,
                store_url: saleStoreUrl,
                payload: salePayload(order, { includeDraftSource: false }),
            });
            const existingIndex = pendingQueue.findIndex((entry) => entry.sync_key === queued.sync_key);
            if (existingIndex !== -1) {
                pendingQueue[existingIndex] = queued;
            } else {
                pendingQueue.push(queued);
            }
            lastSyncMessage = 'Ticket stocke hors ligne. Il sera synchronise automatiquement des que la connexion revient.';
            savePendingQueue();
            void requestServiceWorkerSync();
            replaceOrderAfterQueue(order.uid, {
                sale_date: order.sale_date || todayValue,
                method: order.method || defaultMethod,
            });
            resetBuffer();
            renderCart();
            feedback.textContent = lastSyncMessage;
            searchInput.focus();
        };
        const submitCurrentSale = async (order, snapshot) => {
            if (!window.navigator.onLine) {
                queueCurrentSale(order, snapshot);
                return;
            }

            syncInFlight = true;
            updateOfflineUi();
            submitButton.disabled = true;
            submitButton.textContent = 'Encaissement...';

            window.setTimeout(() => {
                HTMLFormElement.prototype.submit.call(saleForm);
            }, 0);
        };
        const syncPendingSales = async ({ automatic = false } = {}) => {
            if (syncInFlight || !window.navigator.onLine || !pendingQueue.length) {
                updateOfflineUi();
                return;
            }

            syncInFlight = true;
            lastSyncMessage = automatic
                ? 'Connexion retablie. Synchronisation des tickets hors ligne...'
                : 'Synchronisation des tickets hors ligne en cours...';
            updateOfflineUi();

            let syncedCount = 0;

            for (const queued of [...pendingQueue]) {
                if (!shouldAttemptQueuedSale(queued)) {
                    continue;
                }

                try {
                    const response = await fetch(saleStoreUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify(queued.payload),
                    });
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        throw new Error(extractResponseMessage(data, 'Impossible de synchroniser un ticket hors ligne.'));
                    }
                    pendingQueue = pendingQueue.filter((entry) => entry.sync_key !== queued.sync_key);
                    if (queued.draft_id) {
                        try {
                            await deleteDraftById(queued.draft_id);
                        } catch (error) {
                            // Le brouillon serveur sera nettoye manuellement si necessaire.
                        }
                    }
                    syncedCount += 1;
                    lastSyncMessage = data?.invoice?.already_processed
                        ? `Ticket deja present sur le serveur : ${data?.invoice?.invoice_number || queued.label}.`
                        : `Ticket synchronise : ${data?.invoice?.invoice_number || queued.label}.`;
                    persistPendingQueue();
                    updateOfflineUi();
                } catch (error) {
                    const message = simplifyErrorMessage(error?.message || 'Impossible de synchroniser un ticket hors ligne.');
                    const attemptCount = (queued.attempt_count || 0) + 1;
                    const retryDelaySeconds = computeRetryDelaySeconds(attemptCount);
                    const nextRetryAt = new Date(Date.now() + (retryDelaySeconds * 1000)).toISOString();
                    pendingQueue = pendingQueue.map((entry) => entry.sync_key === queued.sync_key ? {
                        ...entry,
                        last_error: message,
                        attempt_count: attemptCount,
                        last_attempt_at: new Date().toISOString(),
                        next_retry_at: nextRetryAt,
                    } : entry);
                    lastSyncMessage = `${message} Nouvelle tentative automatique dans ${retryDelaySeconds}s.`;
                    syncInFlight = false;
                    persistPendingQueue();
                    updateOfflineUi();
                    if (!automatic) {
                        feedback.textContent = message;
                    }
                    return;
                }
            }

            syncInFlight = false;
            persistPendingQueue();
            updateOfflineUi();
            if (syncedCount > 0) {
                feedback.textContent = syncedCount === 1
                    ? lastSyncMessage
                    : `${syncedCount} tickets hors ligne ont ete synchronises avec succes.`;
                renderCart();
            }
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
        const renderPaymentSummary = (snapshot) => {
            const payment = snapshot.payment;
            cashReceivedInput.disabled = !payment.hasCashLine;
            cashReceivedInput.value = payment.hasCashLine ? payment.cashReceivedDisplay : '';
            if (cashStatusOutput) {
                if (!snapshot.total) {
                    cashStatusOutput.textContent = 'Aucun ticket en cours';
                } else if (!payment.hasCashLine) {
                    cashStatusOutput.textContent = 'Paiement sans especes';
                } else if (payment.cashReceived > 0) {
                    cashStatusOutput.textContent = `${money(payment.cashReceived)} recu`;
                } else {
                    cashStatusOutput.textContent = 'Saisir le cash recu';
                }
            }
            paidOutput.textContent = money(payment.paid);
            if (payment.overpaid > 0.01) {
                remainingLabelOutput.textContent = 'Trop saisi';
                remainingOutput.textContent = money(payment.overpaid);
            } else {
                remainingLabelOutput.textContent = 'Reste a couvrir';
                remainingOutput.textContent = money(payment.remaining);
            }
            changeOutput.textContent = money(payment.changeDue);
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
                        <input type="text" inputmode="decimal" autocomplete="off" value="${n(entry.amount)}" data-payment-input="amount" data-payment-index="${index}">
                    </div>
                    <button type="button" class="pos-payment-remove" data-remove-payment="${index}" ${payment.payments.length === 1 ? 'disabled' : ''}>×</button>
                </div>
            `).join('');
            renderPaymentSummary(snapshot);
        };
        const deleteDraftById = async (draftId) => {
            if (!draftId) {
                return;
            }
            const response = await fetch(draftDestroyUrlTemplate.replace('__DRAFT__', String(draftId)), {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error(simplifyErrorMessage(data.message || 'Impossible de supprimer cette commande en attente.'));
            }
        };
        const deletePersistedDraft = async (order) => {
            if (!order?.draft_id) {
                return;
            }
            await deleteDraftById(order.draft_id);
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
                feedback.textContent = `Ajoute au moins un ${String(posVocabulary.product || 'article').toLowerCase()} avant de mettre la commande en attente.`;
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
                    throw new Error(simplifyErrorMessage(data.message || 'Impossible de mettre cette commande en attente.'));
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
                feedback.textContent = simplifyErrorMessage(error?.message || 'Une erreur est survenue pendant la mise en attente.');
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
                feedback.textContent = simplifyErrorMessage(error?.message || 'Impossible de supprimer cette commande en attente.');
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
        const updatePaymentLine = (index, key, value, focusState = null) => {
            const order = key === 'amount' ? ensureActiveOrder() : syncActiveOrderFromForm();
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
                renderCart();
                restorePaymentInputState(focusState);
                return;
            }
            if (key === 'amount') {
                payment.amount = paymentAmount(value, 0);
                refreshPaymentAmounts(order);
                return;
            }
            if (key === 'cash_account_id') {
                payment.cash_account_id = value ? Number(value) : null;
            }
            renderCart();
            restorePaymentInputState(focusState);
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
        const capturePaymentInputState = (element) => {
            if (!element?.dataset?.paymentInput) {
                return null;
            }
            let selectionStart = null;
            let selectionEnd = null;
            try {
                selectionStart = typeof element.selectionStart === 'number' ? element.selectionStart : null;
                selectionEnd = typeof element.selectionEnd === 'number' ? element.selectionEnd : selectionStart;
            } catch (error) {
                selectionStart = null;
                selectionEnd = null;
            }
            return {
                field: element.dataset.paymentInput,
                index: Number(element.dataset.paymentIndex),
                selectionStart,
                selectionEnd,
            };
        };
        const restorePaymentInputState = (stateToRestore) => {
            if (!stateToRestore || Number.isNaN(stateToRestore.index)) {
                return;
            }
            requestAnimationFrame(() => {
                const target = paymentLinesWrap.querySelector(`[data-payment-input="${stateToRestore.field}"][data-payment-index="${stateToRestore.index}"]`);
                if (!target) {
                    return;
                }
                target.focus();
                if (stateToRestore.selectionStart === null || typeof target.setSelectionRange !== 'function') {
                    return;
                }
                try {
                    target.setSelectionRange(stateToRestore.selectionStart, stateToRestore.selectionEnd ?? stateToRestore.selectionStart);
                } catch (error) {
                    // Number inputs do not always support selection ranges; focus is enough.
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
                const total = state.search || state.category ? remoteProductTotal : productCatalogTotal;
                filterCountOutput.textContent = `${visible} / ${total} ${String(posVocabulary.products || 'articles').toLowerCase()}`;
            }
            if (customerChip) {
                customerChip.textContent = customerInput.value
                    ? (customerInput.selectedOptions[0]?.textContent.trim() || posVocabulary.customer)
                    : posVocabulary.counterCustomer;
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
                linesChip.textContent = `${order?.label || posVocabulary.sale} · ${state.items.length} ligne${state.items.length > 1 ? 's' : ''}`;
            }
        };

        const addProduct = (product, { preserveSearch = false } = {}) => {
            if (product.type === 'stockable' && availableProductQty(product.id) <= 0.0001) {
                feedback.textContent = `${product.name} est en rupture sur ${sessionWarehouseName}.`;
                setScanStatus('Article en rupture');
                renderProducts();
                searchInput.focus();

                return;
            }

            const existing = state.items.find((item) => item.product_id === String(product.id) && item.discount_type === 'none' && n(item.discount_value) === 0);
            if (existing) {
                const desiredQty = n(existing.qty, 0) + 1;
                existing.qty = clampLineQuantity(existing, desiredQty);
                state.selectedLine = existing.uid;
                if (existing.qty >= desiredQty - 0.0001) {
                    feedback.textContent = `${product.name} ajoute a nouveau au panier.`;
                }
            } else {
                const line = {
                    uid: `${Date.now()}-${Math.random()}`,
                    product_id: String(product.id),
                    description: product.combo ? `${product.name} · ${product.combo.name}` : product.name,
                    qty: 1,
                    unit_price: comboPrice(product),
                    discount_type: 'none',
                    discount_value: 0,
                };
                state.items.push(line);
                state.selectedLine = line.uid;
                feedback.textContent = `${product.name} ajoute au panier.`;
            }
            setScanStatus(`${product.sku || product.barcode || 'Article'} ajoute`);
            resetBuffer();
            renderCart();
            renderProducts();
            if (!preserveSearch) {
                searchInput.value = '';
                state.search = '';
                void loadRemoteProducts();
            }
            searchInput.focus();
        };

        const updateLine = (uid, key, value) => {
            const line = state.items.find((item) => item.uid === uid);
            if (!line) {
                return;
            }
            state.selectedLine = uid;
            line[key] = ['qty', 'unit_price', 'discount_value'].includes(key) ? n(value, 0) : value;
            if (key === 'qty') {
                line.qty = clampLineQuantity(line, line.qty);
            }
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
            line.qty = clampLineQuantity(line, Math.max(0.001, n(line.qty, 0) + delta));
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
                line.qty = clampLineQuantity(line, bufferNumber('qty'));
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
                const categoryFilters = Array.isArray(product.filter_keys) ? product.filter_keys : [];
                const categoryMatch = !state.category || categoryFilters.includes(state.category);
                const searchMatch = !term || [
                    product.name,
                    product.sku,
                    product.barcode,
                    product.category_name,
                    ...(product.menu_category_names || []),
                    ...(product.tag_names || []),
                    product.combo?.name || '',
                ].some((field) => norm(field).includes(term));
                return categoryMatch && searchMatch;
            });
            const prioritizedProducts = [...filtered].sort((left, right) => {
                const leftUnavailable = left.type === 'stockable' && n(left.available_qty, 0) <= 0.0001;
                const rightUnavailable = right.type === 'stockable' && n(right.available_qty, 0) <= 0.0001;

                if (leftUnavailable !== rightUnavailable) {
                    return leftUnavailable ? 1 : -1;
                }

                return String(left.name || '').localeCompare(String(right.name || ''), 'fr', { sensitivity: 'base' });
            });

            if (!prioritizedProducts.length) {
                productGrid.innerHTML = `<div class="pos-empty" style="grid-column:1 / -1;">Aucun ${String(posVocabulary.product || 'article').toLowerCase()} ne correspond a cette recherche.</div>`;
                updateContext();
                return;
            }

            const visibleProducts = prioritizedProducts.slice(0, maxVisibleProducts);
            const hiddenCount = Math.max(0, prioritizedProducts.length - visibleProducts.length);

            const pager = remoteProductPages > 1
                ? `<div class="pos-catalog-pager">
                    <button type="button" data-catalog-page="${Math.max(remoteProductPage - 1, 1)}" ${remoteProductPage <= 1 ? 'disabled' : ''}>Precedent</button>
                    <strong>Page ${remoteProductPage} / ${remoteProductPages}</strong>
                    <button type="button" data-catalog-page="${Math.min(remoteProductPage + 1, remoteProductPages)}" ${remoteProductPage >= remoteProductPages ? 'disabled' : ''}>Suivant</button>
                </div>`
                : '';

            productGrid.innerHTML = `<div class="pos-grid ${pager ? 'has-pager' : ''}">${visibleProducts.map((product) => {
                const availableQty = product.type === 'stockable' ? n(product.available_qty, 0) : null;
                const isUnavailable = product.type === 'stockable' && availableQty <= 0.0001;
                const stockBadge = product.type === 'service'
                    ? '<span class="badge badge-success">Service</span>'
                    : `<span class="badge ${isUnavailable ? 'badge-danger' : 'badge-warning'}">${isUnavailable ? 'Rupture' : esc(posVocabulary.stock || 'Stock')}</span>`;
                const stockMeta = product.type === 'stockable' && showStockQuantity
                    ? `<div class="meta pos-stock-line ${isUnavailable ? 'is-empty' : ''}">${esc(posVocabulary.stock || 'Stock')} dispo ${formatQty(availableQty)} ${esc(product.unit || '')}</div>`
                    : '';
                const menuMeta = (product.menu_category_names || []).length
                    ? `<div class="meta">${esc(product.menu_category_names.join(' · '))}</div>`
                    : '';
                const comboMeta = product.combo
                    ? `<div class="meta">Combo ${esc(product.combo.name)}${product.combo.price_override !== null ? ` · ${money(product.combo.price_override)}` : ''}</div>`
                    : '';
                const badges = (product.tag_badges || [])
                    .slice(0, 2)
                    .map((tag) => `<span class="pos-line-tag" style="${tag.color ? `border-color:${esc(tag.color)}; color:${esc(tag.color)};` : ''}">${esc(tag.name)}</span>`)
                    .join('');

                return `
                <button type="button" class="pos-product ${isUnavailable ? 'is-unavailable' : ''}" data-product-id="${product.id}" ${isUnavailable && stockPolicy === 'block' ? 'disabled' : ''}>
                    <div class="pos-product-top">
                        <div class="pos-product-thumb ${product.image_url ? 'has-image' : `tone-${tone(product)}`}">${thumbHtml(product)}</div>
                        ${stockBadge}
                    </div>
                    <div>
                        <strong>${esc(product.name)}</strong>
                        <div class="meta">${esc(product.category_name || 'Sans categorie')} / ${esc(product.barcode || product.sku || 'Reference libre')}</div>
                        ${menuMeta}
                        ${comboMeta}
                        ${stockMeta}
                        ${badges ? `<div style="display:flex; gap:6px; flex-wrap:wrap; margin-top:6px;">${badges}</div>` : ''}
                    </div>
                    <div class="price">${money(product.price)}</div>
                </button>
            `;
            }).join('')}</div>${pager}${hiddenCount > 0 ? `<div class="pos-empty" style="margin-top:10px;">${hiddenCount} ${String(posVocabulary.product || 'article').toLowerCase()}${hiddenCount > 1 ? 's' : ''} masque(s) pour garder la caisse fluide. Affine la recherche pour les afficher.</div>` : ''}`;
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
                const stockIssue = lineStockIssue(item);
                const baseTag = item.uid === active?.uid ? 'Ligne active' : 'Toucher pour selectionner';
                const stockTag = product.type === 'stockable'
                    ? `${esc(posVocabulary.stock || 'Stock')} dispo ${formatQty(availableProductQty(item.product_id, item.uid))} ${esc(product.unit || '')}`
                    : 'Disponible immediatement';
                return `
                    <div class="pos-line ${activeClass}" data-line-card="${item.uid}">
                        <div class="pos-line-top">
                            <div class="pos-line-qty">${n(item.qty)}</div>
                            <div class="pos-line-main">
                                <div class="pos-line-title">${esc(item.description || product.name || 'Article')}</div>
                                <div class="pos-line-meta">${esc(product.barcode || product.sku || 'Sans code')} / ${esc(product.category_name || 'Sans categorie')}</div>
                                <div class="pos-line-tag ${stockIssue ? 'is-danger' : ''}">${stockIssue ? stockMessage(stockIssue.product, stockIssue.availableQty) : `${baseTag} · ${stockTag}`}</div>
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

            syncHiddenInputs(order, snapshot);

            subtotalOutput.textContent = money(subtotal);
            lineDiscountOutput.textContent = money(lineDiscounts);
            ticketDiscountOutput.textContent = money(globalDiscount);
            totalOutput.textContent = money(snapshot.total);
            if (totalHeadOutput) {
                totalHeadOutput.textContent = money(snapshot.total);
            }
            sourceDraftInput.value = order.draft_id ? String(order.draft_id) : '';
            renderPaymentLines(order, snapshot);
            applyPaymentValidation(snapshot);
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
                feedback.textContent = `${posVocabulary.counterCustomer} active pour ce ticket.`;
                return;
            }
            if (action === 'clear-cart') {
                const order = ensureActiveOrder();
                order.items = [];
                order.selectedLine = null;
                order.payments = [createPaymentLine({}, order.method || defaultMethod)];
                order.cash_received_amount = 0;
                order.cash_received_raw = '';
                order.cash_received_touched = false;
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
            const pagerButton = event.target.closest('[data-catalog-page]');
            if (pagerButton && !pagerButton.disabled) {
                remoteProductPage = Number(pagerButton.dataset.catalogPage || 1);
                void loadRemoteProducts();
                return;
            }
            const button = event.target.closest('[data-product-id]');
            if (!button) {
                return;
            }
            const product = byId[String(button.dataset.productId)];
            if (product) {
                addProduct(product, { preserveSearch: String(state.search || '').trim() !== '' });
            }
        });

        categoryRow.addEventListener('click', (event) => {
            const button = event.target.closest('[data-category]');
            if (!button) {
                return;
            }
            state.category = String(button.dataset.category || '');
            remoteProductPage = 1;
            categoryRow.querySelectorAll('[data-category]').forEach((chip) => chip.classList.toggle('is-active', chip === button));
            void loadRemoteProducts();
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
        if (syncNowButton) {
            syncNowButton.addEventListener('click', async () => {
                await syncPendingSales();
            });
        }
        if (installAppButton) {
            installAppButton.addEventListener('click', async () => {
                if (!deferredInstallPrompt) {
                    return;
                }
                deferredInstallPrompt.prompt();
                await deferredInstallPrompt.userChoice.catch(() => null);
                deferredInstallPrompt = null;
                updateOfflineUi();
            });
        }

        if (syncQueueWrap) {
            syncQueueWrap.addEventListener('click', (event) => {
                const restoreButton = event.target.closest('[data-offline-restore]');
                if (restoreButton) {
                    restoreQueuedSale(restoreButton.dataset.offlineRestore);
                    return;
                }
                const removeButton = event.target.closest('[data-offline-remove]');
                if (removeButton) {
                    dropQueuedSale(removeButton.dataset.offlineRemove);
                    feedback.textContent = 'Ticket retire de la file hors ligne.';
                }
            });
        }

        window.addEventListener('online', () => {
            lastSyncMessage = 'Connexion retablie. Synchronisation de la file hors ligne...';
            updateOfflineUi();
            syncPendingSales({ automatic: true });
        });

        window.addEventListener('offline', () => {
            lastSyncMessage = 'Connexion perdue. Les tickets valides seront gardes sur cet appareil.';
            updateOfflineUi();
        });
        window.addEventListener('beforeinstallprompt', (event) => {
            event.preventDefault();
            deferredInstallPrompt = event;
            lastSyncMessage = 'Tu peux installer cette caisse sur l appareil pour un acces plus rapide et plus stable.';
            updateOfflineUi();
        });

        window.addEventListener('appinstalled', () => {
            deferredInstallPrompt = null;
            lastSyncMessage = 'La caisse a ete installee sur cet appareil.';
            updateOfflineUi();
        });

        window.addEventListener('storage', (event) => {
            if (event.key !== queueStorageKey) {
                return;
            }
            loadPendingQueue();
            updateOfflineUi();
        });

        if ('serviceWorker' in navigator) {
            const hadServiceWorkerController = Boolean(navigator.serviceWorker.controller);
            let posSwReloaded = false;

            navigator.serviceWorker.addEventListener('controllerchange', () => {
                if (!hadServiceWorkerController || posSwReloaded || state.items.length || syncInFlight) {
                    return;
                }

                posSwReloaded = true;
                window.location.reload();
            });

            navigator.serviceWorker.addEventListener('message', (event) => {
                if (event.data?.type === 'pos-queue-updated') {
                    void hydratePendingQueueFromIndexedDb().then(() => {
                        updateOfflineUi();
                    });
                }
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
            updatePaymentLine(index, field, event.target.value, capturePaymentInputState(event.target));
        });
        paymentLinesWrap.addEventListener('input', (event) => {
            const field = event.target.dataset.paymentInput;
            const index = Number(event.target.dataset.paymentIndex);
            if (field === 'amount' && !Number.isNaN(index)) {
                updatePaymentLine(index, field, event.target.value, capturePaymentInputState(event.target));
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
            order.cash_received_touched = true;
            order.cash_received_raw = cashReceivedInput.value;
            order.cash_received_amount = cashReceivedInput.value === '' ? 0 : n(cashReceivedInput.value, 0);
            renderCart();
        });

        let searchDebounceTimer = null;
        searchInput.addEventListener('input', (event) => {
            state.search = event.target.value;
            remoteProductPage = 1;
            feedback.textContent = state.search ? 'Resultats filtres en direct. Appuie sur Entree pour ajouter le meilleur resultat.' : `Scanne un code-barres ou clique sur un ${String(posVocabulary.product || 'article').toLowerCase()} pour l ajouter au panier.`;
            if (searchDebounceTimer) {
                window.clearTimeout(searchDebounceTimer);
            }
            searchDebounceTimer = window.setTimeout(() => {
                void loadRemoteProducts();
            }, isLowPowerDevice ? 120 : 60);
        });
        searchInput.addEventListener('keydown', async (event) => {
            if (event.key !== 'Enter') {
                return;
            }
            event.preventDefault();
            const query = norm(searchInput.value);
            if (!query) {
                return;
            }
            let exact = productCatalog.find((product) => [product.barcode, product.sku].some((field) => norm(field) === query));
            if (!exact) {
                const results = await loadRemoteProducts();
                exact = results.find((product) => [product.barcode, product.sku].some((field) => norm(field) === query));
            }
            if (exact) {
                addProduct(exact);
                return;
            }
            const match = productCatalog.find((product) => norm(product.name).includes(query));
            if (match) {
                addProduct(match);
                return;
            }
            feedback.textContent = `Aucun ${String(posVocabulary.product || 'article').toLowerCase()} trouve pour ce scan ou cette recherche.`;
            setScanStatus('Article introuvable');
        });
        if (quickSellButton) {
            quickSellButton.addEventListener('click', () => {
                searchInput.focus();
                searchInput.select();
                feedback.textContent = `Mode ${String(posVocabulary.sale || 'vente').toLowerCase()} actif: scanne ou recherche un ${String(posVocabulary.product || 'article').toLowerCase()}.`;
                setScanStatus('Douchette active');
            });
        }
        if (quickCashButton) {
            quickCashButton.addEventListener('click', () => {
                if (quickCashPayment) {
                    const order = syncActiveOrderFromForm();
                    const snapshot = orderSnapshot(order);
                    order.method = 'cash';
                    order.payments = [createPaymentLine({ method: 'cash', amount: snapshot.total }, 'cash')];
                    const received = cashRoundingEnabled && cashRoundingPrecision > 0
                        ? Math.ceil(snapshot.total / cashRoundingPrecision) * cashRoundingPrecision
                        : snapshot.total;
                    order.cash_received_amount = received;
                    order.cash_received_raw = String(received);
                    order.cash_received_touched = true;
                    loadOrderIntoForm(order);
                    renderCart();
                }
                paymentPanel?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                cashReceivedInput?.focus();
                feedback.textContent = 'Paiement prepare. Verifie le montant recu puis valide le ticket.';
            });
        }
        if (quickReceiveButton) {
            quickReceiveButton.addEventListener('click', () => {
                window.location.href = @json(route('goods-receipts.index'));
            });
        }

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

        const attemptSaleSubmission = () => {
            const order = syncActiveOrderFromForm();
            const snapshot = orderSnapshot(order);
            sourceDraftInput.value = order.draft_id ? String(order.draft_id) : '';
            if (posSyncKeyInput) {
                posSyncKeyInput.value = normalizeSyncKey(order.sync_key || generateSyncKey());
            }
            if (!state.items.length) {
            feedback.textContent = `Ajoute au moins un ${String(posVocabulary.product || 'article').toLowerCase()} avant de valider le ticket.`;
                revealSubmitIssue({ target: emptyState || productGrid || searchInput, focus: searchInput });
                searchInput.focus();
                return;
            }
            const stockIssue = currentOrderStockIssue();
            if (stockIssue && stockPolicy === 'block') {
                feedback.textContent = stockMessage(stockIssue.product, stockIssue.availableQty);
                revealSubmitIssue({ target: linesWrap });
                return;
            }
            if (stockIssue && stockPolicy === 'warn' && !window.confirm(`${stockMessage(stockIssue.product, stockIssue.availableQty)} Continuer quand meme ?`)) {
                return;
            }
            const paymentValidation = applyPaymentValidation(snapshot);
            if (!paymentValidation.valid) {
                feedback.textContent = paymentValidation.message;
                if (paymentValidation.cashInvalid) {
                    revealSubmitIssue({ target: paymentPanel, focus: cashReceivedInput });
                } else {
                    revealSubmitIssue({ target: paymentPanel });
                }
                return;
            }
            void submitCurrentSale(order, snapshot);
        };

        saleForm.addEventListener('submit', (event) => {
            event.preventDefault();
            attemptSaleSubmission();
        });
        submitButton.addEventListener('click', (event) => {
            event.preventDefault();
            attemptSaleSubmission();
        });

        void registerPosServiceWorker();
        loadPendingQueue();
        loadOrderIntoForm(ensureActiveOrder());
        renderProducts();
        renderCart();
        updateContext();
        updateTouchUi();
        updateOfflineUi();
        void hydratePendingQueueFromIndexedDb().then(() => {
            updateOfflineUi();
            if (window.navigator.onLine && pendingQueue.length) {
                syncPendingSales({ automatic: true });
            }
        });
        searchInput.focus();
    </script>
@endsection
