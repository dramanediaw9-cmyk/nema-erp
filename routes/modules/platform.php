<?php

use App\Modules\Core\Platform\Http\Controllers\PlatformController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active', 'workspace'])->group(function (): void {
    Route::get('/plateforme', [PlatformController::class, 'index'])
        ->middleware('permission:platform.view')
        ->name('platform.index');
    Route::get('/plateforme/openapi.json', [PlatformController::class, 'openApiSpec'])
        ->middleware('permission:platform.view')
        ->name('platform.openapi');
    Route::put('/plateforme/deploiement', [PlatformController::class, 'updateDeploymentProfile'])
        ->middleware('permission:settings.manage')
        ->name('platform.deployment-profile.update');
    Route::post('/plateforme/connexions', [PlatformController::class, 'storeConnection'])
        ->middleware('permission:settings.integrations.manage')
        ->name('platform.connections.store');
    Route::post('/plateforme/connexions/{integrationConnection}/status', [PlatformController::class, 'updateConnectionStatus'])
        ->middleware('permission:settings.integrations.manage')
        ->name('platform.connections.status');
});
