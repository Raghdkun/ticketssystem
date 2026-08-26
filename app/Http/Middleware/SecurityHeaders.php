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
            // Ticket pages carry a bearer token in the path, so referrers must
            // never leak it to third parties.
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => $this->permissionsPolicy($request),
            'X-Permitted-Cross-Domain-Policies' => 'none',
        ];

        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value, false);
        }

        return $response;
    }

    /**
     * Deny every powerful feature, then grant back the two the product needs
     * on exactly the routes that need them.
     *
     * Granting is not optional politeness: a feature absent from this header
     * is *allowed*, and a feature denied here cannot be re-enabled by asking
     * the user. Leaving geolocation denied made the venue picker's "my
     * location" button fail silently on every device.
     */
    private function permissionsPolicy(Request $request): string
    {
        $features = [
            'camera' => '()',
            'geolocation' => '()',
            'microphone' => '()',
            'payment' => '()',
            'interest-cohort' => '()',
        ];

        // The QR scanner reads the camera, and only there.
        if ($request->routeIs('owner.scan')) {
            $features['camera'] = '(self)';
        }

        // The venue picker offers to drop the pin on where the owner is.
        if ($request->routeIs('owner.place.*')) {
            $features['geolocation'] = '(self)';
        }

        return collect($features)
            ->map(fn (string $value, string $feature) => $feature.'='.$value)
            ->implode(', ');
    }
}
