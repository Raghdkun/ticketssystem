<?php

use App\Http\Controllers\Admin\AgreementController;
use App\Http\Controllers\Admin\EventReviewController;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Admin\InvitationController;
use App\Http\Controllers\Admin\OwnerController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Middleware\EnsureUserIsSuperAdmin;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', EnsureUserIsSuperAdmin::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('owners', [OwnerController::class, 'index'])->name('owners');
        Route::post('owners/{user}/ban', [OwnerController::class, 'ban'])->name('owners.ban');
        Route::post('owners/{user}/unban', [OwnerController::class, 'unban'])->name('owners.unban');
        Route::post('owners/{user}/impersonate', [ImpersonationController::class, 'start'])->name('owners.impersonate');

        Route::get('invitations', [InvitationController::class, 'index'])->name('invitations');
        Route::post('invitations', [InvitationController::class, 'store'])->name('invitations.store');
        Route::delete('invitations/{invitation}', [InvitationController::class, 'destroy'])->name('invitations.destroy');

        Route::get('roles', [RoleController::class, 'index'])->name('roles');
        Route::patch('roles/{user}', [RoleController::class, 'update'])->name('roles.update');

        Route::get('events', [EventReviewController::class, 'index'])->name('events');
        Route::post('events/{event}/{verdict}', [EventReviewController::class, 'decide'])
            ->whereIn('verdict', ['approve', 'reject'])->name('events.decide');
        Route::delete('events/{event}', [EventReviewController::class, 'destroy'])->name('events.destroy');

        Route::get('agreements', [AgreementController::class, 'index'])->name('agreements.index');
        Route::post('agreements', [AgreementController::class, 'store'])->name('agreements.store');
        Route::get('agreements/{agreement}', [AgreementController::class, 'show'])->name('agreements.show');
        Route::patch('agreements/{agreement}', [AgreementController::class, 'update'])->name('agreements.update');
        Route::post('agreements/{agreement}/publish', [AgreementController::class, 'publish'])->name('agreements.publish');
        Route::delete('agreements/{agreement}', [AgreementController::class, 'destroy'])->name('agreements.destroy');

        Route::get('settings', [SettingsController::class, 'edit'])->name('settings');
        Route::post('settings', [SettingsController::class, 'update'])->name('settings.update');
    });
