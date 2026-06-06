<?php

namespace App\Providers;

use App\Modules\Core\Registration\Http\Controllers\SaasRegistrationController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class SaasRegistrationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['web', 'guest'])->group(function (): void {
            Route::get('/commencer', [SaasRegistrationController::class, 'account'])
                ->name('saas.register.account');
            Route::post('/commencer/compte', [SaasRegistrationController::class, 'storeAccount'])
                ->name('saas.register.account.store');
            Route::get('/commencer/entreprise', [SaasRegistrationController::class, 'company'])
                ->name('saas.register.company');
            Route::post('/commencer/entreprise', [SaasRegistrationController::class, 'storeCompany'])
                ->name('saas.register.company.store');
            Route::get('/commencer/espace', [SaasRegistrationController::class, 'workspace'])
                ->name('saas.register.workspace');
            Route::post('/commencer/espace', [SaasRegistrationController::class, 'storeWorkspace'])
                ->name('saas.register.workspace.store');
            Route::get('/commencer/formule', [SaasRegistrationController::class, 'plan'])
                ->name('saas.register.plan');
            Route::post('/commencer/terminer', [SaasRegistrationController::class, 'complete'])
                ->middleware('throttle:5,1')
                ->name('saas.register.complete');
        });
    }
}
