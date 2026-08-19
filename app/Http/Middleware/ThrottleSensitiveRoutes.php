<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rate limits unauthenticated endpoints that Fortify exposes without one.
 *
 * Password reset in particular is unthrottled out of the box, which allows
 * mail bombing a known address and brute forcing reset tokens.
 */
class ThrottleSensitiveRoutes
{
    /** Requests allowed per window, keyed by route name. */
    private const LIMITS = [
        'password.email' => 5,
        'password.update' => 5,
        'password.reset' => 20,
        'tickets.show' => 60,
    ];

    private const WINDOW_SECONDS = 60;

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $name = $request->route()?->getName();
        $limit = self::LIMITS[$name] ?? null;

        if ($limit === null) {
            return $next($request);
        }

        $key = $name.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            abort(429, 'Too many requests. Please try again shortly.');
        }

        RateLimiter::hit($key, self::WINDOW_SECONDS);

        return $next($request);
    }
}
