<?php

use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\SalesController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\RequestController;
use Illuminate\Support\Facades\Route;

Route::post('/api/preorders', [RequestController::class, 'preorder'])->middleware('throttle:8,1');
Route::post('/api/contacts', [RequestController::class, 'contact'])->middleware('throttle:5,1');
Route::get('/politica-de-privacidad', [RequestController::class, 'privacy']);
Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    foreach (['preorders', 'contacts'] as $kind) {
        Route::get('/'.$kind, [SalesController::class, 'index'])->middleware('permission:'.$kind.'.view');
        Route::get('/'.$kind.'/export', [SalesController::class, 'export'])->middleware('permission:'.$kind.'.export');
        Route::get('/'.$kind.'/{id}', [SalesController::class, 'show'])->middleware('permission:'.$kind.'.view');
        Route::post('/'.$kind.'/{id}', [SalesController::class, 'update'])->middleware('permission:'.$kind.'.update');
        Route::post('/'.$kind.'/{id}/anonymize', [SalesController::class, 'anonymize'])->middleware('permission:'.$kind.'.'.($kind === 'contacts' ? 'delete' : 'update'))->middleware('permission:users.manage');
    }
    Route::get('/settings', [SettingsController::class, 'index'])->middleware('permission:settings.view');
    Route::post('/settings', [SettingsController::class, 'save'])->middleware('permission:settings.update');
    Route::post('/settings/privacy', [SettingsController::class, 'privacy'])->middleware('permission:settings.sensitive');
    Route::post('/settings/smtp', [SettingsController::class, 'smtp'])->middleware('permission:settings.sensitive');
    Route::get('/notifications', [NotificationController::class, 'index'])->middleware(['permission:settings.view', 'permission:preorders.view']);
    Route::post('/notifications/{id}/retry', [NotificationController::class, 'retry'])->middleware('permission:settings.sensitive');
});
