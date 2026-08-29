<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\OwnerInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Redeeming an invitation: the one way an account is created.
 *
 * Unauthenticated by necessity -- the person has no account yet -- so every
 * guard is on the token: it is looked up by hash, must be unexpired and
 * unaccepted, and is consumed inside the same transaction that creates the
 * account, under a row lock, so a double submission cannot make two.
 */
class InvitationController extends Controller
{
    public function show(string $token): Response
    {
        $invitation = $this->open($token);

        return Inertia::render('public/invitation', [
            'token' => $token,
            // Shown but not editable: the administrator invited this address,
            // and a forwarded link must not become somebody else's account.
            'email' => $invitation->email,
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'password' => ['required', 'confirmed', Password::defaults()],

            'place_name_ar' => ['required', 'string', 'max:120'],
            'place_name_en' => ['required', 'string', 'max:120'],
            'whatsapp_number' => ['nullable', 'string', 'max:32'],

            'location_name_ar' => ['required', 'string', 'max:120'],
            'location_name_en' => ['required', 'string', 'max:120'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'address_ar' => ['nullable', 'string', 'max:255'],
            'address_en' => ['nullable', 'string', 'max:255'],
            'landmark_ar' => ['nullable', 'string', 'max:255'],
            'landmark_en' => ['nullable', 'string', 'max:255'],
        ]);

        $user = DB::transaction(function () use ($token, $data) {
            // Locked and re-checked inside the transaction: two submissions
            // racing must not both pass the "still open" test and create two
            // accounts from one invitation.
            $invitation = OwnerInvitation::where('token_hash', OwnerInvitation::hash($token))
                ->lockForUpdate()
                ->first();

            abort_if($invitation === null || ! $invitation->isOpen(), 404);

            $user = new User([
                'name' => $data['name'],
                'email' => $invitation->email,
            ]);
            $user->password = Hash::make($data['password']);
            // Not mass-assignable, by design.
            $user->requires_approval = $invitation->requires_approval;
            $user->is_super_admin = false;
            $user->email_verified_at = now();
            $user->save();

            $place = $user->places()->create([
                'slug' => $this->uniqueSlug($data['place_name_en']),
                'name_ar' => $data['place_name_ar'],
                'name_en' => $data['place_name_en'],
                'whatsapp_number' => $data['whatsapp_number'] ?? null,
                'is_active' => true,
            ]);

            // Their first location, and therefore the venue's default.
            $place->locations()->create([
                'name_ar' => $data['location_name_ar'],
                'name_en' => $data['location_name_en'],
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'address_ar' => $data['address_ar'] ?? null,
                'address_en' => $data['address_en'] ?? null,
                'landmark_ar' => $data['landmark_ar'] ?? null,
                'landmark_en' => $data['landmark_en'] ?? null,
                'is_primary' => true,
                'sort' => 0,
            ]);

            $invitation->forceFill([
                'accepted_at' => now(),
                'accepted_user_id' => $user->id,
            ])->save();

            return $user;
        });

        AuditLog::record('owner_invite_accepted', $user, ['email' => $user->email]);

        auth()->login($user);

        // A brand new session for a brand new account: whatever session the
        // visitor arrived with must not carry over into an authenticated one.
        $request->session()->regenerate();

        return to_route('dashboard')->with('success', __('ui.invite.welcome'));
    }

    /**
     * The invitation behind a raw token, or a 404.
     *
     * Deliberately indistinguishable: expired, already used and never existed
     * all look the same from outside.
     */
    private function open(string $token): OwnerInvitation
    {
        $invitation = OwnerInvitation::query()
            ->open()
            ->where('token_hash', OwnerInvitation::hash($token))
            ->first();

        abort_if($invitation === null, 404);

        return $invitation;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'venue';

        return $base.'-'.Str::lower(Str::random(5));
    }
}
