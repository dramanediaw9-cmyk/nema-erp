<?php

namespace App\Providers;

use App\Modules\Core\Approvals\Services\ApprovalInboxService;
use App\Modules\Core\Notifications\Services\NotificationService;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use App\Support\ErpNavigationService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CurrentWorkspace::class, fn() => new CurrentWorkspace);
        $this->app->singleton(ActivityLogger::class, fn() => new ActivityLogger);
    }

    public function boot(): void
    {
        if (str_starts_with(config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Blade::if('allowed', fn(string $permission) => auth()->check() && auth()->user()->hasPermission($permission));
        RateLimiter::for('api', function (Request $request) {
            $token = $request->bearerToken() ?: $request->header('X-Api-Key');

            return Limit::perMinute(120)->by($token ? hash('sha256', $token) : $request->ip());
        });

        $workspace = app(CurrentWorkspace::class);
        View::share('workspace', $workspace);

        View::composer('layouts.app', function ($view) use ($workspace): void {
            $user = auth()->user();
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
                if ($user && $user->hasPermission('notifications.view')) {
                    $branchScopeId = $user->resolvedBranchScope(null, $workspace->branchId());
                    $notificationSummary = app(NotificationService::class)->cachedSummaryForCompany($workspace->companyId(), $branchScopeId);
                }

                if ($user && $user->hasPermission('approvals.view')) {
                    $approvalSummary = app(ApprovalInboxService::class)->cachedSummaryForUser($user, $workspace->companyId());
                }
            }

            $view->with('notificationSummary', $notificationSummary);
            $view->with('approvalSummary', $approvalSummary);
            $view->with('erpNavigation', app(ErpNavigationService::class)->build(
                $user,
                request(),
                session('ui_mode', 'full') === 'merchant',
                $workspace->companyId()
            ));
        });
    }
}
