@extends('layouts.app')

@section('title', 'Alertes internes')
@section('page-title', 'Alertes internes')

@section('content')
    @include('partials.erp-page-head', [
        'eyebrow' => 'Notifications',
        'title' => 'Centre d alertes',
        'description' => 'Retrouve ici les blocages metier, les approbations en attente et les alertes d exploitation.',
    ])

    <div style="display:flex; justify-content:flex-end; margin-bottom:18px;">
        <form method="POST" action="{{ route('notifications.read-all') }}">
            @csrf
            <button type="submit" class="button button-secondary">Tout marquer comme lu</button>
        </form>
    </div>

    <div class="grid stats-grid" style="margin-bottom:20px;">
        <div class="card"><div class="muted">Alertes actives</div><div class="stat-value">{{ $summary['active'] }}</div></div>
        <div class="card"><div class="muted">Non lues</div><div class="stat-value">{{ $summary['unread'] }}</div></div>
        <div class="card"><div class="muted">Critiques ouvertes</div><div class="stat-value">{{ $summary['critical'] }}</div></div>
        <div class="card"><div class="muted">Resolues</div><div class="stat-value">{{ $summary['resolved'] }}</div></div>
    </div>

    <div class="card" style="margin-bottom:18px;">
        <div class="filter-pills">
            <a href="{{ route('notifications.index', array_merge($filters, ['scope' => 'active'])) }}" class="button {{ ($filters['scope'] ?? 'active') === 'active' ? 'button-primary' : 'button-secondary' }}">Actives</a>
            <a href="{{ route('notifications.index', array_merge($filters, ['scope' => 'all'])) }}" class="button {{ ($filters['scope'] ?? 'active') === 'all' ? 'button-primary' : 'button-secondary' }}">Toutes</a>
            <a href="{{ route('notifications.index', array_merge($filters, ['scope' => 'resolved'])) }}" class="button {{ ($filters['scope'] ?? 'active') === 'resolved' ? 'button-primary' : 'button-secondary' }}">Resolues</a>
        </div>

        <form method="GET" action="{{ route('notifications.index') }}" class="form-grid" style="align-items:end; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));">
            <input type="hidden" name="scope" value="{{ $filters['scope'] ?? 'active' }}">
            <div style="grid-column:span 2; min-width:220px;">
                <label for="search">Recherche</label>
                <input type="text" id="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Titre, message, code, agence...">
            </div>
            <div>
                <label for="level">Niveau</label>
                <select id="level" name="level">
                    <option value="">Tous les niveaux</option>
                    <option value="danger" @selected(($filters['level'] ?? null) === 'danger')>Critique</option>
                    <option value="warning" @selected(($filters['level'] ?? null) === 'warning')>Attention</option>
                    <option value="info" @selected(($filters['level'] ?? null) === 'info')>Info</option>
                    <option value="success" @selected(($filters['level'] ?? null) === 'success')>Succes</option>
                </select>
            </div>
            <div>
                <label for="read_state">Lecture</label>
                <select id="read_state" name="read_state">
                    <option value="all" @selected(($filters['read_state'] ?? 'all') === 'all')>Toutes</option>
                    <option value="unread" @selected(($filters['read_state'] ?? 'all') === 'unread')>Non lues</option>
                    <option value="read" @selected(($filters['read_state'] ?? 'all') === 'read')>Lues</option>
                </select>
            </div>
            <div class="actions" style="margin-top:0; justify-content:flex-start; align-self:end;">
                <button type="submit" class="button button-primary">Filtrer</button>
                <a href="{{ route('notifications.index') }}" class="button button-secondary">Reinitialiser</a>
            </div>
        </form>
    </div>

    <div class="grid">
        @forelse ($notifications as $notification)
            <section class="card" style="border-left:6px solid {{ $notification->level === 'danger' ? '#b42318' : ($notification->level === 'warning' ? '#ca6702' : ($notification->level === 'success' ? '#176b4d' : '#005f73')) }};">
                <div style="display:flex; justify-content:space-between; gap:18px; align-items:flex-start; flex-wrap:wrap;">
                    <div style="max-width:820px;">
                        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                            <strong>{{ $notification->title }}</strong>
                            @include('partials.erp-status-badge', [
                                'label' => $notification->resolved_at ? 'Resolue' : ($notification->is_read ? 'Lue' : 'Nouvelle'),
                                'tone' => $notification->resolved_at ? 'success' : (($notification->level === 'danger' || $notification->level === 'warning') ? 'warning' : 'muted'),
                            ])
                            @if ($notification->branch)
                                @include('partials.erp-status-badge', ['label' => $notification->branch->name, 'tone' => 'muted'])
                            @endif
                        </div>
                        <div class="muted" style="margin-top:10px;">{{ $notification->message }}</div>
                        <div class="muted" style="margin-top:12px; font-size:13px;">
                            Creee le {{ $notification->created_at?->format('d/m/Y H:i') }}
                            @if ($notification->resolved_at)
                                · Resolue le {{ $notification->resolved_at?->format('d/m/Y H:i') }}
                            @endif
                            @if ($notification->reader)
                                · Lue par {{ $notification->reader->name }} le {{ $notification->read_at?->format('d/m/Y H:i') }}
                            @endif
                        </div>
                    </div>
                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                        @if ($notification->action_url)
                            <a href="{{ $notification->action_url }}" class="button button-primary">Ouvrir</a>
                        @endif
                        @if (! $notification->is_read)
                            <form method="POST" action="{{ route('notifications.read', $notification) }}">
                                @csrf
                                <button type="submit" class="button button-secondary">Marquer comme lu</button>
                            </form>
                        @endif
                    </div>
                </div>
            </section>
        @empty
            <section class="card empty-state">
                @include('partials.erp-status-badge', ['label' => 'Rien a signaler', 'tone' => 'success'])
                <h3>Aucune alerte pour ce filtre</h3>
                <div class="muted">Le noyau ne remonte actuellement aucun blocage correspondant a ta selection.</div>
                <div class="empty-actions">
                    <a href="{{ route('dashboard') }}" class="button button-primary">Retour dashboard</a>
                    @allowed('approvals.view')
                        <a href="{{ route('approvals.index') }}" class="button button-secondary">Voir les approbations</a>
                    @endallowed
                </div>
            </section>
        @endforelse
    </div>

    <div style="margin-top:18px;">
        {{ $notifications->links() }}
    </div>
@endsection
