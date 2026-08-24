<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreOwnerRequest;
use App\Models\Event;
use App\Models\Place;
use App\Models\Ticket;
use App\Models\User;
use App\Services\PlatformStats;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class OwnerController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/owners', [
            'stats' => app(PlatformStats::class)->all(),
            'owners' => $this->owners(),
        ]);
    }

    /**
     * Provision a venue owner and their place together.
     *
     * Public registration is closed, so this is the only way an owner account
     * comes into existence. Creating the account and its venue in one
     * transaction avoids the half-provisioned state where an owner can sign in
     * but has nothing to manage.
     */
    public function store(StoreOwnerRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $user = new User;
            $user->name = $request->string('name')->value();
            $user->email = $request->string('email')->value();
            $user->password = Hash::make($request->string('password')->value());
            $user->role = UserRole::Owner;
            // Provisioned by an administrator, so the address is already trusted.
            $user->email_verified_at = now();
            $user->save();

            $name = $request->string('place_name_en')->value();

            Place::query()->create([
                'user_id' => $user->id,
                'slug' => $this->uniqueSlug($name),
                'name_en' => $name,
                'name_ar' => $request->string('place_name_ar')->value(),
                'whatsapp_number' => $request->string('whatsapp_number')->value() ?: null,
                'is_active' => true,
            ]);
        });

        return back()->with('success', __('admin.owner_created'));
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'venue';
        $slug = $base;
        $i = 2;

        while (Place::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    public function ban(Request $request, User $user): RedirectResponse
    {
        abort_if($user->isSuperAdmin(), 403, 'Super admins cannot be banned.');

        $user->banned_at = now();
        $user->save();

        return back()->with('success', __('admin.banned'));
    }

    public function unban(User $user): RedirectResponse
    {
        $user->banned_at = null;
        $user->save();

        return back()->with('success', __('admin.unbanned'));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function owners(): array
    {
        return User::query()
            ->where('role', UserRole::Owner)
            ->withCount('places')
            ->with('places:id,user_id,name_ar,name_en,slug')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'banned' => $user->isBanned(),
                'banned_at' => $user->banned_at?->toIso8601String(),
                'places' => $user->places->map->only(['name_ar', 'name_en', 'slug'])->all(),
                'events_count' => Event::whereIn('place_id', $user->places->pluck('id'))->count(),
                'tickets_count' => Ticket::whereIn(
                    'event_id',
                    Event::whereIn('place_id', $user->places->pluck('id'))->select('id')
                )->count(),
            ])
            ->all();
    }
}
