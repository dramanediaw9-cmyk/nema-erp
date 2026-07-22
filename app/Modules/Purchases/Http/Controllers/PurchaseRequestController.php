<?php

namespace App\Modules\Purchases\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Partners\Models\Partner;
use App\Modules\Purchases\Models\PurchaseRequest;
use App\Modules\Purchases\Services\PurchaseRequestService;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PurchaseRequestController extends Controller
{
    public function __construct(
        private readonly PurchaseRequestService $purchaseRequestService,
        private readonly ActivityLogger $activityLogger,
    ) {
    }

    public function index(CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        return view('purchase-requests.index', [
            'requests' => PurchaseRequest::query()
                ->with(['warehouse', 'creator', 'approver', 'convertedPurchaseOrder', 'generatedPurchaseOrders.supplier', 'originSalesOrder.customer'])
                ->where('company_id', $companyId)
                ->latest('request_date')
                ->latest('id')
                ->paginate(15),
        ]);
    }

    public function create(CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();
        abort_if(! $companyId || ! $branchId, 403);
        $defaultRows = old('items', array_fill(0, 6, ['product_id' => '', 'description' => '', 'qty' => '', 'estimated_unit_cost' => '']));

        return view('purchase-requests.create', [
            'warehouses' => Warehouse::query()->where('company_id', $companyId)->where('branch_id', $branchId)->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(),
            'products' => app(\App\Modules\Catalog\Services\ProductOptionService::class)->initial($companyId, 'purchasable', collect($defaultRows)->pluck('product_id')->all()),
            'defaultRows' => $defaultRows,
            'branch' => $workspace->branch(),
        ]);
    }

    public function store(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();
        abort_if(! $companyId || ! $branchId, 403);

        $data = $request->validate([
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('branch_id', $branchId))],
            'request_date' => ['required', 'date'],
            'needed_by_date' => ['nullable', 'date', 'after_or_equal:request_date'],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'notes' => ['nullable', 'string'],
        ]);

        $itemsInput = collect($request->input('items', []))
            ->map(fn ($item) => is_array($item) ? $item : [])
            ->filter(fn (array $item) => filled($item['product_id'] ?? null))
            ->values()
            ->all();

        Validator::make(['items' => $itemsInput], [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', Rule::exists('products', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('purchase_ok', true)->where('is_active', true))],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.estimated_unit_cost' => ['nullable', 'numeric', 'min:0'],
        ])->validate();

        $warehouse = Warehouse::query()->where('company_id', $companyId)->findOrFail($data['warehouse_id']);
        $purchaseRequest = $this->purchaseRequestService->create(
            companyId: $companyId,
            branchId: $branchId,
            warehouse: $warehouse,
            payload: $data,
            items: $this->purchaseRequestService->normalizeItems($companyId, $itemsInput),
            user: $request->user(),
        );

        $this->activityLogger->log('purchase_requests.create', 'Creation demande d achat', $purchaseRequest, [
            'request_number' => $purchaseRequest->request_number,
        ]);

        return redirect()->route('purchase-requests.show', $purchaseRequest)->with('success', 'Demande d achat creee avec succes.');
    }

    public function approve(PurchaseRequest $purchaseRequest, CurrentWorkspace $workspace, Request $request): RedirectResponse
    {
        abort_if($workspace->companyId() !== $purchaseRequest->company_id, 403);

        $purchaseRequest = $this->purchaseRequestService->approve($purchaseRequest, $request->user());
        $this->activityLogger->log('purchase_requests.approve', 'Approbation demande d achat', $purchaseRequest, [
            'request_number' => $purchaseRequest->request_number,
        ]);

        return redirect()->route('purchase-requests.show', $purchaseRequest)->with('success', 'Demande d achat approuvee.');
    }

    public function reject(PurchaseRequest $purchaseRequest, CurrentWorkspace $workspace, Request $request): RedirectResponse
    {
        abort_if($workspace->companyId() !== $purchaseRequest->company_id, 403);

        $purchaseRequest = $this->purchaseRequestService->reject($purchaseRequest, $request->user());
        $this->activityLogger->log('purchase_requests.reject', 'Rejet demande d achat', $purchaseRequest, [
            'request_number' => $purchaseRequest->request_number,
        ]);

        return redirect()->route('purchase-requests.show', $purchaseRequest)->with('success', 'Demande d achat rejetee.');
    }

    public function convert(PurchaseRequest $purchaseRequest, CurrentWorkspace $workspace, Request $request): RedirectResponse
    {
        $companyId = $workspace->companyId();
        abort_if($companyId !== $purchaseRequest->company_id, 403);

        $data = $request->validate([
            'supplier_id' => ['required', Rule::exists('partners', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
        ]);

        $supplier = Partner::query()->suppliers()->where('company_id', $companyId)->findOrFail($data['supplier_id']);
        $result = $this->purchaseRequestService->convertToOrder($purchaseRequest, $supplier, $request->user());

        $this->activityLogger->log('purchase_requests.convert', 'Conversion demande d achat en commande fournisseur', $result['request'], [
            'request_number' => $result['request']->request_number,
            'order_number' => $result['order']->order_number,
            'supplier_name' => $supplier->name,
        ]);

        return redirect()->route('purchase-orders.show', $result['order'])->with('success', 'Commande fournisseur creee depuis la demande d achat.');
    }

    public function autoConvert(PurchaseRequest $purchaseRequest, CurrentWorkspace $workspace, Request $request): RedirectResponse
    {
        abort_if($workspace->companyId() !== $purchaseRequest->company_id, 403);

        $result = $this->purchaseRequestService->autoConvertToRecommendedOrders($purchaseRequest, $request->user());
        $orders = $result['orders'];

        $this->activityLogger->log('purchase_requests.auto_convert', 'Generation automatique commandes fournisseurs recommandees', $result['request'], [
            'request_number' => $result['request']->request_number,
            'order_numbers' => $orders->pluck('order_number')->values()->all(),
            'suppliers' => $orders->pluck('supplier.name')->filter()->values()->all(),
        ]);

        return redirect()->route('purchase-requests.show', $result['request'])->with(
            'success',
            $orders->count() > 1
                ? $orders->count().' commandes fournisseurs recommandees ont ete generees.'
                : 'La commande fournisseur recommandee a ete generee.'
        );
    }

    public function show(PurchaseRequest $purchaseRequest, CurrentWorkspace $workspace): View
    {
        abort_if($workspace->companyId() !== $purchaseRequest->company_id, 403);

        $purchaseRequest = $purchaseRequest->load([
            'warehouse',
            'branch',
            'creator',
            'approver',
            'rejector',
            'convertedPurchaseOrder',
            'generatedPurchaseOrders.supplier',
            'originSalesOrder.customer',
            'items.product.supplierInfos.supplier',
            'items.originSalesOrderItem.product',
        ]);

        return view('purchase-requests.show', [
            'purchaseRequest' => $purchaseRequest,
            'suppliers' => Partner::query()->suppliers()->where('company_id', $purchaseRequest->company_id)->where('is_active', true)->orderBy('name')->get(),
            'supplierPlan' => $this->purchaseRequestService->supplierRecommendationPlan($purchaseRequest),
        ]);
    }
}
