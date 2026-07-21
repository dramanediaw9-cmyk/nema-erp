<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\StockTransferService;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StockTransferController extends Controller
{
    public function __construct(
        private readonly StockTransferService $stockTransferService,
        private readonly ActivityLogger $activityLogger,
    ) {
    }

    public function index(CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        return view('transfers.index', [
            'transfers' => StockTransfer::query()
                ->with(['sourceWarehouse', 'destinationWarehouse', 'creator'])
                ->where('company_id', $companyId)
                ->latest('transfer_date')
                ->latest('id')
                ->paginate(15),
        ]);
    }

    public function create(CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();
        abort_if(! $companyId || ! $branchId, 403);
        $defaultRows = old('items', array_fill(0, 6, ['product_id' => '', 'description' => '', 'qty' => '', 'unit_cost' => '']));

        return view('transfers.create', [
            'warehouses' => Warehouse::query()->where('company_id', $companyId)->where('branch_id', $branchId)->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(),
            'products' => app(\App\Modules\Catalog\Services\ProductOptionService::class)->initial($companyId, 'stockable', collect($defaultRows)->pluck('product_id')->all()),
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
            'source_warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('branch_id', $branchId))],
            'destination_warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('branch_id', $branchId))],
            'transfer_date' => ['required', 'date'],
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
                'items.*.product_id' => ['required', Rule::exists('products', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
                'items.*.description' => ['nullable', 'string', 'max:255'],
                'items.*.qty' => ['required', 'numeric', 'gt:0'],
                'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            ]
        )->validate();

        $sourceWarehouse = Warehouse::query()->findOrFail($data['source_warehouse_id']);
        $destinationWarehouse = Warehouse::query()->findOrFail($data['destination_warehouse_id']);

        $transfer = $this->stockTransferService->create(
            $companyId,
            $branchId,
            $sourceWarehouse,
            $destinationWarehouse,
            $data,
            $this->stockTransferService->normalizeItems($companyId, $itemsInput),
            $request->user(),
        );

        $this->activityLogger->log('transfers.create', 'Creation transfert de stock', $transfer, ['transfer_number' => $transfer->transfer_number]);

        return redirect()->route('transfers.show', $transfer)->with('success', 'Transfert de stock enregistre avec succes.');
    }

    public function show(StockTransfer $transfer, CurrentWorkspace $workspace): View
    {
        abort_if($workspace->companyId() !== $transfer->company_id, 403);

        return view('transfers.show', [
            'transfer' => $transfer->load(['sourceWarehouse', 'destinationWarehouse', 'items.product', 'creator']),
        ]);
    }

    public function print(StockTransfer $transfer, CurrentWorkspace $workspace): View
    {
        abort_if($workspace->companyId() !== $transfer->company_id, 403);

        return view('transfers.print', [
            'transfer' => $transfer->load(['company', 'branch', 'sourceWarehouse', 'destinationWarehouse', 'items.product', 'creator']),
        ]);
    }
}
