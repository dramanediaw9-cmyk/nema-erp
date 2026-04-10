<?php

use App\Modules\Projects\Http\Controllers\ProjectController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active', 'workspace'])->group(function (): void {
    Route::get('/projets', [ProjectController::class, 'index'])->middleware('permission:projects.view')->name('projects.index');
    Route::post('/projets', [ProjectController::class, 'store'])->middleware('permission:projects.manage')->name('projects.store');
});
