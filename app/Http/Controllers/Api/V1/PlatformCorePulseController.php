<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesApiActor;
use App\Modules\Core\Platform\Services\CorePulseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformCorePulseController
{
    use ResolvesApiActor;

    public function __invoke(Request $request, CorePulseService $corePulseService): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('platform.view'), 403);

        return response()->json($corePulseService->summary($company));
    }
}
