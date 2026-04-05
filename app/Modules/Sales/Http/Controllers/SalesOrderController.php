<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Approvals\Services\ApprovalFlowService;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Services\PricingService;
use App\Modules\Core\Notifications\Services\OutboundNotificationService;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Partners\Models\Partner;
use App\Modules\Purchases\Models\PurchaseRequest;
use App\Modules\Purchases\Services\PurchaseRequestService;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Services\SalesInvoiceService;
use App\Modules\Sales\Services\SalesOrderService;
use App\Modules\Sales\Services\SalesPortalLinkService;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use App\Support\Exports\CsvExportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesOrderController extends Controller
{
    public function __construct(
        private readonly SalesOrderService $salesOrderService,
        private readonly SalesInvoiceService $salesInvoiceService,
        private readonly SalesPortalLinkService $salesPortalLinkService,
        private readonly StockService $stockService,
        private readonly PricingService $pricingService,
        private readonly PurchaseRequestService $purchaseRequestService,
        private readonly ApprovalFlowService $approvalFlowService,
        private readonly OutboundNotificationService $outboundNotificationService,
        private readonly ActivityLogger $activityLogger,
        private readonly CsvExportService $csvExportService,
    ) {
    }

    public function index(Request $request, CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $filters = $this->filters($request);

        return view('orders.index', [
            'orders' => $this->filteredQuery($companyId, $filters)
                ->latest('order_date')
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

        $rows = $this->filteredQuery($companyId, $this->filters($request))
            ->orderBy('order_date')
            ->orderBy('id')
            ->get()
            ->map(fn (SalesOrder $order) => [
                $order->order_number,
                $order->order_date?->format('d/m/Y'),
                $order->requested_delivery_date?->format('d/m/Y'),
                $order->commitment_date?->format('d/m/Y'),
                $order->customer?->name,
                $order->customer_reference,
                $order->salesperson_name,
                $order->branch?->name,
                $order->warehouse?->name,
                $order->status,
                number_format((float) $order->total, 2, '.', ''),
            ]);

        return $this->csvExportService->download('commandes-clients.csv', [
            'Numero', 'Date', 'Livraison souhaitee', 'Date engagement', 'Client', 'Ref client', 'Commercial', 'Agence', 'Entrepot', 'Statut', 'Total',
        ], $rows);
    }

    public function create(CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();
        abort_if(! $companyId || ! $branchId, 403);

        return view('orders.create', [
            'customers' => Partner::query()->customers()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(),
            'products' => Product::query()->with('parent')->where('company_id', $companyId)->saleable()->orderBy('name')->get(),
            'warehouses' => Warehouse::query()->where('company_id', $companyId)->where('branch_id', $branchId)->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(),
            'defaultRows' => old('items', array_fill(0, 6, ['product_id' => '', 'description' => '', 'qty' => '', 'unit_price' => ''])),
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
            'order_date' => ['required', 'date'],
            'requested_delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'customer_reference' => ['nullable', 'string', 'max:120'],
            'source_document' => ['nullable', 'string', 'max:120'],
            'salesperson_name' => ['nullable', 'string', 'max:120'],
            'commitment_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'notes' => ['nullable', 'string'],
            'delivery_instruction' => ['nullable', 'string'],
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
                'items.required' => 'Ajoute au moins une ligne a la commande.',
                'items.min' => 'Ajoute au moins une ligne a la commande.',
            ]
        )->validate();

        $customer = Partner::query()->customers()->where('company_id', $companyId)->findOrFail($payload['customer_id']);
        $order = $this->salesOrderService->create(
            $companyId,
            $branchId,
            $customer,
            $payload,
            $this->salesOrderService->normalizeItems($companyId, $itemsInput, $customer, $request->user()),
            $request->user(),
        );

        $this->activityLogger->log('orders.create', 'Creation commande client', $order, [
            'order_number' => $order->order_number,
            'warehouse_id' => $order->warehouse_id,
            'total' => $order->total,
            'customer_reference' => $order->customer_reference,
            'salesperson_name' => $order->salesperson_name,
        ]);

        return redirect()->route('orders.show', $order)->with('success', 'Commande client creee avec succes.');
    }

    public function show(SalesOrder $order, CurrentWorkspace $workspace): View
    {
        abort_if($workspace->companyId() !== $order->company_id, 403);

        $order = $order->load([
            'customer',
            'branch',
            'warehouse',
            'company',
            'items.product',
            'items.deliveryItems.deliveryNote',
            'creator',
            'convertedInvoice',
            'originQuote',
            'deliveryNotes.warehouse',
            'deliveryNotes.convertedInvoice',
            'latestPortalAction',
            'generatedPurchaseRequests.convertedPurchaseOrder',
        ]);

        $lineCoverage = $order->items
            ->mapWithKeys(fn ($item) => [$item->id => $this->lineCoverageSnapshot($order, $item)])
            ->all();

        return view('orders.show', [
            'order' => $order,
            'portal' => $this->salesPortalLinkService->orderPortalData($order),
            'lineCoverage' => $lineCoverage,
            'coverageSummary' => $this->coverageSummary($lineCoverage),
            'generatedPurchaseRequests' => $order->generatedPurchaseRequests->sortByDesc('id')->values(),
            'openGeneratedPurchaseRequest' => $this->purchaseRequestService->findOpenGeneratedRequestForOrder($order),
        ]);
    }

    public function print(SalesOrder $order, CurrentWorkspace $workspace): View
    {
        abort_if($workspace->companyId() !== $order->company_id, 403);

        return view('orders.print', [
            'order' => $order->load(['customer', 'branch', 'warehouse', 'company', 'items.product', 'creator', 'convertedInvoice', 'originQuote']),
        ]);
    }

    public function confirm(SalesOrder $order, CurrentWorkspace $workspace): RedirectResponse
    {
        abort_if($workspace->companyId() !== $order->company_id, 403);

        $order = $this->salesOrderService->updateStatus($order, 'confirmed');
        $this->activityLogger->log('orders.confirm', 'Confirmation commande client', $order, [
            'order_number' => $order->order_number,
            'warehouse_id' => $order->warehouse_id,
        ]);

        return redirect()->route('orders.show', $order)->with('success', 'Commande client confirmee.');
    }

    public function cancel(SalesOrder $order, CurrentWorkspace $workspace): RedirectResponse
    {
        abort_if($workspace->companyId() !== $order->company_id, 403);

        $order = $this->salesOrderService->updateStatus($order, 'cancelled');
        $this->activityLogger->log('orders.cancel', 'Annulation commande client', $order, ['order_number' => $order->order_number]);

        return redirect()->route('orders.show', $order)->with('success', 'Commande client annulee.');
    }

    public function convert(SalesOrder $order, Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        abort_if($workspace->companyId() !== $order->company_id, 403);

        $payload = $request->validate([
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'notes' => ['nullable', 'string'],
        ]);

        $conversion = $this->salesOrderService->convertToInvoice($order, $payload, $request->user());
        /** @var SalesInvoice $invoice */
        $invoice = $conversion['invoice'];
        $order = $conversion['order'];

        $approval = $this->approvalFlowService->autoAdvance(
            $invoice,
            'sales',
            $request->user(),
            fn (SalesInvoice $pendingInvoice, $user) => $this->salesInvoiceService->approve($pendingInvoice, $user),
        );

        /** @var SalesInvoice $invoice */
        $invoice = $approval['document'];
        $this->outboundNotificationService->dispatchApprovalRequest($invoice, 'sales', $approval['next_step']);

        $this->activityLogger->log('orders.convert', 'Conversion commande en facture', $order, [
            'order_number' => $order->order_number,
            'invoice_number' => $invoice->invoice_number,
            'invoice_status' => $invoice->status,
        ]);

        $message = $approval['is_fully_approved']
            ? 'Commande convertie en facture avec succes.'
            : 'Commande convertie en facture. La facture suit maintenant le workflow ventes.';

        return redirect()->route('sales.show', $invoice)->with('success', $message);
    }

    public function generatePurchaseRequest(SalesOrder $order, CurrentWorkspace $workspace, Request $request): RedirectResponse
    {
        abort_if($workspace->companyId() !== $order->company_id, 403);

        $order = $order->load(['customer', 'warehouse', 'items.product', 'generatedPurchaseRequests']);

        if (! $order->warehouse) {
            return redirect()->route('orders.show', $order)->with('error', 'Aucun depot n est defini sur cette commande.');
        }

        $openRequest = $this->purchaseRequestService->findOpenGeneratedRequestForOrder($order);
        if ($openRequest) {
            return redirect()->route('purchase-requests.show', $openRequest)->with('error', 'Une demande d achat issue de cette commande est deja ouverte.');
        }

        $lineCoverage = $order->items
            ->mapWithKeys(fn ($item) => [$item->id => $this->lineCoverageSnapshot($order, $item)])
            ->all();

        $atRiskRows = $this->atRiskCoverageRows($order, $lineCoverage);
        if ($atRiskRows->isEmpty()) {
            return redirect()->route('orders.show', $order)->with('error', 'Aucune ligne de commande ne necessite de demande d achat supplementaire.');
        }

        $itemsPayload = $atRiskRows
            ->map(fn (array $row) => [
                'product_id' => $row['item']->product_id,
                'origin_sales_order_item_id' => $row['item']->id,
                'description' => '',
                'qty' => $row['coverage']['shortage_qty'],
                'estimated_unit_cost' => '',
            ])
            ->all();

        $purchaseRequest = $this->purchaseRequestService->create(
            companyId: $order->company_id,
            branchId: $order->branch_id,
            warehouse: $order->warehouse,
            payload: [
                'origin_sales_order_id' => $order->id,
                'request_date' => now()->toDateString(),
                'needed_by_date' => ($order->commitment_date ?? $order->requested_delivery_date)?->toDateString(),
                'priority' => $this->purchaseRequestPriority($order),
                'notes' => $this->generatedPurchaseRequestNotes($order, $atRiskRows),
            ],
            items: $this->purchaseRequestService->normalizeItems($order->company_id, $itemsPayload),
            user: $request->user(),
        );

        $this->activityLogger->log('orders.generate_purchase_request', 'Generation demande d achat depuis commande client', $purchaseRequest, [
            'request_number' => $purchaseRequest->request_number,
            'order_number' => $order->order_number,
            'origin_sales_order_id' => $order->id,
        ]);

        return redirect()->route('purchase-requests.show', $purchaseRequest)->with('success', 'Demande d achat generee depuis les lignes a risque de la commande.');
    }

    private function lineCoverageSnapshot(SalesOrder $order, $item): array
    {
        $product = $item->product;
        $remainingQty = round($item->remainingQty(), 3);

        if (! $product || $product->type !== 'stockable') {
            return [
                'status' => 'service',
                'label' => 'Sans contrainte stock',
                'tone' => 'badge-muted',
                'required_qty' => $remainingQty,
                'available_now' => null,
                'incoming_qty' => 0.0,
                'coverable_qty' => null,
                'shortage_qty' => 0.0,
                'next_incoming_date' => null,
                'detail' => 'Article non stockable ou service.',
            ];
        }

        if ($remainingQty <= 0.0001) {
            return [
                'status' => 'completed',
                'label' => 'Entierement livre',
                'tone' => 'badge-success',
                'required_qty' => 0.0,
                'available_now' => 0.0,
                'incoming_qty' => 0.0,
                'coverable_qty' => 0.0,
                'shortage_qty' => 0.0,
                'next_incoming_date' => null,
                'detail' => 'Aucune quantite restante a couvrir.',
            ];
        }

        $excludeOrderId = in_array($order->status, ['confirmed', 'partial_delivered'], true) ? $order->id : null;
        $coverage = $this->stockService->forecastCoverage(
            $product,
            $order->company_id,
            $order->branch_id,
            $remainingQty,
            $order->warehouse_id,
            $excludeOrderId,
        );

        $isReservedFlow = in_array($order->status, ['confirmed', 'partial_delivered'], true);

        if ($coverage['required_qty'] <= $coverage['available_now']) {
            return [
                ...$coverage,
                'status' => $isReservedFlow ? 'reserved' : 'available',
                'label' => $isReservedFlow ? 'Reserve sur stock' : 'Disponible immediatement',
                'tone' => 'badge-success',
                'detail' => $isReservedFlow
                    ? 'Le depot peut couvrir le reliquat sans nouvel achat.'
                    : 'Le stock disponible apres reservations couvre deja cette ligne.',
            ];
        }

        if ($coverage['required_qty'] <= $coverage['coverable_qty']) {
            $dateLabel = $coverage['next_incoming_date'] ? $coverage['next_incoming_date']->format('d/m/Y') : 'date a confirmer';

            return [
                ...$coverage,
                'status' => $isReservedFlow ? 'secured_incoming' : 'incoming',
                'label' => $isReservedFlow ? 'Couvert avec appro attendu' : 'Couvert par approvisionnement',
                'tone' => 'badge-warning',
                'detail' => 'Complement attendu le '.$dateLabel.'.',
            ];
        }

        return [
            ...$coverage,
            'status' => $isReservedFlow ? 'at_risk' : 'uncovered',
            'label' => $isReservedFlow ? 'Reservation a risque' : 'Rupture a couvrir',
            'tone' => 'badge-danger',
            'detail' => 'Il manque '.number_format((float) $coverage['shortage_qty'], 3, ',', ' ').' apres le stock et les achats attendus.',
        ];
    }

    private function coverageSummary(array $lineCoverage): array
    {
        $summary = [
            'covered_now' => 0,
            'covered_incoming' => 0,
            'at_risk' => 0,
            'service' => 0,
        ];

        foreach ($lineCoverage as $coverage) {
            switch ($coverage['status']) {
                case 'available':
                case 'reserved':
                case 'completed':
                    $summary['covered_now']++;
                    break;
                case 'incoming':
                case 'secured_incoming':
                    $summary['covered_incoming']++;
                    break;
                case 'service':
                    $summary['service']++;
                    break;
                default:
                    $summary['at_risk']++;
                    break;
            }
        }

        $summary['total'] = count($lineCoverage);

        return $summary;
    }

    private function atRiskCoverageRows(SalesOrder $order, array $lineCoverage): Collection
    {
        return $order->items
            ->map(function ($item) use ($lineCoverage) {
                $coverage = $lineCoverage[$item->id] ?? null;

                if (! $coverage || ! in_array($coverage['status'], ['at_risk', 'uncovered'], true)) {
                    return null;
                }

                if ((float) ($coverage['shortage_qty'] ?? 0) <= 0.0001) {
                    return null;
                }

                return [
                    'item' => $item,
                    'coverage' => $coverage,
                ];
            })
            ->filter()
            ->values();
    }

    private function purchaseRequestPriority(SalesOrder $order): string
    {
        $targetDate = $order->commitment_date ?? $order->requested_delivery_date;
        if (! $targetDate) {
            return 'normal';
        }

        $days = now()->startOfDay()->diffInDays($targetDate, false);

        if ($days <= 2) {
            return 'urgent';
        }

        if ($days <= 7) {
            return 'high';
        }

        return 'normal';
    }

    private function generatedPurchaseRequestNotes(SalesOrder $order, Collection $atRiskRows): string
    {
        $products = $atRiskRows
            ->map(fn (array $row) => $row['item']->product?->display_name ?? $row['item']->description)
            ->filter()
            ->implode(', ');

        return trim('Generee depuis la commande '.$order->order_number.' pour couvrir les lignes en rupture'.($products ? ' : '.$products : '').'.');
    }

    private function filteredQuery(int $companyId, array $filters): Builder
    {
        return SalesOrder::query()
            ->with(['customer', 'branch', 'warehouse', 'creator', 'convertedInvoice', 'deliveryNotes', 'originQuote', 'items'])
            ->where('company_id', $companyId)
            ->when($filters['date_from'], fn (Builder $query, string $dateFrom) => $query->whereDate('order_date', '>=', $dateFrom))
            ->when($filters['date_to'], fn (Builder $query, string $dateTo) => $query->whereDate('order_date', '<=', $dateTo))
            ->when($filters['branch_id'], fn (Builder $query, int $branchId) => $query->where('branch_id', $branchId))
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['search'], function (Builder $query, string $search) {
                $like = '%'.$search.'%';

                $query->where(function (Builder $nested) use ($like) {
                    $nested->where('order_number', 'like', $like)
                        ->orWhere('notes', 'like', $like)
                        ->orWhere('customer_reference', 'like', $like)
                        ->orWhere('source_document', 'like', $like)
                        ->orWhere('salesperson_name', 'like', $like)
                        ->orWhere('delivery_instruction', 'like', $like)
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
        if (! in_array($status, ['draft', 'confirmed', 'partial_delivered', 'delivered', 'cancelled', 'converted'], true)) {
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
            'draft' => (int) SalesOrder::query()->where('company_id', $companyId)->where('status', 'draft')->count(),
            'confirmed' => (int) SalesOrder::query()->where('company_id', $companyId)->where('status', 'confirmed')->count(),
            'partial_delivered' => (int) SalesOrder::query()->where('company_id', $companyId)->where('status', 'partial_delivered')->count(),
            'delivered' => (int) SalesOrder::query()->where('company_id', $companyId)->where('status', 'delivered')->count(),
            'converted' => (int) SalesOrder::query()->where('company_id', $companyId)->where('status', 'converted')->count(),
            'cancelled' => (int) SalesOrder::query()->where('company_id', $companyId)->where('status', 'cancelled')->count(),
        ];
    }
}


