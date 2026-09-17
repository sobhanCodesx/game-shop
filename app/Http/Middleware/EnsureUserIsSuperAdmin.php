<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $request->user()?->isSuperAdmin(),
            Response::HTTP_FORBIDDEN,
            'این بخش فقط برای مدیر کل قابل دسترسی است.',
        );

        return $next($request);
    }
}
