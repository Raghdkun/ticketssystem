<?php

use App\Http\Controllers\Public\AppointmentController;
use App\Http\Controllers\Public\EventController;
use App\Http\Controllers\Public\TicketController;
use Illuminate\Support\Facades\Route;

// The holder's own ticket. Addressed only by its unguessable token.
Route::get('t/{ticket}', [TicketController::class, 'show'])->name('tickets.show');

// Public event page and appointment submission, scoped to the owning place.
Route::get('{place}/{event}', [EventController::class, 'show'])->name('events.show');

Route::post('{place}/{event}/appoint', [AppointmentController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('events.appoint');
