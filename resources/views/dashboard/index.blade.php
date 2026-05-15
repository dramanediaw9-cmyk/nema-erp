@extends('layouts.app')

@section('title', 'Dashboard - Nema ERP')
@section('page-title', $dashboardProfile['page_title'])

@push('page-styles')
    <style>
        .dashboard-shell {
            display: grid;
            gap: 22px;
        }
        .dashboard-banner {
            position: relative;
            overflow: hidden;
        }
        .dashboard-banner::after {
            content: "";
            position: absolute;
            inset: auto -60px -90px auto;
            width: 220px;
            height: 220px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.32);
            filter: blur(4px);
            pointer-events: none;
        }
        .dashboard-banner--warm {
            background: linear-gradient(135deg, #fffaf0 0%, #fff2da 100%);
            border-color: rgba(197, 106, 24, 0.18);
        }
        .dashboard-banner--hero {
            background: linear-gradient(135deg, rgba(255, 249, 240, 0.96) 0%, rgba(240, 248, 246, 0.96) 55%, rgba(255, 241, 221, 0.92) 100%);
            border-color: rgba(11, 79, 86, 0.12);
        }
        .dashboard-banner--period-open {
            background: linear-gradient(135deg, #f7fbfc 0%, #eef6f8 100%);
            border-color: rgba(15, 118, 110, 0.14);
        }
        .dashboard-banner--period-closed {
            background: linear-gradient(135deg, #fff7e8 0%, #fff1d6 100%);
            border-color: rgba(197, 106, 24, 0.18);
        }
        .dashboard-banner--sector {
            background: linear-gradient(135deg, rgba(239, 250, 248, 0.98) 0%, rgba(247, 252, 251, 0.96) 58%, rgba(255, 244, 227, 0.94) 100%);
            border-color: rgba(15, 118, 110, 0.18);
        }
        .dashboard-banner--premium {
            background: linear-gradient(135deg, rgba(10, 27, 44, 0.98) 0%, rgba(12, 64, 89, 0.94) 52%, rgba(179, 126, 30, 0.22) 100%);
            border-color: rgba(12, 64, 89, 0.18);
            color: #eef8f8;
        }
        .dashboard-banner--premium .dashboard-copy,
        .dashboard-banner--premium .muted,
        .dashboard-banner--premium .help {
            color: rgba(238, 248, 248, 0.78);
        }
        .dashboard-banner--premium .dashboard-chip {
            background: rgba(255, 255, 255, 0.1);
            color: #eef8f8;
            border-color: rgba(255, 255, 255, 0.14);
        }
        .dashboard-banner__layout {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: minmax(0, 1.45fr) minmax(280px, .85fr);
            gap: 20px;
            align-items: start;
        }
        .dashboard-banner__copy {
            display: grid;
            gap: 12px;
        }
        .dashboard-display {
            margin: 0;
            font-size: clamp(28px, 4vw, 42px);
            line-height: 1.04;
            letter-spacing: -.04em;
        }
        .dashboard-copy {
            margin: 0;
            font-size: 15px;
            max-width: 760px;
        }
        .dashboard-banner__aside {
            display: grid;
            gap: 14px;
        }
        .dashboard-panel {
            border: 1px solid rgba(102, 82, 56, 0.1);
            border-radius: 20px;
            padding: 16px 18px;
            background: rgba(255, 255, 255, 0.72);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.85);
        }
        .dashboard-panel--contrast {
            background: linear-gradient(135deg, rgba(11, 79, 86, 0.94) 0%, rgba(15, 118, 110, 0.88) 100%);
            color: #effaf8;
            border-color: rgba(11, 79, 86, 0.12);
        }
        .dashboard-panel--contrast .muted,
        .dashboard-panel--contrast .help {
            color: rgba(239, 250, 248, 0.78);
        }
        .dashboard-panel strong {
            display: block;
            font-size: 16px;
            margin-bottom: 8px;
        }
        .dashboard-panel p {
            margin: 0;
        }
        .dashboard-chip-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .dashboard-chip {
            border: 1px solid rgba(102, 82, 56, 0.12);
            background: rgba(255, 255, 255, 0.76);
            border-radius: 999px;
            padding: 9px 13px;
            font-size: 13px;
            font-weight: 700;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.9);
        }
        .dashboard-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        .dashboard-section-head {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: flex-start;
            margin-bottom: 16px;
        }
        .dashboard-section-head h2 {
            margin: 0;
            font-size: 24px;
            letter-spacing: -.03em;
        }
        .dashboard-section-head p {
            margin: 6px 0 0;
        }
        .dashboard-link-grid,
        .dashboard-kpi-grid,
        .dashboard-watch-grid,
        .dashboard-checklist-grid,
        .dashboard-analysis-grid {
            display: grid;
            gap: 16px;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        }
        .dashboard-link-card,
        .dashboard-watch-card,
        .dashboard-analysis-card,
        .dashboard-checklist-card,
        .dashboard-kpi-card {
            position: relative;
            display: block;
            border: 1px solid rgba(102, 82, 56, 0.1);
            border-radius: 20px;
            padding: 18px;
            background: rgba(255, 255, 255, 0.76);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.92);
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
        }
        .dashboard-link-card:hover,
        .dashboard-watch-card:hover,
        .dashboard-analysis-card:hover,
        .dashboard-checklist-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 18px 32px rgba(42, 28, 18, 0.08);
            border-color: rgba(15, 118, 110, 0.18);
        }
        .dashboard-link-card::after,
        .dashboard-watch-card::after,
        .dashboard-analysis-card::after {
            content: ">";
            position: absolute;
            right: 18px;
            top: 18px;
            color: rgba(15, 118, 110, 0.6);
            font-weight: 800;
        }
        .dashboard-link-card strong,
        .dashboard-analysis-card .stat-value,
        .dashboard-watch-card .stat-value {
            display: block;
        }
        .dashboard-link-card p,
        .dashboard-analysis-card p,
        .dashboard-watch-card p,
        .dashboard-checklist-card p {
            margin: 8px 0 0;
        }
        .dashboard-kpi-card {
            background: linear-gradient(180deg, rgba(255, 254, 250, 0.96) 0%, rgba(247, 239, 228, 0.92) 100%);
        }
        .dashboard-kpi-card .stat-value {
            margin-top: 10px;
        }
        .dashboard-kpi-card .help {
            margin-top: 8px;
        }
        .dashboard-watch-card .muted:first-child,
        .dashboard-analysis-card .muted:first-child {
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: .08em;
        }
        .dashboard-period-summary {
            display: grid;
            gap: 18px;
        }
        .dashboard-split {
            display: grid;
            gap: 20px;
            grid-template-columns: minmax(0, 1.12fr) minmax(300px, .88fr);
        }
        .dashboard-activity-list {
            display: grid;
            gap: 14px;
        }
        .dashboard-activity-item {
            padding-bottom: 14px;
            border-bottom: 1px solid rgba(102, 82, 56, 0.1);
        }
        .dashboard-activity-item:last-child {
            padding-bottom: 0;
            border-bottom: 0;
        }
        .dashboard-activity-item strong {
            display: block;
            margin-bottom: 6px;
        }
        .dashboard-empty {
            text-align: center;
            padding: 28px 18px;
            border: 1px dashed rgba(102, 82, 56, 0.18);
            border-radius: 20px;
            color: var(--muted);
            background: rgba(255, 255, 255, 0.5);
        }
        .dashboard-micro-grid {
            display: grid;
            gap: 10px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .dashboard-micro-item {
            border-radius: 16px;
            padding: 12px 14px;
            background: rgba(255, 255, 255, 0.16);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        .dashboard-micro-item span {
            display: block;
            font-size: 12px;
            letter-spacing: .08em;
            text-transform: uppercase;
            opacity: .82;
        }
        .dashboard-micro-item strong {
            margin: 8px 0 0;
            font-size: 20px;
        }
        .dashboard-premium-grid {
            display: grid;
            gap: 16px;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            margin-top: 18px;
        }
        .dashboard-premium-card {
            position: relative;
            display: block;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 20px;
            padding: 18px;
            color: #eef8f8;
            background: rgba(255, 255, 255, 0.08);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
        }
        .dashboard-premium-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 18px 32px rgba(6, 17, 28, 0.22);
            border-color: rgba(255, 255, 255, 0.22);
        }
        .dashboard-premium-card::after {
            content: ">";
            position: absolute;
            right: 18px;
            top: 18px;
            color: rgba(255, 255, 255, 0.7);
            font-weight: 800;
        }
        .dashboard-premium-card strong {
            display: block;
            margin-top: 12px;
            font-size: 18px;
            line-height: 1.2;
        }
        .dashboard-premium-card .stat-value {
            margin-top: 14px;
            color: #ffffff;
        }
        .dashboard-premium-card .muted,
        .dashboard-premium-card .help {
            color: rgba(238, 248, 248, 0.78);
        }
        .dashboard-premium-meta {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }
        .dashboard-premium-card--high {
            background: linear-gradient(180deg, rgba(163, 48, 43, 0.18) 0%, rgba(255, 255, 255, 0.08) 100%);
        }
        .dashboard-premium-card--medium {
            background: linear-gradient(180deg, rgba(197, 106, 24, 0.16) 0%, rgba(255, 255, 255, 0.08) 100%);
        }
        .dashboard-premium-card--low {
            background: linear-gradient(180deg, rgba(15, 118, 110, 0.18) 0%, rgba(255, 255, 255, 0.08) 100%);
        }
        .dashboard-app-grid {
            display: grid;
            gap: 16px;
            grid-template-columns: repeat(auto-fit, minmax(138px, 1fr));
        }
        .dashboard-app-card {
            --app-accent: #0f766e;
            --app-surface: #effaf8;
            --app-soft: #d7f3ee;
            --app-border: #b6e7de;
            --app-ink: #0b4f56;
            --app-muted: #4b6d70;
            --app-shadow: rgba(15, 118, 110, 0.16);
            --app-badge-start: #ffffff;
            --app-badge-end: #bfece5;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            gap: 10px;
            min-height: 168px;
            border: 1px solid var(--app-border);
            border-radius: 22px;
            padding: 18px 14px 16px;
            background: linear-gradient(180deg, var(--app-surface) 0%, var(--app-soft) 100%);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.94), 0 10px 24px var(--app-shadow);
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
            text-align: center;
        }
        .dashboard-app-card::before {
            content: "";
            position: absolute;
            inset: 0 0 auto 0;
            height: 4px;
            background: linear-gradient(90deg, var(--app-accent) 0%, var(--app-badge-end) 100%);
        }
        .dashboard-app-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 34px var(--app-shadow);
            border-color: var(--app-accent);
        }
        .dashboard-app-card__top,
        .dashboard-card-lead {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        .dashboard-app-card__family {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 5px 9px;
            border-radius: 999px;
            border: 1px solid var(--app-border);
            background: rgba(255, 255, 255, 0.56);
            color: var(--app-ink);
            font-size: 11px;
            line-height: 1;
            font-weight: 800;
            letter-spacing: .06em;
            text-transform: uppercase;
        }
        .dashboard-app-card__label {
            margin: 0;
            font-size: 15px;
            font-weight: 800;
            line-height: 1.2;
            max-width: 100%;
            color: var(--app-ink);
        }
        .dashboard-app-card__hint {
            margin: 0;
            font-size: 12px;
            line-height: 1.35;
            color: var(--app-muted);
            display: -webkit-box;
            overflow: hidden;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
        }
        .dashboard-icon-badge {
            width: 48px;
            height: 48px;
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            border: 1px solid transparent;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.62);
        }
        .dashboard-icon-badge svg {
            display: block;
        }
        .dashboard-icon-badge--action,
        .dashboard-icon-badge--quick {
            color: #0b4f56;
            background: linear-gradient(180deg, rgba(15, 118, 110, 0.18) 0%, rgba(239, 250, 248, 0.94) 100%);
            border-color: rgba(15, 118, 110, 0.12);
        }
        .dashboard-icon-badge--app {
            width: 62px;
            height: 62px;
            border-radius: 20px;
            color: var(--app-accent);
            background: linear-gradient(180deg, var(--app-badge-start) 0%, var(--app-badge-end) 100%);
            border-color: var(--app-border);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.95), 0 10px 18px var(--app-shadow);
        }
        .dashboard-icon-badge--sector,
        .dashboard-icon-badge--signal {
            color: #176b4d;
            background: linear-gradient(180deg, rgba(23, 107, 77, 0.16) 0%, rgba(239, 247, 242, 0.94) 100%);
            border-color: rgba(23, 107, 77, 0.12);
        }
        .dashboard-icon-badge--premium {
            color: #fff4de;
            background: linear-gradient(180deg, rgba(197, 106, 24, 0.42) 0%, rgba(163, 48, 43, 0.28) 100%);
            border-color: rgba(255, 255, 255, 0.14);
        }
        .dashboard-icon-badge--watch {
            color: #9a5b00;
            background: linear-gradient(180deg, rgba(197, 106, 24, 0.18) 0%, rgba(255, 247, 232, 0.94) 100%);
            border-color: rgba(197, 106, 24, 0.14);
        }
        .dashboard-icon-badge--spotlight,
        .dashboard-icon-badge--kpi,
        .dashboard-icon-badge--generic {
            color: #6e6154;
            background: linear-gradient(180deg, rgba(110, 97, 84, 0.14) 0%, rgba(255, 252, 246, 0.94) 100%);
            border-color: rgba(110, 97, 84, 0.12);
        }
        .dashboard-card-lead + .stat-value {
            margin-top: 14px;
        }
        .dashboard-card-label {
            margin: 0;
            font-size: 15px;
            font-weight: 800;
            line-height: 1.25;
        }
        .dashboard-card-caption {
            margin-top: 4px;
            font-size: 12px;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: rgba(110, 97, 84, 0.72);
        }
        .dashboard-launcher {
            position: relative;
            overflow: hidden;
            padding: 24px;
            color: #edf4fb;
            background:
                radial-gradient(circle at 12% 0%, rgba(54, 214, 205, 0.18) 0, rgba(54, 214, 205, 0) 28%),
                radial-gradient(circle at 88% 8%, rgba(255, 166, 104, 0.22) 0, rgba(255, 166, 104, 0) 24%),
                linear-gradient(180deg, #0f1b2b 0%, #0a1320 56%, #08101a 100%);
            border-color: rgba(117, 148, 181, 0.24);
            box-shadow: 0 28px 60px rgba(8, 14, 26, 0.34);
        }
        .dashboard-launcher::before {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: inherit;
            pointer-events: none;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.06) 0%, rgba(255, 255, 255, 0) 34%);
        }
        .dashboard-launcher__top,
        .dashboard-launcher__hero {
            position: relative;
            z-index: 1;
        }
        .dashboard-launcher__top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
        }
        .dashboard-launcher__search {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            min-width: min(100%, 520px);
            padding: 12px 16px;
            border-radius: 18px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            background: rgba(255, 255, 255, 0.06);
            color: rgba(237, 244, 251, 0.88);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.06);
            transition: transform .18s ease, border-color .18s ease, background .18s ease;
        }
        .dashboard-launcher__search:hover {
            transform: translateY(-1px);
            border-color: rgba(255, 255, 255, 0.16);
            background: rgba(255, 255, 255, 0.09);
        }
        .dashboard-launcher__search-icon,
        .dashboard-launcher__counter,
        .dashboard-launcher__avatar {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .dashboard-launcher__search-icon {
            width: 34px;
            height: 34px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.08);
            color: #7ce7d6;
        }
        .dashboard-launcher__actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .dashboard-launcher__counter,
        .dashboard-launcher__avatar {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            background: rgba(255, 255, 255, 0.07);
            color: #edf4fb;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.06);
        }
        .dashboard-launcher__counter {
            position: relative;
        }
        .dashboard-launcher__counter strong {
            position: absolute;
            right: 4px;
            top: 4px;
            min-width: 18px;
            height: 18px;
            padding: 0 4px;
            border-radius: 999px;
            background: linear-gradient(135deg, #ff8a65 0%, #ff4d78 100%);
            color: #fff;
            font-size: 10px;
            line-height: 18px;
            text-align: center;
        }
        .dashboard-launcher__avatar {
            background: linear-gradient(135deg, rgba(80, 205, 195, 0.82) 0%, rgba(46, 120, 207, 0.72) 100%);
            color: #03101b;
            font-weight: 900;
        }
        .dashboard-launcher__hero {
            margin-top: 20px;
            display: grid;
            grid-template-columns: minmax(0, 1.24fr) minmax(300px, .76fr);
            gap: 18px;
            align-items: start;
        }
        .dashboard-launcher__copy .badge {
            background: rgba(255, 255, 255, 0.09);
            color: #d7e7ff;
        }
        .dashboard-launcher__title {
            margin: 12px 0 0;
            font-family: "Aptos Display", "Aptos", "Trebuchet MS", sans-serif;
            font-size: clamp(30px, 5vw, 42px);
            line-height: .98;
            letter-spacing: -.05em;
            color: #f8fbff;
        }
        .dashboard-launcher__body {
            margin: 12px 0 0;
            max-width: 720px;
            color: rgba(237, 244, 251, 0.76);
            font-size: 15px;
        }
        .dashboard-launcher__family-strip {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 18px;
        }
        .dashboard-launcher__family {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            background: rgba(255, 255, 255, 0.06);
            color: rgba(237, 244, 251, 0.84);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.06);
        }
        .dashboard-launcher__family strong {
            color: var(--launcher-accent, #7ce7d6);
            font-size: 12px;
        }
        .dashboard-launcher__status-grid {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .dashboard-launcher__status-card,
        .dashboard-launcher__focus-card {
            display: block;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            background: rgba(255, 255, 255, 0.06);
            color: #edf4fb;
            padding: 16px;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.05);
            transition: transform .18s ease, border-color .18s ease, background .18s ease;
        }
        .dashboard-launcher__status-card:hover,
        .dashboard-launcher__focus-card:hover {
            transform: translateY(-2px);
            border-color: rgba(255, 255, 255, 0.16);
            background: rgba(255, 255, 255, 0.09);
        }
        .dashboard-launcher__status-card span,
        .dashboard-launcher__status-card small {
            display: block;
        }
        .dashboard-launcher__status-card span {
            color: rgba(237, 244, 251, 0.68);
            font-size: 12px;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .dashboard-launcher__status-card strong {
            display: block;
            margin-top: 10px;
            font-size: 28px;
            line-height: 1;
            letter-spacing: -.04em;
            color: #fff;
        }
        .dashboard-launcher__status-card small {
            margin-top: 8px;
            color: rgba(237, 244, 251, 0.72);
            font-size: 13px;
            line-height: 1.35;
        }
        .dashboard-app-grid {
            position: relative;
            z-index: 1;
            margin-top: 24px;
            display: grid;
            gap: 18px 14px;
            grid-template-columns: repeat(auto-fit, minmax(112px, 1fr));
        }
        .dashboard-app-card {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            gap: 9px;
            min-height: 136px;
            padding: 2px 4px 0;
            background: transparent;
            border: 0;
            box-shadow: none;
            transition: transform .18s ease;
            text-align: center;
        }
        .dashboard-app-card:hover {
            transform: translateY(-3px);
        }
        .dashboard-app-card__badge {
            position: absolute;
            right: 10px;
            top: 0;
            min-width: 24px;
            height: 24px;
            padding: 0 7px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #ff8a65 0%, #ff4d78 100%);
            color: #fff;
            font-size: 11px;
            font-weight: 900;
            z-index: 2;
            box-shadow: 0 8px 14px rgba(255, 88, 122, 0.28);
        }
        .dashboard-app-card__label {
            position: relative;
            z-index: 1;
            margin: 0;
            min-height: 2.4em;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            color: #f8fbff;
            font-size: 13px;
            font-weight: 800;
            line-height: 1.26;
            text-wrap: balance;
            text-shadow: 0 4px 16px rgba(0, 0, 0, 0.24);
        }
        .dashboard-app-card__family {
            position: relative;
            z-index: 1;
            display: block;
            color: rgba(237, 244, 251, 0.46);
            font-size: 9px;
            line-height: 1;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
        }
        .dashboard-icon-badge--app {
            position: relative;
            width: 82px;
            height: 82px;
            border-radius: 26px;
            color: #fff;
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.08) 0%, rgba(255, 255, 255, 0.02) 100%),
                linear-gradient(180deg, #172236 0%, #0e1523 100%);
            border-color: rgba(255, 255, 255, 0.06);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.08),
                0 18px 30px rgba(4, 8, 16, 0.24);
            overflow: hidden;
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
        }
        .dashboard-icon-badge--app::before {
            content: "";
            position: absolute;
            width: 42px;
            height: 42px;
            left: 11px;
            top: 19px;
            border-radius: 16px;
            background: linear-gradient(135deg, var(--app-accent) 0%, var(--app-badge-end) 100%);
            transform: rotate(-16deg);
            opacity: .96;
        }
        .dashboard-icon-badge--app::after {
            content: "";
            position: absolute;
            width: 20px;
            height: 50px;
            right: 13px;
            top: 11px;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.24);
            transform: rotate(26deg);
        }
        .dashboard-icon-badge--app svg {
            position: relative;
            z-index: 1;
            filter: drop-shadow(0 8px 12px rgba(0, 0, 0, 0.22));
        }
        .dashboard-app-card:hover .dashboard-icon-badge--app {
            transform: translateY(-2px);
            border-color: rgba(255, 255, 255, 0.1);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.08),
                0 24px 36px rgba(4, 8, 16, 0.3);
        }
        .dashboard-launcher__focus-grid {
            position: relative;
            z-index: 1;
            margin-top: 22px;
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        }
        .dashboard-launcher__focus-card .dashboard-card-caption,
        .dashboard-launcher__focus-card .muted {
            color: rgba(237, 244, 251, 0.72);
        }
        .dashboard-launcher__focus-card .dashboard-card-label,
        .dashboard-launcher__focus-card .stat-value {
            color: #fff;
        }
        .dashboard-collapsible__toggle {
            display: none;
            width: 100%;
            border: 0;
            padding: 0;
            text-align: left;
            background: transparent;
            color: inherit;
        }
        .dashboard-collapsible__toggle-inner {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 14px;
            align-items: center;
        }
        .dashboard-collapsible__toggle-copy {
            display: grid;
            gap: 4px;
            min-width: 0;
        }
        .dashboard-collapsible__eyebrow {
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: var(--accent);
        }
        .dashboard-collapsible__title {
            font-size: 16px;
            font-weight: 800;
            line-height: 1.2;
            letter-spacing: -.02em;
        }
        .dashboard-collapsible__hint {
            font-size: 12px;
            line-height: 1.35;
            color: rgba(74, 62, 50, 0.72);
        }
        .dashboard-collapsible__chevron {
            width: 34px;
            height: 34px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(15, 118, 110, 0.08);
            color: var(--brand-deep);
            transition: transform .18s ease, background .18s ease;
        }
        .dashboard-collapsible__chevron::before {
            content: "⌄";
            font-size: 18px;
            line-height: 1;
            transform: translateY(-1px);
        }
        .dashboard-collapsible__body {
            display: block;
        }
        .dashboard-launcher ~ section.card,
        .dashboard-launcher ~ .dashboard-split section.card,
        .dashboard-launcher ~ .dashboard-collapsible--plain .dashboard-split section.card {
            padding: 18px;
            background: linear-gradient(180deg, rgba(255, 253, 249, 0.84) 0%, rgba(255, 250, 245, 0.78) 100%);
            border-color: rgba(102, 82, 56, 0.1);
            box-shadow: 0 12px 28px rgba(42, 28, 18, 0.05);
            backdrop-filter: blur(12px);
        }
        .dashboard-launcher ~ section.card .dashboard-display {
            font-size: clamp(22px, 3.2vw, 30px);
        }
        .dashboard-launcher ~ section.card .dashboard-copy,
        .dashboard-launcher ~ .dashboard-split section.card .muted,
        .dashboard-launcher ~ .dashboard-collapsible--plain .dashboard-split section.card .muted,
        .dashboard-launcher ~ section.card .muted,
        .dashboard-launcher ~ section.card .help {
            color: rgba(74, 62, 50, 0.78);
        }
        .dashboard-launcher ~ section.card .dashboard-section-head,
        .dashboard-launcher ~ .dashboard-split section.card .dashboard-section-head,
        .dashboard-launcher ~ .dashboard-collapsible--plain .dashboard-split section.card .dashboard-section-head {
            margin-bottom: 12px;
        }
        .dashboard-launcher ~ section.card .dashboard-section-head h2,
        .dashboard-launcher ~ .dashboard-split section.card .dashboard-section-head h2,
        .dashboard-launcher ~ .dashboard-collapsible--plain .dashboard-split section.card .dashboard-section-head h2 {
            font-size: 20px;
        }
        .dashboard-launcher ~ section.card .dashboard-panel,
        .dashboard-launcher ~ .dashboard-split section.card .dashboard-panel,
        .dashboard-launcher ~ .dashboard-collapsible--plain .dashboard-split section.card .dashboard-panel {
            padding: 14px 15px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.62);
            box-shadow: none;
            border-color: rgba(102, 82, 56, 0.08);
        }
        .dashboard-launcher ~ section.card .dashboard-chip {
            padding: 7px 10px;
            font-size: 12px;
            background: rgba(255, 255, 255, 0.65);
            box-shadow: none;
        }
        .dashboard-launcher ~ section.card .dashboard-link-card,
        .dashboard-launcher ~ section.card .dashboard-watch-card,
        .dashboard-launcher ~ section.card .dashboard-analysis-card,
        .dashboard-launcher ~ section.card .dashboard-checklist-card,
        .dashboard-launcher ~ .dashboard-split section.card .dashboard-analysis-card,
        .dashboard-launcher ~ .dashboard-split section.card .dashboard-watch-card,
        .dashboard-launcher ~ .dashboard-collapsible--plain .dashboard-split section.card .dashboard-analysis-card,
        .dashboard-launcher ~ .dashboard-collapsible--plain .dashboard-split section.card .dashboard-watch-card,
        .dashboard-launcher ~ .dashboard-kpi-grid .dashboard-kpi-card,
        .dashboard-launcher ~ .dashboard-collapsible--plain .dashboard-kpi-grid .dashboard-kpi-card {
            border-radius: 18px;
            padding: 16px;
            background: rgba(255, 255, 255, 0.66);
            box-shadow: none;
            border-color: rgba(102, 82, 56, 0.08);
        }
        .dashboard-launcher ~ section.card .dashboard-link-card:hover,
        .dashboard-launcher ~ section.card .dashboard-watch-card:hover,
        .dashboard-launcher ~ section.card .dashboard-analysis-card:hover,
        .dashboard-launcher ~ .dashboard-split section.card .dashboard-analysis-card:hover,
        .dashboard-launcher ~ .dashboard-collapsible--plain .dashboard-split section.card .dashboard-analysis-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 24px rgba(42, 28, 18, 0.06);
        }
        .dashboard-launcher ~ section.card .stat-value,
        .dashboard-launcher ~ .dashboard-split section.card .stat-value,
        .dashboard-launcher ~ .dashboard-collapsible--plain .dashboard-split section.card .stat-value,
        .dashboard-launcher ~ .dashboard-kpi-grid .stat-value,
        .dashboard-launcher ~ .dashboard-collapsible--plain .dashboard-kpi-grid .stat-value {
            font-size: 24px;
            margin-top: 12px;
        }
        .dashboard-launcher ~ section.card.dashboard-banner--hero,
        .dashboard-launcher ~ section.card.dashboard-banner--sector,
        .dashboard-launcher ~ section.card.dashboard-banner--period-open,
        .dashboard-launcher ~ section.card.dashboard-banner--period-closed {
            background: linear-gradient(180deg, rgba(255, 253, 249, 0.84) 0%, rgba(245, 241, 234, 0.78) 100%);
        }
        .dashboard-launcher ~ section.card.dashboard-banner--premium {
            color: var(--text);
            background: linear-gradient(180deg, rgba(247, 251, 252, 0.9) 0%, rgba(240, 246, 247, 0.82) 100%);
            border-color: rgba(15, 118, 110, 0.1);
        }
        .dashboard-launcher ~ section.card.dashboard-banner--premium .dashboard-copy,
        .dashboard-launcher ~ section.card.dashboard-banner--premium .muted,
        .dashboard-launcher ~ section.card.dashboard-banner--premium .help,
        .dashboard-launcher ~ section.card.dashboard-banner--premium .dashboard-card-caption {
            color: rgba(50, 68, 71, 0.78);
        }
        .dashboard-launcher ~ section.card.dashboard-banner--premium .dashboard-chip {
            background: rgba(15, 118, 110, 0.08);
            color: #0b4f56;
            border-color: rgba(15, 118, 110, 0.1);
        }
        .dashboard-launcher ~ section.card.dashboard-banner--premium .dashboard-panel {
            background: rgba(255, 255, 255, 0.7);
            color: var(--text);
        }
        .dashboard-launcher ~ section.card.dashboard-banner--premium .dashboard-premium-card {
            color: var(--text);
            border-color: rgba(102, 82, 56, 0.08);
            box-shadow: none;
        }
        .dashboard-launcher ~ section.card.dashboard-banner--premium .dashboard-premium-card .stat-value,
        .dashboard-launcher ~ section.card.dashboard-banner--premium .dashboard-premium-card strong {
            color: var(--text);
        }
        .dashboard-launcher ~ section.card.dashboard-banner--premium .dashboard-premium-card::after {
            color: rgba(15, 118, 110, 0.58);
        }
        .dashboard-launcher ~ section.card.dashboard-banner--premium .dashboard-premium-card--high {
            background: linear-gradient(180deg, rgba(180, 35, 24, 0.08) 0%, rgba(255, 255, 255, 0.68) 100%);
        }
        .dashboard-launcher ~ section.card.dashboard-banner--premium .dashboard-premium-card--medium {
            background: linear-gradient(180deg, rgba(197, 106, 24, 0.08) 0%, rgba(255, 255, 255, 0.68) 100%);
        }
        .dashboard-launcher ~ section.card.dashboard-banner--premium .dashboard-premium-card--low {
            background: linear-gradient(180deg, rgba(15, 118, 110, 0.08) 0%, rgba(255, 255, 255, 0.68) 100%);
        }
        @media (max-width: 1080px) {
            .dashboard-banner__layout,
            .dashboard-split,
            .dashboard-launcher__hero {
                grid-template-columns: 1fr;
            }
            .dashboard-micro-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
        @media (max-width: 760px) {
            .dashboard-launcher {
                padding: 18px;
            }
            .dashboard-launcher__hero {
                margin-top: 14px;
                gap: 14px;
            }
            .dashboard-launcher__title {
                font-size: clamp(24px, 8vw, 30px);
            }
            .dashboard-launcher__body,
            .dashboard-launcher__family-strip,
            .dashboard-launcher__focus-grid {
                display: none;
            }
            .dashboard-launcher__status-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
            }
            .dashboard-launcher__status-card {
                padding: 12px;
                border-radius: 16px;
            }
            .dashboard-launcher__status-card strong {
                margin-top: 8px;
                font-size: 22px;
            }
            .dashboard-launcher__status-card small {
                margin-top: 6px;
                font-size: 12px;
            }
            .dashboard-collapsible {
                overflow: hidden;
            }
            .dashboard-collapsible.card {
                padding: 0 !important;
            }
            .dashboard-collapsible__toggle {
                display: block;
                padding: 16px;
            }
            .dashboard-collapsible__body {
                padding: 0 16px 16px;
            }
            .dashboard-collapsible[data-dashboard-collapsed="true"] .dashboard-collapsible__body {
                display: none;
            }
            .dashboard-collapsible[data-dashboard-collapsed="true"] .dashboard-collapsible__chevron {
                transform: rotate(0deg);
            }
            .dashboard-collapsible[data-dashboard-collapsed="false"] .dashboard-collapsible__chevron {
                transform: rotate(180deg);
            }
            .dashboard-collapsible--plain {
                border: 1px solid rgba(102, 82, 56, 0.1);
                border-radius: 20px;
                background: linear-gradient(180deg, rgba(255, 253, 249, 0.84) 0%, rgba(255, 250, 245, 0.78) 100%);
                box-shadow: 0 12px 28px rgba(42, 28, 18, 0.05);
                backdrop-filter: blur(12px);
            }
            .dashboard-collapsible--plain .dashboard-collapsible__body {
                padding: 0 0 16px;
            }
            .dashboard-launcher__top {
                flex-direction: column;
                align-items: stretch;
            }
            .dashboard-launcher__search {
                min-width: 0;
                width: 100%;
            }
            .dashboard-launcher__actions {
                justify-content: space-between;
            }
            .dashboard-app-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 14px 8px;
                margin-top: 18px;
            }
            .dashboard-launcher ~ section.card,
            .dashboard-launcher ~ .dashboard-split section.card,
            .dashboard-launcher ~ .dashboard-collapsible--plain .dashboard-split section.card {
                padding: 16px;
            }
            .dashboard-app-card {
                min-height: auto;
                gap: 8px;
                padding: 0 2px;
            }
            .dashboard-icon-badge--app {
                width: 68px;
                height: 68px;
                border-radius: 22px;
            }
            .dashboard-icon-badge--app::before {
                width: 34px;
                height: 34px;
                left: 9px;
                top: 16px;
                border-radius: 12px;
            }
            .dashboard-icon-badge--app::after {
                width: 16px;
                height: 38px;
                right: 10px;
                top: 10px;
            }
            .dashboard-app-card__label {
                min-height: 2.6em;
                font-size: 11px;
            }
            .dashboard-app-card__family {
                display: none;
            }
        }
        @media (max-width: 640px) {
            .dashboard-section-head,
            .dashboard-actions {
                flex-direction: column;
                align-items: flex-start;
            }
            .dashboard-micro-grid {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 420px) {
            .dashboard-launcher__status-grid,
            .dashboard-launcher__focus-grid {
                grid-template-columns: 1fr;
            }
            .dashboard-app-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }
        @media (max-width: 360px) {
            .dashboard-app-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }
    </style>
@endpush

@section('content')
    @php
        $iconTone = static fn (array $item): string => match ($item['group'] ?? 'generic') {
            'app' => 'app',
            'quick', 'action' => 'action',
            'sector', 'signal' => 'sector',
            'premium' => 'premium',
            'watch' => 'watch',
            'spotlight' => 'spotlight',
            'kpi' => 'kpi',
            default => 'generic',
        };
    @endphp
    <div class="dashboard-shell">
        @if ($showOnboardingBanner && $onboarding)
            <section class="card dashboard-banner dashboard-banner--warm">
                <div class="dashboard-banner__layout">
                    <div class="dashboard-banner__copy">
                        <div class="badge badge-warning">Demarrage guide</div>
                        <h2 class="dashboard-display">Le noyau ERP est pret, il reste {{ $onboarding['total'] - $onboarding['completed'] }} etape(s) pour finaliser la mise en route.</h2>
                        <p class="dashboard-copy muted">Progression actuelle : {{ $onboarding['completed'] }}/{{ $onboarding['total'] }} etapes completees. Prochaine priorite : {{ $onboarding['next_step']['title'] ?? 'Finaliser les derniers reglages' }}.</p>
                        <div class="progress"><div class="progress-bar" style="width: {{ $onboarding['progress'] }}%;"></div></div>
                    </div>
                    <div class="dashboard-banner__aside">
                        <div class="dashboard-panel">
                            <strong>Checklist de demarrage</strong>
                            <p class="muted">Ferme les derniers ecarts pour avoir une base propre avant exploitation quotidienne.</p>
                        </div>
                        <div class="dashboard-actions">
                            <a href="{{ route('onboarding.index') }}" class="button button-primary">Voir la checklist</a>
                            <form method="POST" action="{{ route('onboarding.dismiss') }}">
                                @csrf
                                <button type="submit" class="button button-secondary">Masquer</button>
                            </form>
                        </div>
                    </div>
                </div>
            </section>
        @endif

        @include('dashboard.partials.app-launcher')

        <section class="card dashboard-banner dashboard-banner--hero dashboard-collapsible" data-dashboard-collapsible data-dashboard-default-open="true">
            <button type="button" class="dashboard-collapsible__toggle" data-dashboard-collapsible-toggle aria-expanded="true">
                <span class="dashboard-collapsible__toggle-inner">
                    <span class="dashboard-collapsible__toggle-copy">
                        <span class="dashboard-collapsible__eyebrow">{{ $dashboardProfile['badge'] }}</span>
                        <span class="dashboard-collapsible__title">Vue de pilotage</span>
                        <span class="dashboard-collapsible__hint">Recherche globale, priorites et cap sur la periode active.</span>
                    </span>
                    <span class="dashboard-collapsible__chevron" aria-hidden="true"></span>
                </span>
            </button>
            <div class="dashboard-collapsible__body" data-dashboard-collapsible-body>
                <div class="dashboard-banner__layout">
                    <div class="dashboard-banner__copy">
                        <div class="badge badge-muted">{{ $dashboardProfile['badge'] }}</div>
                        <h2 class="dashboard-display">{{ $dashboardProfile['headline'] }}</h2>
                        <p class="dashboard-copy muted">{{ $dashboardProfile['description'] }}</p>
                        <div class="dashboard-chip-row">
                            @foreach ($dashboardProfile['priorities'] as $priority)
                                <span class="dashboard-chip">{{ $priority }}</span>
                            @endforeach
                        </div>
                    </div>
                    <div class="dashboard-banner__aside">
                        <div class="dashboard-panel">
                            <strong>Recherche globale</strong>
                            <p class="muted">Tape un nom, un numero ou un code pour retrouver vite le bon document.</p>
                            <div class="help" style="margin-top:8px;">Exemples : {{ implode(' | ', $dashboardProfile['search_examples']) }}</div>
                        </div>
                        @if ($currentPeriodSummary)
                            <div class="dashboard-panel dashboard-panel--contrast">
                                <div class="badge {{ $currentPeriodSummary['period']?->isClosed() ? 'badge-warning' : ($currentPeriodSummary['status'] === 'ready' ? 'badge-success' : 'badge-muted') }}">{{ $currentPeriodSummary['period']?->isClosed() ? 'Periode cloturee' : 'Periode en cours' }}</div>
                                <strong style="margin-top:10px;">{{ $currentPeriodSummary['period']?->name }}</strong>
                                <p class="muted">{{ $currentPeriodSummary['start_date']->format('d/m/Y') }} au {{ $currentPeriodSummary['end_date']->format('d/m/Y') }}</p>
                                <div class="dashboard-micro-grid" style="margin-top:14px;">
                                    <div class="dashboard-micro-item">
                                        <span>Checklist</span>
                                        <strong>{{ count($currentPeriodSummary['checklist']) }}</strong>
                                    </div>
                                    <div class="dashboard-micro-item">
                                        <span>Cloture</span>
                                        <strong>{{ $currentPeriodSummary['can_close'] ? 'Possible' : 'Bloquee' }}</strong>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </section>

        <section class="card dashboard-banner dashboard-banner--sector dashboard-collapsible" data-dashboard-collapsible data-dashboard-default-open="false">
            <button type="button" class="dashboard-collapsible__toggle" data-dashboard-collapsible-toggle aria-expanded="true">
                <span class="dashboard-collapsible__toggle-inner">
                    <span class="dashboard-collapsible__toggle-copy">
                        <span class="dashboard-collapsible__eyebrow">Pack metier</span>
                        <span class="dashboard-collapsible__title">{{ $sectorProfile['label'] }}</span>
                        <span class="dashboard-collapsible__hint">Signals terrain et modules recommandes pour le profil actif.</span>
                    </span>
                    <span class="dashboard-collapsible__chevron" aria-hidden="true"></span>
                </span>
            </button>
            <div class="dashboard-collapsible__body" data-dashboard-collapsible-body>
                <div class="dashboard-banner__layout">
                    <div class="dashboard-banner__copy">
                        <div class="badge badge-success">Pack metier actif</div>
                        <h2 class="dashboard-display" style="font-size:clamp(24px, 3vw, 34px);">{{ $sectorProfile['label'] }}</h2>
                        <p class="dashboard-copy muted">{{ $sectorProfile['description'] }}</p>
                        <div class="dashboard-chip-row">
                            @foreach ($sectorProfile['use_cases'] as $useCase)
                                <span class="dashboard-chip">{{ $useCase }}</span>
                            @endforeach
                        </div>
                    </div>
                    <div class="dashboard-banner__aside">
                        <div class="dashboard-panel">
                            <strong>Ce que Nema ERP privilegie</strong>
                            <p class="muted">{{ implode(' · ', $sectorProfile['operational_focus']) }}</p>
                            <div class="help" style="margin-top:8px;">Catalogue de depart : {{ implode(' · ', $sectorProfile['starter_catalog']) }}</div>
                        </div>
                        <div class="dashboard-panel">
                            <strong>Reglages conseilles</strong>
                            <p class="muted">Unites : {{ implode(' · ', $sectorProfile['recommended_units']) }}</p>
                            <p class="muted" style="margin-top:8px;">Paiements terrain : {{ implode(' · ', $sectorProfile['recommended_payments']) }}</p>
                            @if (auth()->user()?->hasPermission('settings.view'))
                                <div class="dashboard-actions" style="margin-top:12px;">
                                    <a href="{{ route('settings.index') }}" class="button button-secondary">Ajuster le profil</a>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                @if (! empty($sectorSignals))
                    <div style="margin-top:18px;">
                        <div class="dashboard-section-head" style="margin-bottom:14px;">
                            <div>
                                <h2 style="font-size:22px;">Signaux terrain du secteur</h2>
                                <p class="muted">Indicateurs metier remontes specifiquement pour le profil actif.</p>
                            </div>
                        </div>
                        <div class="dashboard-watch-grid">
                            @foreach ($sectorSignals as $item)
                                <a href="{{ $item['url'] }}" class="dashboard-watch-card">
                                    <div class="dashboard-card-lead">
                                        <span class="dashboard-icon-badge dashboard-icon-badge--{{ $iconTone($item) }}">
                                            @include('dashboard.partials.icon', ['name' => $item['icon'] ?? 'grid', 'size' => 20])
                                        </span>
                                        <div>
                                            <p class="dashboard-card-label">{{ $item['label'] }}</p>
                                            <div class="dashboard-card-caption">Signal metier</div>
                                        </div>
                                    </div>
                                    <div class="stat-value">{{ $item['value'] }}</div>
                                    <p class="muted">{{ $item['description'] }}</p>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if (! empty($sectorActionPlan))
                    <div style="margin-top:18px;">
                        <div class="dashboard-section-head" style="margin-bottom:14px;">
                            <div>
                                <h2 style="font-size:22px;">Modules recommandes pour ce secteur</h2>
                                <p class="muted">Raccourcis priorises selon le pack metier actif.</p>
                            </div>
                        </div>
                        <div class="dashboard-link-grid">
                            @foreach ($sectorActionPlan as $action)
                                <a href="{{ $action['url'] }}" class="dashboard-link-card">
                                    <div class="dashboard-card-lead">
                                        <span class="dashboard-icon-badge dashboard-icon-badge--{{ $iconTone($action) }}">
                                            @include('dashboard.partials.icon', ['name' => $action['icon'] ?? 'grid', 'size' => 20])
                                        </span>
                                        <div>
                                            <p class="dashboard-card-label">{{ $action['label'] }}</p>
                                            <div class="dashboard-card-caption">Module</div>
                                        </div>
                                    </div>
                                    <p class="muted">{{ $action['description'] }}</p>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </section>


        @if (! empty($premiumActionCenter))
            <section class="card dashboard-banner dashboard-banner--premium dashboard-collapsible" data-dashboard-collapsible data-dashboard-default-open="false">
                <button type="button" class="dashboard-collapsible__toggle" data-dashboard-collapsible-toggle aria-expanded="true">
                    <span class="dashboard-collapsible__toggle-inner">
                        <span class="dashboard-collapsible__toggle-copy">
                            <span class="dashboard-collapsible__eyebrow">Premium</span>
                            <span class="dashboard-collapsible__title">Centre d actions premium</span>
                            <span class="dashboard-collapsible__hint">Alertes prioritaires, execution et risques techniques a traiter.</span>
                        </span>
                        <span class="dashboard-collapsible__chevron" aria-hidden="true"></span>
                    </span>
                </button>
                <div class="dashboard-collapsible__body" data-dashboard-collapsible-body>
                    <div class="dashboard-banner__layout">
                        <div class="dashboard-banner__copy">
                            <div class="badge badge-success">Centre d actions premium</div>
                            <h2 class="dashboard-display" style="font-size:clamp(24px, 3vw, 34px);">{{ $premiumBrief['headline'] }}</h2>
                            <p class="dashboard-copy">{{ $premiumBrief['description'] }}</p>
                            @if (! empty($premiumBrief['focus']))
                                <div class="dashboard-chip-row">
                                    @foreach (explode(' | ', $premiumBrief['focus']) as $focus)
                                        <span class="dashboard-chip">{{ $focus }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <div class="dashboard-banner__aside">
                            <div class="dashboard-panel">
                                <strong>Commence par les cartes orange</strong>
                                <p class="muted">Chaque carte ouvre directement l ecran a traiter. Orange = urgent, vert = stable.</p>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-premium-grid">
                        @foreach ($premiumActionCenter as $item)
                            <a href="{{ $item['url'] }}" class="dashboard-premium-card dashboard-premium-card--{{ $item['priority'] }}">
                                <div class="dashboard-premium-meta">
                                    <span class="dashboard-icon-badge dashboard-icon-badge--{{ $iconTone($item) }}">
                                        @include('dashboard.partials.icon', ['name' => $item['icon'] ?? 'flash', 'size' => 20])
                                    </span>
                                    <span class="badge {{ $item['priority'] === 'high' ? 'badge-warning' : ($item['priority'] === 'medium' ? 'badge-muted' : 'badge-success') }}">
                                        {{ $item['priority'] === 'high' ? 'Urgent' : ($item['priority'] === 'medium' ? 'A faire' : 'Stable') }}
                                    </span>
                                </div>
                                <div class="dashboard-card-caption" style="color:rgba(238, 248, 248, 0.74); margin-top:12px;">{{ $item['eyebrow'] }}</div>
                                <strong>{{ $item['label'] }}</strong>
                                <div class="stat-value" style="font-size:32px;">{{ $item['metric'] }}</div>
                                <p class="muted">{{ $item['description'] }}</p>
                            </a>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if (! empty($executiveBrief['items']))
            <section class="card dashboard-collapsible" data-dashboard-collapsible data-dashboard-default-open="false">
                <button type="button" class="dashboard-collapsible__toggle" data-dashboard-collapsible-toggle aria-expanded="true">
                    <span class="dashboard-collapsible__toggle-inner">
                        <span class="dashboard-collapsible__toggle-copy">
                            <span class="dashboard-collapsible__eyebrow">Decision</span>
                            <span class="dashboard-collapsible__title">Briefing dirigeant</span>
                            <span class="dashboard-collapsible__hint">{{ $executiveBrief['headline'] }}</span>
                        </span>
                        <span class="dashboard-collapsible__chevron" aria-hidden="true"></span>
                    </span>
                </button>
                <div class="dashboard-collapsible__body" data-dashboard-collapsible-body>
                    <div class="dashboard-section-head">
                        <div>
                            <h2>Briefing dirigeant</h2>
                            <p class="muted">{{ $executiveBrief['headline'] }}</p>
                        </div>
                        @if (! empty($executiveBrief['summary']))
                            <div class="dashboard-chip-row">
                                @foreach (explode(' | ', $executiveBrief['summary']) as $focus)
                                    <span class="dashboard-chip">{{ $focus }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div class="dashboard-analysis-grid">
                        @foreach ($executiveBrief['items'] as $item)
                            <a href="{{ $item['action_url'] }}" class="dashboard-analysis-card">
                                <div class="dashboard-chip-row" style="margin-bottom:10px;">
                                    <span class="badge {{ $item['tone'] === 'danger' ? 'badge-warning' : ($item['tone'] === 'warning' ? 'badge-muted' : 'badge-success') }}">{{ strtoupper($item['tone']) }}</span>
                                </div>
                                <strong>{{ $item['title'] }}</strong>
                                <p class="muted">{{ $item['message'] }}</p>
                                <div class="help" style="margin-top:10px;">{{ $item['action_label'] }}</div>
                            </a>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif
        @if (! empty($dashboardKpis))
            <section class="dashboard-collapsible dashboard-collapsible--plain" data-dashboard-collapsible data-dashboard-default-open="false">
                <button type="button" class="dashboard-collapsible__toggle" data-dashboard-collapsible-toggle aria-expanded="true">
                    <span class="dashboard-collapsible__toggle-inner">
                        <span class="dashboard-collapsible__toggle-copy">
                            <span class="dashboard-collapsible__eyebrow">KPI</span>
                            <span class="dashboard-collapsible__title">Indicateurs</span>
                            <span class="dashboard-collapsible__hint">Les chiffres cles du profil actif, sans prendre tout l’ecran mobile.</span>
                        </span>
                        <span class="dashboard-collapsible__chevron" aria-hidden="true"></span>
                    </span>
                </button>
                <div class="dashboard-collapsible__body" data-dashboard-collapsible-body>
                    <section class="dashboard-kpi-grid">
                        @foreach ($dashboardKpis as $kpi)
                            <article class="dashboard-kpi-card">
                                <div class="dashboard-card-lead">
                                    <span class="dashboard-icon-badge dashboard-icon-badge--{{ $iconTone($kpi) }}">
                                        @include('dashboard.partials.icon', ['name' => $kpi['icon'] ?? 'gauge', 'size' => 20])
                                    </span>
                                    <div>
                                        <p class="dashboard-card-label">{{ $kpi['label'] }}</p>
                                        <div class="dashboard-card-caption">Indicateur</div>
                                    </div>
                                </div>
                                <div class="stat-value">{{ $kpi['value'] }}</div>
                                <div class="help">{{ $kpi['description'] }}</div>
                            </article>
                        @endforeach
                    </section>
                </div>
            </section>
        @endif

        @if (! empty($operationalWatchlist))
            <section class="card dashboard-collapsible" data-dashboard-collapsible data-dashboard-default-open="false">
                <button type="button" class="dashboard-collapsible__toggle" data-dashboard-collapsible-toggle aria-expanded="true">
                    <span class="dashboard-collapsible__toggle-inner">
                        <span class="dashboard-collapsible__toggle-copy">
                            <span class="dashboard-collapsible__eyebrow">Execution</span>
                            <span class="dashboard-collapsible__title">Suivi operationnel</span>
                            <span class="dashboard-collapsible__hint">Les points chauds du jour et les raccourcis utiles pour agir.</span>
                        </span>
                        <span class="dashboard-collapsible__chevron" aria-hidden="true"></span>
                    </span>
                </button>
                <div class="dashboard-collapsible__body" data-dashboard-collapsible-body>
                    <div class="dashboard-section-head">
                        <div>
                            <h2>Suivi operationnel</h2>
                            <p class="muted">Les points chauds du jour, avec un clic direct vers l ecran utile.</p>
                        </div>
                    </div>
                    <div class="dashboard-watch-grid">
                        @foreach ($operationalWatchlist as $item)
                            <a href="{{ $item['url'] }}" class="dashboard-watch-card">
                                <div class="dashboard-card-lead">
                                    <span class="dashboard-icon-badge dashboard-icon-badge--{{ $iconTone($item) }}">
                                        @include('dashboard.partials.icon', ['name' => $item['icon'] ?? 'alert', 'size' => 20])
                                    </span>
                                    <div>
                                        <p class="dashboard-card-label">{{ $item['label'] }}</p>
                                        <div class="dashboard-card-caption">A suivre</div>
                                    </div>
                                </div>
                                <div class="stat-value">{{ number_format((float) $item['count'], 0, ',', ' ') }}</div>
                                <p class="muted">{{ $item['description'] }}</p>
                            </a>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if ($currentPeriodSummary)
            <section class="card dashboard-banner {{ $currentPeriodSummary['period']?->isClosed() ? 'dashboard-banner--period-closed' : 'dashboard-banner--period-open' }} dashboard-collapsible" data-dashboard-collapsible data-dashboard-default-open="false">
                <button type="button" class="dashboard-collapsible__toggle" data-dashboard-collapsible-toggle aria-expanded="true">
                    <span class="dashboard-collapsible__toggle-inner">
                        <span class="dashboard-collapsible__toggle-copy">
                            <span class="dashboard-collapsible__eyebrow">Cloture</span>
                            <span class="dashboard-collapsible__title">{{ $currentPeriodSummary['period']?->name }}</span>
                            <span class="dashboard-collapsible__hint">Checklist de periode, statut et actions de cloture.</span>
                        </span>
                        <span class="dashboard-collapsible__chevron" aria-hidden="true"></span>
                    </span>
                </button>
                <div class="dashboard-collapsible__body" data-dashboard-collapsible-body>
                    <div class="dashboard-period-summary">
                        <div class="dashboard-banner__layout">
                            <div class="dashboard-banner__copy">
                                <div class="badge {{ $currentPeriodSummary['period']?->isClosed() ? 'badge-warning' : ($currentPeriodSummary['status'] === 'ready' ? 'badge-success' : 'badge-muted') }}">{{ $currentPeriodSummary['period']?->isClosed() ? 'Periode cloturee' : 'Periode en cours' }}</div>
                                <h2 style="margin:0; font-size:30px; letter-spacing:-.03em;">{{ $currentPeriodSummary['period']?->name }} | {{ $currentPeriodSummary['start_date']->format('d/m/Y') }} au {{ $currentPeriodSummary['end_date']->format('d/m/Y') }}</h2>
                                @if ($currentPeriodSummary['period']?->isClosed())
                                    <p class="dashboard-copy muted">Les operations datees sur cette periode sont bloquees. Utilise la reouverture seulement si une correction est vraiment necessaire.</p>
                                @elseif (! $currentPeriodSummary['can_close'])
                                    <p class="dashboard-copy muted">Cloture bloquee : des documents en attente d approbation doivent etre traites avant fermeture.</p>
                                @elseif ($currentPeriodSummary['status'] === 'warning')
                                    <p class="dashboard-copy muted">Cloture possible, mais des soldes ouverts restent a suivre pour une fin de mois plus propre.</p>
                                @else
                                    <p class="dashboard-copy muted">La periode est prete pour une cloture propre.</p>
                                @endif
                            </div>
                            <div class="dashboard-actions" style="justify-content:flex-end; align-items:flex-start;">
                                <a href="{{ route('accounting.periods.index') }}" class="button button-primary">Gerer les periodes</a>
                                <a href="{{ route('reports.index') }}" class="button button-secondary">Voir les rapports</a>
                            </div>
                        </div>
                        <div class="dashboard-checklist-grid">
                            @foreach ($currentPeriodSummary['checklist'] as $item)
                                <div class="dashboard-checklist-card">
                                    <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start;">
                                        <strong style="max-width:72%;">{{ $item['title'] }}</strong>
                                        <span class="badge {{ $item['state'] === 'blocked' ? 'badge-warning' : ($item['state'] === 'warning' ? 'badge-muted' : 'badge-success') }}">{{ $item['state'] === 'blocked' ? 'Bloquant' : ($item['state'] === 'warning' ? 'A suivre' : 'OK') }}</span>
                                    </div>
                                    <div class="stat-value" style="font-size:26px;">{{ $item['count'] }}</div>
                                    <p class="muted">{{ $item['message'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>
        @endif

        <section class="dashboard-collapsible dashboard-collapsible--plain" data-dashboard-collapsible data-dashboard-default-open="false">
            <button type="button" class="dashboard-collapsible__toggle" data-dashboard-collapsible-toggle aria-expanded="true">
                <span class="dashboard-collapsible__toggle-inner">
                    <span class="dashboard-collapsible__toggle-copy">
                        <span class="dashboard-collapsible__eyebrow">Lecture</span>
                        <span class="dashboard-collapsible__title">Analyse et activite</span>
                        <span class="dashboard-collapsible__hint">{{ $dashboardProfile['analysis_title'] }} et derniers mouvements utiles.</span>
                    </span>
                    <span class="dashboard-collapsible__chevron" aria-hidden="true"></span>
                </span>
            </button>
            <div class="dashboard-collapsible__body" data-dashboard-collapsible-body>
                <div class="dashboard-split">
                    <section class="card">
                        <div class="dashboard-section-head">
                            <div>
                                <h2>{{ $dashboardProfile['analysis_title'] }}</h2>
                                <p class="muted">{{ $dashboardProfile['analysis_description'] }}</p>
                            </div>
                        </div>
                        <div class="dashboard-analysis-grid">
                            @foreach ($roleSpotlight as $item)
                                <a href="{{ $item['url'] }}" class="dashboard-analysis-card">
                                    <div class="dashboard-card-lead">
                                        <span class="dashboard-icon-badge dashboard-icon-badge--{{ $iconTone($item) }}">
                                            @include('dashboard.partials.icon', ['name' => $item['icon'] ?? 'pulse', 'size' => 20])
                                        </span>
                                        <div>
                                            <p class="dashboard-card-label">{{ $item['label'] }}</p>
                                            <div class="dashboard-card-caption">Vue rapide</div>
                                        </div>
                                    </div>
                                    <div class="stat-value" style="font-size:30px;">{{ $item['value'] }}</div>
                                    <p class="muted">{{ $item['description'] }}</p>
                                </a>
                            @endforeach
                        </div>
                    </section>

                    <section class="card">
                        <div class="dashboard-section-head">
                            <div>
                                <h2>Activite recente</h2>
                                <p class="muted">Les derniers mouvements qui merite ton attention.</p>
                            </div>
                        </div>
                        @if ($recentActivities->isEmpty())
                            <div class="dashboard-empty">Aucune activite enregistree pour le moment.</div>
                        @else
                            <div class="dashboard-activity-list">
                                @foreach ($recentActivities as $activity)
                                    <div class="dashboard-activity-item">
                                        <strong>{{ $activity->description }}</strong>
                                        <div class="muted">{{ $activity->user?->name ?? 'Systeme' }} | {{ $activity->created_at?->format('d/m/Y H:i') }}</div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </section>
                </div>
            </div>
        </section>
    </div>
@endsection







