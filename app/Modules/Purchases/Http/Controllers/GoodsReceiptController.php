<?php

namespace App\Modules\Purchases\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Purchases\Models\GoodsReceipt;
use App\Modules\Purchases\Models\PurchaseOrder;
use App\Modules\Purchases\Services\GoodsReceiptService;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GoodsReceiptController extends Controller
{
    public function __construct(
        private readonly GoodsReceiptService $goodsReceiptService,
        private readonly ActivityLogger $activityLogger,
    ) {
    }

    public function index(CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        return view('goods-receipts.index', [
            'receipts' => GoodsReceipt::query()
                ->with(['supplier', 'warehouse', 'purchaseOrder', 'purchaseBill', 'creator'])
                ->where('company_id', $companyId)
                ->latest('receipt_date')
                ->latest('id')
                ->paginate(15),
        ]);
    }

    public function create(Request $request, CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();
        abort_if(! $companyId || ! $branchId, 403);

        $selectedOrderId = $request->integer('order') ?: null;
        $orders = PurchaseOrder::query()
            ->with(['supplier', 'warehouse', 'items.product'])
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->whereIn('status', ['confirmed', 'partial_received'])
            ->orderByDesc('order_date')
            ->get()
            ->filter(fn (PurchaseOrder $order) => $order->hasRemainingReceipt())
            ->values();

        return view('goods-receipts.create', [
            'orders' => $orders,
            'selectedOrder' => $orders->firstWhere('id', $selectedOrderId),
            'branch' => $workspace->branch(),
        ]);
    }

    public function store(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();
        abort_if(! $companyId || ! $branchId, 403);

        $data = $request->validate([
            'order_id' => ['required', Rule::exists('purchase_orders', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('branch_id', $branchId))],
            'receipt_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array'],
        ]);

        $order = PurchaseOrder::query()->with(['supplier', 'warehouse', 'items.product'])->findOrFail($data['order_id']);
        $receipt = $this->goodsReceiptService->createFromOrder($order, $data, $request->user());

        $this->activityLogger->log('goods_receipts.create', 'Creation reception fournisseur', $receipt, ['receipt_number' => $receipt->receipt_number]);

        return redirect()->route('goods-receipts.show', $receipt)->with('success', 'Reception fournisseur enregistree avec succes.');
    }

    public function show(GoodsReceipt $goodsReceipt, CurrentWorkspace $workspace): View
    {
        abort_if($workspace->companyId() !== $goodsReceipt->company_id, 403);

        return view('goods-receipts.show', [
            'receipt' => $goodsReceipt->load(['supplier', 'warehouse', 'branch', 'purchaseOrder', 'purchaseBill', 'items.product', 'creator']),
            'stockMovements' => StockMovement::query()
                ->with(['product', 'productLot', 'creator', 'warehouse'])
                ->where('company_id', $goodsReceipt->company_id)
                ->where('reference_type', GoodsReceipt::class)
                ->where('reference_id', $goodsReceipt->id)
                ->orderBy('movement_date')
                ->get(),
        ]);
    }

    public function print(GoodsReceipt $goodsReceipt, CurrentWorkspace $workspace): View
    {
        abort_if($workspace->companyId() !== $goodsReceipt->company_id, 403);

        $receipt = $goodsReceipt->load([
            'company',
            'supplier',
            'warehouse',
            'branch',
            'purchaseOrder',
            'purchaseBill',
            'items.product',
            'items.productLots',
            'creator',
        ]);

        $stockMovements = StockMovement::query()
            ->with(['product', 'productLot', 'warehouse'])
            ->where('company_id', $goodsReceipt->company_id)
            ->where('reference_type', GoodsReceipt::class)
            ->where('reference_id', $goodsReceipt->id)
            ->orderBy('movement_date')
            ->get();

        return view('goods-receipts.print', [
            'company' => $receipt->company,
            'receipt' => $receipt,
            'stockMovements' => $stockMovements,
        ]);
    }
}
