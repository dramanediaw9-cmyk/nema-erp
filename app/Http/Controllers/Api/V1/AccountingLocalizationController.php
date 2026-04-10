<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesApiActor;
use App\Modules\Accounting\Services\OhadaLocalizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountingLocalizationController
{
    use ResolvesApiActor;

    public function __construct(private readonly OhadaLocalizationService $ohadaLocalizationService)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        $actor = $this->resolveApiUser($request, $company->id);
        abort_unless($actor->hasPermission('accounting.view'), 403);

        return response()->json($this->ohadaLocalizationService->profile($company->id));
    }
}
