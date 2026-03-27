@extends('layouts.app')

@section('title', 'Journaux d\'activité - Nema ERP')
@section('page-title', 'Journaux d\'activité')

@section('content')
    <div class="card" style="margin-bottom: 20px;">
        <form method="GET" class="form-grid" style="grid-template-columns: 1fr auto; align-items: end;">
            <div>
                <label for="action">Filtrer par action</label>
                <select id="action" name="action">
                    <option value="">Toutes les actions</option>
                    @foreach ($actions as $action)
                        <option value="{{ $action }}" @selected(request('action') === $action)>{{ $action }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <button type="submit" class="button button-primary">Filtrer</button>
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
                <th>IP</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td>{{ $log->created_at?->format('d/m/Y H:i') }}</td>
                    <td><span class="badge badge-muted">{{ $log->action }}</span></td>
                    <td>{{ $log->description }}</td>
                    <td>{{ $log->user?->name ?? 'Système' }}</td>
                    <td>{{ $log->ip_address ?: '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5"><span class="muted">Aucune activité enregistrée.</span></td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 18px;">{{ $logs->links() }}</div>
@endsection
