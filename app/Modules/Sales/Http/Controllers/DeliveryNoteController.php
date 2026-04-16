<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Approvals\Services\ApprovalFlowService;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Notifications\Services\OutboundNotificationService;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Sales\Models\DeliveryNote;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Services\DeliveryNoteService;
use App\Modules\Sales\Services\SalesInvoiceService;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use App\Support\Exports\CsvExportService;
use App\Support\Pdf\PdfDocumentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DeliveryNoteController extends Controller
{
    public function __construct(
        private readonly DeliveryNoteService $deliveryNoteService,
        private readonly SalesInvoiceService $salesInvoiceService,
        private readonly ApprovalFlowService $approvalFlowService,
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

        return view('delivery-notes.index', [
            'deliveryNotes' => $this->filteredQuery($companyId, $filters)
                ->latest('delivery_date')
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
        ]);
    }

    public function export(Request $request, CurrentWorkspace $workspace): StreamedResponse
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $rows = $this->filteredQuery($companyId, $this->filters($request))
            ->orderBy('delivery_date')
            ->orderBy('id')
            ->get()
            ->map(fn (DeliveryNote $deliveryNote) => [
                $deliveryNote->delivery_number,
                $deliveryNote->delivery_date?->format('d/m/Y'),
                $deliveryNote->salesOrder?->order_number,
                $deliveryNote->customer?->name,
                $deliveryNote->branch?->name,
                $deliveryNote->warehouse?->name,
                $deliveryNote->status,
                number_format((float) $deliveryNote->total, 2, '.', ''),
            ]);

        return $this->csvExportService->download('bons-livraison.csv', [
            'Numero', 'Date', 'Commande', 'Client', 'Agence', 'Entrepot', 'Statut', 'Total',
        ], $rows);
    }

    public function create(Request $request, CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();
        abort_if(! $companyId || ! $branchId, 403);

        $selectedOrderId = $request->integer('order') ?: null;

        $orders = SalesOrder::query()
            ->with(['customer', 'warehouse', 'items.product'])
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->whereIn('status', ['confirmed', 'partial_delivered'])
            ->whereNull('converted_sales_invoice_id')
            ->orderByDesc('order_date')
            ->get()
            ->filter(fn (SalesOrder $order) => $order->hasRemainingDelivery())
            ->values();

        return view('delivery-notes.create', [
            'orders' => $orders,
            'selectedOrder' => $orders->firstWhere('id', $selectedOrderId),
            'warehouses' => Warehouse::query()->where('company_id', $companyId)->where('branch_id', $branchId)->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(),
            'branch' => $workspace->branch(),
        ]);
    }

    public function store(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();
        abort_if(! $companyId || ! $branchId, 403);

        $payload = $request->validate([
            'order_id' => ['required', Rule::exists('sales_orders', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('branch_id', $branchId))],
            'warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('branch_id', $branchId))],
            'delivery_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $itemsInput = collect($request->input('items', []))
            ->map(fn ($item) => is_array($item) ? $item : [])
            ->filter(fn (array $item) => filled($item['sales_order_item_id'] ?? null) || filled($item['qty'] ?? null))
            ->values()
            ->all();

        Validator::make(
            ['items' => $itemsInput],
            [
                'items' => ['nullable', 'array'],
                'items.*.sales_order_item_id' => ['nullable', Rule::exists('sales_order_items', 'id')],
                'items.*.qty' => ['nullable', 'numeric', 'gte:0'],
            ]
        )->validate();

        $payload['items'] = $itemsInput;

        $order = SalesOrder::query()
            ->with(['customer', 'items.product', 'deliveryNotes'])
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->findOrFail($payload['order_id']);

        $deliveryNote = $this->deliveryNoteService->createFromOrder($order, $payload, $request->user());

        $this->activityLogger->log('delivery_notes.create', 'Creation bon de livraison', $deliveryNote, [
            'delivery_number' => $deliveryNote->delivery_number,
            'order_number' => $deliveryNote->salesOrder?->order_number,
            'warehouse_id' => $deliveryNote->warehouse_id,
            'total' => $deliveryNote->total,
        ]);

        return redirect()->route('delivery-notes.show', $deliveryNote)->with('success', 'Bon de livraison genere avec succes.');
    }

    public function show(DeliveryNote $deliveryNote, CurrentWorkspace $workspace): View
    {
        abort_if($workspace->companyId() !== $deliveryNote->company_id, 403);

        return view('delivery-notes.show', [
            'deliveryNote' => $deliveryNote->load([
                'customer',
                'branch',
                'warehouse',
                'company',
                'salesOrder',
                'items.product',
                'items.orderItem',
                'creator',
                'convertedInvoice',
            ]),
            'stockMovements' => StockMovement::query()
                ->with(['product', 'warehouse', 'creator'])
                ->where('company_id', $deliveryNote->company_id)
                ->where('reference_type', DeliveryNote::class)
                ->where('reference_id', $deliveryNote->id)
                ->orderBy('movement_date')
                ->get(),
        ]);
    }

    public function print(DeliveryNote $deliveryNote, CurrentWorkspace $workspace): \Symfony\Component\HttpFoundation\Response
    {
        abort_if($workspace->companyId() !== $deliveryNote->company_id, 403);

        return $this->pdfDocumentService->inline('delivery-notes.print', [
            'deliveryNote' => $deliveryNote->load([
                'customer',
                'branch',
                'warehouse',
                'company',
                'salesOrder',
                'items.product',
                'items.orderItem',
                'creator',
                'convertedInvoice',
            ]),
        ], 'bon-livraison-'.$deliveryNote->delivery_number.'.pdf');
    }

    public function convert(DeliveryNote $deliveryNote, Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        abort_if($workspace->companyId() !== $deliveryNote->company_id, 403);

        $payload = $request->validate([
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'notes' => ['nullable', 'string'],
        ]);

        $conversion = $this->deliveryNoteService->convertToInvoice($deliveryNote, $payload, $request->user());
        /** @var SalesInvoice $invoice */
        $invoice = $conversion['invoice'];
        $deliveryNote = $conversion['delivery_note'];

        $approval = $this->approvalFlowService->autoAdvance(
            $invoice,
            'sales',
            $request->user(),
            fn (SalesInvoice $pendingInvoice, $user) => $this->salesInvoiceService->approve($pendingInvoice, $user),
        );

        /** @var SalesInvoice $invoice */
        $invoice = $approval['document'];
        $this->outboundNotificationService->dispatchApprovalRequest($invoice, 'sales', $approval['next_step']);

        $this->activityLogger->log('delivery_notes.convert', 'Conversion bon de livraison en facture', $deliveryNote, [
            'delivery_number' => $deliveryNote->delivery_number,
            'invoice_number' => $invoice->invoice_number,
            'invoice_status' => $invoice->status,
        ]);

        $message = $approval['is_fully_approved']
            ? 'Bon de livraison converti en facture avec succes.'
            : 'Bon de livraison converti en facture. La facture suit maintenant le workflow ventes.';

        return redirect()->route('sales.show', $invoice)->with('success', $message);
    }

    private function filteredQuery(int $companyId, array $filters): Builder
    {
        return DeliveryNote::query()
            ->with(['customer', 'branch', 'warehouse', 'salesOrder', 'creator', 'convertedInvoice'])
            ->where('company_id', $companyId)
            ->when($filters['date_from'], fn (Builder $query, string $dateFrom) => $query->whereDate('delivery_date', '>=', $dateFrom))
            ->when($filters['date_to'], fn (Builder $query, string $dateTo) => $query->whereDate('delivery_date', '<=', $dateTo))
            ->when($filters['branch_id'], fn (Builder $query, int $branchId) => $query->where('branch_id', $branchId))
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['search'], function (Builder $query, string $search) {
                $like = '%'.$search.'%';

                $query->where(function (Builder $nested) use ($like) {
                    $nested->where('delivery_number', 'like', $like)
                        ->orWhere('notes', 'like', $like)
                        ->orWhereHas('customer', function (Builder $customerQuery) use ($like) {
                            $customerQuery->where('name', 'like', $like)
                                ->orWhere('code', 'like', $like)
                                ->orWhere('phone', 'like', $like);
                        })
                        ->orWhereHas('salesOrder', function (Builder $orderQuery) use ($like) {
                            $orderQuery->where('order_number', 'like', $like);
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
        if (! in_array($status, ['issued', 'invoiced'], true)) {
            $status = null;
        }

        return [
            'search' => $request->string('search')->trim()->value() ?: null,
            'date_from' => $request->string('date_from')->value() ?: null,
            'date_to' => $request->string('date_to')->value() ?: null,
            'branch_id' => $request->integer('branch_id') ?: null,
            'status' => $status,
        ];
    }

    private function summary(int $companyId): array
    {
        return [
            'issued' => (int) DeliveryNote::query()->where('company_id', $companyId)->where('status', 'issued')->count(),
            'invoiced' => (int) DeliveryNote::query()->where('company_id', $companyId)->where('status', 'invoiced')->count(),
        ];
    }
}

