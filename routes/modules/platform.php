<?php

use App\Modules\Core\Platform\Http\Controllers\PlatformController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active', 'workspace'])->group(function (): void {
    Route::get('/plateforme', PlatformController::class)
        ->middleware('permission:platform.view')
        ->name('platform.index');
});
