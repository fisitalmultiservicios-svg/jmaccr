<?php

use App\Domain\Catalog\CatalogFileLimits;
use App\Http\Middleware\AdminAccess;
use App\Http\Middleware\Csrf;
use App\Http\Middleware\Permission;
use App\Http\Middleware\Redirects;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);
        $middleware->web(replace: [PreventRequestForgery::class => Csrf::class], append: [Redirects::class]);
        $middleware->alias([
            'admin' => AdminAccess::class,
            'permission' => Permission::class,
        ]);
        $middleware->redirectGuestsTo('/admin/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(function (PostTooLargeException $exception, Request $request) {
            if ($request->is('admin/catalog/imports')) {
                return response()->view('errors.catalog-upload-size', ['limits' => CatalogFileLimits::summary()], 413);
            }
        });
    })->create();
