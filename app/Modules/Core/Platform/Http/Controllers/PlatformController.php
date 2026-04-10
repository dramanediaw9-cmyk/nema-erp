<?php

namespace App\Modules\Core\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Platform\Services\PlatformCatalogService;
use App\Support\CurrentWorkspace;
use Illuminate\View\View;

class PlatformController extends Controller
{
    public function __construct(private readonly PlatformCatalogService $platformCatalogService)
    {
    }

    public function __invoke(CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        return view('platform.index', [
            'catalog' => $this->platformCatalogService->forCompany($companyId),
        ]);
    }
}
