<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceController
{
    public function __invoke(Request $request): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        $token = $request->attributes->get('apiToken');

        return response()->json([
            'tenant_id' => $request->attributes->get('apiTenantId'),
            'company' => [
                'id' => $company?->id,
                'name' => $company?->name,
                'currency_code' => $company?->currency_code,
            ],
            'token' => [
                'name' => $token?->name,
                'last_used_at' => optional($token?->last_used_at)?->toIso8601String(),
                'expires_at' => optional($token?->expires_at)?->toIso8601String(),
            ],
        ]);
    }
}
