<?php

use App\Modules\Reporting\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active', 'workspace'])->group(function (): void {
    Route::get('/rapports', [ReportController::class, 'index'])->middleware('permission:reports.view')->name('reports.index');
});
