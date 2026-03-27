@extends('layouts.app')

@section('title', 'Nouvelle depense - Nema ERP')
@section('page-title', 'Nouvelle depense')

@section('content')
    @php($paymentMethodOptions = $paymentMethodOptions ?? \App\Support\PaymentMethodCatalog::options())

    <form method="POST" action="{{ route('expenses.store') }}">
        @csrf

        <div class="split">
            <section class="card">
                <h2 class="section-title">Saisie de la depense</h2>
                <div class="muted" style="margin-bottom:16px;">Renseigne la charge, puis indique si elle est deja reglee ou si elle doit rester en attente de paiement.</div>

                <div class="form-grid">
                    <div class="full">
                        <div class="help">Agence active : <strong>{{ $branch?->name }}</strong></div>
                    </div>
                    <div>
                        <label for="expense_category_id">Categorie de depense</label>
                        <select id="expense_category_id" name="expense_category_id" required>
                            <option value="">Selectionner une categorie</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected((string) old('expense_category_id') === (string) $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                        @error('expense_category_id')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label for="supplier_id">Fournisseur</label>
                        <select id="supplier_id" name="supplier_id">
                            <option value="">Aucun fournisseur</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected((string) old('supplier_id') === (string) $supplier->id)>{{ $supplier->code }} - {{ $supplier->name }}</option>
                            @endforeach
                        </select>
                        @error('supplier_id')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label for="expense_date">Date</label>
                        <input id="expense_date" type="date" name="expense_date" value="{{ old('expense_date', now()->format('Y-m-d')) }}" required>
                        <div class="chip-row">
                            <button type="button" class="chip" data-expense-date="today">Aujourd'hui</button>
                        </div>
                        @error('expense_date')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label for="total">Montant</label>
                        <input id="total" type="number" step="0.01" min="0.01" name="total" value="{{ old('total') }}" required inputmode="decimal">
                        @error('total')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="full">
                        <label for="description">Description</label>
                        <input id="description" type="text" name="description" value="{{ old('description') }}" placeholder="Exemple : Achat de carburant, loyer, maintenance, internet..." required>
                        @error('description')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label for="cash_account_id">Compte de tresorerie</label>
                        <select id="cash_account_id" name="cash_account_id">
                            <option value="">Depense non reglee pour l'instant</option>
                            @foreach ($cashAccounts as $account)
                                <option value="{{ $account->id }}" @selected((string) old('cash_account_id') === (string) $account->id)>{{ $account->name }}</option>
                            @endforeach
                        </select>
                        <div class="help" id="expense-payment-help">Laisse vide si la depense doit rester a regler plus tard.</div>
                        @error('cash_account_id')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label for="payment_date">Date de paiement</label>
                        <input id="payment_date" type="date" name="payment_date" value="{{ old('payment_date', now()->format('Y-m-d')) }}">
                        @error('payment_date')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label for="payment_method">Mode de paiement</label>
                        <select id="payment_method" name="payment_method">
                            <option value="">Aucun</option>
                            @foreach ($paymentMethodOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('payment_method') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <div class="help">Wave et Moov Money sont suivis separement des autres wallets.</div>
                        @error('payment_method')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label for="payment_reference">Reference de paiement</label>
                        <input id="payment_reference" type="text" name="payment_reference" value="{{ old('payment_reference') }}" placeholder="Reference cheque, numero transaction, mobile money...">
                        @error('payment_reference')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="full">
                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes" placeholder="Commentaire interne ou precision comptable...">{{ old('notes') }}</textarea>
                        @error('notes')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                </div>
            </section>

            <aside class="card">
                <h2 class="section-title">Resume de la depense</h2>
                <div class="summary-stack">
                    <div class="summary-box">
                        <div class="muted">Montant saisi</div>
                        <div class="value" id="expense-summary-total">0 XOF</div>
                    </div>
                    <div class="kpi-row">
                        <div class="kpi">
                            <div class="label">Statut de paiement</div>
                            <div class="value" id="expense-summary-payment" style="font-size:20px;">Non reglee</div>
                        </div>
                        <div class="kpi">
                            <div class="label">Workflow</div>
                            <div class="value" id="expense-summary-workflow" style="font-size:20px;">Soumission</div>
                        </div>
                    </div>
                    <div class="tip-grid">
                        <div class="tip-card">
                            <strong>Impact a l'approbation finale</strong>
                            <div class="muted">La charge est comptabilisee. Si un compte est choisi, la tresorerie est aussi debitee.</div>
                        </div>
                        <div class="tip-card" id="expense-payment-panel">
                            <strong>Conseil operateur</strong>
                            <div class="muted">Choisis un compte seulement si la depense est deja reglee ou si elle doit etre decaissee immediatement apres approbation.</div>
                        </div>
                    </div>
                </div>
            </aside>
        </div>

        <div class="actions">
            <a href="{{ route('expenses.index') }}" class="button button-secondary">Annuler</a>
            <button type="submit" class="button button-primary">Enregistrer la depense</button>
        </div>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const totalInput = document.getElementById('total');
            const expenseDateInput = document.getElementById('expense_date');
            const cashAccountSelect = document.getElementById('cash_account_id');
            const paymentDateInput = document.getElementById('payment_date');
            const paymentMethodSelect = document.getElementById('payment_method');
            const paymentReferenceInput = document.getElementById('payment_reference');
            const paymentHelp = document.getElementById('expense-payment-help');
            const paymentPanel = document.getElementById('expense-payment-panel');
            const summaryTotal = document.getElementById('expense-summary-total');
            const summaryPayment = document.getElementById('expense-summary-payment');
            const summaryWorkflow = document.getElementById('expense-summary-workflow');
            const todayButton = document.querySelector('[data-expense-date="today"]');
            const moneyFormatter = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 });

            const formatMoney = (value) => moneyFormatter.format(value || 0) + ' XOF';

            const syncSummary = () => {
                summaryTotal.textContent = formatMoney(parseFloat(totalInput.value || '0'));

                if (cashAccountSelect.value) {
                    const accountLabel = cashAccountSelect.options[cashAccountSelect.selectedIndex]?.text || 'Compte choisi';
                    summaryPayment.textContent = 'A regler via ' + accountLabel;
                    summaryWorkflow.textContent = 'Soumise puis decaissee';
                    paymentHelp.textContent = 'La sortie de tresorerie sera enregistree a l'approbation finale.';
                    paymentPanel.style.opacity = '1';
                    paymentDateInput.disabled = false;
                    paymentMethodSelect.disabled = false;
                    paymentReferenceInput.disabled = false;

                    if (!paymentDateInput.value) {
                        paymentDateInput.value = expenseDateInput.value;
                    }
                } else {
                    summaryPayment.textContent = 'Non reglee';
                    summaryWorkflow.textContent = 'Soumise en attente';
                    paymentHelp.textContent = 'Laisse vide si la depense doit rester a regler plus tard.';
                    paymentPanel.style.opacity = '0.85';
                    paymentDateInput.disabled = true;
                    paymentMethodSelect.disabled = true;
                    paymentReferenceInput.disabled = true;
                }
            };

            totalInput.addEventListener('input', syncSummary);
            cashAccountSelect.addEventListener('change', syncSummary);
            expenseDateInput.addEventListener('change', function () {
                if (!paymentDateInput.value || paymentDateInput.disabled) {
                    paymentDateInput.value = expenseDateInput.value;
                }
            });

            if (todayButton) {
                todayButton.addEventListener('click', function () {
                    const today = new Date().toISOString().slice(0, 10);
                    expenseDateInput.value = today;
                    if (!cashAccountSelect.value) {
                        paymentDateInput.value = today;
                    }
                });
            }

            syncSummary();
        });
    </script>
@endsection
