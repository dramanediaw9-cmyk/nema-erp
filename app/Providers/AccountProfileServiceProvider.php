<?php

namespace App\Providers;

use App\Modules\Core\Auth\Http\Controllers\AccountProfileController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AccountProfileServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['web', 'auth', 'active', 'workspace'])->group(function (): void {
            Route::get('/mon-compte', [AccountProfileController::class, 'edit'])
                ->name('account.profile.edit');
            Route::put('/mon-compte', [AccountProfileController::class, 'update'])
                ->name('account.profile.update');
            Route::put('/mon-compte/mot-de-passe', [AccountProfileController::class, 'updatePassword'])
                ->name('account.profile.password');
        });
    }
}
