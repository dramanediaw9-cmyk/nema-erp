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
            <div class="card">
                <div class="muted">Depots documentes</div>
                <div class="stat-value">{{ number_format((float) ($candidateInsights['documented_amount'] ?? 0), 0, ',', ' ') }}</div>
                <div class="muted">{{ $candidateInsights['documented_count'] ?? 0 }} candidat(s) prets a rapprocher</div>
            </div>
            <div class="card">
                <div class="muted">Depots a verifier</div>
                <div class="stat-value">{{ $candidateInsights['missing_proof_count'] ?? 0 }}</div>
                <div class="muted">{{ number_format((float) ($candidateInsights['missing_proof_amount'] ?? 0), 0, ',', ' ') }} XOF sans preuve exploitable</div>
            </div>
        </div>

        <form method="POST" action="{{ route('treasury-reconciliations.store') }}" class="card">
            @csrf
            <input type="hidden" name="cash_account_id" value="{{ $selectedCashAccount->id }}">
            <input type="hidden" name="statement_date" value="{{ $statementDate }}">

            @if (($candidateInsights['documented_count'] ?? 0) > 0 || ($candidateInsights['missing_proof_count'] ?? 0) > 0)
                <div class="card" style="margin-bottom:18px; background:#fff8e6; border:1px solid rgba(166, 118, 12, 0.22); box-shadow:none;">
                    <div style="font-weight:700; margin-bottom:6px;">Lecture terrain du rapprochement</div>
                    <div class="muted">
                        @if (($candidateInsights['documented_count'] ?? 0) > 0)
                            {{ $candidateInsights['documented_count'] }} versement(s) agence disposent deja d une reference ou d un justificatif et sont affiches en tete de liste.
                        @endif
                        @if (($candidateInsights['missing_proof_count'] ?? 0) > 0)
                            {{ ($candidateInsights['documented_count'] ?? 0) > 0 ? ' ' : '' }}{{ $candidateInsights['missing_proof_count'] }} depot(s) restent a verifier avant rapprochement.
                        @endif
                    </div>
                </div>
            @endif

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
                        <th>Priorite</th>
                        <th>Reference</th>
                        <th>Preuve</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($candidates as $payment)
                        @php $indicator = $candidateIndicators[$payment->id] ?? ['is_documented_deposit' => false, 'needs_proof_attention' => false, 'has_reference' => false, 'has_attachment' => false]; @endphp
                        <tr>
                            <td>
                                <input type="checkbox" name="payment_ids[]" value="{{ $payment->id }}" @checked(in_array($payment->id, old('payment_ids', [])))>
                            </td>
                            <td>{{ $payment->payment_number }}</td>
                            <td>{{ $payment->payment_date?->format('d/m/Y') }}</td>
                            <td>{{ $payment->partner?->name ?: 'Sans tiers' }}</td>
                            <td>{{ $payment->payment_type === 'supplier_payment' ? 'Reglement fournisseur' : ($payment->payment_type === 'pos_refund' ? 'Remboursement POS' : ($payment->payment_type === 'internal_transfer' ? ($payment->direction === 'in' ? 'Reception de versement' : 'Versement interne') : 'Encaissement client')) }}</td>
                            <td>{{ $payment->direction === 'out' ? '-' : '+' }}{{ number_format((float) $payment->amount, 0, ',', ' ') }} XOF</td>
                            <td>
                                @if ($indicator['is_documented_deposit'])
                                    <span class="badge badge-success">Pret a rapprocher</span>
                                @elseif ($indicator['needs_proof_attention'])
                                    <span class="badge badge-warning">A verifier</span>
                                @else
                                    <span class="muted">Standard</span>
                                @endif
                            </td>
                            <td>{{ $payment->reference ?: 'Sans reference' }}</td>
                            <td>
                                @if ($indicator['has_reference'])
                                    <span class="badge badge-info">Reference depot</span>
                                @endif
                                @if ($indicator['has_attachment'])
                                    <span class="badge badge-success">Bordereau joint</span>
                                @endif
                                @if (! $indicator['has_reference'] && ! $indicator['has_attachment'])
                                    <span class="muted">Aucune preuve jointe</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="muted">Aucun mouvement non rapproche sur ce compte et cette date.</td>
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
