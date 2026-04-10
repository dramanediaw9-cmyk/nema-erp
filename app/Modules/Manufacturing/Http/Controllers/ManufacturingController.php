<?php

namespace App\Modules\Manufacturing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Manufacturing\Models\ManufacturingBom;
use App\Modules\Manufacturing\Models\ProductionOrder;
use App\Modules\Manufacturing\Services\ManufacturingBomService;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ManufacturingController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly ManufacturingBomService $manufacturingBomService,
    )
    {
    }

    public function index(CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        return view('manufacturing.index', [
            'branches' => Branch::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'orders' => ProductionOrder::query()
                ->with(['branch', 'billOfMaterial'])
                ->where('company_id', $companyId)
                ->orderByDesc('planned_start_date')
                ->orderByDesc('id')
                ->get(),
            'boms' => ManufacturingBom::query()
                ->with(['branch', 'lines'])
                ->where('company_id', $companyId)
                ->orderBy('item_name')
                ->get(),
            'summary' => [
                'orders' => (int) ProductionOrder::query()->where('company_id', $companyId)->count(),
                'in_progress' => (int) ProductionOrder::query()->where('company_id', $companyId)->where('status', 'in_progress')->count(),
                'late' => (int) ProductionOrder::query()
                    ->where('company_id', $companyId)
                    ->whereNotIn('status', ['completed'])
                    ->whereDate('due_date', '<', now()->toDateString())
                    ->count(),
                'planned_qty' => (float) ProductionOrder::query()->where('company_id', $companyId)->sum('planned_quantity'),
                'boms' => (int) ManufacturingBom::query()->where('company_id', $companyId)->count(),
            ],
            'statusOptions' => $this->statusOptions(),
            'routingOptions' => $this->routingOptions(),
            'bomStatusOptions' => $this->bomStatusOptions(),
        ]);
    }

    public function store(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $payload = $request->validate([
            'order_number' => ['nullable', 'string', 'max:30', Rule::unique('production_orders', 'order_number')->where(fn ($query) => $query->where('company_id', $companyId))],
            'reference' => ['nullable', 'string', 'max:255'],
            'bill_of_material_id' => ['nullable', Rule::exists('manufacturing_boms', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'item_name' => ['required', 'string', 'max:255'],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'planned_quantity' => ['required', 'numeric', 'min:0.001'],
            'completed_quantity' => ['nullable', 'numeric', 'min:0'],
            'material_cost_estimate' => ['nullable', 'numeric', 'min:0'],
            'actual_material_cost' => ['nullable', 'numeric', 'min:0'],
            'planned_start_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:planned_start_date'],
            'status' => ['required', Rule::in(array_keys($this->statusOptions()))],
            'routing_stage' => ['required', Rule::in(array_keys($this->routingOptions()))],
            'notes' => ['nullable', 'string'],
        ]);

        $order = ProductionOrder::query()->create([
            'company_id' => $companyId,
            'branch_id' => $payload['branch_id'] ?? null,
            'order_number' => ($payload['order_number'] ?? null) ?: $this->generateOrderNumber($companyId),
            'reference' => $payload['reference'] ?? null,
            'bill_of_material_id' => $payload['bill_of_material_id'] ?? null,
            'item_name' => $payload['item_name'],
            'planned_quantity' => $payload['planned_quantity'],
            'completed_quantity' => $payload['completed_quantity'] ?? 0,
            'material_cost_estimate' => $payload['material_cost_estimate'] ?? 0,
            'actual_material_cost' => $payload['actual_material_cost'] ?? 0,
            'planned_start_date' => $payload['planned_start_date'],
            'due_date' => $payload['due_date'] ?? null,
            'status' => $payload['status'],
            'routing_stage' => $payload['routing_stage'],
            'notes' => $payload['notes'] ?? null,
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        $this->activityLogger->log('manufacturing.orders.create', 'Creation ordre de production', $order, [
            'order_number' => $order->order_number,
            'status' => $order->status,
        ]);

        return redirect()->route('manufacturing.index')->with('success', 'Ordre de production enregistre avec succes.');
    }

    public function storeBillOfMaterial(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $payload = $request->validate([
            'code' => ['nullable', 'string', 'max:30', Rule::unique('manufacturing_boms', 'code')->where(fn ($query) => $query->where('company_id', $companyId))],
            'item_name' => ['required', 'string', 'max:255'],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'output_quantity' => ['required', 'numeric', 'min:0.001'],
            'status' => ['required', Rule::in(array_keys($this->bomStatusOptions()))],
            'components' => ['required', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $lines = $this->manufacturingBomService->parseComponentMatrix($payload['components']);
        $bom = $this->manufacturingBomService->createBillOfMaterial($companyId, $request->user()?->id, $payload, $lines);

        $this->activityLogger->log('manufacturing.boms.create', 'Creation nomenclature production', $bom, [
            'code' => $bom->code,
            'status' => $bom->status,
        ]);

        return redirect()->route('manufacturing.index')->with('success', 'Nomenclature enregistree avec succes.');
    }

    private function statusOptions(): array
    {
        return [
            'planned' => 'Planifie',
            'in_progress' => 'En cours',
            'quality' => 'Controle qualite',
            'completed' => 'Termine',
            'on_hold' => 'Suspendu',
        ];
    }

    private function routingOptions(): array
    {
        return [
            'preparation' => 'Preparation',
            'assembly' => 'Assemblage',
            'packing' => 'Conditionnement',
            'dispatch' => 'Expedition',
        ];
    }

    private function bomStatusOptions(): array
    {
        return [
            'active' => 'Active',
            'pilot' => 'Pilote',
            'archived' => 'Archivee',
        ];
    }

    private function generateOrderNumber(int $companyId): string
    {
        $sequence = ProductionOrder::query()->where('company_id', $companyId)->count() + 1;

        return 'OF-'.now()->format('Y').'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
