<?php

namespace App\Modules\Treasury\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Treasury\Models\CashAccount;
use App\Modules\Treasury\Models\Payment;
use App\Modules\Treasury\Models\PaymentAllocation;
use App\Modules\Treasury\Services\PaymentControlService;
use App\Modules\Treasury\Services\PaymentService;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use App\Support\Exports\CsvExportService;
use App\Support\PaymentMethodCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly PaymentControlService $paymentControlService,
        private readonly ActivityLogger $activityLogger,
        private readonly CsvExportService $csvExportService,
    ) {
    }

    public function index(Request $request, CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        $user = $request->user();
        abort_if(! $companyId || ! $user, 403);

        $filters = $this->filters($request);
        $branchScopeId = $this->paymentControlService->listingBranchScope($user, $workspace->branchId());

        return view('payments.index', [
            'payments' => $this->filteredQuery($companyId, $filters, $branchScopeId)
                ->latest('payment_date')
                ->latest('id')
                ->paginate(15)
                ->withQueryString(),
            'filters' => $filters,
            'cashAccounts' => CashAccount::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->when($branchScopeId, function (Builder $query, int $selectedBranchId) {
                    $query->where(function (Builder $nested) use ($selectedBranchId) {
                        $nested->whereNull('branch_id')
                            ->orWhere('branch_id', $selectedBranchId);
                    });
                })
                ->orderBy('name')
                ->get(['id', 'name']),
            'methodOptions' => $this->methodOptions(),
            'scopeBranch' => $branchScopeId ? Branch::query()->where('company_id', $companyId)->find($branchScopeId) : null,
        ]);
    }

    public function show(Request $request, Payment $payment, CurrentWorkspace $workspace): View
    {
        abort_if($workspace->companyId() !== $payment->company_id, 403);

        $user = $request->user();
        abort_if(! $user, 403);
        $this->paymentControlService->assertCanViewPayment($user, $payment, $workspace->branchId());

        $payment->load([
            'company',
            'branch',
            'cashAccount',
            'partner',
            'creator',
            'allocations.allocatable',
            'posSession',
        ]);

        return view('payments.show', [
            'payment' => $payment,
            'linkedDocuments' => $payment->allocations
                ->map(fn (PaymentAllocation $allocation) => $this->allocationContext($allocation))
                ->filter()
                ->values(),
            'journalEntries' => JournalEntry::query()
                ->with(['creator'])
                ->where('company_id', $payment->company_id)
                ->where('source_type', Payment::class)
                ->where('source_id', $payment->id)
                ->orderBy('entry_date')
                ->get(),
            'methodOptions' => $this->methodOptions(),
            'paymentTypeLabel' => $this->paymentTypeLabel($payment->payment_type),
        ]);
    }

    public function export(Request $request, CurrentWorkspace $workspace): StreamedResponse
    {
        $companyId = $workspace->companyId();
        $user = $request->user();
        abort_if(! $companyId || ! $user, 403);

        $filters = $this->filters($request);
        $branchScopeId = $this->paymentControlService->listingBranchScope($user, $workspace->branchId());

        $rows = $this->filteredQuery($companyId, $filters, $branchScopeId)
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get()
            ->map(function (Payment $payment) {
                $document = optional($payment->allocations->first())->allocatable;
                $documentLabel = null;

                if ($document instanceof SalesInvoice) {
                    $documentLabel = $document->invoice_number;
                } elseif ($document instanceof PurchaseBill) {
                    $documentLabel = $document->bill_number;
                }

                return [
                    $payment->payment_number,
                    $payment->payment_date?->format('d/m/Y'),
                    $this->paymentTypeLabel($payment->payment_type),
                    $payment->partner?->name,
                    $documentLabel,
                    $payment->cashAccount?->name,
                    $this->methodOptions()[$payment->method] ?? $payment->method,
                    number_format((float) $payment->amount, 2, '.', ''),
                ];
            });

        return $this->csvExportService->download('paiements.csv', [
            'Numero', 'Date', 'Type', 'Tiers', 'Document', 'Compte', 'Mode', 'Montant',
        ], $rows);
    }

    public function create(Request $request, CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        $user = $request->user();
        abort_if(! $companyId || ! $workspace->branchId() || ! $user, 403);

        $paymentType = $request->string('type')->value() ?: 'customer_receipt';
        if (! in_array($paymentType, ['customer_receipt', 'supplier_payment'], true)) {
            $paymentType = 'customer_receipt';
        }

        $selectedInvoice = $request->integer('invoice')
            ? SalesInvoice::query()->where('company_id', $companyId)->findOrFail($request->integer('invoice'))
            : null;
        $selectedPurchaseBill = $request->integer('purchase_bill')
            ? PurchaseBill::query()->where('company_id', $companyId)->findOrFail($request->integer('purchase_bill'))
            : null;

        $branchScopeId = $this->paymentControlService->resolveCreateScopeBranch(
            $user,
            $workspace->branchId(),
            $selectedInvoice,
            $selectedPurchaseBill,
        ) ?? $workspace->branchId();

        return view('payments.create', [
            'paymentType' => $paymentType,
            'invoiceId' => $selectedInvoice?->id,
            'purchaseBillId' => $selectedPurchaseBill?->id,
            'invoices' => SalesInvoice::query()
                ->with('customer')
                ->where('company_id', $companyId)
                ->where('status', 'validated')
                ->whereIn('payment_status', ['unpaid', 'partial'])
                ->when($branchScopeId, fn (Builder $query, int $selectedBranchId) => $query->where('branch_id', $selectedBranchId))
                ->orderBy('invoice_date')
                ->orderBy('invoice_number')
                ->get(),
            'purchaseBills' => PurchaseBill::query()
                ->with('supplier')
                ->where('company_id', $companyId)
                ->where('status', 'validated')
                ->whereIn('payment_status', ['unpaid', 'partial'])
                ->when($branchScopeId, fn (Builder $query, int $selectedBranchId) => $query->where('branch_id', $selectedBranchId))
                ->orderBy('bill_date')
                ->orderBy('bill_number')
                ->get(),
            'cashAccounts' => CashAccount::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->when($branchScopeId, function (Builder $query, int $selectedBranchId) {
                    $query->where(function (Builder $nested) use ($selectedBranchId) {
                        $nested->whereNull('branch_id')
                            ->orWhere('branch_id', $selectedBranchId);
                    });
                })
                ->orderBy('name')
                ->get(),
            'branch' => $workspace->branch(),
            'scopeBranch' => $branchScopeId ? Branch::query()->where('company_id', $companyId)->find($branchScopeId) : null,
            'validationLimitLabel' => $this->paymentControlService->validationLimitLabel($user, $paymentType),
            'prefill' => [
                'payment_date' => $request->string('payment_date')->value() ?: now()->format('Y-m-d'),
                'amount' => $request->string('amount')->value() ?: null,
                'method' => array_key_exists($request->string('method')->value(), $this->methodOptions()) ? $request->string('method')->value() : null,
                'reference' => $request->string('reference')->value() ?: null,
                'notes' => $request->string('notes')->value() ?: null,
                'source' => $request->string('source')->value() ?: null,
            ],
        ]);
    }

    public function store(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        $user = $request->user();
        abort_if(! $companyId || ! $user, 403);

        $selectedType = $request->input('payment_type', 'customer_receipt');
        if (! in_array($selectedType, ['customer_receipt', 'supplier_payment'], true)) {
            $selectedType = 'customer_receipt';
        }

        $data = $request->validate([
            'payment_type' => ['nullable', Rule::in(['customer_receipt', 'supplier_payment'])],
            'invoice_id' => [
                'nullable',
                'integer',
                Rule::requiredIf($selectedType === 'customer_receipt'),
                Rule::exists('sales_invoices', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'purchase_bill_id' => [
                'nullable',
                'integer',
                Rule::requiredIf($selectedType === 'supplier_payment'),
                Rule::exists('purchase_bills', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'cash_account_id' => ['required', Rule::exists('cash_accounts', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', Rule::in(array_keys($this->methodOptions()))],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $paymentType = $data['payment_type'] ?? 'customer_receipt';
        $cashAccount = CashAccount::query()->where('company_id', $companyId)->findOrFail($data['cash_account_id']);

        if ($paymentType === 'supplier_payment') {
            $bill = PurchaseBill::query()->where('company_id', $companyId)->findOrFail($data['purchase_bill_id']);
            $branchId = $this->paymentControlService->authorizePayment(
                $user,
                $cashAccount,
                null,
                $bill,
                (float) $data['amount'],
                $paymentType,
                $workspace->branchId(),
            );
            $payment = $this->paymentService->recordSupplierPayment($companyId, $branchId, $bill, $cashAccount, $data, $user);

            $this->activityLogger->log('payments.create', 'Enregistrement reglement fournisseur', $payment, [
                'payment_number' => $payment->payment_number,
                'amount' => $payment->amount,
                'payment_type' => $paymentType,
            ]);

            return redirect()->route('purchases.show', $bill)->with('success', 'Reglement fournisseur enregistre avec succes.');
        }

        $invoice = SalesInvoice::query()->where('company_id', $companyId)->findOrFail($data['invoice_id']);
        $branchId = $this->paymentControlService->authorizePayment(
            $user,
            $cashAccount,
            $invoice,
            null,
            (float) $data['amount'],
            $paymentType,
            $workspace->branchId(),
        );
        $payment = $this->paymentService->recordCustomerReceipt($companyId, $branchId, $invoice, $cashAccount, $data, $user);

        $this->activityLogger->log('payments.create', 'Enregistrement encaissement client', $payment, [
            'payment_number' => $payment->payment_number,
            'amount' => $payment->amount,
            'payment_type' => $paymentType,
        ]);

        return redirect()->route('sales.show', $invoice)->with('success', 'Paiement enregistre avec succes.');
    }

    private function filteredQuery(int $companyId, array $filters, ?int $branchScopeId = null): Builder
    {
        return Payment::query()
            ->with(['cashAccount', 'partner', 'creator', 'allocations.allocatable'])
            ->where('company_id', $companyId)
            ->when($branchScopeId, fn (Builder $query, int $selectedBranchId) => $query->where('branch_id', $selectedBranchId))
            ->when($filters['date_from'], fn (Builder $query, string $dateFrom) => $query->whereDate('payment_date', '>=', $dateFrom))
            ->when($filters['date_to'], fn (Builder $query, string $dateTo) => $query->whereDate('payment_date', '<=', $dateTo))
            ->when($filters['payment_type'], fn (Builder $query, string $paymentType) => $query->where('payment_type', $paymentType))
            ->when($filters['method'], fn (Builder $query, string $method) => $query->where('method', $method))
            ->when($filters['cash_account_id'], fn (Builder $query, int $cashAccountId) => $query->where('cash_account_id', $cashAccountId))
            ->when($filters['search'], function (Builder $query, string $search) {
                $like = '%'.$search.'%';

                $query->where(function (Builder $nested) use ($like) {
                    $nested->where('payment_number', 'like', $like)
                        ->orWhere('reference', 'like', $like)
                        ->orWhereHas('partner', function (Builder $partnerQuery) use ($like) {
                            $partnerQuery->where('name', 'like', $like)
                                ->orWhere('code', 'like', $like);
                        })
                        ->orWhereHas('cashAccount', fn (Builder $cashAccountQuery) => $cashAccountQuery->where('name', 'like', $like));
                });
            });
    }

    private function filters(Request $request): array
    {
        $paymentType = $request->string('payment_type')->trim()->value() ?: null;
        if (! in_array($paymentType, ['customer_receipt', 'supplier_payment', 'pos_refund'], true)) {
            $paymentType = null;
        }

        $method = $request->string('method')->trim()->value() ?: null;
        if (! array_key_exists($method, $this->methodOptions())) {
            $method = null;
        }

        return [
            'search' => $request->string('search')->trim()->value() ?: null,
            'date_from' => $request->string('date_from')->value() ?: null,
            'date_to' => $request->string('date_to')->value() ?: null,
            'payment_type' => $paymentType,
            'method' => $method,
            'cash_account_id' => $request->integer('cash_account_id') ?: null,
        ];
    }

    private function methodOptions(): array
    {
        return PaymentMethodCatalog::options();
    }

    private function paymentTypeLabel(string $paymentType): string
    {
        return match ($paymentType) {
            'supplier_payment' => 'Reglement fournisseur',
            'pos_refund' => 'Remboursement POS',
            default => 'Encaissement client',
        };
    }

    private function allocationContext(PaymentAllocation $allocation): ?array
    {
        $document = $allocation->allocatable;

        if ($document instanceof SalesInvoice) {
            return [
                'label' => 'Facture client',
                'number' => $document->invoice_number,
                'date' => $document->invoice_date,
                'amount' => $document->total,
                'allocated_amount' => $allocation->allocated_amount,
                'url' => route('sales.show', $document),
            ];
        }

        if ($document instanceof PurchaseBill) {
            return [
                'label' => 'Facture fournisseur',
                'number' => $document->bill_number,
                'date' => $document->bill_date,
                'amount' => $document->total,
                'allocated_amount' => $allocation->allocated_amount,
                'url' => route('purchases.show', $document),
            ];
        }

        return null;
    }
}
