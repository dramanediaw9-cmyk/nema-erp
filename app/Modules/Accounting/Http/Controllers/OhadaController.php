<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Services\OhadaLocalizationService;
use App\Support\CurrentWorkspace;
use Illuminate\View\View;

class OhadaController extends Controller
{
    public function __construct(private readonly OhadaLocalizationService $ohadaLocalizationService)
    {
    }

    public function index(CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        return view('accounting.ohada.index', [
            'profile' => $this->ohadaLocalizationService->profile($companyId),
        ]);
    }
}
