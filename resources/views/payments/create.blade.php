@extends('layouts.app')

@section('title', 'Nouveau paiement - Nema ERP')
@section('page-title', 'Enregistrer un paiement')

@section('content')
    @php($paymentMethodOptions = $methodOptions ?? \App\Support\PaymentMethodCatalog::options())

    <form method="POST" action="{{ route('payments.store') }}">
        @csrf
        <div class="card">
            <div class="form-grid">
                <div class="full">
                    <div class="help">Agence active : <strong>{{ $branch?->name }}</strong></div>
                </div>
                <div>
                    <label for="payment_type">Type de paiement</label>
                    <select id="payment_type" name="payment_type" required>
                        <option value="customer_receipt" @selected(old('payment_type', $paymentType) === 'customer_receipt')>Encaissement client</option>
                        <option value="supplier_payment" @selected(old('payment_type', $paymentType) === 'supplier_payment')>Reglement fournisseur</option>
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
                    <label for="cash_account_id">Compte de tresorerie</label>
                    <select id="cash_account_id" name="cash_account_id" required>
                        <option value="">Choisir un compte</option>
                        @foreach ($cashAccounts as $account)
                            <option value="{{ $account->id }}" @selected((string) old('cash_account_id') === (string) $account->id)>{{ $account->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="full">
                    <div id="document-balance-help" class="help"></div>
                </div>
                <div>
                    <label for="payment_date">Date du paiement</label>
                    <input id="payment_date" type="date" name="payment_date" value="{{ old('payment_date', now()->format('Y-m-d')) }}" required>
                </div>
                <div>
                    <label for="amount">Montant</label>
                    <input id="amount" type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" required>
                </div>
                <div>
                    <label for="method">Mode de paiement</label>
                    <select id="method" name="method" required>
                        @foreach ($paymentMethodOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('method') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <div class="help">Les wallets dedies comme Wave et Moov Money sont maintenant suivis distinctement.</div>
                </div>
                <div>
                    <label for="reference">Reference</label>
                    <input id="reference" type="text" name="reference" value="{{ old('reference') }}">
                </div>
                <div class="full">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes">{{ old('notes') }}</textarea>
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
            const help = document.getElementById('document-balance-help');
            const amountInput = document.getElementById('amount');

            const currentSelect = () => paymentTypeSelect.value === 'supplier_payment' ? purchaseBillSelect : invoiceSelect;

            const updateHelp = () => {
                const select = currentSelect();
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

                invoiceField.style.display = isSupplierPayment ? 'none' : '';
                purchaseBillField.style.display = isSupplierPayment ? '' : 'none';
                invoiceSelect.required = !isSupplierPayment;
                purchaseBillSelect.required = isSupplierPayment;

                updateHelp();
            };

            paymentTypeSelect.addEventListener('change', syncFields);
            invoiceSelect.addEventListener('change', updateHelp);
            purchaseBillSelect.addEventListener('change', updateHelp);

            syncFields();
        });
    </script>
@endsection
