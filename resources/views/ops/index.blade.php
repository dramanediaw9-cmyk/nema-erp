@extends('layouts.app')

@section('title', 'Operations - Nema ERP')
@section('page-title', 'Operations')

@section('content')
    @php
        $backupCheck = collect($report['checks'])->firstWhere('key', 'backups');
        $logsSummary = $appMonitoring['logs'];
        $failedJobsSummary = $appMonitoring['failed_jobs'];
        $secretProfiles = collect($secretGovernance['items'] ?? [])->keyBy('id');
    @endphp

    <div class="page-head">
        <div>
            <h2 style="margin:0;">Sante systeme</h2>
            <div class="muted">Vue exploitation, readiness production et surveillance de l outbox.</div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <span class="badge {{ $report['overall_status'] === 'ok' ? 'badge-success' : ($report['overall_status'] === 'warning' ? 'badge-warning' : 'badge-muted') }}">
                Etat global : {{ strtoupper($report['overall_status']) }}
            </span>
            <span class="badge badge-muted">{{ $report['captured_at']->format('d/m/Y H:i') }}</span>
        </div>
    </div>

    <div class="grid stats-grid" style="margin-bottom:20px;">
        <div class="card"><div class="muted">Statut global</div><div class="stat-value">{{ strtoupper($report['overall_status']) }}</div></div>
        <div class="card"><div class="muted">Alertes</div><div class="stat-value">{{ $report['warning_count'] }}</div></div>
        <div class="card"><div class="muted">Echecs</div><div class="stat-value">{{ $report['failure_count'] }}</div></div>
        <div class="card"><div class="muted">Cache</div><div class="stat-value">{{ $report['meta']['cache_store'] }}</div></div>
        <div class="card"><div class="muted">Session</div><div class="stat-value">{{ $report['meta']['session_driver'] }}</div></div>
        <div class="card"><div class="muted">Queue</div><div class="stat-value">{{ $report['meta']['queue_connection'] }}</div></div>
    </div>

    <section class="card" style="margin-bottom:18px;">
        <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start; flex-wrap:wrap; margin-bottom:16px;">
            <div>
                <h3 class="section-title">Noyau Nema</h3>
                <div class="muted">Controle du moteur ERP: multi-entreprise, droits, documents, stock, caisse et audit.</div>
            </div>
            <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                <span class="badge {{ $coreNucleus['status'] === 'ok' ? 'badge-success' : ($coreNucleus['status'] === 'warning' ? 'badge-warning' : 'badge-muted') }}">
                    Noyau : {{ strtoupper($coreNucleus['status']) }}
                </span>
                <span class="badge badge-muted">Score {{ $coreNucleus['score'] }}%</span>
                <span class="badge badge-muted">{{ $coreNucleus['warning_count'] }} alerte(s)</span>
                <span class="badge badge-muted">{{ $coreNucleus['failure_count'] }} echec(s)</span>
            </div>
        </div>

        <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:12px;">
            @foreach ($coreNucleus['checks'] as $check)
                <div class="summary-box">
                    <div style="display:flex; justify-content:space-between; gap:10px; align-items:flex-start;">
                        <strong>{{ $check['label'] }}</strong>
                        <span class="badge {{ $check['status'] === 'ok' ? 'badge-success' : ($check['status'] === 'warning' ? 'badge-warning' : 'badge-muted') }}">
                            {{ strtoupper($check['status']) }}
                        </span>
                    </div>
                    <div class="muted" style="margin-top:8px;">{{ $check['message'] }}</div>
                    @if (! empty($check['metrics']))
                        <div class="help" style="margin-top:10px;">
                            @foreach ($check['metrics'] as $metric => $value)
                                @if (is_array($value))
                                    <div>{{ $metric }} : {{ empty($value) ? 'aucun' : implode(', ', $value) }}</div>
                                @else
                                    <div>{{ $metric }} : {{ $value }}</div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                    @if ($check['status'] !== 'ok')
                        <div class="help" style="margin-top:10px;">Action : {{ $check['action'] }}</div>
                    @endif
                </div>
            @endforeach
        </div>

        @if (! empty($coreNucleus['next_actions']))
            <div class="summary-box" style="margin-top:14px;">
                <strong>Actions prioritaires noyau</strong>
                <ul class="summary-list">
                    @foreach ($coreNucleus['next_actions'] as $action)
                        <li>{{ $action }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>

    <div class="split" style="margin-bottom:18px;">
        <section class="card">
            <h3 class="section-title">Checks</h3>
            <div class="grid" style="gap:12px;">
                @foreach ($report['checks'] as $check)
                    <div class="summary-box">
                        <div style="display:flex; justify-content:space-between; gap:12px; align-items:center;">
                            <strong>{{ $check['label'] }}</strong>
                            <span class="badge {{ $check['status'] === 'ok' ? 'badge-success' : ($check['status'] === 'warning' ? 'badge-warning' : 'badge-muted') }}">{{ strtoupper($check['status']) }}</span>
                        </div>
                        <div class="muted" style="margin-top:8px;">{{ $check['message'] }}</div>
                        @if (! empty($check['meta']))
                            <pre style="margin-top:12px; padding:12px; background:#f8f3ea; border-radius:14px; overflow:auto; font-size:12px;">{{ json_encode($check['meta'], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>

        <section class="card">
            <h3 class="section-title">Exploitation</h3>
            <div class="summary-stack">
                <div class="summary-box">
                    <strong>Commandes utiles</strong>
                    <div class="muted" style="margin-top:8px;">`php artisan nema:ops:health-check --store`</div>
                    <div class="muted">`php artisan nema:ops:backup-run --keep=7`</div>
                    <div class="muted">`php artisan nema:ops:backup-verify`</div>
                    <div class="muted">`php artisan nema:ops:monitor-app`</div>
                    <div class="muted">`php artisan nema:notifications:sync-internal`</div>
                    <div class="muted">`php artisan nema:notifications:dispatch-outbound --limit=50`</div>
                    <div class="muted">`php artisan nema:integrations:dispatch-outbox --limit=50`</div>
                    <div class="muted">`php artisan nema:ops:outbox-retry-failed --limit=50`</div>
                    <div class="muted">`php artisan nema:ops:outbox-prune --days=30`</div>
                    <div class="muted">`php artisan schedule:list`</div>
                </div>
                @if ($backupCheck)
                    <div class="summary-box">
                        <strong>Sauvegardes locales</strong>
                        <div class="chip-row" style="margin-top:8px;">
                            <span class="badge {{ $backupCheck['status'] === 'ok' ? 'badge-success' : ($backupCheck['status'] === 'fail' ? 'badge-muted' : 'badge-warning') }}">{{ strtoupper($backupCheck['status']) }}</span>
                            @if (! empty($backupCheck['meta']['created_at']))
                                <span class="badge badge-muted">{{ \Illuminate\Support\Carbon::parse($backupCheck['meta']['created_at'])->format('d/m/Y H:i') }}</span>
                            @endif
                        </div>
                        <div class="muted" style="margin-top:8px;">{{ $backupCheck['message'] }}</div>
                        <div class="help" style="margin-top:8px;">{{ $backupCheck['meta']['tables_checked'] ?? 0 }}/{{ $backupCheck['meta']['tables_expected'] ?? 0 }} table(s) verifiee(s) · {{ $backupCheck['meta']['verified_rows'] ?? 0 }} ligne(s) relues</div>
                        <div class="help" style="margin-top:8px;">{{ $backupCheck['meta']['assets_checked'] ?? 0 }}/{{ $backupCheck['meta']['assets_expected'] ?? 0 }} dossier(s) assets controles · {{ $backupCheck['meta']['asset_files_verified'] ?? 0 }} fichier(s)</div>
                        @if (! empty($backupCheck['meta']['directory']))
                            <div class="help" style="margin-top:8px;">Emplacement : {{ $backupCheck['meta']['directory'] }}</div>
                        @endif
                    </div>
                @endif
                <div class="summary-box">
                    <strong>Webhook outbox</strong>
                    <div class="chip-row" style="margin-top:8px;">
                        <span class="badge {{ $outboxWebhook['enabled'] ? 'badge-success' : 'badge-muted' }}">{{ $outboxWebhook['enabled'] ? 'Actif' : 'Inactif' }}</span>
                        <span class="badge badge-muted">Timeout {{ $outboxWebhook['timeout'] }}s</span>
                    </div>
                    <div class="muted" style="margin-top:8px;">{{ $outboxWebhook['url'] ?: 'Aucune URL configuree' }}</div>
                </div>
                <div class="summary-box">
                    <strong>Email sortant</strong>
                    <div class="chip-row" style="margin-top:8px;">
                        <span class="badge {{ config('mail.default') === 'log' ? 'badge-warning' : 'badge-success' }}">Mailer {{ config('mail.default') }}</span>
                        <span class="badge badge-muted">{{ config('mail.from.address') ?: 'From non defini' }}</span>
                    </div>
                    <div class="muted" style="margin-top:8px;">
                        Utilise ce test pour verifier le SMTP reel apres configuration cloud.
                    </div>
                    @error('mail_test')
                        <div class="help" style="margin-top:10px; color:#9c3d2f;">{{ $message }}</div>
                    @enderror
                    <form method="POST" action="{{ route('ops.mail-test') }}" style="margin-top:12px; display:grid; gap:10px;">
                        @csrf
                        <input type="email" name="email" value="{{ old('email', auth()->user()?->email) }}" placeholder="email@exemple.com" required>
                        <input type="text" name="subject" value="{{ old('subject', 'Test email - '.config('app.name')) }}" maxlength="120" placeholder="Objet du test">
                        <button type="submit" class="button button-secondary">Envoyer un test email</button>
                    </form>
                </div>
                <div class="summary-box">
                    <strong>Historique recent</strong>
                    <ul class="summary-list">
                        @forelse ($snapshots as $snapshot)
                            <li>{{ $snapshot->captured_at?->format('d/m H:i') }} - {{ strtoupper($snapshot->overall_status) }} - {{ $snapshot->warning_count }} alerte(s) - {{ $snapshot->failure_count }} echec(s)</li>
                        @empty
                            <li>Aucun snapshot enregistre pour le moment.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </section>
    </div>

    <section class="card" style="margin-bottom:18px;">
        <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start; flex-wrap:wrap; margin-bottom:16px;">
            <div>
                <h3 class="section-title">Surveillance applicative</h3>
                <div class="muted">Lecture rapide des logs applicatifs et des jobs en echec pour attraper les incidents avant qu ils ne deviennent metier.</div>
            </div>
            <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                <span class="badge {{ $appMonitoring['status'] === 'ok' ? 'badge-success' : ($appMonitoring['status'] === 'fail' ? 'badge-muted' : 'badge-warning') }}">Monitoring : {{ strtoupper($appMonitoring['status']) }}</span>
                <span class="badge {{ $logsSummary['status'] === 'ok' ? 'badge-success' : ($logsSummary['status'] === 'fail' ? 'badge-muted' : 'badge-warning') }}">Logs : {{ $logsSummary['signals_count'] ?? 0 }}</span>
                <span class="badge {{ $failedJobsSummary['status'] === 'ok' ? 'badge-success' : ($failedJobsSummary['status'] === 'fail' ? 'badge-muted' : 'badge-warning') }}">Jobs en echec : {{ $failedJobsSummary['count'] ?? 0 }}</span>
            </div>
        </div>

        <div class="grid">
            <div class="summary-box">
                <strong>Logs applicatifs</strong>
                <div class="muted" style="margin-top:8px;">{{ $logsSummary['message'] }}</div>
                <div class="help" style="margin-top:8px;">{{ $logsSummary['line_count'] ?? 0 }} ligne(s) lues · {{ $logsSummary['critical_count'] ?? 0 }} signal(s) critiques · {{ $logsSummary['exception_mentions'] ?? 0 }} mention(s) d exception</div>
                @if (! empty($logsSummary['path']))
                    <div class="help" style="margin-top:8px;">Fichier : {{ $logsSummary['path'] }}</div>
                @endif
                @if (! empty($logsSummary['last_signal_excerpt']))
                    <div class="help" style="margin-top:8px;">Dernier signal : {{ $logsSummary['last_signal_at'] }} · {{ $logsSummary['last_signal_excerpt'] }}</div>
                @endif
                @if (! empty($logsSummary['recent_signals']))
                    <ul class="summary-list" style="margin-top:12px;">
                        @foreach ($logsSummary['recent_signals'] as $signal)
                            <li>{{ $signal['occurred_at'] }} · {{ $signal['level'] }} · {{ $signal['message'] }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
            <div class="summary-box">
                <strong>Jobs en echec</strong>
                <div class="muted" style="margin-top:8px;">{{ $failedJobsSummary['message'] }}</div>
                <div class="help" style="margin-top:8px;">{{ $failedJobsSummary['recent_count'] ?? 0 }} echec(s) sur 24 h · dernier a {{ $failedJobsSummary['last_failed_at'] ?: 'n/a' }}</div>
                @if (! empty($failedJobsSummary['recent_jobs']))
                    <ul class="summary-list" style="margin-top:12px;">
                        @foreach ($failedJobsSummary['recent_jobs'] as $job)
                            <li>#{{ $job['id'] }} · {{ $job['queue'] }} · {{ $job['failed_at'] }} · {{ $job['exception'] }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </section>

    <section class="card" style="margin-bottom:18px;">
        <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start; flex-wrap:wrap; margin-bottom:16px;">
            <div>
                <h3 class="section-title">Connecteurs partenaires</h3>
                <div class="muted">Vue rapide des integrations sensibles pour prioriser les resynchronisations, rotations de secrets et escalades.</div>
            </div>
            <a href="{{ route('platform.index') }}" class="button button-secondary">Ouvrir la plateforme</a>
        </div>

        <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:10px; margin-bottom:12px;">
            <div class="summary-box"><strong>Secrets sains</strong><div class="muted" style="margin-top:8px;">{{ $secretGovernance['healthy'] ?? 0 }}</div></div>
            <div class="summary-box"><strong>A surveiller</strong><div class="muted" style="margin-top:8px;">{{ $secretGovernance['watch'] ?? 0 }}</div></div>
            <div class="summary-box"><strong>Critiques</strong><div class="muted" style="margin-top:8px;">{{ $secretGovernance['critical'] ?? 0 }}</div></div>
            <div class="summary-box"><strong>Rotation overdue</strong><div class="muted" style="margin-top:8px;">{{ $secretGovernance['rotation_overdue'] ?? 0 }}</div></div>
            <div class="summary-box"><strong>Expiration proche</strong><div class="muted" style="margin-top:8px;">{{ $secretGovernance['expiring_soon'] ?? 0 }}</div></div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Connexion</th>
                        <th>Statut</th>
                        <th>Sante</th>
                        <th>Secrets</th>
                        <th>Derniere synchro</th>
                        <th>Dernier controle</th>
                        <th>Responsable</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($integrationConnections as $connection)
                        @php($secretProfile = $secretProfiles->get($connection->id))
                        <tr>
                            <td>
                                <strong>{{ $connection->name }}</strong>
                                <div class="muted" style="font-size:12px;">{{ $connection->partner_name }} · {{ $connection->code }}</div>
                            </td>
                            <td>
                                <span class="badge {{ $connection->status === 'active' ? 'badge-success' : ($connection->status === 'paused' ? 'badge-warning' : 'badge-muted') }}">
                                    {{ strtoupper($connection->status) }}
                                </span>
                            </td>
                            <td>
                                <span class="badge {{ $connection->health_status === 'healthy' ? 'badge-success' : ($connection->health_status === 'watch' ? 'badge-warning' : 'badge-muted') }}">
                                    {{ strtoupper($connection->health_status) }}
                                </span>
                            </td>
                            <td>
                                @if ($secretProfile)
                                    <span class="badge {{ $secretProfile['computed_status'] === 'healthy' ? 'badge-success' : ($secretProfile['computed_status'] === 'watch' ? 'badge-warning' : 'badge-muted') }}">
                                        {{ strtoupper($secretProfile['computed_status']) }}
                                    </span>
                                    <div class="muted" style="font-size:12px; margin-top:6px;">{{ $secretProfile['authentication_mode_label'] }}</div>
                                    <div class="muted" style="font-size:12px;">{{ $secretProfile['message'] }}</div>
                                @else
                                    <span class="muted">n/a</span>
                                @endif
                            </td>
                            <td>{{ $connection->last_sync_at?->format('d/m/Y H:i') ?: 'Jamais' }}</td>
                            <td>{{ $connection->last_health_at?->format('d/m/Y H:i') ?: 'Jamais' }}</td>
                            <td>{{ $connection->owner?->name ?: 'Non affecte' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="muted">Aucune connexion partenaire pour cette societe.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card" style="margin-bottom:18px;">
        <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start; flex-wrap:wrap; margin-bottom:16px;">
            <div>
                <h3 class="section-title">Restauration guidee</h3>
                <div class="muted">La sauvegarde n a de valeur que si elle peut etre reprise. Ce bloc rappelle le dernier point de reprise et la sequence de redemarrage.</div>
            </div>
            <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                <span class="badge {{ $backupVerification['status'] === 'ok' ? 'badge-success' : ($backupVerification['status'] === 'fail' ? 'badge-muted' : 'badge-warning') }}">Verification : {{ strtoupper($backupVerification['status']) }}</span>
                @if (! empty($backupRestorePreview['created_at']))
                    <span class="badge badge-muted">{{ \Illuminate\Support\Carbon::parse($backupRestorePreview['created_at'])->format('d/m/Y H:i') }}</span>
                @endif
            </div>
        </div>

        <div class="grid">
            <div class="summary-box">
                <strong>{{ $backupRestorePreview['title'] }}</strong>
                <div class="muted" style="margin-top:8px;">{{ $backupRestorePreview['summary'] }}</div>
                @if (! empty($backupRestorePreview['directory']))
                    <div class="help" style="margin-top:8px;">Source : {{ $backupRestorePreview['directory'] }}</div>
                @endif
                @if (! empty($backupVerification['errors']))
                    <div class="help" style="margin-top:12px; color:#9c3d2f;">Erreurs detectees :</div>
                    <ul class="summary-list">
                        @foreach ($backupVerification['errors'] as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                @endif
                @if (! empty($backupVerification['warnings']))
                    <div class="help" style="margin-top:12px;">Points de vigilance :</div>
                    <ul class="summary-list">
                        @foreach ($backupVerification['warnings'] as $warning)
                            <li>{{ $warning }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
            <div class="summary-box">
                <strong>Sequence conseillee</strong>
                <ol class="summary-list" style="padding-left:18px;">
                    @foreach ($backupRestorePreview['steps'] as $step)
                        <li>{{ $step }}</li>
                    @endforeach
                </ol>
            </div>
        </div>
    </section>

    <section class="card" style="margin-bottom:18px;">
        <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start; flex-wrap:wrap; margin-bottom:16px;">
            <div>
                <h3 class="section-title">Outbox integration</h3>
                <div class="muted">Surveillance des evenements metier a publier, reprise manuelle des echecs et lecture rapide des files en attente.</div>
            </div>
            <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                <span class="badge badge-warning">En attente : {{ $outboxSummary['pending'] }}</span>
                <span class="badge {{ $outboxSummary['failed'] > 0 ? 'badge-warning' : 'badge-success' }}">En echec : {{ $outboxSummary['failed'] }}</span>
                <span class="badge badge-muted">Publies : {{ $outboxSummary['published'] }}</span>
                <form method="POST" action="{{ route('ops.outbox.process') }}">
                    @csrf
                    <button type="submit" class="button button-primary">Traiter la file</button>
                </form>
                @if ($outboxSummary['failed'] > 0)
                    <form method="POST" action="{{ route('ops.outbox.retry-failed') }}">
                        @csrf
                        <button type="submit" class="button button-secondary">Relancer les echecs</button>
                    </form>
                @endif
            </div>
        </div>

        @if ($outboxSummary['failed'] > 0 || $outboxSummary['pending'] > 0 || $outboxSummary['published'] > 0)
            <div class="help" style="margin-bottom:16px;">
                Plus ancien en attente : {{ $outboxSummary['oldest_pending_at'] ? \Illuminate\Support\Carbon::parse($outboxSummary['oldest_pending_at'])->format('d/m/Y H:i') : 'Aucun' }} · Dernier echec : {{ $outboxSummary['last_failed_at'] ? \Illuminate\Support\Carbon::parse($outboxSummary['last_failed_at'])->format('d/m/Y H:i') : 'Aucun' }} · Derniere publication : {{ $outboxSummary['last_published_at'] ? \Illuminate\Support\Carbon::parse($outboxSummary['last_published_at'])->format('d/m/Y H:i') : 'Aucune' }}
            </div>
        @endif

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Evenement</th>
                        <th>Aggregate</th>
                        <th>Statut</th>
                        <th>Tentatives</th>
                        <th>Disponible</th>
                        <th>Publie</th>
                        <th>Derniere livraison</th>
                        <th>Derniere erreur</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($outboxEvents as $event)
                        <tr>
                            <td>#{{ $event->id }}</td>
                            <td>{{ $event->event_name }}</td>
                            <td>{{ class_basename($event->aggregate_type) }} / {{ $event->aggregate_id }}</td>
                            <td><span class="badge {{ $event->status === 'published' ? 'badge-success' : ($event->status === 'pending' ? 'badge-warning' : 'badge-muted') }}">{{ $event->status }}</span></td>
                            <td>{{ $event->attempts }}</td>
                            <td>{{ $event->available_at?->format('d/m/Y H:i') ?: '-' }}</td>
                            <td>{{ $event->published_at?->format('d/m/Y H:i') ?: '-' }}</td>
                            <td>
                                @if ($event->latestDelivery)
                                    {{ strtoupper($event->latestDelivery->status) }}
                                    @if ($event->latestDelivery->response_status)
                                        · HTTP {{ $event->latestDelivery->response_status }}
                                    @endif
                                @else
                                    -
                                @endif
                            </td>
                            <td>{{ $event->last_error ?: '-' }}</td>
                            <td>
                                @if ($event->status === 'failed')
                                    <form method="POST" action="{{ route('ops.outbox.retry', $event) }}">
                                        @csrf
                                        <button type="submit" class="button button-secondary">Relancer</button>
                                    </form>
                                @else
                                    <span class="muted">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="muted">Aucun evenement outbox pour cette societe.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start; flex-wrap:wrap; margin-bottom:16px;">
            <div>
                <h3 class="section-title">Journal des livraisons</h3>
                <div class="muted">Chaque tentative webhook est tracee avec la charge envoyee, le code HTTP et le message d erreur si besoin.</div>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Tentative</th>
                        <th>Evenement</th>
                        <th>Statut</th>
                        <th>URL cible</th>
                        <th>HTTP</th>
                        <th>Demande</th>
                        <th>Reponse</th>
                        <th>Erreur</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($deliveryLogs as $delivery)
                        <tr>
                            <td>#{{ $delivery->attempt_number }} · {{ $delivery->requested_at?->format('d/m/Y H:i') ?: '-' }}</td>
                            <td>{{ $delivery->event?->event_name ?: 'Evenement supprime' }}</td>
                            <td><span class="badge {{ $delivery->status === 'sent' ? 'badge-success' : 'badge-warning' }}">{{ $delivery->status }}</span></td>
                            <td>{{ $delivery->target_url ?: '-' }}</td>
                            <td>{{ $delivery->response_status ?: '-' }}</td>
                            <td><code style="font-size:12px;">{{ json_encode($delivery->request_payload, JSON_UNESCAPED_UNICODE) }}</code></td>
                            <td>{{ $delivery->response_body ?: '-' }}</td>
                            <td>{{ $delivery->error_message ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="muted">Aucune tentative de livraison pour le moment.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
