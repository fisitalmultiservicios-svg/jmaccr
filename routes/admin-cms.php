<?php

use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\ThemeController;
use App\Http\Controllers\MediaController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::get('/media/candidates/{name}', [MediaController::class, 'candidate'])->middleware('permission:media.view');
    Route::post('/media/candidates/{name}', [MediaController::class, 'importCandidate'])->middleware('permission:media.create');
    Route::post('/media/{id}/update', [MediaController::class, 'update'])->middleware('permission:media.update');
    Route::post('/media/{id}/approve', [MediaController::class, 'approve'])->middleware('permission:media.approve');
    Route::post('/media/{id}/delete', [MediaController::class, 'delete'])->middleware('permission:media.delete');
    Route::get('/pages', [PageController::class, 'index'])->middleware('permission:pages.view');
    Route::post('/pages', [PageController::class, 'create'])->middleware('permission:pages.create');
    Route::get('/pages/{id}/edit', [PageController::class, 'edit'])->middleware('permission:pages.view');
    Route::post('/pages/{id}', [PageController::class, 'save'])->middleware('permission:pages.update');
    Route::get('/pages/{id}/preview', [PageController::class, 'preview'])->middleware('permission:pages.view');
    Route::post('/pages/{id}/preview', [PageController::class, 'preview'])->middleware('permission:pages.update');
    Route::post('/pages/{id}/publish', [PageController::class, 'publish'])->middleware('permission:pages.publish');
    Route::post('/pages/{id}/rollback', [PageController::class, 'rollback'])->middleware('permission:pages.update');
    Route::post('/pages/{id}/archive', [PageController::class, 'archive'])->middleware('permission:pages.publish');
    Route::post('/sections', [PageController::class, 'section'])->middleware('permission:pages.update');
    Route::get('/theme', [ThemeController::class, 'index'])->middleware('permission:theme.view');
    Route::post('/theme', [ThemeController::class, 'save'])->middleware('permission:theme.update');
    Route::post('/theme/reset', [ThemeController::class, 'reset'])->middleware('permission:theme.update');
    Route::post('/theme/{id}/publish', [ThemeController::class, 'publish'])->middleware('permission:theme.publish');
    Route::post('/theme/{id}/rollback', [ThemeController::class, 'rollback'])->middleware('permission:theme.update');
    Route::get('/menus', [MenuController::class, 'index'])->middleware('permission:menus.view');
    Route::get('/menus/{id}', [MenuController::class, 'edit'])->middleware('permission:menus.view');
    Route::post('/menus/{id}', [MenuController::class, 'save'])->middleware('permission:menus.update');
    Route::post('/menus/{id}/publish', [MenuController::class, 'publish'])->middleware('permission:menus.update');
    Route::post('/menus/{id}/rollback', [MenuController::class, 'rollback'])->middleware('permission:menus.update');
});
