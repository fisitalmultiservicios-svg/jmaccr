<?php

use App\Http\Controllers\Admin\CatalogImportController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\TaxonomyController;
use App\Http\Controllers\CatalogController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->prefix('admin/catalog')->group(function () {
    Route::middleware('permission:products.view')->group(function () {
        Route::get('/imports', [CatalogImportController::class, 'index']);
        Route::get('/imports/template', [CatalogImportController::class, 'template']);
        Route::post('/imports', [CatalogImportController::class, 'preview'])->middleware('throttle:3,1');
        Route::get('/imports/{id}', [CatalogImportController::class, 'show'])->whereNumber('id');
        Route::post('/imports/{id}/apply', [CatalogImportController::class, 'apply'])->whereNumber('id')->middleware('throttle:5,1');
        Route::post('/imports/{id}/cancel', [CatalogImportController::class, 'cancel'])->whereNumber('id');
    });
    foreach (['categories', 'brands'] as $resource) {
        Route::get('/'.$resource, [TaxonomyController::class, 'index'])->middleware('permission:'.$resource.'.view');
        Route::post('/'.$resource, [TaxonomyController::class, 'save'])->middleware('permission:'.$resource.'.create');
        Route::post('/'.$resource.'/sort', [TaxonomyController::class, 'sort'])->middleware('permission:'.$resource.'.update');
        Route::post('/'.$resource.'/{id}', [TaxonomyController::class, 'save'])->middleware('permission:'.$resource.'.update');
        Route::post('/'.$resource.'/{id}/delete', [TaxonomyController::class, 'delete'])->middleware('permission:'.$resource.'.delete');
    }
    Route::get('/products', [ProductController::class, 'index'])->middleware('permission:products.view');
    Route::get('/products/create', [ProductController::class, 'edit'])->middleware('permission:products.create');
    Route::post('/products', [ProductController::class, 'save'])->middleware('permission:products.create');
    Route::get('/products/{id}/edit', [ProductController::class, 'edit'])->middleware('permission:products.view');
    Route::post('/products/{id}', [ProductController::class, 'save'])->middleware('permission:products.update');
    Route::post('/products/{id}/duplicate', [ProductController::class, 'duplicate'])->middleware('permission:products.create');
    Route::post('/products/{id}/archive', [ProductController::class, 'archive'])->middleware('permission:products.publish');
    Route::post('/products/{id}/delete', [ProductController::class, 'delete'])->middleware('permission:products.delete');
    Route::post('/products/{id}/restore', [ProductController::class, 'restore'])->middleware('permission:products.delete');
    Route::get('/products/{id}/preview', [ProductController::class, 'preview'])->middleware('permission:products.view');
});
Route::get('/api/catalog/search', [CatalogController::class, 'search'])->middleware('throttle:120,1');
Route::get('/catalogo/{slug}', [CatalogController::class, 'show']);
