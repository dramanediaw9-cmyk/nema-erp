<?php

namespace App\Providers;

use App\Modules\Core\Approvals\Services\ApprovalInboxService;
use App\Modules\Core\Notifications\Services\NotificationService;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CurrentWorkspace::class, fn () => new CurrentWorkspace());
        $this->app->singleton(ActivityLogger::class, fn () => new ActivityLogger());
    }

    public function boot(): void
    {
        Blade::if('allowed', fn (string $permission) => auth()->check() && auth()->user()->hasPermission($permission));

        $workspace = app(CurrentWorkspace::class);
        View::share('workspace', $workspace);

        View::composer('layouts.app', function ($view) use ($workspace): void {
            $notificationSummary = [
                'count' => 0,
                'items' => collect(),
            ];
            $approvalSummary = [
                'count' => 0,
                'by_module' => [
                    'sales' => 0,
                    'purchases' => 0,
                    'expenses' => 0,
                ],
            ];

            if (auth()->check() && $workspace->companyId()) {
                $user = auth()->user();

                if ($user && $user->hasPermission('notifications.view')) {
                    $branchScopeId = $user->resolvedBranchScope(null, $workspace->branchId());
                    $notificationSummary = app(NotificationService::class)->summaryForCompany($workspace->companyId(), $branchScopeId);
                }

                if ($user && $user->hasPermission('approvals.view')) {
                    $approvalSummary = app(ApprovalInboxService::class)->summaryForUser($user, $workspace->companyId());
                }
            }

            $view->with('notificationSummary', $notificationSummary);
            $view->with('approvalSummary', $approvalSummary);
        });
    }
}
