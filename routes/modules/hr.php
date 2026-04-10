<?php

use App\Modules\Hr\Http\Controllers\HrController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active', 'workspace'])->group(function (): void {
    Route::get('/capital-humain', [HrController::class, 'index'])->middleware('permission:hr.view')->name('hr.index');
    Route::post('/capital-humain/departements', [HrController::class, 'storeDepartment'])->middleware('permission:hr.manage')->name('hr.departments.store');
    Route::post('/capital-humain/employes', [HrController::class, 'storeEmployee'])->middleware('permission:hr.manage')->name('hr.employees.store');
    Route::post('/capital-humain/conges', [HrController::class, 'storeLeaveRequest'])->middleware('permission:hr.manage')->name('hr.leave-requests.store');
});
