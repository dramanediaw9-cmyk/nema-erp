<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesApiActor;
use App\Models\User;
use App\Modules\Core\Approvals\Models\ApprovalStep;
use App\Modules\Core\Approvals\Services\ApprovalFlowService;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Notifications\Services\OutboundNotificationService;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Partners\Models\Partner;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Services\SalesInvoiceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SalesInvoiceController
{
    use ResolvesApiActor;

    public function __construct(
        private readonly SalesInvoiceService $salesInvoiceService,
        private readonly ApprovalFlowService $approvalFlowService,
        private readonly OutboundNotificationService $outboundNotificationService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        $actor = $this->resolveApiUser($request, $company->id);
        $this->ensureApiPermission($actor, 'sales.view');

        $invoices = $this->indexQuery($company->id, $request)
            ->latest('invoice_date')
            ->latest('id')
            ->paginate(min(max((int) $request->integer('per_page', 50), 1), 200));

        return response()->json($invoices);
    }

    public function show(Request $request, SalesInvoice $salesInvoice): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        abort_unless($salesInvoice->company_id === $company->id, 404);
        $actor = $this->resolveApiUser($request, $company->id);
        $this->ensureApiPermission($actor, 'sales.view');

        return response()->json($this->invoicePayload($salesInvoice));
    }

    public function store(Request $request): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        $actor = $this->resolveApiUser($request, $company->id);
        $this->ensureApiPermission($actor, 'sales.manage');
        $payload = $this->validateInvoice($request, $company->id);
        $branchId = $this->resolveBranchId($company->id, $actor, $request->integer('branch_id') ?: null);
        $itemsInput = $this->resolveInputItems($request);

        Validator::make(
            ['items' => $itemsInput],
            [
                'items' => ['required', 'array', 'min:1'],
                'items.*.product_id' => ['required', Rule::exists('products', 'id')->where(fn ($query) => $query->where('company_id', $company->id))],
                'items.*.description' => ['nullable', 'string', 'max:255'],
                'items.*.qty' => ['required', 'numeric', 'gt:0'],
                'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
                'items.*.discount_type' => ['nullable', Rule::in(['none', 'fixed', 'percent'])],
                'items.*.discount_value' => ['nullable', 'numeric', 'min:0'],
            ],
            [
                'items.required' => 'Ajoute au moins une ligne a la facture.',
                'items.min' => 'Ajoute au moins une ligne a la facture.',
            ]
        )->validate();

        $customer = Partner::query()
            ->customers()
            ->where('company_id', $company->id)
            ->findOrFail($payload['customer_id']);

        $this->assertWarehouseBelongsToBranch($company->id, $branchId, $payload['warehouse_id'] ?? null);

        $normalizedItems = $this->salesInvoiceService->normalizeItems($company->id, $itemsInput, $customer);
        $this->salesInvoiceService->assertCreatable($company->id, $branchId, $normalizedItems, $payload['warehouse_id'] ?? null);

        $payload['sale_channel'] = $payload['sale_channel'] ?? 'api';

        $invoice = $this->salesInvoiceService->createPending(
            $company->id,
            $branchId,
            $customer,
            $payload,
            $normalizedItems,
            $actor,
        );

        $result = $this->approvalFlowService->autoAdvance(
            $invoice,
            'sales',
            $actor,
            fn (SalesInvoice $pendingInvoice, User $user) => $this->salesInvoiceService->approve($pendingInvoice, $user),
        );

        /** @var SalesInvoice $invoice */
        $invoice = $result['document'];
        $this->outboundNotificationService->dispatchApprovalRequest($invoice, 'sales', $result['next_step']);

        return response()->json([
            'invoice' => $this->invoicePayload($invoice),
            'workflow' => [
                'status' => $invoice->status,
                'is_fully_approved' => $result['is_fully_approved'],
                'approved_steps' => $result['approved_steps']->pluck('step_order')->values()->all(),
                'next_step' => $this->stepPayload($result['next_step']),
            ],
        ], 201);
    }

    private function indexQuery(int $companyId, Request $request): Builder
    {
        $status = $request->string('status')->trim()->value();
        $paymentStatus = $request->string('payment_status')->trim()->value();
        $search = $request->string('search')->trim()->value();
        $branchId = $request->integer('branch_id');
        $customerId = $request->integer('customer_id');

        return SalesInvoice::query()
            ->with(['customer', 'branch', 'warehouse', 'approver', 'rejector', 'approvalSteps'])
            ->where('company_id', $companyId)
            ->when(in_array($status, ['validated', 'pending_approval', 'cancelled', 'rejected'], true), fn (Builder $query) => $query->where('status', $status))
            ->when(in_array($paymentStatus, ['unpaid', 'partial', 'paid'], true), fn (Builder $query) => $query->where('payment_status', $paymentStatus))
            ->when($branchId > 0, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->when($customerId > 0, fn (Builder $query) => $query->where('customer_id', $customerId))
            ->when($search !== '', function (Builder $query) use ($search) {
                $like = '%'.$search.'%';

                $query->where(function (Builder $nested) use ($like) {
                    $nested->where('invoice_number', 'like', $like)
                        ->orWhere('notes', 'like', $like)
                        ->orWhereHas('customer', function (Builder $customerQuery) use ($like) {
                            $customerQuery->where('name', 'like', $like)
                                ->orWhere('code', 'like', $like)
                                ->orWhere('phone', 'like', $like);
                        })
                        ->orWhereHas('branch', function (Builder $branchQuery) use ($like) {
                            $branchQuery->where('name', 'like', $like)
                                ->orWhere('code', 'like', $like);
                        })
                        ->orWhereHas('warehouse', function (Builder $warehouseQuery) use ($like) {
                            $warehouseQuery->where('name', 'like', $like)
                                ->orWhere('code', 'like', $like);
                        });
                });
            });
    }

    private function invoicePayload(SalesInvoice $invoice): SalesInvoice
    {
        return $invoice->load([
            'customer',
            'branch',
            'warehouse',
            'company',
            'items.product',
            'items.taxRule',
            'creator',
            'approver',
            'cancelledBy',
            'rejector',
            'approvalSteps.approver',
            'approvalSteps.rejectedBy',
            'paymentAllocations.payment.cashAccount',
        ]);
    }

    private function stepPayload(?ApprovalStep $step): ?array
    {
        if (! $step) {
            return null;
        }

        return [
            'step_order' => $step->step_order,
            'code' => $step->code,
            'label' => $step->label,
            'rule' => $step->rule,
        ];
    }

    private function validateInvoice(Request $request, int $companyId): array
    {
        return $request->validate([
            'customer_id' => [
                'required',
                Rule::exists('partners', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->whereIn('type', ['customer', 'both'])),
            ],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('is_active', true))],
            'warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'discount_type' => ['nullable', Rule::in(['none', 'fixed', 'percent'])],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'sale_channel' => ['nullable', Rule::in(['standard', 'api', 'pos'])],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function resolveInputItems(Request $request): array
    {
        return collect($request->input('items', []))
            ->map(function ($item) {
                $row = is_array($item) ? $item : [];

                return array_merge([
                    'product_id' => null,
                    'description' => null,
                    'qty' => null,
                    'unit_price' => null,
                    'discount_type' => 'none',
                    'discount_value' => 0,
                ], $row);
            })
            ->filter(fn (array $item) => filled($item['product_id']))
            ->values()
            ->all();
    }

    private function resolveBranchId(int $companyId, User $user, ?int $requestedBranchId = null): int
    {
        if ($requestedBranchId) {
            $branchId = (int) (Branch::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->whereKey($requestedBranchId)
                ->value('id') ?? 0);

            if ($branchId > 0) {
                return $branchId;
            }

            throw ValidationException::withMessages([
                'branch_id' => 'L agence selectionnee est introuvable ou inactive.',
            ]);
        }

        if ($user->branch_id && Branch::query()->where('company_id', $companyId)->where('is_active', true)->whereKey($user->branch_id)->exists()) {
            return (int) $user->branch_id;
        }

        $defaultBranchId = (int) (Branch::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($defaultBranchId > 0) {
            return $defaultBranchId;
        }

        throw ValidationException::withMessages([
            'branch_id' => 'Aucune agence active n est disponible pour ce jeton API.',
        ]);
    }

    private function assertWarehouseBelongsToBranch(int $companyId, int $branchId, mixed $warehouseId): void
    {
        if (! filled($warehouseId)) {
            return;
        }

        $exists = Warehouse::query()
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->whereKey((int) $warehouseId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'L entrepot selectionne n appartient pas a l agence courante.',
            ]);
        }
    }
}

