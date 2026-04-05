<?php

namespace App\Modules\Core\Onboarding\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Onboarding\Services\OnboardingService;
use App\Modules\Core\Onboarding\Services\SectorDemoDataService;
use App\Modules\Core\Onboarding\Services\SectorStarterService;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    public function __construct(
        private readonly OnboardingService $onboardingService,
        private readonly SectorStarterService $sectorStarterService,
        private readonly SectorDemoDataService $sectorDemoDataService,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function index(CurrentWorkspace $workspace): View
    {
        $company = $workspace->company();
        abort_if(! $company, 403);

        return view('onboarding.index', [
            'company' => $company,
            'summary' => $this->onboardingService->summary($company->id),
            'bannerDismissed' => $this->onboardingService->isDashboardBannerDismissed($company->id),
        ]);
    }

    public function applySectorStarter(CurrentWorkspace $workspace): RedirectResponse
    {
        $company = $workspace->company();
        abort_if(! $company, 403);

        $result = $this->sectorStarterService->apply($company);
        $profile = $result['profile'];

        $this->activityLogger->log('onboarding.sector_starter.apply', 'Application starter pack secteur', $company, [
            'profile' => $profile['key'],
            'profile_label' => $profile['label'],
            'created' => $result['created'],
            'configured_gateways' => $result['configured_gateways'],
        ]);

        return redirect()->to(route('onboarding.index').'#starter-pack')
            ->with('success', 'Starter pack '.$profile['label'].' applique avec succes.');
    }

    public function applySectorDemoData(CurrentWorkspace $workspace): RedirectResponse
    {
        $company = $workspace->company();
        abort_if(! $company, 403);

        $result = $this->sectorDemoDataService->apply($company);
        $profile = $result['profile'];

        $this->activityLogger->log('onboarding.sector_demo.apply', 'Chargement demo secteur', $company, [
            'profile' => $profile['key'],
            'profile_label' => $profile['label'],
            'created' => $result['created'],
            'branch' => $result['branch']->name,
            'warehouse' => $result['warehouse']->name,
        ]);

        return redirect()->to(route('onboarding.index').'#demo-data')
            ->with('success', 'Donnees de demonstration '.$profile['label'].' chargees avec succes.');
    }

    public function dismiss(CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $this->onboardingService->dismissDashboardBanner($companyId);

        return back()->with('success', 'Le bandeau de demarrage a ete masque pour cette societe.');
    }

    public function reopen(CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $this->onboardingService->reopenDashboardBanner($companyId);

        return back()->with('success', 'Le bandeau de demarrage a ete reactive.');
    }
}
