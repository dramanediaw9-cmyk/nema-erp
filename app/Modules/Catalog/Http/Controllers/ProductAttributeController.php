<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\ProductAttribute;
use App\Modules\Catalog\Models\ProductAttributeValue;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductAttributeController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger)
    {
    }

    public function index(CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        return view('product-attributes.index', [
            'attributes' => ProductAttribute::query()
                ->with(['values' => fn ($query) => $query->orderBy('value')])
                ->where('company_id', $companyId)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $data = $request->validate([
            'code' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique('product_attributes', 'code')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $attribute = ProductAttribute::query()->create([
            'company_id' => $companyId,
            'code' => $data['code'] ?: strtoupper(substr(preg_replace('/[^A-Za-z0-9]+/', '', $data['name']), 0, 12)),
            'name' => $data['name'],
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->activityLogger->log('product_attributes.create', 'Creation attribut produit', $attribute, [
            'code' => $attribute->code,
            'name' => $attribute->name,
        ]);

        return redirect()->route('product-attributes.index')->with('success', 'Attribut produit ajoute avec succes.');
    }

    public function storeValue(ProductAttribute $attribute, Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        abort_if($workspace->companyId() !== $attribute->company_id, 403);

        $data = $request->validate([
            'value' => [
                'required',
                'string',
                'max:100',
                Rule::unique('product_attribute_values', 'value')->where(fn ($query) => $query->where('product_attribute_id', $attribute->id)),
            ],
            'code' => ['nullable', 'string', 'max:40'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $value = ProductAttributeValue::query()->create([
            'company_id' => $attribute->company_id,
            'product_attribute_id' => $attribute->id,
            'value' => $data['value'],
            'code' => $data['code'] ?: null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->activityLogger->log('product_attributes.value.create', 'Creation valeur attribut produit', $value, [
            'attribute' => $attribute->name,
            'value' => $value->value,
        ]);

        return redirect()->route('product-attributes.index')->with('success', 'Valeur d attribut ajoutee avec succes.');
    }
}
