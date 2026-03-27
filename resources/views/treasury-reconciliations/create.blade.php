@extends('layouts.app')

@section('title', 'Nouveau rapprochement - Nema ERP')
@section('page-title', 'Nouveau rapprochement')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Preparation du rapprochement</h2>
            <div class="muted">Choisir un compte banque ou mobile money, charger les mouvements non rapproches, puis enregistrer le releve.</div>
        </div>
        <a href="{{ route('treasury-reconciliations.index') }}" class="button button-secondary">Retour</a>
    </div>

    <section class="card" style="margin-bottom:20px;">
        <form method="GET" action="{{ route('treasury-reconciliations.create') }}" class="form-grid" style="grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); align-items:end;">
            <div>
                <label for="cash_account_id">Compte a rapprocher</label>
                <select id="cash_account_id" name="cash_account_id" required>
                    <option value="">Choisir</option>
                    @foreach ($cashAccounts as $cashAccount)
                        <option value="{{ $cashAccount->id }}" @selected((int) request('cash_account_id') === $cashAccount->id)>{{ $cashAccount->name }} · {{ $cashAccount->type === 'bank' ? 'Banque' : 'Mobile money' }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="statement_date">Date du releve</label>
                <input type="date" id="statement_date" name="statement_date" value="{{ $statementDate }}" required>
            </div>
            <div class="actions" style="margin-top:0; justify-content:flex-start; align-self:end;">
                <button type="submit" class="button button-primary">Charger les mouvements</button>
            </div>
        </form>
    </section>

    @if ($selectedCashAccount)
        <div class="grid stats-grid" style="margin-bottom:20px;">
            <div class="card"><div class="muted">Compte</div><div class="stat-value" style="font-size:24px;">{{ $selectedCashAccount->name }}</div></div>
            <div class="card"><div class="muted">Solde comptable</div><div class="stat-value">{{ number_format((float) $bookBalance, 0, ',', ' ') }}</div></div>
            <div class="card"><div class="muted">Mouvements candidats</div><div class="stat-value">{{ $candidates->count() }}</div></div>
            <div class="card"><div class="muted">Total non rapproche</div><div class="stat-value">{{ number_format((float) $candidateSignedTotal, 0, ',', ' ') }}</div></div>
        </div>

        <form method="POST" action="{{ route('treasury-reconciliations.store') }}" class="card">
            @csrf
            <input type="hidden" name="cash_account_id" value="{{ $selectedCashAccount->id }}">
            <input type="hidden" name="statement_date" value="{{ $statementDate }}">

            <div class="form-grid" style="margin-bottom:18px; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));">
                <div>
                    <label for="statement_reference">Reference releve</label>
                    <input id="statement_reference" name="statement_reference" value="{{ old('statement_reference') }}" placeholder="Releve mars 2026, export OM...">
                    @error('statement_reference')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="statement_balance">Solde du releve</label>
                    <input id="statement_balance" name="statement_balance" type="number" step="0.01" value="{{ old('statement_balance', $bookBalance) }}" required>
                    @error('statement_balance')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="full">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" placeholder="Commentaires sur l ecart, source du releve, controles faits...">{{ old('notes') }}</textarea>
                    @error('notes')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th></th>
                        <th>Numero</th>
                        <th>Date</th>
                        <th>Tiers</th>
                        <th>Type</th>
                        <th>Montant</th>
                        <th>Reference</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($candidates as $payment)
                        <tr>
                            <td>
                                <input type="checkbox" name="payment_ids[]" value="{{ $payment->id }}" @checked(in_array($payment->id, old('payment_ids', [])))>
                            </td>
                            <td>{{ $payment->payment_number }}</td>
                            <td>{{ $payment->payment_date?->format('d/m/Y') }}</td>
                            <td>{{ $payment->partner?->name ?: 'Sans tiers' }}</td>
                            <td>{{ $payment->payment_type === 'supplier_payment' ? 'Reglement fournisseur' : ($payment->payment_type === 'pos_refund' ? 'Remboursement POS' : 'Encaissement client') }}</td>
                            <td>{{ $payment->direction === 'out' ? '-' : '+' }}{{ number_format((float) $payment->amount, 0, ',', ' ') }} XOF</td>
                            <td>{{ $payment->reference ?: 'Sans reference' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="muted">Aucun mouvement non rapproche sur ce compte et cette date.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @error('payment_ids')<div class="field-error" style="margin-top:12px;">{{ $message }}</div>@enderror
            @error('payment_ids.*')<div class="field-error" style="margin-top:12px;">{{ $message }}</div>@enderror

            <div class="actions">
                <a href="{{ route('treasury-reconciliations.index') }}" class="button button-secondary">Annuler</a>
                <button type="submit" class="button button-primary" @disabled($candidates->isEmpty())>Enregistrer le rapprochement</button>
            </div>
        </form>
    @endif
@endsection
