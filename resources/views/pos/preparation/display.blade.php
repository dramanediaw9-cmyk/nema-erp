@extends('layouts.app')

@section('title', 'Preparation Display - Nema ERP')
@section('page-title', 'Preparation Display')

@section('content')
    <style>
        .prep-display {
            display: grid;
            gap: 22px;
            color: #eff6ff;
        }
        .prep-display-shell {
            padding: 24px;
            border-radius: 28px;
            background:
                radial-gradient(circle at top right, rgba(57, 198, 178, 0.28), transparent 28%),
                linear-gradient(160deg, #09111f 0%, #10233a 48%, #173b59 100%);
            box-shadow: 0 24px 50px rgba(8, 15, 30, 0.32);
        }
        .prep-display-shell.is-ready-alert {
            box-shadow: 0 0 0 2px rgba(125, 211, 252, 0.4), 0 24px 50px rgba(8, 15, 30, 0.32);
            animation: prep-display-pulse 1.15s ease-in-out 2;
        }
        @keyframes prep-display-pulse {
            0%, 100% { transform: scale(1); }
            35% { transform: scale(1.004); }
            70% { transform: scale(1.002); }
        }
        .prep-display-topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 18px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }
        .prep-display-title {
            display: grid;
            gap: 10px;
        }
        .prep-display-title h2 {
            margin: 0;
            font-size: 36px;
            line-height: 1.05;
            letter-spacing: -0.04em;
        }
        .prep-display-title .muted {
            color: rgba(239, 246, 255, 0.76);
            max-width: 820px;
        }
        .prep-display-chip-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .prep-display-chip {
            display: inline-flex;
            align-items: center;
            padding: 9px 14px;
            border-radius: 999px;
            border: 1px solid rgba(191, 219, 254, 0.16);
            background: rgba(148, 163, 184, 0.14);
            color: #eff6ff;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .prep-display-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .prep-display-kpis {
            display: grid;
            gap: 14px;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            margin-bottom: 20px;
        }
        .prep-display-kpi {
            padding: 18px 18px 16px;
            border-radius: 22px;
            border: 1px solid rgba(148, 163, 184, 0.18);
            background: rgba(8, 15, 30, 0.3);
            backdrop-filter: blur(12px);
        }
        .prep-display-kpi .label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: rgba(191, 219, 254, 0.75);
            font-weight: 700;
        }
        .prep-display-kpi .value {
            margin-top: 10px;
            font-size: 34px;
            line-height: 1;
            font-weight: 800;
        }
        .prep-display-board {
            display: grid;
            gap: 16px;
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
        .prep-display-column {
            display: grid;
            gap: 14px;
            padding: 16px;
            border-radius: 24px;
            border: 1px solid rgba(148, 163, 184, 0.16);
            background: rgba(8, 15, 30, 0.28);
            min-height: 420px;
        }
        .prep-display-column-head {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            align-items: baseline;
        }
        .prep-display-column-head h3 {
            margin: 0;
            font-size: 22px;
            letter-spacing: -0.02em;
        }
        .prep-display-column-head .hint {
            color: rgba(191, 219, 254, 0.7);
            font-size: 13px;
        }
        .prep-display-column-count {
            font-size: 28px;
            font-weight: 800;
            color: #7dd3fc;
        }
        .prep-display-list {
            display: grid;
            gap: 12px;
            align-content: start;
        }
        .prep-display-ticket {
            display: grid;
            gap: 12px;
            padding: 16px;
            border-radius: 22px;
            background: rgba(15, 23, 42, 0.9);
            border: 1px solid rgba(148, 163, 184, 0.16);
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.02);
        }
        .prep-display-ticket-head {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            align-items: flex-start;
        }
        .prep-display-ticket-head strong {
            font-size: 22px;
            line-height: 1.1;
        }
        .prep-display-ticket-meta {
            color: rgba(191, 219, 254, 0.76);
            font-size: 13px;
            display: grid;
            gap: 4px;
        }
        .prep-display-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 74px;
            padding: 7px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            background: rgba(59, 130, 246, 0.18);
            border: 1px solid rgba(96, 165, 250, 0.3);
            color: #dbeafe;
        }
        .prep-display-badge.is-late {
            background: rgba(239, 68, 68, 0.18);
            border-color: rgba(248, 113, 113, 0.38);
            color: #fecaca;
        }
        .prep-display-items {
            display: grid;
            gap: 8px;
        }
        .prep-display-item {
            padding: 12px 14px;
            border-radius: 18px;
            background: rgba(15, 23, 42, 0.82);
            border: 1px solid rgba(148, 163, 184, 0.1);
        }
        .prep-display-item strong {
            display: block;
            margin-bottom: 4px;
            font-size: 18px;
            color: #f8fafc;
        }
        .prep-display-item .muted {
            color: rgba(191, 219, 254, 0.72);
            font-size: 13px;
        }
        .prep-display-ticket-actions {
            display: grid;
            gap: 10px;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        }
        .prep-display-ticket-actions form {
            display: grid;
        }
        .prep-display-empty {
            display: grid;
            place-items: center;
            min-height: 180px;
            padding: 18px;
            border-radius: 18px;
            border: 1px dashed rgba(191, 219, 254, 0.18);
            color: rgba(191, 219, 254, 0.72);
            text-align: center;
            background: rgba(8, 15, 30, 0.22);
        }
        .prep-display-footer {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 18px;
            color: rgba(191, 219, 254, 0.72);
            font-size: 13px;
        }
        .prep-display .button {
            justify-content: center;
            min-height: 52px;
            font-size: 15px;
            font-weight: 800;
            border-radius: 16px;
        }
        .prep-display .button.button-primary {
            background: linear-gradient(135deg, #14b8a6 0%, #0ea5e9 100%);
            border-color: rgba(20, 184, 166, 0.16);
        }
        .prep-display .button.button-secondary {
            background: rgba(15, 23, 42, 0.8);
            color: #e2e8f0;
            border-color: rgba(148, 163, 184, 0.22);
        }
        @media (max-width: 1180px) {
            .prep-display-board {
                grid-template-columns: 1fr;
            }
            .prep-display-column {
                min-height: 0;
            }
        }
    </style>

    <div class="prep-display">
        <div
            class="prep-display-shell"
            id="prep-display-shell"
            data-refresh-seconds="{{ $board['refresh_seconds'] }}"
            data-snapshot-url="{{ route('pos.preparation.display.snapshot', $board['display']) }}"
            data-ready-ticket-ids='@json($board['grouped_tickets']['ready']->pluck('id')->values()->all())'
        >
            @include('pos.preparation.partials.display-live', ['board' => $board])
        </div>
    </div>

    <script>
        (() => {
            const shell = document.querySelector('[data-refresh-seconds]');
            const refreshSeconds = Number(shell?.dataset.refreshSeconds || 20);
            const snapshotUrl = shell?.dataset.snapshotUrl;
            let previousReadyIds = parseReadyIds(shell?.dataset.readyTicketIds || '[]');
            let pollHandle = null;

            bindFullscreenAction();

            if (!shell || !snapshotUrl) {
                return;
            }

            startPolling();

            function startPolling() {
                pollHandle = window.setInterval(refreshSnapshot, Math.max(5, refreshSeconds) * 1000);
            }

            async function refreshSnapshot() {
                if (document.hidden) {
                    return;
                }

                try {
                    const response = await fetch(snapshotUrl, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                        credentials: 'same-origin',
                    });

                    if (!response.ok) {
                        return;
                    }

                    const payload = await response.json();
                    const nextReadyIds = Array.isArray(payload.ready_ticket_ids) ? payload.ready_ticket_ids.map(String) : [];
                    const gainedReadyIds = nextReadyIds.filter((id) => !previousReadyIds.includes(id));

                    if (typeof payload.html === 'string' && payload.html.trim() !== '') {
                        shell.innerHTML = payload.html;
                    }

                    shell.dataset.readyTicketIds = JSON.stringify(nextReadyIds);
                    bindFullscreenAction();

                    if (gainedReadyIds.length > 0) {
                        triggerReadyAlert();
                    }

                    previousReadyIds = nextReadyIds;
                } catch (error) {
                    console.warn('Preparation display refresh failed', error);
                }
            }

            function bindFullscreenAction() {
                const fullscreenButton = document.getElementById('prep-display-fullscreen');

                fullscreenButton?.addEventListener('click', async () => {
                    const element = document.documentElement;
                    if (!document.fullscreenElement && element.requestFullscreen) {
                        await element.requestFullscreen();
                    }
                }, { once: true });
            }

            function triggerReadyAlert() {
                shell.classList.remove('is-ready-alert');
                void shell.offsetWidth;
                shell.classList.add('is-ready-alert');
                window.setTimeout(() => shell.classList.remove('is-ready-alert'), 2400);
                playTone();
            }

            function playTone() {
                const AudioContextClass = window.AudioContext || window.webkitAudioContext;
                if (!AudioContextClass) {
                    return;
                }

                try {
                    const audioContext = new AudioContextClass();
                    const oscillator = audioContext.createOscillator();
                    const gainNode = audioContext.createGain();

                    oscillator.type = 'triangle';
                    oscillator.frequency.setValueAtTime(880, audioContext.currentTime);
                    oscillator.frequency.linearRampToValueAtTime(1174, audioContext.currentTime + 0.18);
                    gainNode.gain.setValueAtTime(0.0001, audioContext.currentTime);
                    gainNode.gain.exponentialRampToValueAtTime(0.08, audioContext.currentTime + 0.02);
                    gainNode.gain.exponentialRampToValueAtTime(0.0001, audioContext.currentTime + 0.42);

                    oscillator.connect(gainNode);
                    gainNode.connect(audioContext.destination);
                    oscillator.start();
                    oscillator.stop(audioContext.currentTime + 0.45);

                    oscillator.onended = () => {
                        audioContext.close().catch(() => {});
                    };
                } catch (error) {
                    console.warn('Preparation display alert tone failed', error);
                }
            }

            function parseReadyIds(value) {
                try {
                    const parsed = JSON.parse(value);
                    return Array.isArray(parsed) ? parsed.map(String) : [];
                } catch (error) {
                    return [];
                }
            }
        })();
    </script>
@endsection
