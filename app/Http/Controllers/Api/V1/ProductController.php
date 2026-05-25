<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesApiActor;
use App\Modules\Catalog\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController
{
    use ResolvesApiActor;

    public function index(Request $request): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        $actor = $this->resolveApiUser($request, $company->id);
        $this->ensureApiPermission($actor, 'products.view');

        $products = Product::query()
            ->with(['category', 'saleTaxRule', 'purchaseTaxRule'])
            ->where('company_id', $company->id)
            ->when($request->boolean('active_only', true), fn ($query) => $query->where('is_active', true))
            ->orderBy('name')
            ->paginate(min((int) $request->integer('per_page', 50), 200));

        return response()->json($products);
    }
}
