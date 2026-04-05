<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Inventory\Models\Warehouse;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WarehouseController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
    ) {
    }

    public function index(CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        return view('warehouses.index', [
            'warehouses' => Warehouse::query()
                ->with('branch')
                ->where('company_id', $companyId)
                ->orderBy('branch_id')
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(),
            'branches' => Branch::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $data = $request->validate([
            'branch_id' => ['required', Rule::exists('branches', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', Rule::unique('warehouses', 'code')->where(fn ($query) => $query->where('company_id', $companyId))],
            'is_default' => ['nullable', 'boolean'],
        ]);

        if (! empty($data['is_default'])) {
            Warehouse::query()->where('company_id', $companyId)->where('branch_id', $data['branch_id'])->update(['is_default' => false]);
        }

        $warehouse = Warehouse::query()->create([
            'company_id' => $companyId,
            'branch_id' => $data['branch_id'],
            'name' => $data['name'],
            'code' => $data['code'],
            'is_default' => (bool) ($data['is_default'] ?? false),
            'is_active' => true,
        ]);

        $this->activityLogger->log('warehouses.create', 'Creation entrepot', $warehouse, ['code' => $warehouse->code]);

        return redirect()->route('warehouses.index')->with('success', 'Entrepot cree avec succes.');
    }
}
