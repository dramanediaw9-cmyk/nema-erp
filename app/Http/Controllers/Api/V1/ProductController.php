<?php

namespace App\Http\Controllers\Api\V1;

use App\Modules\Catalog\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController
{
    public function index(Request $request): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');

        $products = Product::query()
            ->with(['category', 'saleTaxRule', 'purchaseTaxRule'])
            ->where('company_id', $company->id)
            ->when($request->boolean('active_only', true), fn ($query) => $query->where('is_active', true))
            ->orderBy('name')
            ->paginate(min((int) $request->integer('per_page', 50), 200));

        return response()->json($products);
    }
}
