<?php

namespace App\Modules\Sales\Http\Controllers;

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
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Services\SalesCreditNoteService;
use App\Modules\Sales\Services\SalesInvoiceService;
use App\Modules\Sales\Services\SalesPortalLinkService;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use App\Support\Exports\CsvExportService;
use App\Support\Pdf\PdfDocumentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesInvoiceController extends Controller
{
    public function __construct(
        private readonly SalesInvoiceService $salesInvoiceService,
        private readonly SalesCreditNoteService $salesCreditNoteService,
        private readonly ApprovalFlowService $approvalFlowService,
        private readonly PricingService $pricingService,
        private readonly OutboundNotificationService $outboundNotificationService,
        private readonly SalesPortalLinkService $salesPortalLinkService,
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

        return view('sales.index', [
            'invoices' => $this->filteredQuery($companyId, $filters)
                ->latest('invoice_date')
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
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->get()
            ->map(function (SalesInvoice $invoice) use ($today, $soonDate) {
                return [
                    $invoice->invoice_number,
                    $invoice->invoice_date?->format('d/m/Y'),
                    $invoice->due_date?->format('d/m/Y'),
                    $invoice->customer?->name,
                    $invoice->branch?->name,
                    $invoice->warehouse?->name,
                    $this->workflowLabel($invoice),
                    number_format((float) $invoice->total, 2, '.', ''),
                    number_format((float) $invoice->amount_paid, 2, '.', ''),
                    number_format((float) $invoice->balance_due, 2, '.', ''),
                    $invoice->payment_status,
                    $this->followUpLabel($invoice, $today, $soonDate),
                ];
            });

        return $this->csvExportService->download('ventes.csv', [
            'Numero', 'Date', 'Echeance', 'Client', 'Agence', 'Entrepot', 'Workflow', 'Total', 'Paye', 'Reste', 'Statut paiement', 'Suivi',
        ], $rows);
    }

    public function create(CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();
        abort_if(! $companyId || ! $branchId, 403);

        $hasProductParent = Schema::hasColumn('products', 'parent_id');
        $productQuery = Product::query()
            ->where('company_id', $companyId)
            ->saleable()
            ->orderBy('name');
        $productColumns = ['id', 'company_id', 'sku', 'name', 'unit', 'description', 'sales_description', 'sale_price'];

        if ($hasProductParent) {
            $productQuery->with('parent:id,name');
            array_splice($productColumns, 2, 0, ['parent_id']);
        }

        return view('sales.create', [
            'customers' => Partner::query()
                ->customers()
                ->with('priceList:id,name')
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'company_id', 'code', 'name', 'price_list_id']),
            'products' => $productQuery->get($productColumns),
            'warehouses' => Warehouse::query()
                ->where('company_id', $companyId)
                ->where('branch_id', $branchId)
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'is_default']),
            'defaultRows' => old('items', array_fill(0, 3, ['product_id' => '', 'description' => '', 'qty' => '', 'unit_price' => ''])),
            'priceRules' => $this->pricingService->rulesPayloadForCompany($companyId),
            'branch' => $workspace->branch(),
        ]);
    }

    public function store(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();
        abort_if(! $companyId || ! $branchId, 403);

        $payload = $request->validate([
            'customer_id' => ['required', Rule::exists('partners', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('branch_id', $branchId))],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'notes' => ['nullable', 'string'],
        ]);

        $itemsInput = collect($request->input('items', []))
            ->map(fn ($item) => is_array($item) ? $item : [])
            ->filter(fn (array $item) => filled($item['product_id'] ?? null))
            ->values()
            ->all();

        Validator::make(
            ['items' => $itemsInput],
            [
                'items' => ['required', 'array', 'min:1'],
                'items.*.product_id' => ['required', Rule::exists('products', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('sale_ok', true)->where('is_active', true))],
                'items.*.description' => ['nullable', 'string', 'max:255'],
                'items.*.qty' => ['required', 'numeric', 'gt:0'],
                'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            ],
            [
                'items.required' => 'Ajoute au moins une ligne a la facture.',
                'items.min' => 'Ajoute au moins une ligne a la facture.',
            ]
        )->validate();

        $customer = Partner::query()->customers()->where('company_id', $companyId)->findOrFail($payload['customer_id']);
        $normalizedItems = $this->salesInvoiceService->normalizeItems($companyId, $itemsInput, $customer, $request->user());
        $this->salesInvoiceService->assertCreatable($companyId, $branchId, $normalizedItems, $payload['warehouse_id'] ?? null);
        $invoice = $this->salesInvoiceService->createPending($companyId, $branchId, $customer, $payload, $normalizedItems, $request->user());
        $result = $this->approvalFlowService->autoAdvance(
            $invoice,
            'sales',
            $request->user(),
            fn (SalesInvoice $pendingInvoice, $user) => $this->salesInvoiceService->approve($pendingInvoice, $user),
        );

        /** @var SalesInvoice $invoice */
        $invoice = $result['document'];
        $this->outboundNotificationService->dispatchApprovalRequest($invoice, 'sales', $result['next_step']);

        $this->activityLogger->log($result['is_fully_approved'] ? 'sales.create' : 'sales.submit', $result['is_fully_approved'] ? 'Creation facture de vente' : 'Soumission facture de vente pour approbation', $invoice, [
            'invoice_number' => $invoice->invoice_number,
            'warehouse_id' => $invoice->warehouse_id,
            'total' => $invoice->total,
            'status' => $invoice->status,
            'approved_steps' => $result['approved_steps']->pluck('step_order')->all(),
        ]);

        $message = $result['is_fully_approved']
            ? 'Facture enregistree et approuvee avec succes.'
            : ($result['approved_steps']->isNotEmpty() && $result['next_step']
                ? 'Facture enregistree. Etape suivante requise : '.$result['next_step']->label.'.'
                : 'Facture soumise pour approbation.');

        return redirect()->route('sales.show', $invoice)->with('success', $message);
    }

    public function cancel(SalesInvoice $sale, CurrentWorkspace $workspace, Request $request): RedirectResponse
    {
        abort_if($workspace->companyId() !== $sale->company_id, 403);

        $invoice = $this->salesInvoiceService->cancel($sale, $request->user());
        $this->outboundNotificationService->cancelQueuedForResource($invoice, 'Document annule avant validation finale.');

        $this->activityLogger->log('sales.cancel', 'Annulation facture de vente en attente', $invoice, [
            'invoice_number' => $invoice->invoice_number,
            'cancelled_by' => $request->user()->id,
            'cancelled_at' => $invoice->cancelled_at?->toIso8601String(),
        ]);

        return redirect()->route('sales.show', $invoice)->with('success', 'Facture client annulee. Une facture deja approuvee doit passer par un avoir client.');
    }

    public function approve(SalesInvoice $sale, CurrentWorkspace $workspace, Request $request): RedirectResponse
    {
        abort_if($workspace->companyId() !== $sale->company_id, 403);

        $result = $this->approvalFlowService->approve(
            $sale,
            'sales',
            $request->user(),
            fn (SalesInvoice $pendingInvoice, $user) => $this->salesInvoiceService->approve($pendingInvoice, $user),
        );

        /** @var SalesInvoice $invoice */
        $invoice = $result['document'];
        foreach ($result['approved_steps'] as $approvedStep) {
            $this->outboundNotificationService->cancelQueuedForApprovalStep($invoice, (int) $approvedStep->step_order, 'Etape deja approuvee, notification obsolete.');
        }
        $this->outboundNotificationService->dispatchApprovalRequest($invoice, 'sales', $result['next_step']);

        $this->activityLogger->log('sales.approve', 'Approbation facture de vente', $invoice, [
            'invoice_number' => $invoice->invoice_number,
            'approved_by' => $request->user()->id,
            'approved_steps' => $result['approved_steps']->pluck('step_order')->all(),
            'is_fully_approved' => $result['is_fully_approved'],
        ]);

        $message = $result['is_fully_approved']
            ? 'Facture client completement approuvee avec succes.'
            : 'Etape d approbation validee. Prochaine etape : '.($result['next_step']?->label ?? 'Aucune').'.';

        return redirect()->route('sales.show', $invoice)->with('success', $message);
    }

    public function reject(SalesInvoice $sale, CurrentWorkspace $workspace, Request $request): RedirectResponse
    {
        abort_if($workspace->companyId() !== $sale->company_id, 403);

        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $result = $this->approvalFlowService->reject(
            $sale,
            'sales',
            $request->user(),
            fn (SalesInvoice $pendingInvoice, $user, ?string $reason) => $this->salesInvoiceService->reject($pendingInvoice, $user, $reason),
            $data['rejection_reason'],
        );

        /** @var SalesInvoice $invoice */
        $invoice = $result['document'];
        $this->outboundNotificationService->cancelQueuedForResource($invoice, 'Workflow rejete avant validation finale.');

        $this->activityLogger->log('sales.reject', 'Rejet facture de vente', $invoice, [
            'invoice_number' => $invoice->invoice_number,
            'rejected_by' => $request->user()->id,
            'rejected_step_order' => $result['rejected_step']->step_order,
            'rejection_reason' => $data['rejection_reason'],
        ]);

        return redirect()->route('sales.show', $invoice)->with('success', 'Facture client rejetee avec motif.');
    }

    public function show(SalesInvoice $sale, CurrentWorkspace $workspace): View
    {
        abort_if($workspace->companyId() !== $sale->company_id, 403);

        $invoice = $sale->load([
            'customer',
            'branch',
            'warehouse',
            'company',
            'items.product',
            'creator',
            'approver',
            'cancelledBy',
            'rejector',
            'approvalSteps.approver',
            'approvalSteps.rejectedBy',
            'approvalSteps.assignedApprover',
            'approvalSteps.delegatedBy',
            'paymentAllocations.payment.cashAccount',
            'paymentAllocations.payment.creator',
            'internalComments.creator',
            'attachments.creator',
            'followUps.creator',
            'creditNotes.items.product',
            'creditNotes.creator',
            'latestPortalAction',
            'latestPaymentGatewayCallback',
            'paymentGatewayCallbacks.payment.cashAccount',
            'paymentGatewayCallbacks.cashAccount',
        ]);

        return view('sales.show', [
            'invoice' => $invoice,
            'paymentPortal' => $this->salesPortalLinkService->invoicePaymentPortalData($invoice),
            'creditableLinesCount' => $invoice->status === 'validated' ? $this->salesCreditNoteService->creditableLines($invoice)->count() : 0,
            'journalEntries' => JournalEntry::query()
                ->with(['creator'])
                ->where('company_id', $sale->company_id)
                ->where('source_type', SalesInvoice::class)
                ->where('source_id', $sale->id)
                ->orderBy('entry_date')
                ->get(),
            'stockMovements' => StockMovement::query()
                ->with(['product', 'warehouse', 'creator'])
                ->where('company_id', $sale->company_id)
                ->where('reference_type', SalesInvoice::class)
                ->where('reference_id', $sale->id)
                ->orderBy('movement_date')
                ->get(),
        ]);
    }

    public function print(SalesInvoice $sale, CurrentWorkspace $workspace): \Symfony\Component\HttpFoundation\Response
    {
        abort_if($workspace->companyId() !== $sale->company_id, 403);

        return $this->pdfDocumentService->inline('sales.print', [
            'invoice' => $sale->load([
                'customer',
                'branch',
                'warehouse',
                'company',
                'items.product',
                'approver',
                'cancelledBy',
                'rejector',
                'approvalSteps.approver',
                'approvalSteps.rejectedBy',
                'approvalSteps.assignedApprover',
                'approvalSteps.delegatedBy',
                'paymentAllocations.payment.cashAccount',
            ]),
        ], 'facture-'.$sale->invoice_number.'.pdf');
    }

    private function filteredQuery(int $companyId, array $filters): Builder
    {
        $today = now()->toDateString();
        $soonDate = now()->addDays(7)->toDateString();

        return SalesInvoice::query()
            ->with(['customer', 'branch', 'warehouse', 'approver', 'cancelledBy', 'approvalSteps'])
            ->where('company_id', $companyId)
            ->when($filters['date_from'], fn (Builder $query, string $dateFrom) => $query->whereDate('invoice_date', '>=', $dateFrom))
            ->when($filters['date_to'], fn (Builder $query, string $dateTo) => $query->whereDate('invoice_date', '<=', $dateTo))
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

    private function filters(Request $request): array
    {
        $status = $request->string('status')->trim()->value() ?: null;
        if (! in_array($status, ['validated', 'pending_approval', 'cancelled', 'rejected'], true)) {
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
            'open_count' => (int) SalesInvoice::query()
                ->where('company_id', $companyId)
                ->where('status', 'validated')
                ->whereIn('payment_status', ['unpaid', 'partial'])
                ->count(),
            'open_balance' => (float) SalesInvoice::query()
                ->where('company_id', $companyId)
                ->where('status', 'validated')
                ->whereIn('payment_status', ['unpaid', 'partial'])
                ->sum('balance_due'),
            'overdue_balance' => (float) SalesInvoice::query()
                ->where('company_id', $companyId)
                ->where('status', 'validated')
                ->whereIn('payment_status', ['unpaid', 'partial'])
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', $today)
                ->sum('balance_due'),
            'due_soon_balance' => (float) SalesInvoice::query()
                ->where('company_id', $companyId)
                ->where('status', 'validated')
                ->whereIn('payment_status', ['unpaid', 'partial'])
                ->whereNotNull('due_date')
                ->whereBetween('due_date', [$today, $soonDate])
                ->sum('balance_due'),
            'pending_approval_count' => (int) SalesInvoice::query()
                ->where('company_id', $companyId)
                ->where('status', 'pending_approval')
                ->count(),
        ];
    }

    private function workflowLabel(SalesInvoice $invoice): string
    {
        return match ($invoice->status) {
            'validated' => 'Approuvee',
            'cancelled' => 'Annulee',
            'rejected' => 'Rejetee',
            default => 'En attente',
        };
    }

    private function followUpLabel(SalesInvoice $invoice, $today, $soonDate): string
    {
        if ($invoice->status === 'cancelled') {
            return 'Annulee';
        }

        if ($invoice->status === 'rejected') {
            return 'Rejetee';
        }

        if ($invoice->status !== 'validated') {
            return 'Workflow';
        }

        if ($invoice->payment_status === 'paid') {
            return 'A jour';
        }

        if (! $invoice->due_date) {
            return 'Sans echeance';
        }

        if ($invoice->due_date->lt($today)) {
            return 'En retard';
        }

        if ($invoice->due_date->lte($soonDate)) {
            return 'Echeance proche';
        }

        return 'Dans les delais';
    }
}
