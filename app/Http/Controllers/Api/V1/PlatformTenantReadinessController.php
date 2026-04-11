<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesApiActor;
use App\Modules\Core\Platform\Services\TenantReadinessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformTenantReadinessController
{
    use ResolvesApiActor;

    public function __construct(
        private readonly TenantReadinessService $tenantReadinessService,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('platform.view'), 403);

        return response()->json($this->tenantReadinessService->summary($company));
    }
}
