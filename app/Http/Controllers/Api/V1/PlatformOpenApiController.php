<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesApiActor;
use App\Modules\Core\Platform\Services\PlatformOpenApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformOpenApiController
{
    use ResolvesApiActor;

    public function __invoke(Request $request, PlatformOpenApiService $platformOpenApiService): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('platform.view'), 403);

        return response()->json($platformOpenApiService->spec($company->id));
    }
}
