<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\DispatchSmsOutbox;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RejectImpersonatedDeployment;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('web')->group(base_path('routes/video-progress.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn ($request) => $request->is('admin', 'admin/*') ? '/admin/login' : '/login');

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'deployment.guard' => RejectImpersonatedDeployment::class,
        ]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            DispatchSmsOutbox::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
