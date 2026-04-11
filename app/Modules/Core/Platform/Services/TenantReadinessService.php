<?php

namespace App\Modules\Core\Platform\Services;

use App\Modules\Core\Company\Models\Company;
use Illuminate\Support\Collection;

class TenantReadinessService
{
    public function __construct(
        private readonly DeploymentProfileService $deploymentProfileService,
        private readonly DeploymentReadinessService $deploymentReadinessService,
    ) {
    }

    public function summary(Company $company): array
    {
        $companies = Company::query()
            ->with(['tenant', 'deploymentProfile.owner'])
            ->withCount([
                'users as active_users_count' => fn ($query) => $query->where('is_active', true),
                'branches as active_branches_count' => fn ($query) => $query->where('is_active', true),
            ])
            ->where('tenant_id', $company->tenant_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $companySummaries = $companies->map(function (Company $tenantCompany) use ($company): array {
            $profile = $tenantCompany->deploymentProfile ?: $this->deploymentProfileService->profileForCompany($tenantCompany);
            $profile->loadMissing('owner');
            $readiness = $this->deploymentReadinessService->summary($tenantCompany, $profile);

            return [
                'company_id' => $tenantCompany->id,
                'company_name' => $tenantCompany->name,
                'is_current' => $tenantCompany->id === $company->id,
                'currency_code' => $tenantCompany->currency_code,
                'active_users' => (int) ($tenantCompany->active_users_count ?? 0),
                'active_branches' => (int) ($tenantCompany->active_branches_count ?? 0),
                'has_profile' => $tenantCompany->deploymentProfile !== null,
                'owner_name' => $profile->owner?->name,
                'commercial_offer' => $profile->commercial_offer,
                'commercial_offer_label' => $this->deploymentProfileService->label('commercial_offer', $profile->commercial_offer),
                'deployment_mode' => $profile->deployment_mode,
                'deployment_mode_label' => $this->deploymentProfileService->label('deployment_mode', $profile->deployment_mode),
                'lifecycle_stage' => $profile->lifecycle_stage,
                'lifecycle_stage_label' => $this->deploymentProfileService->label('lifecycle_stage', $profile->lifecycle_stage),
                'support_tier' => $profile->support_tier,
                'support_tier_label' => $this->deploymentProfileService->label('support_tier', $profile->support_tier),
                'go_live_target_at' => $profile->go_live_target_at,
                'readiness_status' => $readiness['status'],
                'readiness_status_label' => $this->readinessStatusLabel($readiness['status']),
                'readiness_score' => $readiness['score'],
                'top_blockers' => collect($readiness['blockers'])->take(3)->values()->all(),
                'top_warnings' => collect($readiness['warnings'])->take(3)->values()->all(),
                'next_actions' => collect($readiness['next_actions'])->take(3)->values()->all(),
            ];
        })->values();

        $averageScore = (int) round($companySummaries->avg('readiness_score') ?: 0);
        $highestScore = (int) ($companySummaries->max('readiness_score') ?? 0);
        $lowestScore = (int) ($companySummaries->min('readiness_score') ?? 0);
        $portfolioStatus = $this->portfolioStatus($companySummaries, $averageScore);

        return [
            'tenant_id' => $company->tenant_id,
            'tenant_name' => $company->tenant?->name,
            'current_company_id' => $company->id,
            'active_companies' => $companySummaries->count(),
            'active_users' => (int) $companySummaries->sum('active_users'),
            'active_branches' => (int) $companySummaries->sum('active_branches'),
            'average_score' => $averageScore,
            'highest_score' => $highestScore,
            'lowest_score' => $lowestScore,
            'portfolio_status' => $portfolioStatus,
            'portfolio_status_label' => $this->readinessStatusLabel($portfolioStatus),
            'status_breakdown' => $this->statusBreakdown($companySummaries),
            'offer_breakdown' => $this->breakdown($companySummaries, 'commercial_offer', 'commercial_offer_label'),
            'deployment_mode_breakdown' => $this->breakdown($companySummaries, 'deployment_mode', 'deployment_mode_label'),
            'lifecycle_breakdown' => $this->breakdown($companySummaries, 'lifecycle_stage', 'lifecycle_stage_label'),
            'focus_companies' => $companySummaries
                ->sortBy(fn (array $companySummary): string => str_pad((string) $companySummary['readiness_score'], 3, '0', STR_PAD_LEFT).'-'.$companySummary['company_name'])
                ->take(3)
                ->map(fn (array $companySummary): array => [
                    'company_id' => $companySummary['company_id'],
                    'company_name' => $companySummary['company_name'],
                    'readiness_status' => $companySummary['readiness_status'],
                    'readiness_score' => $companySummary['readiness_score'],
                    'top_issue' => $companySummary['top_blockers'][0]
                        ?? $companySummary['top_warnings'][0]
                        ?? 'Aucune alerte prioritaire.',
                ])
                ->values()
                ->all(),
            'next_actions' => $companySummaries
                ->flatMap(fn (array $companySummary): array => collect($companySummary['next_actions'])
                    ->map(fn (string $action): string => $companySummary['company_name'].': '.$action)
                    ->all())
                ->unique()
                ->values()
                ->take(6)
                ->all(),
            'companies' => $companySummaries->all(),
        ];
    }

    private function portfolioStatus(Collection $companySummaries, int $averageScore): string
    {
        if ($companySummaries->contains(fn (array $companySummary): bool => $companySummary['readiness_status'] === 'at_risk')) {
            return 'at_risk';
        }

        if ($companySummaries->every(fn (array $companySummary): bool => $companySummary['readiness_status'] === 'ready')) {
            return 'ready';
        }

        return $averageScore >= 60 ? 'progressing' : 'foundation';
    }

    private function statusBreakdown(Collection $companySummaries): array
    {
        $statuses = ['ready', 'progressing', 'foundation', 'at_risk'];

        return collect($statuses)
            ->map(fn (string $status): array => [
                'key' => $status,
                'label' => $this->readinessStatusLabel($status),
                'count' => $companySummaries->where('readiness_status', $status)->count(),
            ])
            ->all();
    }

    private function breakdown(Collection $companySummaries, string $key, string $labelKey): array
    {
        return $companySummaries
            ->groupBy($key)
            ->map(function (Collection $items, string $value) use ($labelKey): array {
                $first = $items->first();

                return [
                    'key' => $value,
                    'label' => $first[$labelKey] ?? $value,
                    'count' => $items->count(),
                ];
            })
            ->sortBy('label')
            ->values()
            ->all();
    }

    private function readinessStatusLabel(string $status): string
    {
        return match ($status) {
            'ready' => 'Pret',
            'progressing' => 'En progression',
            'foundation' => 'Fondation',
            'at_risk' => 'A risque',
            default => $status,
        };
    }
}
