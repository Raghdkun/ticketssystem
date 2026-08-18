<?php

use App\Http\Controllers\Public\AppointmentController;
use App\Http\Controllers\Public\EventController;
use App\Http\Controllers\Public\TicketController;
use Illuminate\Support\Facades\Route;

// The holder's own ticket. Addressed only by its unguessable token.
Route::get('t/{ticket}', [TicketController::class, 'show'])->name('tickets.show');

// Public event pages. Event slugs are only unique within a place, so these
// bindings are scoped: the event is resolved through the place relationship.
Route::scopeBindings()->group(function () {
    Route::get('{place}/{event:slug}', [EventController::class, 'show'])
        ->name('events.show');

    Route::post('{place}/{event:slug}/appoint', [AppointmentController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('events.appoint');
});
