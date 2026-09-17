<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user?->canAccessAdminPanel(), Response::HTTP_FORBIDDEN);

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();
        abort_unless(is_string($routeName), Response::HTTP_FORBIDDEN);

        if ($routeName === 'admin.resources.index') {
            $resource = (string) $request->route('resource');
            $permission = config("admin-access.resource_permissions.{$resource}");

            abort_unless(
                is_string($permission) && $user->hasPermission($permission),
                Response::HTTP_FORBIDDEN,
            );

            return $next($request);
        }

        $requiredPermission = null;
        foreach (config('admin-access.route_permissions', []) as $pattern => $permission) {
            if (Str::is($pattern, $routeName)) {
                $requiredPermission = $permission;
                break;
            }
        }

        // Default-deny for every admin route that has not explicitly been mapped.
        abort_unless(
            is_string($requiredPermission) && $user->hasPermission($requiredPermission),
            Response::HTTP_FORBIDDEN,
        );

        return $next($request);
    }
}
