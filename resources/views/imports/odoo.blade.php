@extends('layouts.app')

@section('title', 'Synchronisation Odoo - Nema ERP')
@section('page-title', 'Synchronisation produits Odoo')

@section('content')
    <style>
        .odoo-grid{display:grid;grid-template-columns:minmax(300px,.85fr) minmax(440px,1.4fr);gap:14px;align-items:start}.odoo-stack{display:grid;gap:12px}.odoo-card{padding:16px}.odoo-card h2,.odoo-card h3{margin:0 0 8px}.odoo-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}.odoo-actions form{margin:0}.odoo-meta{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;font-size:.9rem}.odoo-meta div{padding:8px 10px;border:1px solid var(--border);border-radius:10px}.odoo-meta strong{display:block;margin-top:2px;overflow-wrap:anywhere}.odoo-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.odoo-form-grid .wide{grid-column:1/-1}.odoo-checks{display:flex;gap:14px;flex-wrap:wrap}.odoo-checks label{display:flex;gap:7px;align-items:center;margin:0}.odoo-checks input{width:auto}.odoo-progress{height:10px;background:color-mix(in srgb,var(--border) 75%,transparent);border-radius:999px;overflow:hidden}.odoo-progress>span{display:block;height:100%;background:linear-gradient(90deg,var(--primary),#0aa77f);transition:width .25s}.odoo-run{padding:12px;border:1px solid var(--border);border-radius:12px}.odoo-run-head{display:flex;justify-content:space-between;gap:10px;align-items:center}.odoo-counts{display:flex;gap:12px;flex-wrap:wrap;font-size:.84rem;margin:7px 0}.odoo-state{display:inline-flex;padding:4px 9px;border-radius:999px;font-weight:700;font-size:.78rem;background:#edf1f6}.odoo-state.completed{background:#dcfce7;color:#166534}.odoo-state.failed{background:#fee2e2;color:#991b1b}.odoo-state.running,.odoo-state.queued{background:#dbeafe;color:#1d4ed8}.odoo-errors{margin-top:8px;padding:8px 10px;background:#fff4f4;border-radius:9px;color:#8d2323;font-size:.86rem}.odoo-table-wrap{overflow:auto}.odoo-table-wrap table{min-width:760px}.odoo-note{padding:10px 12px;background:color-mix(in srgb,var(--primary) 8%,transparent);border-radius:10px}.odoo-secret{font-size:.82rem}.odoo-card label{margin-bottom:4px}.odoo-card input,.odoo-card select{min-height:42px}@media(max-width:980px){.odoo-grid{grid-template-columns:1fr}}@media(max-width:640px){.odoo-form-grid,.odoo-meta{grid-template-columns:1fr}.odoo-form-grid .wide{grid-column:auto}.odoo-card{padding:12px}.odoo-actions .button,.odoo-actions button{flex:1 1 auto;min-height:44px}}
    </style>

    <div class="page-head">
        <div>
            <h2 style="margin:0;">Odoo → Nema ERP</h2>
            <div class="muted">Import complet ou incremental des produits, variantes, images, prix, taxes, fournisseurs et stocks. La reprise se fait au dernier curseur valide.</div>
        </div>
        <div class="odoo-actions">
            <a class="button button-secondary" href="{{ route('imports.index') }}">Retour aux imports</a>
            <a class="button button-primary" href="{{ route('imports.odoo.index', ['new' => 1]) }}">Nouvelle connexion</a>
        </div>
    </div>

    <div class="odoo-grid">
        <div class="odoo-stack">
            <section class="card odoo-card">
                <h2>{{ $editingConnection ? 'Configurer '.$editingConnection->name : 'Nouvelle connexion Odoo' }}</h2>
                <p class="muted odoo-secret">Le mot de passe ou la cle API est chiffre(e) en base et n'est jamais reaffiche(e).</p>
                <form method="POST" action="{{ route('imports.odoo.connections.save') }}">
                    @csrf
                    @if($editingConnection)<input type="hidden" name="connection_id" value="{{ $editingConnection->id }}">@endif
                    <div class="odoo-form-grid">
                        <div><label for="odoo_name">Nom</label><input id="odoo_name" name="name" required value="{{ old('name', $editingConnection?->name ?? 'Odoo principal') }}"></div>
                        <div><label for="odoo_protocol">Protocole</label><select id="odoo_protocol" name="protocol"><option value="jsonrpc" @selected(old('protocol', $editingConnection?->protocol ?? 'jsonrpc') === 'jsonrpc')>JSON-RPC</option><option value="xmlrpc" @selected(old('protocol', $editingConnection?->protocol) === 'xmlrpc')>XML-RPC</option></select></div>
                        <div class="wide"><label for="odoo_url">Adresse Odoo</label><input id="odoo_url" type="url" name="url" required placeholder="https://mon-odoo.com" value="{{ old('url', $editingConnection?->url) }}"></div>
                        <div><label for="odoo_database">Base de donnees</label><input id="odoo_database" name="database" required value="{{ old('database', $editingConnection?->database) }}"></div>
                        <div><label for="odoo_username">Utilisateur</label><input id="odoo_username" name="username" required autocomplete="username" value="{{ old('username', $editingConnection?->username) }}"></div>
                        <div><label for="odoo_secret">Mot de passe / cle API</label><input id="odoo_secret" type="password" name="secret" autocomplete="new-password" @required(!$editingConnection) placeholder="{{ $editingConnection ? 'Laisser vide pour conserver' : '' }}"></div>
                        <div><label for="odoo_batch">Taille des lots</label><input id="odoo_batch" type="number" name="batch_size" min="10" max="{{ config('odoo.max_batch_size', 1000) }}" required value="{{ old('batch_size', $editingConnection?->batch_size ?? config('odoo.batch_size', 250)) }}"></div>
                        <div class="wide"><label for="odoo_locations">Emplacements Odoo (facultatif)</label><input id="odoo_locations" name="stock_location_ids" placeholder="12, 18, 27" value="{{ old('stock_location_ids', $editingConnection?->stock_location_ids ? implode(', ', $editingConnection->stock_location_ids) : '') }}"></div>
                        <div class="wide odoo-checks">
                            <label><input type="hidden" name="verify_ssl" value="0"><input type="checkbox" name="verify_ssl" value="1" @checked(old('verify_ssl', $editingConnection?->verify_ssl ?? true))> SSL verifie</label>
                            <label><input type="hidden" name="import_images" value="0"><input type="checkbox" name="import_images" value="1" @checked(old('import_images', $editingConnection?->import_images ?? true))> Images</label>
                            <label><input type="hidden" name="import_stock" value="0"><input type="checkbox" name="import_stock" value="1" @checked(old('import_stock', $editingConnection?->import_stock ?? true))> Stocks</label>
                            <label><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editingConnection?->is_active ?? true))> Active</label>
                        </div>
                    </div>
                    <div class="actions" style="justify-content:flex-start;margin-top:12px;"><button class="button button-primary" type="submit">Enregistrer la connexion</button></div>
                </form>
            </section>

            @foreach($connections as $connection)
                <section class="card odoo-card">
                    <div class="odoo-run-head"><h3>{{ $connection->name }}</h3><span class="odoo-state {{ $connection->health_status }}">{{ $connection->health_status }}</span></div>
                    <div class="odoo-meta">
                        <div>Base<strong>{{ $connection->database }}</strong></div><div>Protocole<strong>{{ strtoupper($connection->protocol) }}</strong></div>
                        <div>Dernier test<strong>{{ $connection->last_tested_at?->format('d/m/Y H:i') ?? 'Jamais' }}</strong></div><div>Derniere synchro<strong>{{ $connection->last_sync_at?->format('d/m/Y H:i') ?? 'Jamais' }}</strong></div>
                    </div>
                    @if($connection->last_error)<div class="odoo-errors">{{ $connection->last_error }}</div>@endif
                    <div class="odoo-actions" style="margin-top:10px;">
                        <a class="button button-secondary" href="{{ route('imports.odoo.index', ['connection' => $connection->id]) }}">Modifier</a>
                        <form method="POST" action="{{ route('imports.odoo.connections.test', $connection) }}">@csrf<button class="button button-secondary" type="submit">Tester</button></form>
                        <form method="POST" action="{{ route('imports.odoo.connections.start', $connection) }}">@csrf<input type="hidden" name="mode" value="incremental"><button class="button button-primary" type="submit">Synchro incrementale</button></form>
                        <form method="POST" action="{{ route('imports.odoo.connections.start', $connection) }}" onsubmit="return confirm('Lancer une synchronisation complete de tous les produits Odoo ?')">@csrf<input type="hidden" name="mode" value="full"><button class="button button-secondary" type="submit">Synchro complete</button></form>
                    </div>
                </section>
            @endforeach
        </div>

        <section class="card odoo-card">
            <h2>Progression et journal</h2>
            <div class="odoo-note muted" style="margin-bottom:12px;">Les doublons sont controles par ID Odoo, SKU et code-barres. Les erreurs d'une fiche sont journalisees sans bloquer les autres produits.</div>
            <div class="odoo-stack" id="odoo-runs">
                @forelse($runs as $run)
                    <article class="odoo-run" data-status-url="{{ route('imports.odoo.runs.status', $run) }}" data-final="{{ in_array($run->status, ['completed','cancelled'], true) ? '1' : '0' }}">
                        <div class="odoo-run-head">
                            <div><strong>{{ $run->connection?->name }}</strong> <span class="muted">{{ $run->mode }} · {{ $run->phase }}</span></div>
                            <span class="odoo-state {{ $run->status }}" data-run-status>{{ $run->status }}</span>
                        </div>
                        <div class="odoo-counts"><span data-progress-label>{{ $run->processed_count }} / {{ $run->source_total ?: '?' }} ({{ $run->progressPercent() }}%)</span><span>Cree : <b data-created>{{ $run->created_count }}</b></span><span>Mis a jour : <b data-updated>{{ $run->updated_count }}</b></span><span>Ignores : <b data-skipped>{{ $run->skipped_count }}</b></span><span>Erreurs : <b data-failed>{{ $run->failed_count }}</b></span></div>
                        <div class="odoo-progress"><span data-progress-bar style="width:{{ $run->progressPercent() }}%"></span></div>
                        @if($run->last_error)<div class="odoo-errors" data-last-error>{{ $run->last_error }}</div>@else<div class="odoo-errors" data-last-error hidden></div>@endif
                        @if($run->errors_count)
                            <details style="margin-top:8px;"><summary>Voir le journal d'erreurs ({{ $run->errors_count }})</summary><div class="odoo-errors">@foreach($run->errors()->limit(8)->get() as $error)<div>#{{ $error->odoo_id ?? '-' }} · {{ $error->phase }} · {{ $error->message }}</div>@endforeach</div></details>
                        @endif
                        <div class="odoo-actions" style="margin-top:8px;">
                            @if(in_array($run->status, ['failed','queued','running'], true))<form method="POST" action="{{ route('imports.odoo.runs.resume', $run) }}">@csrf<button class="button button-secondary" type="submit">Reprendre</button></form>@endif
                            @if(!in_array($run->status, ['completed','cancelled'], true))<form method="POST" action="{{ route('imports.odoo.runs.cancel', $run) }}">@csrf<button class="button button-secondary" type="submit">Annuler</button></form>@endif
                        </div>
                    </article>
                @empty
                    <div class="muted">Aucune synchronisation lancee.</div>
                @endforelse
            </div>
        </section>
    </div>

    <script>
        (() => {
            const activeRuns = [...document.querySelectorAll('[data-status-url]')].filter(el => el.dataset.final !== '1');
            if (!activeRuns.length) return;
            const refresh = async (el) => {
                try {
                    const response = await fetch(el.dataset.statusUrl, {headers: {'Accept': 'application/json'}, credentials: 'same-origin'});
                    if (!response.ok) return;
                    const run = await response.json();
                    el.querySelector('[data-run-status]').textContent = run.status;
                    el.querySelector('[data-run-status]').className = 'odoo-state ' + run.status;
                    el.querySelector('[data-progress-label]').textContent = `${run.processed_count} / ${run.source_total || '?'} (${run.progress}%)`;
                    el.querySelector('[data-progress-bar]').style.width = run.progress + '%';
                    el.querySelector('[data-created]').textContent = run.created_count;
                    el.querySelector('[data-updated]').textContent = run.updated_count;
                    el.querySelector('[data-skipped]').textContent = run.skipped_count;
                    el.querySelector('[data-failed]').textContent = run.failed_count;
                    const error = el.querySelector('[data-last-error]');
                    error.textContent = run.last_error || '';
                    error.hidden = !run.last_error;
                    if (['completed', 'cancelled'].includes(run.status)) el.dataset.final = '1';
                } catch (_) {}
            };
            const timer = setInterval(async () => {
                const pending = activeRuns.filter(el => el.dataset.final !== '1');
                await Promise.all(pending.map(refresh));
                if (!pending.length) clearInterval(timer);
            }, 2500);
        })();
    </script>
@endsection
