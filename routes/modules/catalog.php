<?php

use App\Modules\Catalog\Http\Controllers\CategoryController;
use App\Modules\Catalog\Http\Controllers\ProductAttributeController;
use App\Modules\Catalog\Http\Controllers\ProductController;
use App\Modules\Catalog\Http\Controllers\ProductImageController;
use Illuminate\Support\Facades\Route;

Route::get('/media/produits/{path}', [ProductImageController::class, 'show'])
    ->where('path', '.*')
    ->name('products.media.show');

Route::middleware(['auth', 'active', 'workspace'])->group(function (): void {
    Route::get('/categories', [CategoryController::class, 'index'])->middleware('permission:categories.view')->name('categories.index');
    Route::get('/categories/creer', [CategoryController::class, 'create'])->middleware('permission:categories.manage')->name('categories.create');
    Route::post('/categories', [CategoryController::class, 'store'])->middleware('permission:categories.manage')->name('categories.store');
    Route::get('/categories/{category}/modifier', [CategoryController::class, 'edit'])->middleware('permission:categories.manage')->name('categories.edit');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->middleware('permission:categories.manage')->name('categories.update');

    Route::get('/produits/attributs', [ProductAttributeController::class, 'index'])->middleware('permission:products.manage')->name('product-attributes.index');
    Route::post('/produits/attributs', [ProductAttributeController::class, 'store'])->middleware('permission:products.manage')->name('product-attributes.store');
    Route::post('/produits/attributs/{attribute}/valeurs', [ProductAttributeController::class, 'storeValue'])->middleware('permission:products.manage')->name('product-attributes.values.store');

    Route::get('/produits', [ProductController::class, 'index'])->middleware('permission:products.view')->name('products.index');
    Route::get('/produits/creer', [ProductController::class, 'create'])->middleware('permission:products.manage')->name('products.create');
    Route::post('/produits', [ProductController::class, 'store'])->middleware('permission:products.manage')->name('products.store');
    Route::get('/produits/{product}', [ProductController::class, 'show'])->middleware('permission:products.view')->name('products.show');
    Route::get('/produits/{product}/modifier', [ProductController::class, 'edit'])->middleware('permission:products.manage')->name('products.edit');
    Route::put('/produits/{product}', [ProductController::class, 'update'])->middleware('permission:products.manage')->name('products.update');
    Route::patch('/produits/{product}/archiver', [ProductController::class, 'archive'])->middleware('permission:products.manage')->name('products.archive');
    Route::patch('/produits/{product}/reactiver', [ProductController::class, 'restore'])->middleware('permission:products.manage')->name('products.restore');
    Route::delete('/produits/{product}', [ProductController::class, 'destroy'])->middleware('permission:products.manage')->name('products.destroy');
});

