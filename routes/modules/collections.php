<?php

use App\Modules\Collections\Http\Controllers\CollectionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'active', 'workspace'])->group(function () {
    Route::get('/recouvrement', [CollectionController::class, 'index'])
        ->middleware('permission:collections.view')
        ->name('collections.index');

    Route::get('/recouvrement/factures/{sale}', [CollectionController::class, 'show'])
        ->middleware('permission:collections.view')
        ->name('collections.show');

    Route::post('/recouvrement/factures/{sale}/relances', [CollectionController::class, 'storeFollowUp'])
        ->middleware('permission:collections.manage')
        ->name('collections.follow-ups.store');
});