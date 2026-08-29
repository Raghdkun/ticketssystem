<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Who administers the platform, and which owners need sign-off.
 *
 * Administering and running a venue are independent: an account can do either,
 * both, or neither. The guards here exist because there is no way back in --
 * registration is closed, so a platform with no administrator left cannot
 * appoint one.
 */
class RoleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/roles', [
            'people' => User::query()
                ->withCount('places')
                ->orderByDesc('is_super_admin')
                ->orderBy('name')
                ->get()
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_super_admin' => $user->is_super_admin,
                    'requires_approval' => $user->requires_approval,
                    'places_count' => $user->places_count,
                    'banned' => $user->banned_at !== null,
                ])->all(),
            'adminCount' => User::where('is_super_admin', true)->count(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'is_super_admin' => ['required', 'boolean'],
            'requires_approval' => ['required', 'boolean'],
        ]);

        $this->guardAdminFlag($request, $user, (bool) $data['is_super_admin']);

        $before = [
            'is_super_admin' => $user->is_super_admin,
            'requires_approval' => $user->requires_approval,
        ];

        // Assigned explicitly rather than mass-assigned. These two flags are
        // deliberately absent from the model's fillable list: nothing should
        // ever be able to grant itself administrator access by smuggling a
        // field into an unrelated request.
        $user->is_super_admin = (bool) $data['is_super_admin'];
        $user->requires_approval = (bool) $data['requires_approval'];
        $user->save();

        // Who may administer the platform is worth being able to answer
        // "who changed that" about, long after the fact.
        AuditLog::record('role_changed', $user, ['from' => $before, 'to' => $data]);

        return back()->with('flash', [
            'toast' => ['type' => 'success', 'message' => __('ui.roles.saved')],
        ]);
    }

    /**
     * Refuse the two ways this ends with nobody able to administer anything.
     */
    private function guardAdminFlag(Request $request, User $user, bool $wantsAdmin): void
    {
        if ($wantsAdmin || ! $user->is_super_admin) {
            return;
        }

        // Demoting yourself is how an administrator locks themselves out by
        // accident. Another administrator can always do it for you.
        if ($request->user()?->is($user)) {
            throw ValidationException::withMessages([
                'is_super_admin' => __('ui.roles.cannot_demote_self'),
            ]);
        }

        if (User::where('is_super_admin', true)->count() <= 1) {
            throw ValidationException::withMessages([
                'is_super_admin' => __('ui.roles.cannot_demote_last'),
            ]);
        }
    }
}
