<?php

use App\Http\Controllers\Owner\EventController;
use App\Http\Controllers\Owner\VerificationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])
    ->prefix('owner')
    ->name('owner.')
    ->group(function () {
        Route::resource('events', EventController::class)->except(['show']);

        Route::get('scan', [VerificationController::class, 'scanner'])->name('scan');
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
