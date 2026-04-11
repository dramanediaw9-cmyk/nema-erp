@extends('layouts.app')

@section('title', 'Projets - Nema ERP')
@section('page-title', 'Projets')

@section('content')
    @php
        $canManageProjects = auth()->user()?->hasPermission('projects.manage') ?? false;
        $projectBadge = [
            'planning' => 'badge-muted',
            'active' => 'badge-success',
            'at_risk' => 'badge-danger',
            'complete' => 'badge-warning',
        ];
        $taskBadge = [
            'todo' => 'badge-muted',
            'in_progress' => 'badge-success',
            'blocked' => 'badge-danger',
            'done' => 'badge-warning',
        ];
        $priorityBadge = [
            'low' => 'badge-muted',
            'normal' => 'badge-warning',
            'high' => 'badge-danger',
            'critical' => 'badge-danger',
        ];
        $healthCopy = [
            'stable' => ['label' => 'Sous controle', 'class' => 'badge-success'],
            'watch' => ['label' => 'Jalon proche', 'class' => 'badge-warning'],
            'risk' => ['label' => 'Sous tension', 'class' => 'badge-danger'],
            'done' => ['label' => 'Execution terminee', 'class' => 'badge-muted'],
        ];
    @endphp

    <div class="page-head">
        <div>
            <h2 style="margin:0;">Pilotage projets</h2>
            <div class="muted">Portefeuille, execution terrain, jalons et alertes de retard dans un meme cockpit.</div>
        </div>
    </div>

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

    <div class="grid stats-grid" style="margin-bottom:20px;">
        <div class="card"><div class="muted">Projets</div><div class="stat-value">{{ $summary['projects'] }}</div></div>
        <div class="card"><div class="muted">Actifs</div><div class="stat-value">{{ $summary['active'] }}</div></div>
        <div class="card"><div class="muted">Sous tension</div><div class="stat-value">{{ $summary['at_risk'] }}</div></div>
        <div class="card"><div class="muted">Budget cumule</div><div class="stat-value">{{ number_format($summary['budget'], 0, ',', ' ') }} XOF</div></div>
        <div class="card"><div class="muted">Elements ouverts</div><div class="stat-value">{{ $summary['open_tasks'] }}</div></div>
        <div class="card"><div class="muted">Retards / jalons proches</div><div class="stat-value">{{ $summary['overdue_tasks'] }} / {{ $summary['milestones_due'] }}</div></div>
    </div>

    @allowed('projects.manage')
        <form method="POST" action="{{ route('projects.store') }}" class="card form-grid" style="margin-bottom:18px;">
            @csrf
            <div class="full">
                <h3 class="section-title">Nouveau projet</h3>
            </div>
            <div>
                <label for="code">Code</label>
                <input id="code" name="code" value="{{ old('code') }}" placeholder="PRJ-2026-0001">
            </div>
            <div>
                <label for="name">Nom</label>
                <input id="name" name="name" value="{{ old('name') }}" required>
            </div>
            <div>
                <label for="customer_name">Client</label>
                <input id="customer_name" name="customer_name" value="{{ old('customer_name') }}">
            </div>
            <div>
                <label for="owner_id">Proprietaire</label>
                <select id="owner_id" name="owner_id">
                    <option value="">Non affecte</option>
                    @foreach ($owners as $owner)
                        <option value="{{ $owner->id }}" @selected(old('owner_id') == $owner->id)>{{ $owner->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="branch_id">Agence</label>
                <select id="branch_id" name="branch_id">
                    <option value="">Toutes les agences</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="status">Statut</label>
                <select id="status" name="status" required>
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', 'planning') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="start_date">Debut</label>
                <input id="start_date" name="start_date" type="date" value="{{ old('start_date', now()->toDateString()) }}" required>
            </div>
            <div>
                <label for="target_end_date">Echeance</label>
                <input id="target_end_date" name="target_end_date" type="date" value="{{ old('target_end_date', now()->addMonth()->toDateString()) }}">
            </div>
            <div>
                <label for="progress">Avancement</label>
                <input id="progress" name="progress" type="number" min="0" max="100" value="{{ old('progress', 0) }}">
            </div>
            <div>
                <label for="budget_amount">Budget</label>
                <input id="budget_amount" name="budget_amount" type="number" min="0" step="0.01" value="{{ old('budget_amount', 0) }}">
            </div>
            <div class="full">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes">{{ old('notes') }}</textarea>
            </div>
            <div class="full actions">
                <button type="submit" class="button button-primary">Enregistrer le projet</button>
            </div>
        </form>
    @endallowed

    <section class="card" style="margin-bottom:18px;">
        <h3 class="section-title">Portefeuille projets</h3>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Code</th>
                    <th>Nom</th>
                    <th>Client</th>
                    <th>Proprietaire</th>
                    <th>Execution</th>
                    <th>Budget</th>
                    <th>Statut</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($projects as $project)
                    @php($snapshot = $project->execution_snapshot)
                    <tr>
                        <td>{{ $project->code }}</td>
                        <td>
                            <strong>{{ $project->name }}</strong>
                            <div class="muted" style="font-size:12px;">Debut {{ optional($project->start_date)->format('d/m/Y') ?: '-' }} · Echeance {{ optional($project->target_end_date)->format('d/m/Y') ?: '-' }}</div>
                        </td>
                        <td>{{ $project->customer_name ?: '-' }}</td>
                        <td>{{ $project->owner?->name ?? '-' }}</td>
                        <td>
                            <div>{{ $project->progress }}% · {{ $snapshot['open'] }} ouvert(s)</div>
                            <div class="muted" style="font-size:12px;">{{ $snapshot['overdue'] }} en retard · {{ $snapshot['milestones_due'] }} jalon(x) proche(s)</div>
                        </td>
                        <td>{{ number_format((float) $project->budget_amount, 0, ',', ' ') }} XOF</td>
                        <td>
                            <span class="badge {{ $projectBadge[$project->status] ?? 'badge-muted' }}">{{ $statusOptions[$project->status] ?? $project->status }}</span>
                            <span class="badge {{ $healthCopy[$snapshot['health']]['class'] ?? 'badge-muted' }}">{{ $healthCopy[$snapshot['health']]['label'] ?? $snapshot['health_label'] }}</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">Aucun projet enregistre pour le moment.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="grid" style="gap:18px;">
        @forelse ($projects as $project)
            @php($snapshot = $project->execution_snapshot)
            <article class="card" style="border-color: {{ $snapshot['health'] === 'risk' ? '#9c3d2f' : '#d6dde8' }};">
                <div class="page-head" style="margin-bottom:14px;">
                    <div>
                        <h3 style="margin:0 0 4px;">{{ $project->name }}</h3>
                        <div class="muted">{{ $project->code }} · {{ $project->customer_name ?: 'Client interne / non precise' }}</div>
                    </div>
                    <div style="display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end;">
                        <span class="badge {{ $projectBadge[$project->status] ?? 'badge-muted' }}">{{ $statusOptions[$project->status] ?? $project->status }}</span>
                        <span class="badge {{ $healthCopy[$snapshot['health']]['class'] ?? 'badge-muted' }}">{{ $healthCopy[$snapshot['health']]['label'] ?? $snapshot['health_label'] }}</span>
                    </div>
                </div>

                <div class="summary-list" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:10px; margin-bottom:14px;">
                    <div><strong>Proprietaire</strong><div class="muted">{{ $project->owner?->name ?? 'Non affecte' }}</div></div>
                    <div><strong>Avancement</strong><div class="muted">{{ $project->progress }}%</div></div>
                    <div><strong>Ouverts</strong><div class="muted">{{ $snapshot['open'] }} / {{ $snapshot['total'] }}</div></div>
                    <div><strong>Retards</strong><div class="muted">{{ $snapshot['overdue'] }}</div></div>
                    <div><strong>Budget</strong><div class="muted">{{ number_format((float) $project->budget_amount, 0, ',', ' ') }} XOF</div></div>
                    <div><strong>Echeance</strong><div class="muted">{{ optional($project->target_end_date)->format('d/m/Y') ?: 'Non definie' }}</div></div>
                </div>

                @if ($project->notes)
                    <div class="muted" style="margin-bottom:14px;">{{ $project->notes }}</div>
                @endif

                @allowed('projects.manage')
                    <form method="POST" action="{{ route('projects.tasks.store', $project) }}" class="card form-grid" style="margin-bottom:14px; background:#f8fafc;">
                        @csrf
                        <div class="full">
                            <h4 class="section-title" style="margin-bottom:0;">Ajouter une tache ou un jalon</h4>
                        </div>
                        <div>
                            <label for="title-{{ $project->id }}">Intitule</label>
                            <input id="title-{{ $project->id }}" name="title" value="{{ old('title') }}" required placeholder="Ex: valider les prix, former l equipe, lancer pilote">
                        </div>
                        <div>
                            <label for="item-type-{{ $project->id }}">Type</label>
                            <select id="item-type-{{ $project->id }}" name="item_type">
                                @foreach ($itemTypeOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('item_type', 'task') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="task-owner-{{ $project->id }}">Responsable</label>
                            <select id="task-owner-{{ $project->id }}" name="owner_id">
                                <option value="">Non affecte</option>
                                @foreach ($owners as $owner)
                                    <option value="{{ $owner->id }}" @selected(old('owner_id', $project->owner_id) == $owner->id)>{{ $owner->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="due-date-{{ $project->id }}">Echeance</label>
                            <input id="due-date-{{ $project->id }}" name="due_date" type="date" value="{{ old('due_date', optional($project->target_end_date)->toDateString()) }}">
                        </div>
                        <div>
                            <label for="task-priority-{{ $project->id }}">Priorite</label>
                            <select id="task-priority-{{ $project->id }}" name="priority">
                                @foreach ($taskPriorityOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('priority', 'normal') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="task-status-{{ $project->id }}">Statut</label>
                            <select id="task-status-{{ $project->id }}" name="status">
                                @foreach ($taskStatusOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('status', 'todo') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="full">
                            <label for="task-notes-{{ $project->id }}">Notes</label>
                            <textarea id="task-notes-{{ $project->id }}" name="notes" rows="2">{{ old('notes') }}</textarea>
                        </div>
                        <div class="full actions">
                            <button type="submit" class="button button-primary">Ajouter a l execution</button>
                        </div>
                    </form>
                @endallowed

                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>Element</th>
                            <th>Responsable</th>
                            <th>Echeance</th>
                            <th>Progression</th>
                            <th>Statut</th>
                            @if ($canManageProjects)
                                <th>Actions</th>
                            @endif
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($project->tasks as $task)
                            <tr>
                                <td>
                                    <strong>{{ $task->title }}</strong>
                                    <div style="display:flex; gap:6px; flex-wrap:wrap; margin-top:4px;">
                                        <span class="badge badge-muted">{{ $itemTypeOptions[$task->item_type] ?? $task->item_type }}</span>
                                        <span class="badge {{ $priorityBadge[$task->priority] ?? 'badge-muted' }}">{{ $taskPriorityOptions[$task->priority] ?? $task->priority }}</span>
                                    </div>
                                    @if ($task->notes)
                                        <div class="muted" style="font-size:12px; margin-top:6px;">{{ $task->notes }}</div>
                                    @endif
                                </td>
                                <td>{{ $task->owner?->name ?? '-' }}</td>
                                <td>
                                    {{ optional($task->due_date)->format('d/m/Y') ?: '-' }}
                                    @if ($task->isOverdue())
                                        <div class="muted" style="font-size:12px; color:#9c3d2f;">Retard actif</div>
                                    @endif
                                </td>
                                <td>{{ $task->progress }}%</td>
                                <td><span class="badge {{ $taskBadge[$task->status] ?? 'badge-muted' }}">{{ $taskStatusOptions[$task->status] ?? $task->status }}</span></td>
                                @if ($canManageProjects)
                                    <td>
                                        <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                            @if ($task->status !== 'in_progress')
                                                <form method="POST" action="{{ route('projects.tasks.status', $task) }}">
                                                    @csrf
                                                    <input type="hidden" name="status" value="in_progress">
                                                    <button type="submit" class="button button-secondary">Demarrer</button>
                                                </form>
                                            @endif
                                            @if ($task->status !== 'blocked' && $task->status !== 'done')
                                                <form method="POST" action="{{ route('projects.tasks.status', $task) }}">
                                                    @csrf
                                                    <input type="hidden" name="status" value="blocked">
                                                    <button type="submit" class="button button-secondary">Bloquer</button>
                                                </form>
                                            @endif
                                            @if ($task->status !== 'done')
                                                <form method="POST" action="{{ route('projects.tasks.status', $task) }}">
                                                    @csrf
                                                    <input type="hidden" name="status" value="done">
                                                    <button type="submit" class="button button-primary">Terminer</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canManageProjects ? 6 : 5 }}" class="muted">Aucun element d execution pour ce projet.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </article>
        @empty
            <div class="card">
                <div class="muted">Ajoute un premier projet pour demarrer le cockpit d execution.</div>
            </div>
        @endforelse
    </section>
@endsection
