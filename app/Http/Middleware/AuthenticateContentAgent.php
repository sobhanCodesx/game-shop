<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateContentAgent
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = trim((string) config('content_agent.token'));
        if ($expected === '') {
            return response()->json([
                'error' => 'PlayNexus content agent is not configured.',
            ], 503);
        }

        $provided = (string) $request->bearerToken();
        if ($provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json([
                'error' => 'Unauthorized.',
            ], 401);
        }

        return $next($request);
    }
}
