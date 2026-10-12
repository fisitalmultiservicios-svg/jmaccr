<?php

use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\PostController;
use App\Http\Controllers\Admin\SeoController;
use App\Http\Controllers\Admin\SystemController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/novedades/{slug}', [App\Http\Controllers\PostController::class, 'show'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*');
Route::get('/robots.txt', [App\Http\Controllers\SeoController::class, 'robots']);
Route::get('/sitemap.xml', [App\Http\Controllers\SeoController::class, 'sitemap']);
Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::get('/posts', [PostController::class, 'index'])->middleware('permission:posts.view');
    Route::post('/posts', [PostController::class, 'create'])->middleware('permission:posts.create');
    Route::get('/posts/{id}/edit', [PostController::class, 'edit'])->middleware('permission:posts.update');
    Route::post('/posts/{id}', [PostController::class, 'save'])->middleware('permission:posts.update');
    Route::match(['GET', 'POST'], '/posts/{id}/preview', [PostController::class, 'preview'])->middleware('permission:posts.view');
    Route::post('/posts/{id}/publish', [PostController::class, 'publish'])->middleware('permission:posts.publish');
    Route::post('/posts/{id}/rollback', [PostController::class, 'rollback'])->middleware('permission:posts.update');
    Route::post('/posts/{id}/archive', [PostController::class, 'archive'])->middleware('permission:posts.delete');
    Route::post('/post-categories', [PostController::class, 'category'])->middleware('permission:posts.create');
    Route::post('/post-categories/{id}/delete', [PostController::class, 'deleteCategory'])->middleware('permission:posts.delete');
    Route::post('/post-sections', [PageController::class, 'section'])->middleware('permission:posts.update');
    Route::get('/users', [UserController::class, 'index'])->middleware('permission:users.manage');
    Route::post('/users', [UserController::class, 'create'])->middleware('permission:users.manage');
    Route::post('/users/{id}', [UserController::class, 'save'])->middleware('permission:users.manage');
    Route::post('/users/{id}/invite', [UserController::class, 'invite'])->middleware(['permission:users.manage', 'throttle:5,1']);
    Route::post('/users/{id}/mfa-reset', [UserController::class, 'resetMfa'])->middleware(['permission:users.manage', 'throttle:5,1']);
    Route::post('/roles', [UserController::class, 'role'])->middleware('permission:users.manage');
    Route::get('/seo', [SeoController::class, 'index'])->middleware('permission:seo.view');
    Route::post('/seo', [SeoController::class, 'save'])->middleware('permission:seo.update');
    Route::post('/seo/redirects', [SeoController::class, 'redirect'])->middleware('permission:seo.update');
    Route::post('/seo/redirects/{id}/delete', [SeoController::class, 'delete'])->middleware('permission:seo.update');
    Route::get('/backups', [BackupController::class, 'index'])->middleware('permission:backups.view');
    Route::post('/backups', [BackupController::class, 'create'])->middleware(['permission:backups.create', 'throttle:3,1']);
    Route::get('/backups/{name}/download', [BackupController::class, 'download'])->middleware('permission:backups.view');
    Route::post('/backups/key', [BackupController::class, 'key'])->middleware(['permission:backups.restore', 'permission:users.manage', 'throttle:3,1']);
    Route::post('/backups/import', [BackupController::class, 'import'])->middleware(['permission:backups.restore', 'permission:users.manage', 'throttle:3,1']);
    Route::post('/backups/{name}/restore', [BackupController::class, 'restore'])->middleware(['permission:backups.restore', 'permission:users.manage', 'throttle:3,1']);
    Route::get('/diagnostics', [SystemController::class, 'diagnostics'])->middleware('permission:system.diagnostics');
    Route::post('/system/cache', [SystemController::class, 'cache'])->middleware('permission:system.cache_clear');
    Route::post('/system/process', [SystemController::class, 'process'])->middleware(['permission:settings.sensitive', 'throttle:3,1']);
    Route::post('/system/automation', [SystemController::class, 'automation'])->middleware('permission:settings.sensitive');
    Route::post('/settings/smtp-test', [SystemController::class, 'smtpTest'])->middleware(['permission:settings.sensitive', 'throttle:3,1']);
});
