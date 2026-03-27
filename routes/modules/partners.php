<?php

use App\Modules\Partners\Http\Controllers\CustomerController;
use App\Modules\Partners\Http\Controllers\PartnerProfileController;
use App\Modules\Partners\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active', 'workspace'])->group(function (): void {
    Route::get('/clients', [CustomerController::class, 'index'])->middleware('permission:customers.view')->name('customers.index');
    Route::get('/clients/creer', [CustomerController::class, 'create'])->middleware('permission:customers.manage')->name('customers.create');
    Route::post('/clients', [CustomerController::class, 'store'])->middleware('permission:customers.manage')->name('customers.store');
    Route::get('/clients/{customer}', [CustomerController::class, 'show'])->middleware('permission:customers.view')->name('customers.show');
    Route::get('/clients/{customer}/modifier', [CustomerController::class, 'edit'])->middleware('permission:customers.manage')->name('customers.edit');
    Route::put('/clients/{customer}', [CustomerController::class, 'update'])->middleware('permission:customers.manage')->name('customers.update');

    Route::get('/fournisseurs', [SupplierController::class, 'index'])->middleware('permission:suppliers.view')->name('suppliers.index');
    Route::get('/fournisseurs/creer', [SupplierController::class, 'create'])->middleware('permission:suppliers.manage')->name('suppliers.create');
    Route::post('/fournisseurs', [SupplierController::class, 'store'])->middleware('permission:suppliers.manage')->name('suppliers.store');
    Route::get('/fournisseurs/{supplier}', [SupplierController::class, 'show'])->middleware('permission:suppliers.view')->name('suppliers.show');
    Route::get('/fournisseurs/{supplier}/modifier', [SupplierController::class, 'edit'])->middleware('permission:suppliers.manage')->name('suppliers.edit');
    Route::put('/fournisseurs/{supplier}', [SupplierController::class, 'update'])->middleware('permission:suppliers.manage')->name('suppliers.update');

    Route::post('/tiers/{partner}/contacts', [PartnerProfileController::class, 'storeContact'])->name('partners.contacts.store');
    Route::delete('/tiers/{partner}/contacts/{contact}', [PartnerProfileController::class, 'destroyContact'])->name('partners.contacts.destroy');
    Route::post('/tiers/{partner}/adresses', [PartnerProfileController::class, 'storeAddress'])->name('partners.addresses.store');
    Route::delete('/tiers/{partner}/adresses/{address}', [PartnerProfileController::class, 'destroyAddress'])->name('partners.addresses.destroy');
    Route::post('/tiers/{partner}/comptes-bancaires', [PartnerProfileController::class, 'storeBankAccount'])->name('partners.bank-accounts.store');
    Route::delete('/tiers/{partner}/comptes-bancaires/{bankAccount}', [PartnerProfileController::class, 'destroyBankAccount'])->name('partners.bank-accounts.destroy');
    Route::post('/tiers/{partner}/wallets', [PartnerProfileController::class, 'storeWallet'])->name('partners.wallets.store');
    Route::delete('/tiers/{partner}/wallets/{wallet}', [PartnerProfileController::class, 'destroyWallet'])->name('partners.wallets.destroy');
});
