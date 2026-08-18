<?php

use App\Http\Controllers\Owner\EventController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])
    ->prefix('owner')
    ->name('owner.')
    ->group(function () {
        Route::resource('events', EventController::class)->except(['show']);
    });
