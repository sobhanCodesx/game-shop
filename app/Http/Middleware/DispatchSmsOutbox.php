<?php

namespace App\Http\Middleware;

use App\Services\Sms\SmsDispatcher;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class DispatchSmsOutbox
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            app(SmsDispatcher::class)->dispatchDue();
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
