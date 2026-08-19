<?php

use App\Http\Controllers\Admin\OwnerController;
use App\Http\Middleware\EnsureUserIsSuperAdmin;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', EnsureUserIsSuperAdmin::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('owners', [OwnerController::class, 'index'])->name('owners');
        Route::post('owners', [OwnerController::class, 'store'])->name('owners.store');
        Route::post('owners/{user}/ban', [OwnerController::class, 'ban'])->name('owners.ban');
        Route::post('owners/{user}/unban', [OwnerController::class, 'unban'])->name('owners.unban');
    });
