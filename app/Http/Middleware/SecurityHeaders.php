<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers.
 *
 * Framing is denied outright: no part of this app is meant to be embedded, and
 * the owner's verification screen is a one-click state change, which is exactly
 * what clickjacking targets.
 */
class SecurityHeaders
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            // Ticket pages carry a bearer token in the path, so referrers must
            // never leak it to third parties.
            'Permissions-Policy' => 'geolocation=(), microphone=(), payment=(), interest-cohort=()',
            'X-Permitted-Cross-Domain-Policies' => 'none',
        ];

        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value, false);
        }

        // The camera is needed for the owner's QR scanner, and only there.
        if ($request->routeIs('owner.scan')) {
            $response->headers->set('Permissions-Policy', 'camera=(self), geolocation=(), microphone=(), payment=()');
        }

        return $response;
    }
}
