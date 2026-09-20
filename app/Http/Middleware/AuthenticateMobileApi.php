<?php

namespace App\Http\Middleware;

use App\Services\MobileApiTokenService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMobileApi
{
    public function __construct(private readonly MobileApiTokenService $tokens) {}

    public function handle(Request $request, Closure $next, string $mode = 'required'): Response
    {
        $plainTextToken = $request->bearerToken();

        if (! $plainTextToken) {
            if ($mode === 'optional') {
                return $next($request);
            }

            return $this->unauthorized();
        }

        $accessToken = $this->tokens->resolve($plainTextToken);

        if (! $accessToken || ! $accessToken->user || $accessToken->user->status !== 'active') {
            $accessToken?->delete();

            return $this->unauthorized();
        }

        $request->setUserResolver(fn () => $accessToken->user);
        $request->attributes->set('mobile_access_token', $accessToken);

        return $next($request);
    }

    private function unauthorized(): JsonResponse
    {
        return response()->json([
            'message' => 'نشست موبایل معتبر نیست یا منقضی شده است.',
            'code' => 'mobile_unauthenticated',
        ], 401);
    }
}
