<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesApiActor;
use App\Modules\Manufacturing\Models\ProductionOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductionOrderController
{
    use ResolvesApiActor;

    public function index(Request $request): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('manufacturing.view'), 403);

        $status = $request->string('status')->trim()->value();
        $routing = $request->string('routing_stage')->trim()->value();
        $search = $request->string('search')->trim()->value();

        $orders = ProductionOrder::query()
            ->with(['branch', 'billOfMaterial'])
            ->where('company_id', $company->id)
            ->when($request->integer('branch_id') > 0, fn (Builder $query) => $query->where('branch_id', $request->integer('branch_id')))
            ->when(in_array($status, ['planned', 'in_progress', 'quality', 'completed', 'on_hold'], true), fn (Builder $query) => $query->where('status', $status))
            ->when(in_array($routing, ['preparation', 'assembly', 'packing', 'dispatch'], true), fn (Builder $query) => $query->where('routing_stage', $routing))
            ->when($search !== '', function (Builder $query) use ($search) {
                $like = '%'.$search.'%';

                $query->where(function (Builder $nested) use ($like) {
                    $nested->where('order_number', 'like', $like)
                        ->orWhere('reference', 'like', $like)
                        ->orWhere('item_name', 'like', $like);
                });
            })
            ->orderByDesc('planned_start_date')
            ->paginate(min(max((int) $request->integer('per_page', 50), 1), 200));

        return response()->json($orders);
    }

    public function show(Request $request, ProductionOrder $productionOrder): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        abort_unless($productionOrder->company_id === $company->id, 404);

        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('manufacturing.view'), 403);

        return response()->json($productionOrder->load(['branch', 'creator', 'billOfMaterial']));
    }

    public function store(Request $request): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('manufacturing.manage'), 403);

        $data = $request->validate([
            'order_number' => ['nullable', 'string', 'max:30', Rule::unique('production_orders', 'order_number')->where(fn ($query) => $query->where('company_id', $company->id))],
            'reference' => ['nullable', 'string', 'max:255'],
            'bill_of_material_id' => ['nullable', Rule::exists('manufacturing_boms', 'id')->where(fn ($query) => $query->where('company_id', $company->id))],
            'item_name' => ['required', 'string', 'max:255'],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where(fn ($query) => $query->where('company_id', $company->id))],
            'planned_quantity' => ['required', 'numeric', 'min:0.001'],
            'completed_quantity' => ['nullable', 'numeric', 'min:0'],
            'material_cost_estimate' => ['nullable', 'numeric', 'min:0'],
            'actual_material_cost' => ['nullable', 'numeric', 'min:0'],
            'planned_start_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:planned_start_date'],
            'status' => ['nullable', Rule::in(['planned', 'in_progress', 'quality', 'completed', 'on_hold'])],
            'routing_stage' => ['nullable', Rule::in(['preparation', 'assembly', 'packing', 'dispatch'])],
            'notes' => ['nullable', 'string'],
        ]);

        $order = ProductionOrder::query()->create([
            'company_id' => $company->id,
            'branch_id' => $data['branch_id'] ?? null,
            'order_number' => ($data['order_number'] ?? null) ?: $this->generateNumber($company->id),
            'reference' => $data['reference'] ?? null,
            'bill_of_material_id' => $data['bill_of_material_id'] ?? null,
            'item_name' => $data['item_name'],
            'planned_quantity' => $data['planned_quantity'],
            'completed_quantity' => $data['completed_quantity'] ?? 0,
            'material_cost_estimate' => $data['material_cost_estimate'] ?? 0,
            'actual_material_cost' => $data['actual_material_cost'] ?? 0,
            'planned_start_date' => $data['planned_start_date'],
            'due_date' => $data['due_date'] ?? null,
            'status' => $data['status'] ?? 'planned',
            'routing_stage' => $data['routing_stage'] ?? 'preparation',
            'notes' => $data['notes'] ?? null,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        return response()->json($order->load(['branch', 'billOfMaterial']), 201);
    }

    private function generateNumber(int $companyId): string
    {
        return app(\App\Modules\Core\Company\Services\DocumentNumberService::class)
            ->nextNumber($companyId, 'production_order_number');
    }
}
