<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesApiActor;
use App\Modules\Manufacturing\Models\ManufacturingBom;
use App\Modules\Manufacturing\Services\ManufacturingBomService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ManufacturingBomController
{
    use ResolvesApiActor;

    public function __construct(private readonly ManufacturingBomService $manufacturingBomService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('manufacturing.view'), 403);

        $status = $request->string('status')->trim()->value();
        $search = $request->string('search')->trim()->value();

        $boms = ManufacturingBom::query()
            ->with(['branch', 'lines'])
            ->where('company_id', $company->id)
            ->when($request->integer('branch_id') > 0, fn (Builder $query) => $query->where('branch_id', $request->integer('branch_id')))
            ->when(in_array($status, ['active', 'pilot', 'archived'], true), fn (Builder $query) => $query->where('status', $status))
            ->when($search !== '', function (Builder $query) use ($search) {
                $like = '%'.$search.'%';

                $query->where(function (Builder $nested) use ($like) {
                    $nested->where('code', 'like', $like)
                        ->orWhere('item_name', 'like', $like);
                });
            })
            ->orderBy('item_name')
            ->paginate(min(max((int) $request->integer('per_page', 50), 1), 200));

        return response()->json($boms);
    }

    public function show(Request $request, ManufacturingBom $manufacturingBom): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        abort_unless($manufacturingBom->company_id === $company->id, 404);

        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('manufacturing.view'), 403);

        return response()->json($manufacturingBom->load(['branch', 'lines', 'orders']));
    }

    public function store(Request $request): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('manufacturing.manage'), 403);

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:30', Rule::unique('manufacturing_boms', 'code')->where(fn ($query) => $query->where('company_id', $company->id))],
            'item_name' => ['required', 'string', 'max:255'],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where(fn ($query) => $query->where('company_id', $company->id))],
            'output_quantity' => ['required', 'numeric', 'min:0.001'],
            'status' => ['nullable', Rule::in(['active', 'pilot', 'archived'])],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.component_code' => ['nullable', 'string', 'max:40'],
            'lines.*.component_name' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'lines.*.unit' => ['nullable', 'string', 'max:20'],
            'lines.*.wastage_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.notes' => ['nullable', 'string'],
        ]);

        $bom = $this->manufacturingBomService->createBillOfMaterial($company->id, $actor->id, $data, $data['lines']);

        return response()->json($bom, 201);
    }
}
