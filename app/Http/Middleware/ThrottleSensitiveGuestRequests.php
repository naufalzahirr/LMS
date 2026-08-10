<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

class ThrottleSensitiveGuestRequests
{
    public function __construct(private readonly ThrottleRequests $throttle) {}

    /**
     * Apply focused limits to Fortify routes that do not expose limiter configuration.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('password.email')) {
            return $this->throttle->handle($request, $next, 'password-reset');
        }

        if ($request->routeIs('password.update')) {
            return $this->throttle->handle($request, $next, 6, 1);
        }

        return $next($request);
    }
}
