<?php

namespace App\Modules\Purchases\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Approvals\Services\ApprovalFlowService;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Services\PricingService;
use App\Modules\Core\Notifications\Services\OutboundNotificationService;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Partners\Models\Partner;
use App\Modules\Purchases\Models\GoodsReceipt;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Purchases\Services\PurchaseBillService;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use App\Support\Exports\CsvExportService;
use App\Support\Pdf\PdfDocumentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchaseBillController extends Controller
{
    public function __construct(
        private readonly PurchaseBillService $purchaseBillService,
        private readonly ApprovalFlowService $approvalFlowService,
        private readonly PricingService $pricingService,
        private readonly OutboundNotificationService $outboundNotificationService,
        private readonly ActivityLogger $activityLogger,
        private readonly CsvExportService $csvExportService,
        private readonly PdfDocumentService $pdfDocumentService,
    ) {
    }

    public function index(Request $request, CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $filters = $this->filters($request);

        return view('purchases.index', [
            'bills' => $this->filteredQuery($companyId, $filters)
                ->latest('bill_date')
                ->latest('id')
                ->paginate(15)
                ->withQueryString(),
            'filters' => $filters,
            'branches' => Branch::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'summary' => $this->summary($companyId),
            'today' => now()->startOfDay(),
            'soonDate' => now()->addDays(7)->endOfDay(),
        ]);
    }

    public function export(Request $request, CurrentWorkspace $workspace): StreamedResponse
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $filters = $this->filters($request);
        $today = now()->startOfDay();
        $soonDate = now()->addDays(7)->endOfDay();

        $rows = $this->filteredQuery($companyId, $filters)
            ->orderBy('bill_date')
            ->orderBy('id')
            ->get()
            ->map(function (PurchaseBill $bill) use ($today, $soonDate) {
                return [
                    $bill->bill_number,
                    $bill->bill_date?->format('d/m/Y'),
                    $bill->due_date?->format('d/m/Y'),
                    $bill->supplier?->name,
                    $bill->branch?->name,
                    $bill->warehouse?->name,
                    $bill->status === 'validated' ? 'Approuvee' : 'En attente',
                    number_format((float) $bill->total, 2, '.', ''),
                    number_format((float) $bill->amount_paid, 2, '.', ''),
                    number_format((float) $bill->balance_due, 2, '.', ''),
                    $bill->payment_status,
                    $this->followUpLabel($bill, $today, $soonDate),
                ];
            });

        return $this->csvExportService->download('achats.csv', [
            'Numero', 'Date', 'Echeance', 'Fournisseur', 'Agence', 'Entrepot', 'Workflow', 'Total', 'Paye', 'Reste', 'Statut paiement', 'Suivi',
        ], $rows);
    }

    public function create(Request $request, CurrentWorkspace $workspace): View|RedirectResponse
    {
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();
        abort_if(! $companyId || ! $branchId, 403);

        $selectedReceipt = $this->selectedReceipt($request->integer('receipt') ?: old('goods_receipt_id'), $companyId, $branchId);

        if ($selectedReceipt?->purchaseBill && ! session()->hasOldInput()) {
            return redirect()
                ->route('purchases.show', $selectedReceipt->purchaseBill)
                ->with('success', 'Cette reception fournisseur a deja genere une facture.');
        }

        return view('purchases.create', [
            'suppliers' => Partner::query()->suppliers()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(),
            'products' => Product::query()->with('parent')->where('company_id', $companyId)->purchasable()->orderBy('name')->get(),
            'warehouses' => Warehouse::query()->where('company_id', $companyId)->where('branch_id', $branchId)->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(),
            'defaultRows' => old('items', $this->defaultRows($selectedReceipt)),
            'priceRules' => $this->pricingService->rulesPayloadForCompany($companyId),
            'branch' => $workspace->branch(),
            'selectedReceipt' => $selectedReceipt,
        ]);
    }

    public function store(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();
        abort_if(! $companyId || ! $branchId, 403);

        $sourceReceiptId = $request->integer('goods_receipt_id') ?: null;

        $payload = $request->validate([
            'goods_receipt_id' => ['nullable', 'integer', Rule::exists('goods_receipts', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('branch_id', $branchId))],
            'supplier_id' => [
                'nullable',
                'integer',
                Rule::requiredIf(! $sourceReceiptId),
                Rule::exists('partners', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('branch_id', $branchId))],
            'bill_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:bill_date'],
            'notes' => ['nullable', 'string'],
        ]);

        $sourceReceipt = $this->selectedReceipt($sourceReceiptId, $companyId, $branchId);
        $itemsInput = $this->rawItems($request, (bool) $sourceReceipt);

        $validator = Validator::make(
            ['items' => $itemsInput],
            $sourceReceipt
                ? [
                    'items' => ['required', 'array', 'min:1'],
                    'items.*.goods_receipt_item_id' => ['required', Rule::exists('goods_receipt_items', 'id')->where(fn ($query) => $query->where('goods_receipt_id', $sourceReceipt->id))],
                    'items.*.product_id' => ['nullable', Rule::exists('products', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('purchase_ok', true)->where('is_active', true))],
                    'items.*.description' => ['nullable', 'string', 'max:255'],
                    'items.*.qty' => ['required', 'numeric', 'gt:0'],
                    'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
                ]
                : [
                    'items' => ['required', 'array', 'min:1'],
                    'items.*.product_id' => ['required', Rule::exists('products', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('purchase_ok', true)->where('is_active', true))],
                    'items.*.description' => ['nullable', 'string', 'max:255'],
                    'items.*.qty' => ['required', 'numeric', 'gt:0'],
                    'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
                ],
            [
                'items.required' => 'Ajoute au moins une ligne a la facture fournisseur.',
                'items.min' => 'Ajoute au moins une ligne a la facture fournisseur.',
            ]
        );
        $validator->validate();

        if ($sourceReceipt) {
            $normalizedItems = $this->purchaseBillService->normalizeReceiptItems($sourceReceipt, $itemsInput);
            $bill = $this->purchaseBillService->createPendingFromReceipt($sourceReceipt, $payload, $normalizedItems, $request->user());
        } else {
            $supplier = Partner::query()->suppliers()->where('company_id', $companyId)->findOrFail($payload['supplier_id']);
            $normalizedItems = $this->purchaseBillService->normalizeItems($companyId, $itemsInput, $supplier);
            $bill = $this->purchaseBillService->createPending($companyId, $branchId, $supplier, $payload, $normalizedItems, $request->user());
        }

        $result = $this->approvalFlowService->autoAdvance(
            $bill,
            'purchases',
            $request->user(),
            fn (PurchaseBill $pendingBill, $user) => $this->purchaseBillService->approve($pendingBill, $user),
        );

        /** @var PurchaseBill $bill */
        $bill = $result['document'];
        $this->outboundNotificationService->dispatchApprovalRequest($bill, 'purchases', $result['next_step']);

        $this->activityLogger->log($result['is_fully_approved'] ? 'purchases.create' : 'purchases.submit', $sourceReceipt ? ($result['is_fully_approved'] ? 'Creation facture fournisseur depuis reception' : 'Soumission facture fournisseur depuis reception pour approbation') : ($result['is_fully_approved'] ? 'Creation facture fournisseur' : 'Soumission facture fournisseur pour approbation'), $bill, [
            'bill_number' => $bill->bill_number,
            'warehouse_id' => $bill->warehouse_id,
            'total' => $bill->total,
            'status' => $bill->status,
            'goods_receipt_id' => $bill->goods_receipt_id,
            'purchase_order_id' => $bill->purchase_order_id,
            'approved_steps' => $result['approved_steps']->pluck('step_order')->all(),
        ]);

        $message = $result['is_fully_approved']
            ? ($sourceReceipt ? 'Facture fournisseur creee depuis la reception et approuvee avec succes.' : 'Facture fournisseur enregistree et approuvee avec succes.')
            : ($result['approved_steps']->isNotEmpty() && $result['next_step']
                ? 'Facture fournisseur enregistree. Etape suivante requise : '.$result['next_step']->label.'.'
                : 'Facture fournisseur soumise pour approbation.');

        return redirect()->route('purchases.show', $bill)->with('success', $message);
    }

    public function approve(PurchaseBill $purchase, CurrentWorkspace $workspace, Request $request): RedirectResponse
    {
        abort_if($workspace->companyId() !== $purchase->company_id, 403);

        $result = $this->approvalFlowService->approve(
            $purchase,
            'purchases',
            $request->user(),
            fn (PurchaseBill $pendingBill, $user) => $this->purchaseBillService->approve($pendingBill, $user),
        );

        /** @var PurchaseBill $bill */
        $bill = $result['document'];
        $this->outboundNotificationService->dispatchApprovalRequest($bill, 'purchases', $result['next_step']);

        $this->activityLogger->log('purchases.approve', 'Approbation facture fournisseur', $bill, [
            'bill_number' => $bill->bill_number,
            'approved_by' => $request->user()->id,
            'approved_steps' => $result['approved_steps']->pluck('step_order')->all(),
            'is_fully_approved' => $result['is_fully_approved'],
        ]);

        $message = $result['is_fully_approved']
            ? 'Facture fournisseur completement approuvee avec succes.'
            : 'Etape d approbation validee. Prochaine etape : '.($result['next_step']?->label ?? 'Aucune').'.';

        return redirect()->route('purchases.show', $bill)->with('success', $message);
    }

    public function show(PurchaseBill $purchase, CurrentWorkspace $workspace): View
    {
        abort_if($workspace->companyId() !== $purchase->company_id, 403);

        $bill = $purchase->load([
            'supplier',
            'branch',
            'warehouse',
            'company',
            'purchaseOrder.goodsReceipts.purchaseBill',
            'goodsReceipt.purchaseOrder',
            'items.product',
            'creator',
            'approver',
            'approvalSteps.approver',
            'approvalSteps.assignedApprover',
            'approvalSteps.delegatedBy',
            'paymentAllocations.payment.cashAccount',
            'internalComments.creator',
            'attachments.creator',
        ]);

        $stockReferenceType = $bill->goods_receipt_id ? GoodsReceipt::class : PurchaseBill::class;
        $stockReferenceId = $bill->goods_receipt_id ?: $bill->id;

        return view('purchases.show', [
            'bill' => $bill,
            'journalEntries' => JournalEntry::query()
                ->with(['creator'])
                ->where('company_id', $purchase->company_id)
                ->where('source_type', PurchaseBill::class)
                ->where('source_id', $purchase->id)
                ->orderBy('entry_date')
                ->get(),
            'stockMovements' => StockMovement::query()
                ->with(['product', 'warehouse', 'creator'])
                ->where('company_id', $purchase->company_id)
                ->where('reference_type', $stockReferenceType)
                ->where('reference_id', $stockReferenceId)
                ->orderBy('movement_date')
                ->get(),
        ]);
    }

    public function print(PurchaseBill $purchase, CurrentWorkspace $workspace): \Symfony\Component\HttpFoundation\Response
    {
        abort_if($workspace->companyId() !== $purchase->company_id, 403);

        return $this->pdfDocumentService->inline('purchases.print', [
            'bill' => $purchase->load([
                'supplier',
                'branch',
                'warehouse',
                'company',
                'items.product',
                'approver',
                'approvalSteps.approver',
                'approvalSteps.assignedApprover',
                'approvalSteps.delegatedBy',
                'paymentAllocations.payment.cashAccount',
                'internalComments.creator',
                'attachments.creator',
            ]),
        ], 'facture-fournisseur-'.$purchase->bill_number.'.pdf');
    }

    private function filteredQuery(int $companyId, array $filters): Builder
    {
        $today = now()->toDateString();
        $soonDate = now()->addDays(7)->toDateString();

        return PurchaseBill::query()
            ->with(['supplier', 'branch', 'warehouse', 'approver', 'approvalSteps', 'goodsReceipt', 'purchaseOrder'])
            ->where('company_id', $companyId)
            ->when($filters['date_from'], fn (Builder $query, string $dateFrom) => $query->whereDate('bill_date', '>=', $dateFrom))
            ->when($filters['date_to'], fn (Builder $query, string $dateTo) => $query->whereDate('bill_date', '<=', $dateTo))
            ->when($filters['branch_id'], fn (Builder $query, int $branchId) => $query->where('branch_id', $branchId))
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['payment_status'], fn (Builder $query, string $paymentStatus) => $query->where('payment_status', $paymentStatus))
            ->when($filters['due_state'] === 'open', fn (Builder $query) => $query->where('status', 'validated')->whereIn('payment_status', ['unpaid', 'partial']))
            ->when($filters['due_state'] === 'overdue', fn (Builder $query) => $query->where('status', 'validated')->whereIn('payment_status', ['unpaid', 'partial'])->whereNotNull('due_date')->whereDate('due_date', '<', $today))
            ->when($filters['due_state'] === 'due_soon', fn (Builder $query) => $query->where('status', 'validated')->whereIn('payment_status', ['unpaid', 'partial'])->whereNotNull('due_date')->whereBetween('due_date', [$today, $soonDate]))
            ->when($filters['due_state'] === 'no_due', fn (Builder $query) => $query->where('status', 'validated')->whereIn('payment_status', ['unpaid', 'partial'])->whereNull('due_date'))
            ->when($filters['search'], function (Builder $query, string $search) {
                $like = '%'.$search.'%';

                $query->where(function (Builder $nested) use ($like) {
                    $nested->where('bill_number', 'like', $like)
                        ->orWhere('notes', 'like', $like)
                        ->orWhereHas('supplier', function (Builder $supplierQuery) use ($like) {
                            $supplierQuery->where('name', 'like', $like)
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
                        })
                        ->orWhereHas('goodsReceipt', function (Builder $receiptQuery) use ($like) {
                            $receiptQuery->where('receipt_number', 'like', $like);
                        })
                        ->orWhereHas('purchaseOrder', function (Builder $orderQuery) use ($like) {
                            $orderQuery->where('order_number', 'like', $like);
                        });
                });
            });
    }

    private function filters(Request $request): array
    {
        $status = $request->string('status')->trim()->value() ?: null;
        if (! in_array($status, ['validated', 'pending_approval'], true)) {
            $status = null;
        }

        $paymentStatus = $request->string('payment_status')->trim()->value() ?: null;
        if (! in_array($paymentStatus, ['unpaid', 'partial', 'paid'], true)) {
            $paymentStatus = null;
        }

        $dueState = $request->string('due_state')->trim()->value() ?: null;
        if (! in_array($dueState, ['open', 'overdue', 'due_soon', 'no_due'], true)) {
            $dueState = null;
        }

        return [
            'search' => $request->string('search')->trim()->value() ?: null,
            'date_from' => $request->string('date_from')->value() ?: null,
            'date_to' => $request->string('date_to')->value() ?: null,
            'branch_id' => $request->integer('branch_id') ?: null,
            'status' => $status,
            'payment_status' => $paymentStatus,
            'due_state' => $dueState,
        ];
    }

    private function summary(int $companyId): array
    {
        $today = now()->toDateString();
        $soonDate = now()->addDays(7)->toDateString();

        return [
            'open_count' => (int) PurchaseBill::query()
                ->where('company_id', $companyId)
                ->where('status', 'validated')
                ->whereIn('payment_status', ['unpaid', 'partial'])
                ->count(),
            'open_balance' => (float) PurchaseBill::query()
                ->where('company_id', $companyId)
                ->where('status', 'validated')
                ->whereIn('payment_status', ['unpaid', 'partial'])
                ->sum('balance_due'),
            'overdue_balance' => (float) PurchaseBill::query()
                ->where('company_id', $companyId)
                ->where('status', 'validated')
                ->whereIn('payment_status', ['unpaid', 'partial'])
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', $today)
                ->sum('balance_due'),
            'due_soon_balance' => (float) PurchaseBill::query()
                ->where('company_id', $companyId)
                ->where('status', 'validated')
                ->whereIn('payment_status', ['unpaid', 'partial'])
                ->whereNotNull('due_date')
                ->whereBetween('due_date', [$today, $soonDate])
                ->sum('balance_due'),
            'pending_approval_count' => (int) PurchaseBill::query()
                ->where('company_id', $companyId)
                ->where('status', 'pending_approval')
                ->count(),
        ];
    }

    private function followUpLabel(PurchaseBill $bill, $today, $soonDate): string
    {
        if ($bill->status !== 'validated') {
            return 'Workflow';
        }

        if ($bill->payment_status === 'paid') {
            return 'A jour';
        }

        if (! $bill->due_date) {
            return 'Sans echeance';
        }

        if ($bill->due_date->lt($today)) {
            return 'En retard';
        }

        if ($bill->due_date->lte($soonDate)) {
            return 'Echeance proche';
        }

        return 'Dans les delais';
    }

    private function selectedReceipt(?int $receiptId, int $companyId, int $branchId): ?GoodsReceipt
    {
        if (! $receiptId) {
            return null;
        }

        return GoodsReceipt::query()
            ->with(['supplier.paymentTerm', 'warehouse', 'purchaseOrder', 'purchaseBill', 'items.product.purchaseTaxRule'])
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->findOrFail($receiptId);
    }

    private function defaultRows(?GoodsReceipt $selectedReceipt): array
    {
        if (! $selectedReceipt) {
            return array_fill(0, 6, ['product_id' => '', 'description' => '', 'qty' => '', 'unit_cost' => '']);
        }

        return $selectedReceipt->items
            ->map(fn ($item) => [
                'goods_receipt_item_id' => $item->id,
                'product_id' => $item->product_id,
                'description' => $item->description,
                'qty' => (float) $item->qty,
                'unit_cost' => (float) $item->unit_cost,
            ])
            ->values()
            ->all();
    }

    private function rawItems(Request $request, bool $fromReceipt): array
    {
        return collect($request->input('items', []))
            ->map(fn ($item) => is_array($item) ? $item : [])
            ->filter(fn (array $item) => $fromReceipt
                ? filled($item['goods_receipt_item_id'] ?? null)
                : filled($item['product_id'] ?? null))
            ->values()
            ->all();
    }
}












