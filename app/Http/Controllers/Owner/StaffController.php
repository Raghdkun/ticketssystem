<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\OwnerInvitation;
use App\Models\Place;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The people an owner trusts with their door.
 *
 * A door hand can check people in, look up a ticket and print the door sheet.
 * They cannot create events, see what the venue took, or invite anybody else
 * -- which is what makes this safe to hand to somebody helping for one night.
 */
class StaffController extends Controller
{
    public function index(Request $request): Response
    {
        $place = $this->place($request);

        if ($place === null) {
            return Inertia::render('owner/staff', ['hasPlace' => false, 'staff' => [], 'invitations' => []]);
        }

        return Inertia::render('owner/staff', [
            'hasPlace' => true,
            'staff' => User::where('door_staff_for', $place->id)
                ->orderBy('name')
                ->get()
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'banned' => $user->banned_at !== null,
                ])->all(),
            'invitations' => OwnerInvitation::query()
                ->where('place_id', $place->id)
                ->where('role', OwnerInvitation::ROLE_STAFF)
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn (OwnerInvitation $invitation) => [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'is_open' => $invitation->isOpen(),
                    'accepted_at' => $invitation->accepted_at?->toIso8601String(),
                    'expires_at' => $invitation->expires_at->toIso8601String(),
                ])->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $place = $this->place($request);

        abort_if($place === null, 404);
        $this->authorize('update', $place);

        $data = $request->validate([
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email'),
                Rule::unique('owner_invitations', 'email')->where(
                    fn ($query) => $query->whereNull('accepted_at')->where('expires_at', '>', now())
                ),
            ],
        ]);

        ['token' => $token] = OwnerInvitation::mint(
            $data['email'],
            $request->user(),
            requiresApproval: false,
            role: OwnerInvitation::ROLE_STAFF,
            placeId: $place->id,
        );

        AuditLog::record('staff_invited', null, ['email' => $data['email'], 'place' => $place->id]);

        return back()
            ->with('invitation_link', route('invitations.show', ['token' => $token]))
            ->with('success', __('ui.staff.invited'));
    }

    /**
     * Removing somebody from the door.
     *
     * The account is kept and unlinked rather than deleted: it may be on an
     * audit trail, and a deleted user would take that with it.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        $place = $this->place($request);

        abort_if($place === null || $user->door_staff_for !== $place->id, 404);

        $user->door_staff_for = null;
        $user->banned_at = now();
        $user->save();

        AuditLog::record('staff_removed', $user, ['place' => $place->id]);

        return back()->with('success', __('ui.staff.removed'));
    }

    private function place(Request $request): ?Place
    {
        return $request->user()?->places()->first();
    }
}
