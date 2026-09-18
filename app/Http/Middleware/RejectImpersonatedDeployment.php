<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RejectImpersonatedDeployment
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(
            $request->session()->has('impersonator_id'),
            Response::HTTP_FORBIDDEN,
            'در حالت ورود آزمایشی امکان اجرای عملیات زیرساختی وجود ندارد.',
        );

        return $next($request);
    }
}
