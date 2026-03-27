@extends('layouts.app')

@section('title', 'Entrepots - Nema ERP')
@section('page-title', 'Entrepots')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Depots et entrepots</h2>
            <div class="muted">Chaque agence peut avoir plusieurs points de stockage avec un depot par defaut.</div>
        </div>
    </div>

    <div class="split">
        <section class="card">
            <h2 class="section-title">Entrepots existants</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Code</th>
                        <th>Nom</th>
                        <th>Agence</th>
                        <th>Etat</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($warehouses as $warehouse)
                        <tr>
                            <td><strong>{{ $warehouse->code }}</strong></td>
                            <td>{{ $warehouse->name }}</td>
                            <td>{{ $warehouse->branch?->name }}</td>
                            <td>
                                @if ($warehouse->is_default)
                                    <span class="badge badge-success">Defaut</span>
                                @else
                                    <span class="badge badge-muted">Secondaire</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <aside class="card">
            <h2 class="section-title">Nouvel entrepot</h2>
            <form method="POST" action="{{ route('warehouses.store') }}">
                @csrf
                <div class="form-grid" style="grid-template-columns:1fr;">
                    <div>
                        <label for="branch_id">Agence</label>
                        <select id="branch_id" name="branch_id" required>
                            <option value="">Selectionner</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="code">Code</label>
                        <input id="code" name="code" type="text" required placeholder="DEP-BKO-03">
                    </div>
                    <div>
                        <label for="name">Nom</label>
                        <input id="name" name="name" type="text" required placeholder="Depot secondaire rive droite">
                    </div>
                    <label class="checkbox-row"><input type="checkbox" name="is_default" value="1"> <span>Definir comme depot par defaut de l agence</span></label>
                </div>
                <div class="actions" style="justify-content:flex-start;">
                    <button type="submit" class="button button-primary">Creer l entrepot</button>
                </div>
            </form>
        </aside>
    </div>
@endsection
