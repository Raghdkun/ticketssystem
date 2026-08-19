<?php

use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Admin\OwnerController;
use App\Http\Controllers\Admin\SettingsController;
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
        Route::post('owners/{user}/impersonate', [ImpersonationController::class, 'start'])->name('owners.impersonate');

        Route::get('settings', [SettingsController::class, 'edit'])->name('settings');
        Route::post('settings', [SettingsController::class, 'update'])->name('settings.update');
    });
