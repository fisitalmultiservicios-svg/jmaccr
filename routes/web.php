<?php

use App\Http\Controllers\Admin\SystemController;
use App\Http\Controllers\Auth\MfaController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', [SiteController::class, 'page']);
foreach (['nosotros', 'contacto', 'novedades', 'catalogo'] as $slug) {
    Route::get('/'.$slug, fn () => app(SiteController::class)->page($slug));
}
Route::get('/media/{id}', [SiteController::class, 'media'])->whereNumber('id');
Route::get('/admin/login', [SessionController::class, 'create'])->name('login');
Route::post('/admin/login', [SessionController::class, 'store'])->middleware('throttle:10,1');
Route::get('/admin/forgot-password', [PasswordController::class, 'requestForm'])->name('password.request');
Route::post('/admin/forgot-password', [PasswordController::class, 'send'])->middleware('throttle:3,1')->name('password.email');
Route::get('/admin/reset-password/{token}', [PasswordController::class, 'resetForm'])->name('password.reset');
Route::post('/admin/reset-password', [PasswordController::class, 'reset'])->middleware('throttle:5,1')->name('password.update');
Route::middleware(['auth', 'admin:session'])->group(function () {
    Route::post('/admin/logout', [SessionController::class, 'destroy'])->name('logout');
    Route::get('/admin/mfa/enroll', [MfaController::class, 'enroll'])->name('mfa.enroll');
    Route::post('/admin/mfa/enroll', [MfaController::class, 'confirm'])->middleware('throttle:5,1');
    Route::get('/admin/mfa/challenge', [MfaController::class, 'challenge'])->name('mfa.challenge');
    Route::post('/admin/mfa/challenge', [MfaController::class, 'verify'])->middleware('throttle:5,1');
});
Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::get('/', function () {
        $counts = [];
        foreach (['categories', 'brands', 'products', 'posts'] as $table) {
            $counts[$table] = DB::table($table)->count();
        }

        return view('admin.dashboard', ['counts' => $counts]);
    });
    Route::get('/audit', [SystemController::class, 'audit'])->middleware('permission:audit.view');
    Route::get('/media', [MediaController::class, 'index'])->middleware('permission:media.view');
    Route::post('/media/upload', [MediaController::class, 'store'])->middleware(['permission:media.create', 'throttle:20,1']);
    Route::get('/media/{id}/preview', [MediaController::class, 'preview'])->middleware('permission:media.view');
});

require __DIR__.'/admin-cms.php';
require __DIR__.'/admin-catalog.php';
require __DIR__.'/admin-sales.php';
require __DIR__.'/admin-system.php';

Route::get('/{slug}', [SiteController::class, 'page'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*');
