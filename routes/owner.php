<?php

use App\Http\Controllers\Owner\DoorSheetController;
use App\Http\Controllers\Owner\EventController;
use App\Http\Controllers\Owner\EventMediaController;
use App\Http\Controllers\Owner\TicketSearchController;
use App\Http\Controllers\Owner\VerificationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])
    ->prefix('owner')
    ->name('owner.')
    ->group(function () {
        Route::resource('events', EventController::class)->except(['show']);

        Route::get('scan', [VerificationController::class, 'scanner'])->name('scan');
        Route::get('search', TicketSearchController::class)->name('search');
        Route::get('events/{event}/door-sheet', DoorSheetController::class)->name('events.door_sheet');

        Route::post('events/{event}/media', [EventMediaController::class, 'store'])->name('events.media.store');
        Route::delete('events/{event}/media/{medium}', [EventMediaController::class, 'destroy'])->name('events.media.destroy');
        Route::post('events/{event}/media/{medium}/promo', [EventMediaController::class, 'setPromo'])->name('events.media.promo');
    });

// Ticket verification. Auth-gated and policy-checked: holding the QR URL is
// not sufficient, because the attendee holds the very same URL.
Route::middleware(['auth', 'verified'])
    ->group(function () {
        Route::get('verify/{ticket}', [VerificationController::class, 'show'])->name('tickets.verify');
        Route::post('verify/{ticket}/paid', [VerificationController::class, 'markPaid'])->name('tickets.verify.paid');
        Route::post('verify/{ticket}/cancel', [VerificationController::class, 'cancel'])->name('tickets.verify.cancel');
        Route::post('verify/{ticket}/no-show', [VerificationController::class, 'noShow'])->name('tickets.verify.no_show');
    });
