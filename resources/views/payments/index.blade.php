@extends('layouts.app')

@section('title', 'Paiements - Nema ERP')
@section('page-title', 'Paiements')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Historique des paiements</h2>
            <div class="muted">Les encaissements clients, reglements fournisseurs et remboursements POS sont centralises ici.</div>
            @if ($scopeBranch)
                <div class="help" style="margin-top:8px;">Perimetre agence actif : <strong>{{ $scopeBranch->name }}</strong></div>
            @endif
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="{{ route('payments.export', request()->query()) }}" class="button button-secondary">Exporter CSV</a>
            @allowed('payments.validate')
                <a href="{{ route('payments.create', ['type' => 'customer_receipt']) }}" class="button button-primary">Nouvel encaissement</a>
                <a href="{{ route('payments.create', ['type' => 'supplier_payment']) }}" class="button button-secondary">Nouveau reglement</a>
            @endallowed
        </div>
    </div>

    <section class="card" style="margin-bottom:18px;">
        <form method="GET" action="{{ route('payments.index') }}" class="form-grid" style="align-items:end; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));">
            <div style="grid-column:span 2; min-width:220px;">
                <label for="search">Recherche</label>
                <input type="text" id="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Numero, reference, tiers, compte...">
            </div>
            <div>
                <label for="date_from">Date debut</label>
                <input type="date" id="date_from" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
            </div>
            <div>
                <label for="date_to">Date fin</label>
                <input type="date" id="date_to" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
            </div>
            <div>
                <label for="payment_type">Type</label>
                <select id="payment_type" name="payment_type">
                    <option value="">Tous les flux</option>
                    <option value="customer_receipt" @selected(($filters['payment_type'] ?? null) === 'customer_receipt')>Encaissements clients</option>
                    <option value="supplier_payment" @selected(($filters['payment_type'] ?? null) === 'supplier_payment')>Reglements fournisseurs</option>
                    <option value="pos_refund" @selected(($filters['payment_type'] ?? null) === 'pos_refund')>Remboursements POS</option>
                </select>
            </div>
            <div>
                <label for="method">Mode</label>
                <select id="method" name="method">
                    <option value="">Tous les modes</option>
                    @foreach ($methodOptions as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['method'] ?? null) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="cash_account_id">Compte</label>
                <select id="cash_account_id" name="cash_account_id">
                    <option value="">Tous les comptes</option>
                    @foreach ($cashAccounts as $cashAccount)
                        <option value="{{ $cashAccount->id }}" @selected((int) ($filters['cash_account_id'] ?? 0) === $cashAccount->id)>{{ $cashAccount->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="actions" style="margin-top:0; justify-content:flex-start; align-self:end;">
                <button type="submit" class="button button-primary">Filtrer</button>
                <a href="{{ route('payments.index') }}" class="button button-secondary">Reinitialiser</a>
            </div>
        </form>
    </section>

    <section class="card table-wrap">
        <table>
            <thead>
            <tr>
                <th>Numero</th>
                <th>Date</th>
                <th>Type</th>
                <th>Tiers</th>
                <th>Document</th>
                <th>Compte</th>
                <th>Mode</th>
                <th>Montant</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($payments as $payment)
                @php
                    $document = optional($payment->allocations->first())->allocatable;
                    $documentLabel = 'Non relie';
                    $documentUrl = null;

                    if ($document instanceof \App\Modules\Sales\Models\SalesInvoice) {
                        $documentLabel = $document->invoice_number;
                        $documentUrl = route('sales.show', $document);
                    } elseif ($document instanceof \App\Modules\Purchases\Models\PurchaseBill) {
                        $documentLabel = $document->bill_number;
                        $documentUrl = route('purchases.show', $document);
                    }

                    $paymentTypeLabel = match ($payment->payment_type) {
                        'supplier_payment' => 'Reglement fournisseur',
                        'pos_refund' => 'Remboursement POS',
                        default => 'Encaissement client',
                    };
                @endphp
                <tr>
                    <td>
                        <a href="{{ route('payments.show', $payment) }}" style="font-weight:700;">{{ $payment->payment_number }}</a>
                        @if ($payment->reference)
                            <div class="muted" style="font-size:13px;">Ref. {{ $payment->reference }}</div>
                        @endif
                    </td>
                    <td>{{ $payment->payment_date?->format('d/m/Y') }}</td>
                    <td>{{ $paymentTypeLabel }}</td>
                    <td>
                        <div>{{ $payment->partner?->name }}</div>
                        @if ($payment->partner?->code)
                            <div class="muted" style="font-size:13px;">{{ $payment->partner->code }}</div>
                        @endif
                    </td>
                    <td>
                        @if ($documentUrl)
                            <a href="{{ $documentUrl }}">{{ $documentLabel }}</a>
                        @else
                            <span class="muted">{{ $documentLabel }}</span>
                        @endif
                    </td>
                    <td>{{ $payment->cashAccount?->name }}</td>
                    <td>{{ $methodOptions[$payment->method] ?? str($payment->method)->replace('_', ' ')->title() }}</td>
                    <td>{{ number_format((float) $payment->amount, 0, ',', ' ') }} XOF</td>
                    <td><a href="{{ route('payments.show', $payment) }}">Voir</a></td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="muted">Aucun paiement ne correspond aux filtres selectionnes.</td>
                </tr>
            @endforelse
            </tbody>
        </table>

        @if (method_exists($payments, 'links'))
            <div style="margin-top:18px;">{{ $payments->links() }}</div>
        @endif
    </section>
@endsection
