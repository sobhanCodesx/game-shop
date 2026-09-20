<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateDeploymentAgent
{
    public function handle(Request $request, Closure $next): Response
    {
        $rootToken = trim((string) config('content_agent.token'));
        if ($rootToken === '') {
            return response()->json([
                'error' => 'PlayNexus deployment agent is not configured.',
            ], 503);
        }

        /*
         * Domain-separate deployment authentication from the MCP/GraphQL
         * bearer token. GitHub derives this scoped credential from the existing
         * root token, so leaking a deployment bearer does not grant MCP access.
         */
        $expected = hash_hmac(
            'sha256',
            'playnexus/deployment-auth/v1',
            $rootToken,
        );

        $provided = (string) $request->bearerToken();
        if ($provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json([
                'error' => 'Unauthorized.',
            ], 401);
        }

        return $next($request);
    }
}
