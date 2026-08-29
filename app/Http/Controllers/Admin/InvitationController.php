<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\OwnerInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Inviting somebody to run a venue.
 *
 * This is the only door into the platform. There is no open sign-up form, so
 * an invitation is a specific, expiring, one-use permission to create exactly
 * one account -- and the link is shown once, because only its hash is kept.
 */
class InvitationController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/invitations', [
            'invitations' => OwnerInvitation::query()
                ->with('inviter:id,name')
                ->latest()
                ->limit(50)
                ->get()
                ->map(fn (OwnerInvitation $invitation) => [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'requires_approval' => $invitation->requires_approval,
                    'expires_at' => $invitation->expires_at->toIso8601String(),
                    'accepted_at' => $invitation->accepted_at?->toIso8601String(),
                    'is_open' => $invitation->isOpen(),
                    'invited_by' => $invitation->inviter?->name,
                ])->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => [
                'required', 'email', 'max:255',
                // Not an existing account, and not already invited and waiting.
                Rule::unique('users', 'email'),
                Rule::unique('owner_invitations', 'email')->where(
                    fn ($query) => $query->whereNull('accepted_at')->where('expires_at', '>', now())
                ),
            ],
            'requires_approval' => ['required', 'boolean'],
        ]);

        ['invitation' => $invitation, 'token' => $token] = OwnerInvitation::mint(
            $data['email'],
            $request->user(),
            (bool) $data['requires_approval'],
        );

        AuditLog::record('owner_invited', null, [
            'email' => $invitation->email,
            'requires_approval' => $invitation->requires_approval,
        ]);

        // Flashed once and never retrievable again: only the hash is stored,
        // so an administrator who loses the link issues a new invitation.
        return back()
            ->with('invitation_link', route('invitations.show', ['token' => $token]))
            ->with('success', __('ui.invite.created'));
    }

    public function destroy(OwnerInvitation $invitation): RedirectResponse
    {
        abort_if($invitation->accepted_at !== null, 409, 'An accepted invitation cannot be revoked.');

        AuditLog::record('owner_invite_revoked', null, ['email' => $invitation->email]);

        $invitation->delete();

        return back()->with('success', __('ui.invite.revoked'));
    }
}
