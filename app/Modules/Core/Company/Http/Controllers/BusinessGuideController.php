<?php

namespace App\Modules\Core\Company\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Company\Services\SectorProfileService;
use App\Support\CurrentWorkspace;
use Illuminate\Contracts\View\View;

class BusinessGuideController extends Controller
{
    public function __construct(
        private readonly SectorProfileService $sectorProfileService,
    ) {}

    public function __invoke(CurrentWorkspace $workspace): View
    {
        $company = $workspace->company();
        abort_if(! $company, 403);

        return view('business-guide.index', [
            'company' => $company,
            'profile' => $this->sectorProfileService->profileForCompany($company->id),
            'profiles' => $this->sectorProfileService->profiles(),
        ]);
    }
}
