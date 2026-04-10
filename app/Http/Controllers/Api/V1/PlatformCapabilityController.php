<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Core\Platform\Services\PlatformCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformCapabilityController extends Controller
{
    public function __invoke(Request $request, PlatformCatalogService $platformCatalogService): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');

        return response()->json([
            'workspace' => [
                'company_id' => $company?->id,
                'company_name' => $company?->name,
                'tenant_id' => $request->attributes->get('apiTenantId'),
            ],
            'catalog' => $platformCatalogService->forCompany($company?->id),
        ]);
    }
}
