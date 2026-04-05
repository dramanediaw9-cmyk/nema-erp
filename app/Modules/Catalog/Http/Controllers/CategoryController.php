<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\ProductCategory;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger)
    {
    }

    public function index(CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        return view('categories.index', [
            'categories' => ProductCategory::query()
                ->where('company_id', $companyId)
                ->orderBy('name')
                ->paginate(15),
        ]);
    }

    public function create(CurrentWorkspace $workspace): View
    {
        abort_if(! $workspace->companyId(), 403);

        return view('categories.create', [
            'category' => new ProductCategory(),
        ]);
    }

    public function store(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $data = $this->validateCategory($request, $companyId);
        $data['company_id'] = $companyId;
        $data['is_active'] = $request->boolean('is_active', true);

        $category = ProductCategory::query()->create($data);
        $this->activityLogger->log('categories.create', 'Creation categorie produit', $category);

        return redirect()->route('categories.index')->with('success', 'Categorie creee avec succes.');
    }

    public function edit(ProductCategory $category, CurrentWorkspace $workspace): View
    {
        abort_if($workspace->companyId() !== $category->company_id, 403);

        return view('categories.edit', compact('category'));
    }

    public function update(Request $request, ProductCategory $category, CurrentWorkspace $workspace): RedirectResponse
    {
        abort_if($workspace->companyId() !== $category->company_id, 403);

        $data = $this->validateCategory($request, $category->company_id, $category->id);
        $data['is_active'] = $request->boolean('is_active', true);

        $category->update($data);
        $this->activityLogger->log('categories.update', 'Mise a jour categorie produit', $category);

        return redirect()->route('categories.index')->with('success', 'Categorie mise a jour avec succes.');
    }

    private function validateCategory(Request $request, int $companyId, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('product_categories', 'name')
                    ->where(fn ($query) => $query->where('company_id', $companyId))
                    ->ignore($ignoreId),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
