@extends('layouts.app')

@section('title', 'Automatisations - Nema ERP')
@section('page-title', 'Automatisations')

@section('content')
    @php
        $statusBadge = ['draft' => 'badge-muted', 'active' => 'badge-success', 'paused' => 'badge-warning'];
        $severityBadge = ['info' => 'badge-muted', 'warning' => 'badge-warning', 'danger' => 'badge-danger'];
        $executionBadge = ['matched' => 'badge-danger', 'cooldown' => 'badge-warning', 'clear' => 'badge-success', 'error' => 'badge-danger'];
    @endphp

    <div class="erp-work-page">
        <section class="erp-work-toolbar">
            <div class="erp-work-toolbar__context">
                <div>
                    <strong>Moteur d automatisation transverse</strong>
                    <div class="muted">Signaux critiques, regles actives et execution transverse.</div>
                </div>
            </div>
            <div class="erp-work-toolbar__actions">
                @allowed('automation.manage')
                    <form method="POST" action="{{ route('automation.run-all') }}">
                        @csrf
                        <button type="submit" class="button button-primary">Executer les regles actives</button>
                    </form>
                @endallowed
            </div>
        </section>

    @if ($errors->any())
        <div class="card" style="margin-bottom:18px; border-color:#9c3d2f;">
            <strong>Des validations sont a corriger</strong>
            <ul class="summary-list" style="margin-top:10px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

        <div class="erp-kpi-strip">
            <div class="card erp-kpi-card"><div class="label">Regles</div><div class="value">{{ $catalog['summary']['rules'] }}</div></div>
            <div class="card erp-kpi-card"><div class="label">Actives</div><div class="value">{{ $catalog['summary']['active'] }}</div></div>
            <div class="card erp-kpi-card"><div class="label">En pause</div><div class="value">{{ $catalog['summary']['paused'] }}</div></div>
            <div class="card erp-kpi-card"><div class="label">Signaux 24 h</div><div class="value">{{ $catalog['summary']['matched_last_24h'] }}</div></div>
            <div class="card erp-kpi-card"><div class="label">Signaux en veille</div><div class="value">{{ $catalog['summary']['signals_on_watch'] }}</div></div>
        </div>

        @allowed('automation.manage')
            <details class="card erp-filter-panel" @if($errors->any()) open @endif>
                <summary>Ajouter une regle</summary>
                <div class="erp-filter-panel__body">
                    <form method="POST" action="{{ route('automation.store') }}" class="form-grid">
            @csrf
            <div class="full"><h3 class="section-title">Nouvelle regle noyau</h3></div>
            <div><label for="automation-code">Code</label><input id="automation-code" name="code" value="{{ old('code') }}" placeholder="AUTO-0001"></div>
            <div><label for="automation-name">Nom</label><input id="automation-name" name="name" value="{{ old('name') }}" required placeholder="Ex: Proteger execution projets"></div>
            <div>
                <label for="automation-signal">Signal</label>
                <select id="automation-signal" name="signal_key" required>
                    @foreach ($signalDefinitions as $key => $definition)
                        <option value="{{ $key }}" @selected(old('signal_key') === $key)>{{ $definition['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="automation-status">Statut</label>
                <select id="automation-status" name="status" required>
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', 'active') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="automation-severity">Severite</label>
                <select id="automation-severity" name="severity" required>
                    @foreach ($severityOptions as $value => $label)
                        <option value="{{ $value }}" @selected(old('severity', 'warning') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="automation-action-type">Action</label>
                <select id="automation-action-type" name="action_type" required>
                    @foreach ($actionTypeOptions as $value => $label)
                        <option value="{{ $value }}" @selected(old('action_type', 'internal_alert') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div><label for="automation-threshold">Seuil</label><input id="automation-threshold" type="number" min="1" step="1" name="threshold_value" value="{{ old('threshold_value', 1) }}" required></div>
            <div><label for="automation-window">Fenetre (heures)</label><input id="automation-window" type="number" min="1" step="1" name="window_hours" value="{{ old('window_hours') }}" placeholder="Ex: 48"></div>
            <div><label for="automation-cooldown">Cooldown (minutes)</label><input id="automation-cooldown" type="number" min="0" step="1" name="cooldown_minutes" value="{{ old('cooldown_minutes', 240) }}"></div>
            <div>
                <label for="automation-branch">Agence</label>
                <select id="automation-branch" name="branch_id">
                    <option value="">Toutes les agences</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="automation-owner">Responsable</label>
                <select id="automation-owner" name="owner_id">
                    <option value="">Non affecte</option>
                    @foreach ($owners as $owner)
                        <option value="{{ $owner->id }}" @selected(old('owner_id') == $owner->id)>{{ $owner->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="full"><label for="automation-description">Description</label><textarea id="automation-description" name="description" rows="2">{{ old('description') }}</textarea></div>
            <div class="full"><label for="automation-notes">Notes</label><textarea id="automation-notes" name="notes" rows="2">{{ old('notes') }}</textarea></div>
            <div class="full actions"><button type="submit" class="button button-primary">Enregistrer la regle</button></div>
                    </form>
                </div>
            </details>
        @endallowed

        <section class="card">
        <h3 class="section-title">Catalogue des signaux noyau</h3>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Signal</th><th>Module</th><th>Description</th><th>Lecture live</th></tr></thead>
                <tbody>
                @foreach ($catalog['signals'] as $signal)
                    <tr>
                        <td><strong>{{ $signal['label'] }}</strong><div class="muted" style="font-size:12px;">{{ $signal['key'] }}</div></td>
                        <td>{{ $signal['module_key'] }}</td>
                        <td>{{ $signal['description'] }}</td>
                        <td>
                            <span class="badge {{ $signal['preview']['matched'] ? 'badge-danger' : 'badge-success' }}">{{ $signal['preview']['value'] }} / seuil {{ $signal['preview']['threshold_value'] }}</span>
                            <div class="muted" style="font-size:12px;">{{ $signal['preview']['message'] }}</div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        </section>

        <section class="card">
        <h3 class="section-title">Regles du noyau</h3>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Code</th><th>Nom</th><th>Signal</th><th>Seuil</th><th>Etat</th><th>Derniere execution</th><th>Actions</th></tr></thead>
                <tbody>
                @forelse ($catalog['rules'] as $rule)
                    @php($latestExecution = $rule->latestExecution)
                    <tr>
                        <td>{{ $rule->code }}</td>
                        <td><strong>{{ $rule->name }}</strong><div class="muted" style="font-size:12px;">{{ $rule->owner?->name ?? 'Non affecte' }} · {{ $rule->branch?->name ?? 'Toutes agences' }}</div></td>
                        <td>{{ $signalDefinitions[$rule->signal_key]['label'] ?? $rule->signal_key }}</td>
                        <td>{{ $rule->threshold_value }} @if($rule->window_hours) / {{ $rule->window_hours }} h @endif</td>
                        <td>
                            <span class="badge {{ $statusBadge[$rule->status] ?? 'badge-muted' }}">{{ $statusOptions[$rule->status] ?? $rule->status }}</span>
                            <span class="badge {{ $severityBadge[$rule->severity] ?? 'badge-warning' }}">{{ $severityOptions[$rule->severity] ?? $rule->severity }}</span>
                        </td>
                        <td>
                            @if ($latestExecution)
                                <span class="badge {{ $executionBadge[$latestExecution->status] ?? 'badge-muted' }}">{{ $latestExecution->status }}</span>
                                <div class="muted" style="font-size:12px;">{{ $latestExecution->observed_value }} · {{ optional($latestExecution->executed_at)->format('d/m/Y H:i') }}</div>
                            @else
                                <span class="muted">Jamais executee</span>
                            @endif
                        </td>
                        <td>
                            @allowed('automation.manage')
                                <form method="POST" action="{{ route('automation.run', $rule) }}">
                                    @csrf
                                    <button type="submit" class="button button-secondary">Executer</button>
                                </form>
                            @endallowed
                        </td>
                    </tr>
                    @allowed('automation.manage')
                        <tr>
                            <td colspan="7">
                                <details class="erp-filter-panel">
                                    <summary>Modifier {{ $rule->code }} — {{ $rule->name }}</summary>
                                    <div class="erp-filter-panel__body">
                                        <form method="POST" action="{{ route('automation.update', $rule) }}" class="form-grid">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="code" value="{{ $rule->code }}">
                                    <div><label for="rule-name-{{ $rule->id }}">Nom</label><input id="rule-name-{{ $rule->id }}" name="name" value="{{ $rule->name }}" required></div>
                                    <div>
                                        <label for="rule-signal-{{ $rule->id }}">Signal</label>
                                        <select id="rule-signal-{{ $rule->id }}" name="signal_key">
                                            @foreach ($signalDefinitions as $key => $definition)
                                                <option value="{{ $key }}" @selected($rule->signal_key === $key)>{{ $definition['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label for="rule-status-{{ $rule->id }}">Statut</label>
                                        <select id="rule-status-{{ $rule->id }}" name="status">
                                            @foreach ($statusOptions as $value => $label)
                                                <option value="{{ $value }}" @selected($rule->status === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div><label for="rule-threshold-{{ $rule->id }}">Seuil</label><input id="rule-threshold-{{ $rule->id }}" name="threshold_value" type="number" min="1" value="{{ $rule->threshold_value }}" required></div>
                                    <div><label for="rule-window-{{ $rule->id }}">Fenetre (h)</label><input id="rule-window-{{ $rule->id }}" name="window_hours" type="number" min="1" value="{{ $rule->window_hours }}"></div>
                                    <div><label for="rule-cooldown-{{ $rule->id }}">Cooldown</label><input id="rule-cooldown-{{ $rule->id }}" name="cooldown_minutes" type="number" min="0" value="{{ $rule->cooldown_minutes }}"></div>
                                    <div>
                                        <label for="rule-severity-{{ $rule->id }}">Severite</label>
                                        <select id="rule-severity-{{ $rule->id }}" name="severity">
                                            @foreach ($severityOptions as $value => $label)
                                                <option value="{{ $value }}" @selected($rule->severity === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label for="rule-action-{{ $rule->id }}">Action</label>
                                        <select id="rule-action-{{ $rule->id }}" name="action_type">
                                            @foreach ($actionTypeOptions as $value => $label)
                                                <option value="{{ $value }}" @selected($rule->action_type === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label for="rule-owner-{{ $rule->id }}">Responsable</label>
                                        <select id="rule-owner-{{ $rule->id }}" name="owner_id">
                                            <option value="">Non affecte</option>
                                            @foreach ($owners as $owner)
                                                <option value="{{ $owner->id }}" @selected($rule->owner_id === $owner->id)>{{ $owner->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label for="rule-branch-{{ $rule->id }}">Agence</label>
                                        <select id="rule-branch-{{ $rule->id }}" name="branch_id">
                                            <option value="">Toutes les agences</option>
                                            @foreach ($branches as $branch)
                                                <option value="{{ $branch->id }}" @selected($rule->branch_id === $branch->id)>{{ $branch->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="full"><label for="rule-description-{{ $rule->id }}">Description</label><textarea id="rule-description-{{ $rule->id }}" name="description" rows="2">{{ $rule->description }}</textarea></div>
                                    <div class="full"><label for="rule-notes-{{ $rule->id }}">Notes</label><textarea id="rule-notes-{{ $rule->id }}" name="notes" rows="2">{{ $rule->notes }}</textarea></div>
                                    <div class="full actions"><button type="submit" class="button button-primary">Mettre a jour</button></div>
                                        </form>
                                    </div>
                                </details>
                            </td>
                        </tr>
                    @endallowed
                @empty
                    <tr><td colspan="7" class="muted">Aucune regle d automatisation configuree.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        </section>

        <section class="card">
        <h3 class="section-title">Historique d execution</h3>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Execution</th><th>Regle</th><th>Signal</th><th>Valeur</th><th>Message</th></tr></thead>
                <tbody>
                @forelse ($catalog['recent_executions'] as $execution)
                    <tr>
                        <td><span class="badge {{ $executionBadge[$execution->status] ?? 'badge-muted' }}">{{ $execution->status }}</span><div class="muted" style="font-size:12px;">{{ optional($execution->executed_at)->format('d/m/Y H:i') }}</div></td>
                        <td>{{ $execution->rule?->name ?? '-' }}</td>
                        <td>{{ $signalDefinitions[$execution->signal_key]['label'] ?? $execution->signal_key }}</td>
                        <td>{{ $execution->observed_value }}</td>
                        <td>{{ $execution->message }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">Aucune execution enregistree pour le moment.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        </section>
    </div>
@endsection
