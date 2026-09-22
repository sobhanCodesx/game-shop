<?php

use App\Http\Middleware\AuthenticateContentAgent;
use App\Http\Middleware\AuthenticateDeploymentAgent;
use App\Http\Middleware\AuthenticateMobileApi;
use App\Http\Middleware\DispatchSmsOutbox;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsSuperAdmin;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RejectImpersonatedDeployment;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('web')->group(base_path('routes/video-progress.php'));
            Route::middleware('web')->group(base_path('routes/video-preview.php'));
            Route::middleware('web')->group(base_path('routes/admin-file-manager.php'));
            Route::middleware('web')->group(base_path('routes/android-app.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(function ($request): string {
            if ($request->is('admin', 'admin/*')) {
                $destination = '/'.ltrim($request->getRequestUri(), '/');

                return '/login?redirect='.rawurlencode($destination);
            }

            return '/login';
        });

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'super-admin' => EnsureUserIsSuperAdmin::class,
            'deployment.guard' => RejectImpersonatedDeployment::class,
            'content.agent' => AuthenticateContentAgent::class,
            'deployment.agent' => AuthenticateDeploymentAgent::class,
            'mobile.api' => AuthenticateMobileApi::class,
        ]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            DispatchSmsOutbox::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (
            TooManyRequestsHttpException $exception,
            Request $request,
        ) {
            if (! $request->is(
                'login/otp',
                'login/otp/*',
                'verify-email',
                'verify-email/*',
                'forgot-password',
                'reset-password',
            )) {
                return null;
            }

            $retryAfter = max(
                1,
                (int) ($exception->getHeaders()['Retry-After'] ?? 60),
            );
            $message = "درخواست‌های پشت‌سرهم زیاد بود؛ {$retryAfter} ثانیه صبر کن و دوباره تلاش کن.";

            if ($request->is('login/otp/telegram', 'verify-email/telegram')) {
                return back()->withErrors(['telegram' => $message]);
            }

            return back()->with('error', $message);
        });
    })->create();
