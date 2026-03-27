<?php

use App\Modules\FixedAssets\Http\Controllers\FixedAssetController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active', 'workspace'])->group(function (): void {
    Route::get('/comptabilite/immobilisations', [FixedAssetController::class, 'index'])
        ->middleware('permission:fixed_assets.view')
        ->name('fixed-assets.index');

    Route::get('/comptabilite/immobilisations/creer', [FixedAssetController::class, 'create'])
        ->middleware('permission:fixed_assets.manage')
        ->name('fixed-assets.create');

    Route::post('/comptabilite/immobilisations', [FixedAssetController::class, 'store'])
        ->middleware('permission:fixed_assets.manage')
        ->name('fixed-assets.store');

    Route::get('/comptabilite/immobilisations/{fixedAsset}', [FixedAssetController::class, 'show'])
        ->middleware('permission:fixed_assets.view')
        ->name('fixed-assets.show');
});
