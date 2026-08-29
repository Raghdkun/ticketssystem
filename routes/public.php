<?php

use App\Http\Controllers\Public\AppointmentController;
use App\Http\Controllers\Public\EventController;
use App\Http\Controllers\Public\InvitationController;
use App\Http\Controllers\Public\LegalController;
use App\Http\Controllers\Public\PlaceController;
use App\Http\Controllers\Public\PushSubscriptionController;
use App\Http\Controllers\Public\SitemapController;
use App\Http\Controllers\Public\TicketController;
use App\Http\Controllers\Public\TicketLookupController;
use App\Http\Controllers\Public\WatchlistController;
use Illuminate\Support\Facades\Route;

// Recover a booking by mobile number. Throttled and deliberately partial,
// because phone numbers are guessable.
Route::get('my-tickets', TicketLookupController::class)
    ->middleware('throttle:12,1')
    ->name('tickets.lookup');

Route::get('privacy', [LegalController::class, 'privacy'])->name('legal.privacy');
Route::get('terms', [LegalController::class, 'terms'])->name('legal.terms');

/*
 * Redeeming an invitation. Unauthenticated by necessity -- the person has no
 * account yet -- so it is rate limited: the token is unguessable, and this
 * makes trying anyway pointless rather than merely slow.
 */
Route::middleware('throttle:10,1')->group(function () {
    Route::get('invite/{token}', [InvitationController::class, 'show'])
        ->name('invitations.show');
    Route::post('invite/{token}', [InvitationController::class, 'accept'])
        ->name('invitations.accept');
});

Route::get('sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('robots.txt', [SitemapController::class, 'robots'])->name('robots');

// Push opt-in for a ticket, authorised by possession of its token.
Route::post('t/{ticket}/push', [PushSubscriptionController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('tickets.push.store');

Route::delete('t/{ticket}/push', [PushSubscriptionController::class, 'destroy'])
    ->middleware('throttle:20,1')
    ->name('tickets.push.destroy');

// The holder's own ticket. Addressed only by its unguessable token.
Route::get('t/{ticket}', [TicketController::class, 'show'])->name('tickets.show');

// Releasing your own seats. A POST because it changes state, and throttled
// because the token is the only thing standing in front of it.
Route::post('t/{ticket}/release', [TicketController::class, 'release'])
    ->middleware('throttle:10,1')
    ->name('tickets.release');

// Public event pages. Event slugs are only unique within a place, so these
// bindings are scoped: the event is resolved through the place relationship.
Route::scopeBindings()->group(function () {
    Route::get('{place}/{event:slug}', [EventController::class, 'show'])
        ->name('events.show');

    Route::post('{place}/{event:slug}/appoint', [AppointmentController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('events.appoint');

    // Join the queue for a sold-out event.
    Route::post('{place}/{event:slug}/watch', [WatchlistController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('events.watch');
});

/*
 * A venue's own page. Registered dead last, after every fixed path in the
 * application: a single free segment would otherwise swallow /login and
 * /privacy on its way past.
 */
Route::get('{place}', [PlaceController::class, 'show'])
    ->where('place', '(?!(?:'.implode('|', [
        // Anything the application already owns. Without this the catch-all
        // answers every single-segment path, which turns a clean 404 on
        // /register into a 405 and tells a prober something lives there.
        'register', 'login', 'logout', 'dashboard', 'settings', 'admin',
        'owner', 'verify', 'invite', 'privacy', 'terms', 'my-tickets',
        'sitemap\.xml', 'robots\.txt', 'up', 'storage', 'build', 'api', 'user',
        't', 'forgot-password', 'reset-password', 'two-factor-challenge',
    ]).')$)[a-z0-9-]+')
    ->name('places.show');
