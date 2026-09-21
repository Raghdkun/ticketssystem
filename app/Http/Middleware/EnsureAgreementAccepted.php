<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Admin\ImpersonationController;
use App\Services\Agreements;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Nothing about a venue can be changed until its terms are accepted.
 *
 * Sits on the same routes as EnsureManagesVenue -- everything that shapes a
 * venue or shows what it took. The door is outside it on purpose: paperwork
 * must not stop somebody scanning tickets on the night.
 *
 * An administrator acting as an owner passes through. Impersonation is for
 * looking, it is logged, and an administrator cannot accept on somebody
 * else's behalf; blocking them would only hide what they came to see.
 */
class EnsureAgreementAccepted
{
    public function __construct(private readonly Agreements $agreements) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user === null
            || $request->session()->has(ImpersonationController::SESSION_KEY)
            || ! $this->agreements->needsAcceptance($user)
        ) {
            return $next($request);
        }

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            abort(423, 'The partner terms must be accepted first.');
        }

        // Where they were going is kept, so accepting drops them back there.
        if ($request->isMethod('GET')) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return redirect()->route('owner.agreement.show');
    }
}
