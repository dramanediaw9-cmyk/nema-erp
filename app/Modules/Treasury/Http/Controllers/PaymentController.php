<?php

namespace App\Modules\Treasury\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Core\Audit\Services\ActivityFeedService;
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
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly PaymentControlService $paymentControlService,
        private readonly ActivityFeedService $activityFeedService,
        private readonly ActivityLogger $activityLogger,
        private readonly CsvExportService $csvExportService,
    ) {}

    public function index(Request $request, CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        $user = $request->user();
        abort_if(! $companyId || ! $user, 403);

        $filters = $this->filters($request);
        $branchScopeId = $this->paymentControlService->listingBranchScope($user, $workspace->branchId());
        $filteredQuery = $this->filteredQuery($companyId, $filters, $branchScopeId);
        $filteredPayments = (clone $filteredQuery)->get();
        $payments = $filteredQuery
            ->latest('payment_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();
        $mobileInsights = $this->mobileMoneyInsights($filteredPayments);
        $internalTransferInsights = $this->internalTransferDepositInsights($filteredPayments);

        return view('payments.index', [
            'payments' => $payments,
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
            'mobileInsights' => $mobileInsights,
            'internalTransferInsights' => $internalTransferInsights,
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
            'internalComments.creator',
            'attachments.creator',
            'posSession',
        ]);
        $payment->allocations->loadMorph('allocatable', [
            Payment::class => ['cashAccount', 'branch'],
        ]);

        $linkedPaymentIds = $payment->allocations
            ->pluck('allocatable')
            ->filter(fn ($allocatable) => $allocatable instanceof Payment)
            ->pluck('id')
            ->push($payment->id)
            ->unique()
            ->values();
        $linkedPayments = $payment->allocations
            ->pluck('allocatable')
            ->filter(fn ($allocatable) => $allocatable instanceof Payment)
            ->values();

        return view('payments.show', [
            'payment' => $payment,
            'linkedDocuments' => $payment->allocations
                ->map(fn (PaymentAllocation $allocation) => $this->allocationContext($payment, $allocation))
                ->filter()
                ->values(),
            'journalEntries' => JournalEntry::query()
                ->with(['creator'])
                ->where('company_id', $payment->company_id)
                ->where('source_type', Payment::class)
                ->whereIn('source_id', $linkedPaymentIds)
                ->orderBy('entry_date')
                ->get(),
            'methodOptions' => $this->methodOptions(),
            'paymentTypeLabel' => $this->paymentTypeLabel($payment->payment_type, $payment->direction),
            'recentActivities' => $this->activityFeedService->recentForSubjects(
                $payment->company_id,
                collect([$payment])->merge($linkedPayments),
            ),
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
                } elseif ($document instanceof Payment) {
                    $documentLabel = $document->payment_number;
                }

                return [
                    $payment->payment_number,
                    $payment->payment_date?->format('d/m/Y'),
                    $this->paymentTypeLabel($payment->payment_type, $payment->direction),
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
        if (! in_array($paymentType, ['customer_receipt', 'supplier_payment', 'internal_transfer'], true)) {
            $paymentType = 'customer_receipt';
        }

        $selectedInvoice = $request->integer('invoice')
            ? SalesInvoice::query()->where('company_id', $companyId)->findOrFail($request->integer('invoice'))
            : null;
        $selectedPurchaseBill = $request->integer('purchase_bill')
            ? PurchaseBill::query()->where('company_id', $companyId)->findOrFail($request->integer('purchase_bill'))
            : null;

        $branchScopeId = $paymentType === 'internal_transfer' && $user->canAccessAllBranches()
            ? null
            : ($this->paymentControlService->resolveCreateScopeBranch(
                $user,
                $workspace->branchId(),
                $selectedInvoice,
                $selectedPurchaseBill,
            ) ?? $workspace->branchId());

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
            'scopeBranchLabel' => $branchScopeId
                ? (Branch::query()->where('company_id', $companyId)->find($branchScopeId)?->name ?? 'Agence non determinee')
                : 'Toutes les agences autorisees',
            'validationLimitLabel' => $this->paymentControlService->validationLimitLabel($user, $paymentType),
            'prefill' => [
                'payment_date' => $request->string('payment_date')->value() ?: now()->format('Y-m-d'),
                'amount' => $request->string('amount')->value() ?: null,
                'method' => array_key_exists($request->string('method')->value(), $this->methodOptions()) ? $request->string('method')->value() : null,
                'cash_account_id' => $request->integer('cash_account_id') ?: null,
                'destination_cash_account_id' => $request->integer('destination_cash_account_id') ?: null,
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
        if (! in_array($selectedType, ['customer_receipt', 'supplier_payment', 'internal_transfer'], true)) {
            $selectedType = 'customer_receipt';
        }

        $data = $request->validate([
            'payment_type' => ['nullable', Rule::in(['customer_receipt', 'supplier_payment', 'internal_transfer'])],
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
            'destination_cash_account_id' => [
                'nullable',
                'integer',
                'different:cash_account_id',
                Rule::requiredIf($selectedType === 'internal_transfer'),
                Rule::exists('cash_accounts', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', Rule::in(array_keys($this->methodOptions()))],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $paymentType = $data['payment_type'] ?? 'customer_receipt';
        $cashAccount = CashAccount::query()->where('company_id', $companyId)->findOrFail($data['cash_account_id']);

        if ($paymentType === 'internal_transfer') {
            $destinationCashAccount = CashAccount::query()->where('company_id', $companyId)->findOrFail($data['destination_cash_account_id']);
            $branchIds = $this->paymentControlService->authorizeInternalTransfer(
                $user,
                $cashAccount,
                $destinationCashAccount,
                (float) $data['amount'],
                $workspace->branchId(),
            );
            $payment = $this->paymentService->recordInternalTransfer(
                $companyId,
                $branchIds['source_branch_id'],
                $branchIds['destination_branch_id'],
                $cashAccount,
                $destinationCashAccount,
                $data,
                $user,
            );

            $this->activityLogger->log('payments.create', 'Enregistrement versement interne', $payment, [
                'payment_number' => $payment->payment_number,
                'amount' => $payment->amount,
                'payment_type' => $paymentType,
                'destination_cash_account_id' => $destinationCashAccount->id,
            ]);

            return redirect()->route('payments.show', $payment)->with('success', 'Versement interne enregistre avec succes.');
        }

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
        $day2 = now()->subDays(2)->toDateString();
        $day5 = now()->subDays(5)->toDateString();
        $day3 = now()->subDays(3)->toDateString();
        $day7 = now()->subDays(7)->toDateString();

        return Payment::query()
            ->with(['cashAccount.branch', 'partner', 'creator', 'allocations.allocatable', 'reconciliationItem.reconciliation'])
            ->withCount('attachments')
            ->where('company_id', $companyId)
            ->when($branchScopeId, fn (Builder $query, int $selectedBranchId) => $query->where('branch_id', $selectedBranchId))
            ->when($filters['date_from'], fn (Builder $query, string $dateFrom) => $query->whereDate('payment_date', '>=', $dateFrom))
            ->when($filters['date_to'], fn (Builder $query, string $dateTo) => $query->whereDate('payment_date', '<=', $dateTo))
            ->when($filters['payment_type'], fn (Builder $query, string $paymentType) => $query->where('payment_type', $paymentType))
            ->when($filters['method'], fn (Builder $query, string $method) => $query->where('method', $method))
            ->when($filters['cash_account_id'], fn (Builder $query, int $cashAccountId) => $query->where('cash_account_id', $cashAccountId))
            ->when($filters['reconciliation_status'] === 'reconciled', fn (Builder $query) => $query->whereHas('reconciliationItem'))
            ->when($filters['reconciliation_status'] === 'unreconciled', fn (Builder $query) => $query->whereDoesntHave('reconciliationItem'))
            ->when($filters['aging_state'] === 'mobile_age_3_plus', function (Builder $query) use ($day3) {
                $query
                    ->whereIn('method', $this->mobileMethodKeys())
                    ->whereDoesntHave('reconciliationItem')
                    ->whereDate('payment_date', '<=', $day3);
            })
            ->when($filters['aging_state'] === 'mobile_age_7_plus', function (Builder $query) use ($day7) {
                $query
                    ->whereIn('method', $this->mobileMethodKeys())
                    ->whereDoesntHave('reconciliationItem')
                    ->whereDate('payment_date', '<=', $day7);
            })
            ->when($filters['aging_state'] === 'deposit_bank_age_2_plus', function (Builder $query) use ($day2) {
                $query
                    ->where('payment_type', 'internal_transfer')
                    ->where('direction', 'in')
                    ->whereHas('cashAccount', fn (Builder $cashAccountQuery) => $cashAccountQuery->whereIn('type', $this->externalReconciliationAccountTypes()))
                    ->whereDoesntHave('reconciliationItem')
                    ->whereDate('payment_date', '<=', $day2);
            })
            ->when($filters['aging_state'] === 'deposit_bank_age_5_plus', function (Builder $query) use ($day5) {
                $query
                    ->where('payment_type', 'internal_transfer')
                    ->where('direction', 'in')
                    ->whereHas('cashAccount', fn (Builder $cashAccountQuery) => $cashAccountQuery->whereIn('type', $this->externalReconciliationAccountTypes()))
                    ->whereDoesntHave('reconciliationItem')
                    ->whereDate('payment_date', '<=', $day5);
            })
            ->when($filters['missing_reference'], function (Builder $query) {
                $query
                    ->whereIn('method', $this->mobileMethodKeys())
                    ->where(function (Builder $nested) {
                        $nested->whereNull('reference')
                            ->orWhere('reference', '');
                    });
            })
            ->when($filters['deposit_missing_reference'], function (Builder $query) {
                $query
                    ->where('payment_type', 'internal_transfer')
                    ->where('direction', 'in')
                    ->whereHas('cashAccount', fn (Builder $cashAccountQuery) => $cashAccountQuery->whereIn('type', $this->externalReconciliationAccountTypes()))
                    ->whereDoesntHave('reconciliationItem')
                    ->whereDoesntHave('attachments')
                    ->where(function (Builder $nested) {
                        $nested->whereNull('reference')
                            ->orWhere('reference', '');
                    });
            })
            ->when($filters['deposit_documented'], function (Builder $query) {
                $query
                    ->where('payment_type', 'internal_transfer')
                    ->where('direction', 'in')
                    ->whereHas('cashAccount', fn (Builder $cashAccountQuery) => $cashAccountQuery->whereIn('type', $this->externalReconciliationAccountTypes()))
                    ->whereDoesntHave('reconciliationItem')
                    ->where(function (Builder $documented) {
                        $documented->where(function (Builder $reference) {
                            $reference->whereNotNull('reference')
                                ->where('reference', '!=', '');
                        })->orWhereHas('attachments');
                    });
            })
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
        $view = $request->string('view')->trim()->value() === 'kanban' ? 'kanban' : 'list';
        $paymentType = $request->string('payment_type')->trim()->value() ?: null;
        if (! in_array($paymentType, ['customer_receipt', 'supplier_payment', 'pos_refund', 'internal_transfer'], true)) {
            $paymentType = null;
        }

        $method = $request->string('method')->trim()->value() ?: null;
        if (! array_key_exists($method, $this->methodOptions())) {
            $method = null;
        }

        $reconciliationStatus = $request->string('reconciliation_status')->trim()->value() ?: null;
        if (! in_array($reconciliationStatus, ['reconciled', 'unreconciled'], true)) {
            $reconciliationStatus = null;
        }

        $agingState = $request->string('aging_state')->trim()->value() ?: null;
        if (! in_array($agingState, ['mobile_age_3_plus', 'mobile_age_7_plus', 'deposit_bank_age_2_plus', 'deposit_bank_age_5_plus'], true)) {
            $agingState = null;
        }

        return [
            'view' => $view,
            'search' => $request->string('search')->trim()->value() ?: null,
            'date_from' => $request->string('date_from')->value() ?: null,
            'date_to' => $request->string('date_to')->value() ?: null,
            'payment_type' => $paymentType,
            'method' => $method,
            'cash_account_id' => $request->integer('cash_account_id') ?: null,
            'reconciliation_status' => $reconciliationStatus,
            'aging_state' => $agingState,
            'missing_reference' => $request->boolean('missing_reference'),
            'deposit_missing_reference' => $request->boolean('deposit_missing_reference'),
            'deposit_documented' => $request->boolean('deposit_documented'),
        ];
    }

    private function methodOptions(): array
    {
        return PaymentMethodCatalog::options();
    }

    private function mobileMethodKeys(): array
    {
        return ['wave', 'orange_money', 'moov_money', 'mobile_money'];
    }

    private function externalReconciliationAccountTypes(): array
    {
        return ['bank', 'mobile_money'];
    }

    private function mobileMoneyInsights(Collection $payments): array
    {
        $mobileMethods = $this->mobileMethodKeys();
        $mobilePayments = $payments
            ->filter(fn (Payment $payment) => in_array($payment->method, $mobileMethods, true))
            ->values();
        $day3 = now()->subDays(3)->startOfDay();
        $unreconciledMobilePayments = $mobilePayments
            ->filter(fn (Payment $payment) => ! $payment->reconciliationItem)
            ->values();
        $staleUnreconciledPayments = $unreconciledMobilePayments
            ->filter(fn (Payment $payment) => $payment->payment_date && $payment->payment_date->startOfDay()->lte($day3))
            ->values();

        $providerTotals = collect($mobileMethods)->mapWithKeys(function (string $method): array {
            return [$method => [
                'label' => $this->methodOptions()[$method] ?? $method,
                'amount' => 0.0,
                'count' => 0,
            ]];
        });

        foreach ($providerTotals as $method => $entry) {
            $providerTotals[$method] = [
                'label' => $entry['label'],
                'amount' => round((float) $mobilePayments->where('method', $method)->sum('amount'), 2),
                'count' => (int) $mobilePayments->where('method', $method)->count(),
            ];
        }

        $accountInsights = $mobilePayments
            ->groupBy(fn (Payment $payment) => (string) ($payment->cash_account_id ?: 'none'))
            ->map(function (Collection $items): array {
                /** @var Payment|null $first */
                $first = $items->first();
                $unreconciled = $items->filter(fn (Payment $payment) => ! $payment->reconciliationItem);
                $oldestUnreconciledPayment = $unreconciled
                    ->sortBy(fn (Payment $payment) => $payment->payment_date?->timestamp ?? PHP_INT_MAX)
                    ->first();
                $staleUnreconciled = $unreconciled
                    ->filter(fn (Payment $payment) => $payment->payment_date && $payment->payment_date->startOfDay()->lte(now()->subDays(3)->startOfDay()));

                return [
                    'cash_account_id' => $first?->cash_account_id,
                    'cash_account_name' => $first?->cashAccount?->name ?? 'Compte non rattache',
                    'account_number' => $first?->cashAccount?->account_number,
                    'branch_name' => $first?->cashAccount?->branch?->name ?? $first?->branch?->name,
                    'payments_count' => (int) $items->count(),
                    'total_amount' => round((float) $items->sum('amount'), 2),
                    'unreconciled_count' => (int) $unreconciled->count(),
                    'unreconciled_amount' => round((float) $unreconciled->sum(function (Payment $payment): float {
                        return $payment->direction === 'out'
                            ? -1 * (float) $payment->amount
                            : (float) $payment->amount;
                    }), 2),
                    'missing_reference_count' => (int) $items->filter(fn (Payment $payment) => $this->paymentNeedsReferenceAttention($payment))->count(),
                    'stale_unreconciled_count' => (int) $staleUnreconciled->count(),
                    'oldest_unreconciled_payment_date' => $oldestUnreconciledPayment?->payment_date,
                    'latest_payment_date' => $items
                        ->sortByDesc(fn (Payment $payment) => $payment->payment_date?->timestamp ?? 0)
                        ->first()?->payment_date,
                ];
            })
            ->sortByDesc(fn (array $account) => abs((float) $account['unreconciled_amount']) * 1000 + $account['unreconciled_count'])
            ->values();

        return [
            'count' => (int) $mobilePayments->count(),
            'amount' => round((float) $mobilePayments->sum('amount'), 2),
            'incoming_amount' => round((float) $mobilePayments->where('direction', 'in')->sum('amount'), 2),
            'outgoing_amount' => round((float) $mobilePayments->where('direction', 'out')->sum('amount'), 2),
            'unreconciled_count' => (int) $unreconciledMobilePayments->count(),
            'unreconciled_amount' => round((float) $unreconciledMobilePayments->sum(function (Payment $payment): float {
                return $payment->direction === 'out'
                    ? -1 * (float) $payment->amount
                    : (float) $payment->amount;
            }), 2),
            'missing_reference_count' => (int) $mobilePayments->filter(fn (Payment $payment) => $this->paymentNeedsReferenceAttention($payment))->count(),
            'stale_unreconciled_count' => (int) $staleUnreconciledPayments->count(),
            'stale_unreconciled_amount' => round((float) $staleUnreconciledPayments->sum(function (Payment $payment): float {
                return $payment->direction === 'out'
                    ? -1 * (float) $payment->amount
                    : (float) $payment->amount;
            }), 2),
            'oldest_unreconciled_payment_date' => $unreconciledMobilePayments
                ->sortBy(fn (Payment $payment) => $payment->payment_date?->timestamp ?? PHP_INT_MAX)
                ->first()?->payment_date,
            'provider_totals' => $providerTotals,
            'accounts' => $accountInsights,
        ];
    }

    private function internalTransferDepositInsights(Collection $payments): array
    {
        $reconcilableAccountTypes = $this->externalReconciliationAccountTypes();
        $depositPayments = $payments
            ->filter(fn (Payment $payment) => $payment->payment_type === 'internal_transfer'
                && $payment->direction === 'in'
                && in_array($payment->cashAccount?->type, $reconcilableAccountTypes, true))
            ->values();
        $day2 = now()->subDays(2)->startOfDay();
        $day5 = now()->subDays(5)->startOfDay();
        $unreconciledDepositPayments = $depositPayments
            ->filter(fn (Payment $payment) => ! $payment->reconciliationItem)
            ->values();
        $stale2DepositPayments = $unreconciledDepositPayments
            ->filter(fn (Payment $payment) => $payment->payment_date && $payment->payment_date->startOfDay()->lte($day2))
            ->values();
        $stale5DepositPayments = $unreconciledDepositPayments
            ->filter(fn (Payment $payment) => $payment->payment_date && $payment->payment_date->startOfDay()->lte($day5))
            ->values();
        $documentedDepositPayments = $unreconciledDepositPayments
            ->filter(fn (Payment $payment) => $this->paymentReadyForExternalReconciliation($payment))
            ->values();
        $staleDocumentedDepositPayments = $documentedDepositPayments
            ->filter(fn (Payment $payment) => $payment->payment_date && $payment->payment_date->startOfDay()->lte($day2))
            ->values();

        return [
            'count' => (int) $depositPayments->count(),
            'amount' => round((float) $depositPayments->sum('amount'), 2),
            'account_count' => (int) $depositPayments->pluck('cash_account_id')->filter()->unique()->count(),
            'unreconciled_count' => (int) $unreconciledDepositPayments->count(),
            'unreconciled_amount' => round((float) $unreconciledDepositPayments->sum('amount'), 2),
            'missing_reference_count' => (int) $unreconciledDepositPayments
                ->filter(fn (Payment $payment) => $this->paymentNeedsReferenceAttention($payment))
                ->count(),
            'documented_count' => (int) $documentedDepositPayments->count(),
            'documented_amount' => round((float) $documentedDepositPayments->sum('amount'), 2),
            'documented_stale_count' => (int) $staleDocumentedDepositPayments->count(),
            'stale_2_count' => (int) $stale2DepositPayments->count(),
            'stale_2_amount' => round((float) $stale2DepositPayments->sum('amount'), 2),
            'stale_5_count' => (int) $stale5DepositPayments->count(),
            'stale_5_amount' => round((float) $stale5DepositPayments->sum('amount'), 2),
            'oldest_unreconciled_payment_date' => $unreconciledDepositPayments
                ->sortBy(fn (Payment $payment) => $payment->payment_date?->timestamp ?? PHP_INT_MAX)
                ->first()?->payment_date,
        ];
    }

    private function paymentNeedsReferenceAttention(Payment $payment): bool
    {
        if ($this->internalTransferDepositNeedsReferenceAttention($payment)) {
            return true;
        }

        return in_array($payment->method, $this->mobileMethodKeys(), true)
            && blank(trim((string) $payment->reference));
    }

    private function internalTransferDepositNeedsReferenceAttention(Payment $payment): bool
    {
        return $payment->payment_type === 'internal_transfer'
            && $payment->direction === 'in'
            && in_array($payment->cashAccount?->type, $this->externalReconciliationAccountTypes(), true)
            && blank(trim((string) $payment->reference))
            && ! $this->paymentHasSupportingAttachment($payment);
    }

    private function paymentReadyForExternalReconciliation(Payment $payment): bool
    {
        return $payment->payment_type === 'internal_transfer'
            && $payment->direction === 'in'
            && in_array($payment->cashAccount?->type, $this->externalReconciliationAccountTypes(), true)
            && (filled(trim((string) $payment->reference)) || $this->paymentHasSupportingAttachment($payment));
    }

    private function paymentHasSupportingAttachment(Payment $payment): bool
    {
        if (array_key_exists('attachments_count', $payment->getAttributes())) {
            return (int) $payment->attachments_count > 0;
        }

        if ($payment->relationLoaded('attachments')) {
            return $payment->attachments->isNotEmpty();
        }

        return $payment->attachments()->exists();
    }

    private function paymentTypeLabel(string $paymentType, ?string $direction = null): string
    {
        return match ($paymentType) {
            'supplier_payment' => 'Reglement fournisseur',
            'pos_refund' => 'Remboursement POS',
            'internal_transfer' => $direction === 'in' ? 'Reception de versement' : 'Versement interne',
            default => 'Encaissement client',
        };
    }

    private function allocationContext(Payment $payment, PaymentAllocation $allocation): ?array
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

        if ($document instanceof Payment) {
            $document->loadMissing(['cashAccount', 'branch']);

            return [
                'label' => $payment->direction === 'out' ? 'Compte destination' : 'Compte source',
                'number' => $document->payment_number,
                'date' => $document->payment_date,
                'amount' => $document->amount,
                'allocated_amount' => $allocation->allocated_amount,
                'url' => route('payments.show', $document),
                'context' => trim(collect([
                    $document->cashAccount?->name,
                    $document->branch?->name,
                ])->filter()->implode(' · ')),
            ];
        }

        return null;
    }
}
