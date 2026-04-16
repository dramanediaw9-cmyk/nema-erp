@extends('layouts.app')

@section('title', 'Tarification POS - Nema ERP')
@section('page-title', 'Tarification POS')

@section('content')
    <div class="grid" style="gap:18px;">
        @include('pos.partials.backoffice-nav')

        <div class="page-head">
            <div>
                <h2 style="margin:0;">Listes de prix, fidelite et cartes valeur</h2>
                <div class="muted">Structure tarifaire POS avec appui sur les listes de prix deja presentes dans le noyau Nema.</div>
            </div>
            <a href="{{ route('settings.index') }}" class="button button-secondary">Parametres entreprise</a>
        </div>

        <div class="grid stats-grid">
            <div class="card"><div class="muted">Listes de prix</div><div class="stat-value">{{ $data['summary']['price_lists'] }}</div></div>
            <div class="card"><div class="muted">Fidelite</div><div class="stat-value">{{ $data['summary']['loyalty_programs'] }}</div></div>
            <div class="card"><div class="muted">Cartes-cadeaux</div><div class="stat-value">{{ $data['summary']['gift_cards'] }}</div></div>
            <div class="card"><div class="muted">E-wallets</div><div class="stat-value">{{ $data['summary']['e_wallets'] }}</div></div>
            <div class="card"><div class="muted">Valeur embarquee</div><div class="stat-value">{{ number_format($data['summary']['stored_value_balance'], 0, ',', ' ') }} XOF</div></div>
        </div>

        @allowed('pos.manage')
            <div class="split">
                <form method="POST" action="{{ route('pos.loyalty-programs.store') }}" class="card form-grid">
                    @csrf
                    <div class="full"><h3 class="section-title">Remise & Fidelite</h3></div>
                    <div>
                        <label for="loyalty_name">Nom</label>
                        <input id="loyalty_name" name="name" value="{{ old('name') }}" required>
                    </div>
                    <div>
                        <label for="program_type">Type</label>
                        <select id="program_type" name="program_type" required>
                            <option value="discount">Remise</option>
                            <option value="points">Points</option>
                            <option value="stamp">Tampon</option>
                        </select>
                    </div>
                    <div>
                        <label for="trigger_mode">Declencheur</label>
                        <select id="trigger_mode" name="trigger_mode" required>
                            <option value="ticket_total">Montant ticket</option>
                            <option value="product_qty">Quantite produit</option>
                            <option value="combo">Combo</option>
                        </select>
                    </div>
                    <div>
                        <label for="reward_unit">Recompense</label>
                        <select id="reward_unit" name="reward_unit" required>
                            <option value="percent">Pourcentage</option>
                            <option value="fixed">Montant fixe</option>
                            <option value="points">Points</option>
                            <option value="gift">Cadeau</option>
                        </select>
                    </div>
                    <div>
                        <label for="reward_value">Valeur</label>
                        <input id="reward_value" name="reward_value" type="number" min="0" step="0.01" value="{{ old('reward_value', 5) }}" required>
                    </div>
                    <div>
                        <label for="min_ticket_total">Seuil ticket</label>
                        <input id="min_ticket_total" name="min_ticket_total" type="number" min="0" step="0.01" value="{{ old('min_ticket_total', 10000) }}">
                    </div>
                    <div class="full actions">
                        <button type="submit" class="button button-primary">Enregistrer le programme</button>
                    </div>
                </form>

                <form method="POST" action="{{ route('pos.stored-value-cards.store') }}" class="card form-grid">
                    @csrf
                    <div class="full"><h3 class="section-title">Carte-cadeau & e-wallet</h3></div>
                    <div>
                        <label for="card_type">Type</label>
                        <select id="card_type" name="card_type" required>
                            <option value="gift_card">Carte-cadeau</option>
                            <option value="e_wallet">E-wallet</option>
                        </select>
                    </div>
                    <div>
                        <label for="partner_id">Client</label>
                        <select id="partner_id" name="partner_id">
                            <option value="">Aucun</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="holder_name">Titulaire</label>
                        <input id="holder_name" name="holder_name" value="{{ old('holder_name') }}">
                    </div>
                    <div>
                        <label for="balance">Solde initial</label>
                        <input id="balance" name="balance" type="number" min="0" step="0.01" value="{{ old('balance', 0) }}" required>
                    </div>
                    <div>
                        <label for="status">Statut</label>
                        <select id="status" name="status" required>
                            <option value="active">Actif</option>
                            <option value="draft">Brouillon</option>
                            <option value="blocked">Bloque</option>
                            <option value="redeemed">Consomme</option>
                        </select>
                    </div>
                    <div>
                        <label for="expires_at">Expiration</label>
                        <input id="expires_at" name="expires_at" type="date" value="{{ old('expires_at') }}">
                    </div>
                    <div class="full actions">
                        <button type="submit" class="button button-primary">Enregistrer la carte</button>
                    </div>
                </form>
            </div>
        @endallowed

        <div class="split">
            <section class="card">
                <h3 class="section-title">Listes de prix disponibles</h3>
                <div class="summary-stack">
                    @forelse ($data['price_lists'] as $priceList)
                        <div class="summary-box">
                            <strong>{{ $priceList->name }}</strong>
                            <div class="muted" style="margin-top:8px;">{{ $priceList->items->count() }} regle(s) tarifaire(s)</div>
                            <div class="help" style="margin-top:8px;">{{ $priceList->is_default ? 'Tarif par defaut' : 'Tarif secondaire' }}</div>
                        </div>
                    @empty
                        <div class="muted">Aucune liste de prix definie.</div>
                    @endforelse
                </div>
            </section>

            <section class="card">
                <h3 class="section-title">Programmes et cartes</h3>
                <div class="summary-stack">
                    @foreach ($data['loyalty_programs'] as $program)
                        <div class="summary-box">
                            <strong>{{ $program->name }}</strong>
                            <div class="help" style="margin-top:8px;">{{ strtoupper($program->program_type) }} · {{ number_format((float) $program->reward_value, 0, ',', ' ') }} {{ $program->reward_unit }}</div>
                        </div>
                    @endforeach
                    @foreach ($data['stored_value_cards'] as $card)
                        <div class="summary-box">
                            <strong>{{ $card->code }}</strong>
                            <div class="muted" style="margin-top:8px;">{{ $card->holder_name ?: ($card->partner?->name ?? 'Sans titulaire') }}</div>
                            <div class="help" style="margin-top:8px;">{{ $card->card_type === 'gift_card' ? 'Carte-cadeau' : 'E-wallet' }} · {{ number_format((float) $card->balance, 0, ',', ' ') }} XOF</div>
                        </div>
                    @endforeach
                    @if ($data['loyalty_programs']->isEmpty() && $data['stored_value_cards']->isEmpty())
                        <div class="muted">Aucun programme ni carte valeur configure.</div>
                    @endif
                </div>
            </section>
        </div>
    </div>
@endsection
