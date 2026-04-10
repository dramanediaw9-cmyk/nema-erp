<?php

use App\Modules\Manufacturing\Http\Controllers\ManufacturingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active', 'workspace'])->group(function (): void {
    Route::get('/production', [ManufacturingController::class, 'index'])->middleware('permission:manufacturing.view')->name('manufacturing.index');
    Route::post('/production', [ManufacturingController::class, 'store'])->middleware('permission:manufacturing.manage')->name('manufacturing.store');
    Route::post('/production/nomenclatures', [ManufacturingController::class, 'storeBillOfMaterial'])->middleware('permission:manufacturing.manage')->name('manufacturing.boms.store');
});
