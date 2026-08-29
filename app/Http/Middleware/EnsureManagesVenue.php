<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps door staff at the door.
 *
 * Applied to everything that changes what a venue offers or shows what it
 * took. Scanning, verifying and ticket search are deliberately outside it --
 * that is the whole job of the account.
 *
 * A redirect rather than a 403 for page loads: somebody handed this account
 * lands on the dashboard by habit, and sending them where they can actually
 * work is more use than telling them they are forbidden.
 */
class EnsureManagesVenue
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->managesVenue()) {
            return $next($request);
        }

        if ($request->isMethod('GET') && ! $request->expectsJson()) {
            return redirect()->route('owner.scan');
        }

        abort(403, 'This account may verify tickets only.');
    }
}
