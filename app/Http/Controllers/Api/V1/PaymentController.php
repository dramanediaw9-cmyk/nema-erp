<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Treasury\Models\CashAccount;
use App\Modules\Treasury\Models\Payment;
use App\Modules\Treasury\Services\PaymentControlService;
use App\Modules\Treasury\Services\PaymentService;
use App\Support\PaymentMethodCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PaymentController
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly PaymentControlService $paymentControlService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('payments.view'), 403);

        $branchScopeId = $this->paymentControlService->listingBranchScope(
            $actor,
            null,
            $request->integer('branch_id') ?: null,
        );

        $payments = Payment::query()
            ->with(['cashAccount', 'partner', 'allocations.allocatable'])
            ->where('company_id', $company->id)
            ->when($branchScopeId, fn (Builder $query, int $selectedBranchId) => $query->where('branch_id', $selectedBranchId))
            ->when(in_array($request->string('payment_type')->trim()->value(), ['customer_receipt', 'customer_refund', 'supplier_payment', 'pos_refund', 'internal_transfer'], true), fn (Builder $query) => $query->where('payment_type', $request->string('payment_type')->trim()->value()))
            ->when(in_array($request->string('method')->trim()->value(), PaymentMethodCatalog::values(), true), fn (Builder $query) => $query->where('method', $request->string('method')->trim()->value()))
            ->when($request->integer('cash_account_id') > 0, fn (Builder $query) => $query->where('cash_account_id', $request->integer('cash_account_id')))
            ->when($request->string('search')->trim()->value() !== '', function (Builder $query) use ($request) {
                $like = '%'.$request->string('search')->trim()->value().'%';

                $query->where(function (Builder $nested) use ($like) {
                    $nested->where('payment_number', 'like', $like)
                        ->orWhere('reference', 'like', $like)
                        ->orWhereHas('partner', function (Builder $partnerQuery) use ($like) {
                            $partnerQuery->where('name', 'like', $like)
                                ->orWhere('code', 'like', $like);
                        })
                        ->orWhereHas('cashAccount', fn (Builder $cashAccountQuery) => $cashAccountQuery->where('name', 'like', $like));
                });
            })
            ->latest('payment_date')
            ->latest('id')
            ->paginate(min(max((int) $request->integer('per_page', 50), 1), 200));

        return response()->json($payments);
    }

    public function show(Request $request, Payment $payment): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        abort_unless($payment->company_id === $company->id, 404);

        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('payments.view'), 403);
        $this->paymentControlService->assertCanViewPayment($actor, $payment);

        return response()->json($this->paymentPayload($payment));
    }

    public function store(Request $request): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('payments.validate'), 403);
        $selectedType = $request->input('payment_type', 'customer_receipt');

        if (! in_array($selectedType, ['customer_receipt', 'customer_refund', 'supplier_payment', 'internal_transfer'], true)) {
            $selectedType = 'customer_receipt';
        }

        $data = $this->validatePayment($request, $company->id, $selectedType);
        $cashAccount = CashAccount::query()->where('company_id', $company->id)->findOrFail($data['cash_account_id']);

        if ($selectedType === 'internal_transfer') {
            $destinationCashAccount = CashAccount::query()->where('company_id', $company->id)->findOrFail($data['destination_cash_account_id']);
            $branchIds = $this->paymentControlService->authorizeInternalTransfer(
                $actor,
                $cashAccount,
                $destinationCashAccount,
                (float) $data['amount'],
                null,
                $request->integer('branch_id') ?: null,
            );
            $payment = $this->paymentService->recordInternalTransfer(
                $company->id,
                $branchIds['source_branch_id'],
                $branchIds['destination_branch_id'],
                $cashAccount,
                $destinationCashAccount,
                $data,
                $actor,
            );

            return response()->json($this->paymentPayload($payment), 201);
        }

        if ($selectedType === 'supplier_payment') {
            $bill = PurchaseBill::query()->where('company_id', $company->id)->findOrFail($data['purchase_bill_id']);
            $branchId = $this->paymentControlService->authorizePayment(
                $actor,
                $cashAccount,
                null,
                $bill,
                (float) $data['amount'],
                $selectedType,
                null,
                $request->integer('branch_id') ?: null,
            );
            $payment = $this->paymentService->recordSupplierPayment($company->id, $branchId, $bill, $cashAccount, $data, $actor);

            return response()->json($this->paymentPayload($payment), 201);
        }

        $invoice = SalesInvoice::query()->where('company_id', $company->id)->findOrFail($data['invoice_id']);
        $branchId = $this->paymentControlService->authorizePayment(
            $actor,
            $cashAccount,
            $invoice,
            null,
            (float) $data['amount'],
            $selectedType,
            null,
            $request->integer('branch_id') ?: null,
        );

        if ($selectedType === 'customer_refund') {
            $payment = $this->paymentService->recordCustomerRefund($company->id, $branchId, $invoice, $cashAccount, $data, $actor);

            return response()->json($this->paymentPayload($payment), 201);
        }

        $payment = $this->paymentService->recordCustomerReceipt($company->id, $branchId, $invoice, $cashAccount, $data, $actor);

        return response()->json($this->paymentPayload($payment), 201);
    }

    private function paymentPayload(Payment $payment): Payment
    {
        return $payment->load([
            'company',
            'branch',
            'cashAccount',
            'partner',
            'creator',
            'allocations.allocatable',
            'posSession',
        ]);
    }

    private function validatePayment(Request $request, int $companyId, string $selectedType): array
    {
        return $request->validate([
            'payment_type' => ['nullable', Rule::in(['customer_receipt', 'customer_refund', 'supplier_payment', 'internal_transfer'])],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('is_active', true))],
            'invoice_id' => [
                'nullable',
                'integer',
                Rule::requiredIf(in_array($selectedType, ['customer_receipt', 'customer_refund'], true)),
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
            'method' => ['required', Rule::in(PaymentMethodCatalog::values())],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function resolveApiUser(Request $request, int $companyId): User
    {
        $token = $request->attributes->get('apiToken');
        $userId = (int) ($token?->created_by ?? 0);

        $user = User::query()
            ->with(['roles.permissions'])
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->find($userId);

        if (! $user) {
            throw ValidationException::withMessages([
                'api_token' => 'Le jeton API n est rattache a aucun utilisateur actif.',
            ]);
        }

        return $user;
    }
}
