<?php

namespace App\Modules\Purchases\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Company\Services\PricingService;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Partners\Models\Partner;
use App\Modules\Purchases\Models\PurchaseOrder;
use App\Modules\Purchases\Services\PurchaseOrderService;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderService $purchaseOrderService,
        private readonly PricingService $pricingService,
        private readonly ActivityLogger $activityLogger,
    ) {
    }

    public function index(CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        return view('purchase-orders.index', [
            'orders' => PurchaseOrder::query()
                ->with(['supplier', 'warehouse', 'creator'])
                ->where('company_id', $companyId)
                ->latest('order_date')
                ->latest('id')
                ->paginate(15),
        ]);
    }

    public function create(CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();
        abort_if(! $companyId || ! $branchId, 403);

        $hasProductParent = Schema::hasColumn('products', 'parent_id');
        $productQuery = Product::query()
            ->where('company_id', $companyId)
            ->purchasable()
            ->orderBy('name');
        $productColumns = ['id', 'company_id', 'sku', 'name', 'unit', 'description', 'purchase_description', 'purchase_price'];

        if ($hasProductParent) {
            $productQuery->with('parent:id,name');
            array_splice($productColumns, 2, 0, ['parent_id']);
        }

        return view('purchase-orders.create', [
            'suppliers' => Partner::query()
                ->suppliers()
                ->with('priceList:id,name')
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'company_id', 'code', 'name', 'price_list_id']),
            'warehouses' => Warehouse::query()
                ->where('company_id', $companyId)
                ->where('branch_id', $branchId)
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(['id', 'name', 'is_default']),
            'products' => $productQuery->get($productColumns),
            'defaultRows' => old('items', array_fill(0, 3, ['product_id' => '', 'description' => '', 'qty' => '', 'unit_cost' => ''])),
            'priceRules' => $this->pricingService->rulesPayloadForCompany($companyId),
            'branch' => $workspace->branch(),
        ]);
    }

    public function store(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();
        abort_if(! $companyId || ! $branchId, 403);

        $data = $request->validate([
            'supplier_id' => ['required', Rule::exists('partners', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('branch_id', $branchId))],
            'order_date' => ['required', 'date'],
            'expected_receipt_date' => ['nullable', 'date', 'after_or_equal:order_date'],
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
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
        ])->validate();

        $supplier = Partner::query()->suppliers()->where('company_id', $companyId)->findOrFail($data['supplier_id']);
        $warehouse = Warehouse::query()->findOrFail($data['warehouse_id']);
        $order = $this->purchaseOrderService->create(
            $companyId,
            $branchId,
            $warehouse,
            $supplier,
            $data,
            $this->purchaseOrderService->normalizeItems($companyId, $itemsInput, $supplier),
            $request->user(),
        );

        $this->activityLogger->log('purchase_orders.create', 'Creation commande fournisseur', $order, ['order_number' => $order->order_number]);

        return redirect()->route('purchase-orders.show', $order)->with('success', 'Commande fournisseur creee avec succes.');
    }

    public function confirm(PurchaseOrder $purchaseOrder, CurrentWorkspace $workspace): RedirectResponse
    {
        abort_if($workspace->companyId() !== $purchaseOrder->company_id, 403);

        $order = $this->purchaseOrderService->updateStatus($purchaseOrder, 'confirmed');
        $this->activityLogger->log('purchase_orders.confirm', 'Confirmation commande fournisseur', $order, ['order_number' => $order->order_number]);

        return redirect()->route('purchase-orders.show', $order)->with('success', 'Commande fournisseur confirmee.');
    }

    public function cancel(PurchaseOrder $purchaseOrder, CurrentWorkspace $workspace): RedirectResponse
    {
        abort_if($workspace->companyId() !== $purchaseOrder->company_id, 403);

        $order = $this->purchaseOrderService->updateStatus($purchaseOrder, 'cancelled');
        $this->activityLogger->log('purchase_orders.cancel', 'Annulation commande fournisseur', $order, ['order_number' => $order->order_number]);

        return redirect()->route('purchase-orders.show', $order)->with('success', 'Commande fournisseur annulee.');
    }

    public function show(PurchaseOrder $purchaseOrder, CurrentWorkspace $workspace): View
    {
        abort_if($workspace->companyId() !== $purchaseOrder->company_id, 403);

        return view('purchase-orders.show', [
            'order' => $purchaseOrder->load(['supplier', 'warehouse', 'branch', 'sourcePurchaseRequest', 'items.product', 'creator', 'goodsReceipts.purchaseBill']),
        ]);
    }
}
