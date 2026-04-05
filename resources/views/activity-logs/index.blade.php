@extends('layouts.app')

@section('title', 'Journaux d\'activite - Nema ERP')
@section('page-title', 'Journaux d\'activite')

@section('content')
    <div class="card" style="margin-bottom: 20px;">
        <form method="GET" class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); align-items: end;">
            <div style="min-width: 220px;">
                <label for="search">Recherche</label>
                <input type="text" id="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Action, utilisateur, agence, IP...">
            </div>
            <div>
                <label for="action">Action</label>
                <select id="action" name="action">
                    <option value="">Toutes les actions</option>
                    @foreach ($actions as $action)
                        <option value="{{ $action }}" @selected(($filters['action'] ?? null) === $action)>{{ $action }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="branch_id">Agence</label>
                <select id="branch_id" name="branch_id">
                    <option value="">Toutes les agences</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((int) ($filters['branch_id'] ?? 0) === $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="actions" style="margin-top: 0; justify-content: flex-start; align-self: end;">
                <button type="submit" class="button button-primary">Filtrer</button>
                <a href="{{ route('activity-logs.index') }}" class="button button-secondary">Reinitialiser</a>
            </div>
        </form>
    </div>

    <div class="card table-wrap">
        <table>
            <thead>
            <tr>
                <th>Date</th>
                <th>Action</th>
                <th>Description</th>
                <th>Utilisateur</th>
                <th>Agence</th>
                <th>IP</th>
                <th>Contexte</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td>{{ $log->created_at?->format('d/m/Y H:i') }}</td>
                    <td><span class="badge badge-muted">{{ $log->action }}</span></td>
                    <td>{{ $log->description }}</td>
                    <td>
                        <div>{{ $log->user?->name ?? 'Systeme' }}</div>
                        @if ($log->user?->email)
                            <div class="muted" style="font-size: 12px; margin-top: 4px;">{{ $log->user->email }}</div>
                        @endif
                    </td>
                    <td>{{ $log->branch?->name ?? 'Non renseignee' }}</td>
                    <td>{{ $log->ip_address ?: '-' }}</td>
                    <td title="{{ $log->user_agent ?: 'Non renseigne' }}">{{ \Illuminate\Support\Str::limit($log->user_agent ?: 'Non renseigne', 56) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7"><span class="muted">Aucune activite enregistree.</span></td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 18px;">{{ $logs->links() }}</div>
@endsection
