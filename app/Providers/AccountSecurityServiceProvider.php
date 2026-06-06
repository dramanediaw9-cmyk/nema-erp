<?php

namespace App\Providers;

use App\Modules\Core\Auth\Http\Controllers\ForgotPasswordController;
use App\Modules\Core\Auth\Http\Controllers\ResetPasswordController;
use App\Modules\Core\Company\Http\Controllers\CompanyWorkspaceController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AccountSecurityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('web')->group(function (): void {
            Route::middleware('guest')->group(function (): void {
                Route::get('/mot-de-passe-oublie', [ForgotPasswordController::class, 'create'])
                    ->name('password.request');
                Route::post('/mot-de-passe-oublie', [ForgotPasswordController::class, 'store'])
                    ->name('password.email');
                Route::get('/reinitialiser-mot-de-passe/{token}', [ResetPasswordController::class, 'create'])
                    ->name('password.reset');
                Route::post('/reinitialiser-mot-de-passe', [ResetPasswordController::class, 'store'])
                    ->name('password.update');
            });

            Route::middleware(['auth', 'active', 'workspace'])
                ->post('/entreprises/{company}/activer', CompanyWorkspaceController::class)
                ->middleware('permission:companies.view')
                ->name('companies.switch');
        });
    }
}
