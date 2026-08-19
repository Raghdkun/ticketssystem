<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImpersonationLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonationController extends Controller
{
    /** Session key holding the real administrator's id while impersonating. */
    public const SESSION_KEY = 'impersonator_id';

    /**
     * Act as another user for support.
     *
     * Full access, so every action taken is indistinguishable from the owner's
     * own apart from this log — which is why start and stop are both recorded
     * and a banner stays on screen throughout.
     */
    public function start(Request $request, User $user): RedirectResponse
    {
        $admin = $request->user();

        abort_if($user->isSuperAdmin(), 403, 'Super admins cannot be impersonated.');
        abort_if($user->is($admin), 400);
        // Nesting would lose track of who the real actor is.
        abort_if($request->session()->has(self::SESSION_KEY), 409);

        $log = ImpersonationLog::query()->create([
            'admin_id' => $admin->id,
            'target_id' => $user->id,
            'ip' => $request->ip(),
            'started_at' => now(),
        ]);

        Auth::guard('web')->login($user);

        // Regenerate but keep the marker, so a fixated session cannot be
        // reused and the banner still knows who to return to.
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, $admin->id);
        $request->session()->put('impersonation_log_id', $log->id);

        return to_route('dashboard')->with('success', __('admin.impersonating', ['name' => $user->name]));
    }

    public function stop(Request $request): RedirectResponse
    {
        $adminId = $request->session()->get(self::SESSION_KEY);

        abort_if($adminId === null, 400);

        /** @var User $admin */
        $admin = User::query()->findOrFail($adminId);

        ImpersonationLog::query()
            ->whereKey($request->session()->get('impersonation_log_id'))
            ->update(['ended_at' => now()]);

        Auth::guard('web')->login($admin);
        $request->session()->regenerate();
        $request->session()->forget([self::SESSION_KEY, 'impersonation_log_id']);

        return to_route('admin.owners')->with('success', __('admin.impersonation_ended'));
    }
}
