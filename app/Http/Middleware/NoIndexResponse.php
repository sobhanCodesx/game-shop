<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class NoIndexResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Header-based indexing control works even without JavaScript or SSR.
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
