@extends('layouts.app')

@section('title', 'Nouveau paiement - Nema ERP')
@section('page-title', 'Enregistrer un paiement')

@section('content')
    @php
        $paymentMethodOptions = $methodOptions ?? \App\Support\PaymentMethodCatalog::options();
        $prefill = $prefill ?? [];
        $scopeBranch = $scopeBranch ?? $branch ?? null;
        $scopeBranchLabel = $scopeBranchLabel ?? ($scopeBranch?->name ?? 'Agence non determinee');
    @endphp

    <form method="POST" action="{{ route('payments.store') }}">
        @csrf
        <div class="card">
            <div class="form-grid">
                <div class="full">
                    <div class="help">Agence active : <strong>{{ $branch?->name }}</strong></div>
                    <div class="help" style="margin-top:8px;">Perimetre de validation : <strong>{{ $scopeBranchLabel }}</strong> · Plafond profil : <strong>{{ $validationLimitLabel ?? 'Illimite' }}</strong></div>
                    @if ($scopeBranch && $branch && $scopeBranch->id !== $branch->id)
                        <div class="notice" style="margin-top:12px;">
                            <strong>Perimetre adapte au document</strong>
                            <div class="muted" style="margin-top:8px;">Le paiement sera rattache a {{ $scopeBranch->name }} pour rester coherent avec le document et le compte de tresorerie selectionnes.</div>
                        </div>
                    @endif
                    @if (($prefill['source'] ?? null) === 'portal_payment_notice')
                        <div class="notice" style="margin-top:12px;">
                            <strong>Pre-remplissage depuis le portail client</strong>
                            <div class="muted" style="margin-top:8px;">Le client a annonce un reglement via le lien de paiement. Verifie le compte de tresorerie reel puis enregistre l encaissement.</div>
                        </div>
                    @elseif (($prefill['source'] ?? null) === 'gateway_callback')
                        <div class="notice" style="margin-top:12px;">
                            <strong>Pre-remplissage depuis un callback paiement terrain</strong>
                            <div class="muted" style="margin-top:8px;">Un retour automatique du prestataire a ete recu. Verifie le compte de tresorerie reel puis confirme l encaissement.</div>
                        </div>
                    @endif
                </div>
                <div>
                    <label for="payment_type">Type de paiement</label>
                    <select id="payment_type" name="payment_type" required>
                        <option value="customer_receipt" @selected(old('payment_type', $paymentType) === 'customer_receipt')>Encaissement client</option>
                        <option value="supplier_payment" @selected(old('payment_type', $paymentType) === 'supplier_payment')>Reglement fournisseur</option>
                        <option value="internal_transfer" @selected(old('payment_type', $paymentType) === 'internal_transfer')>Versement interne</option>
                    </select>
                </div>
                <div id="invoice-field">
                    <label for="invoice_id">Facture client</label>
                    <select id="invoice_id" name="invoice_id">
                        <option value="">Selectionner une facture</option>
                        @foreach ($invoices as $invoice)
                            <option value="{{ $invoice->id }}" data-balance="{{ $invoice->balance_due }}" data-partner="{{ $invoice->customer?->name }}" @selected((string) old('invoice_id', $invoiceId) === (string) $invoice->id)>
                                {{ $invoice->invoice_number }} - {{ $invoice->customer?->name }} (reste {{ number_format((float) $invoice->balance_due, 0, ',', ' ') }} XOF)
                            </option>
                        @endforeach
                    </select>
                </div>
                <div id="purchase-bill-field">
                    <label for="purchase_bill_id">Facture fournisseur</label>
                    <select id="purchase_bill_id" name="purchase_bill_id">
                        <option value="">Selectionner une facture</option>
                        @foreach ($purchaseBills as $bill)
                            <option value="{{ $bill->id }}" data-balance="{{ $bill->balance_due }}" data-partner="{{ $bill->supplier?->name }}" @selected((string) old('purchase_bill_id', $purchaseBillId) === (string) $bill->id)>
                                {{ $bill->bill_number }} - {{ $bill->supplier?->name }} (reste {{ number_format((float) $bill->balance_due, 0, ',', ' ') }} XOF)
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="cash_account_id" id="cash-account-label">Compte de tresorerie</label>
                    <select id="cash_account_id" name="cash_account_id" required>
                        <option value="">Choisir un compte</option>
                        @foreach ($cashAccounts as $account)
                            <option value="{{ $account->id }}" @selected((string) old('cash_account_id', $prefill['cash_account_id'] ?? null) === (string) $account->id)>{{ $account->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div id="destination-account-field">
                    <label for="destination_cash_account_id">Compte destination</label>
                    <select id="destination_cash_account_id" name="destination_cash_account_id">
                        <option value="">Choisir un compte</option>
                        @foreach ($cashAccounts as $account)
                            <option value="{{ $account->id }}" @selected((string) old('destination_cash_account_id', $prefill['destination_cash_account_id'] ?? null) === (string) $account->id)>{{ $account->name }}</option>
                        @endforeach
                    </select>
                    <div class="help">Utilise ce flux pour tracer un versement agence vers banque, caisse centrale ou autre compte interne.</div>
                </div>
                <div class="full">
                    <div id="document-balance-help" class="help"></div>
                </div>
                <div>
                    <label for="payment_date">Date du paiement</label>
                    <input id="payment_date" type="date" name="payment_date" value="{{ old('payment_date', $prefill['payment_date'] ?? now()->format('Y-m-d')) }}" required>
                </div>
                <div>
                    <label for="amount">Montant</label>
                    <input id="amount" type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount', $prefill['amount'] ?? null) }}" required>
                </div>
                <div>
                    <label for="method">Mode de paiement</label>
                    <select id="method" name="method" required>
                        @foreach ($paymentMethodOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('method', $prefill['method'] ?? null) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <div class="help">Les wallets dedies comme Wave et Moov Money sont suivis distinctement, avec controle agence et plafond de validation.</div>
                </div>
                <div>
                    <label for="reference">Reference</label>
                    <input id="reference" type="text" name="reference" value="{{ old('reference', $prefill['reference'] ?? null) }}">
                </div>
                <div class="full">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes">{{ old('notes', $prefill['notes'] ?? null) }}</textarea>
                </div>
            </div>
            <div class="actions">
                <a href="{{ route('payments.index') }}" class="button button-secondary">Annuler</a>
                <button type="submit" class="button button-primary">Enregistrer le paiement</button>
            </div>
        </div>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const paymentTypeSelect = document.getElementById('payment_type');
            const invoiceField = document.getElementById('invoice-field');
            const purchaseBillField = document.getElementById('purchase-bill-field');
            const invoiceSelect = document.getElementById('invoice_id');
            const purchaseBillSelect = document.getElementById('purchase_bill_id');
            const destinationAccountField = document.getElementById('destination-account-field');
            const destinationAccountSelect = document.getElementById('destination_cash_account_id');
            const cashAccountLabel = document.getElementById('cash-account-label');
            const help = document.getElementById('document-balance-help');
            const amountInput = document.getElementById('amount');

            const currentSelect = () => {
                if (paymentTypeSelect.value === 'supplier_payment') {
                    return purchaseBillSelect;
                }

                if (paymentTypeSelect.value === 'customer_receipt') {
                    return invoiceSelect;
                }

                return null;
            };

            const updateHelp = () => {
                const select = currentSelect();
                if (!select) {
                    help.textContent = 'Le versement interne cree un mouvement sortant et un mouvement entrant relies entre eux.';
                    return;
                }

                const option = select.options[select.selectedIndex];

                if (!option || !option.value) {
                    help.textContent = '';
                    return;
                }

                const balance = parseFloat(option.dataset.balance || '0');
                const partner = option.dataset.partner || '';
                help.textContent = 'Solde restant : ' + balance.toFixed(2) + ' XOF' + (partner ? ' · Tiers : ' + partner : '');

                if (!amountInput.value) {
                    amountInput.value = balance.toFixed(2);
                }
            };

            const syncFields = () => {
                const isSupplierPayment = paymentTypeSelect.value === 'supplier_payment';
                const isInternalTransfer = paymentTypeSelect.value === 'internal_transfer';

                invoiceField.style.display = isSupplierPayment || isInternalTransfer ? 'none' : '';
                purchaseBillField.style.display = isSupplierPayment && !isInternalTransfer ? '' : 'none';
                destinationAccountField.style.display = isInternalTransfer ? '' : 'none';
                invoiceSelect.required = !isSupplierPayment && !isInternalTransfer;
                purchaseBillSelect.required = isSupplierPayment && !isInternalTransfer;
                destinationAccountSelect.required = isInternalTransfer;
                cashAccountLabel.textContent = isInternalTransfer ? 'Compte source' : 'Compte de tresorerie';

                updateHelp();
            };

            paymentTypeSelect.addEventListener('change', syncFields);
            invoiceSelect.addEventListener('change', updateHelp);
            purchaseBillSelect.addEventListener('change', updateHelp);

            syncFields();
        });
    </script>
@endsection

