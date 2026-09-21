<?php

use App\Http\Controllers\Admin\AcceptanceController;
use App\Http\Controllers\Admin\AgreementController;
use App\Http\Controllers\Admin\CommercialOfferController;
use App\Http\Controllers\Admin\EventReviewController;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Admin\InvitationController;
use App\Http\Controllers\Admin\OwnerController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\ServiceOrderController;
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

        Route::get('acceptances', AcceptanceController::class)->name('acceptances');

        Route::get('commercial-offers', [CommercialOfferController::class, 'index'])->name('offers.index');
        Route::post('commercial-offers', [CommercialOfferController::class, 'store'])->name('offers.store');
        Route::get('commercial-offers/{offer}', [CommercialOfferController::class, 'show'])->name('offers.show');
        Route::patch('commercial-offers/{offer}', [CommercialOfferController::class, 'update'])->name('offers.update');
        Route::post('commercial-offers/{offer}/send', [CommercialOfferController::class, 'send'])->name('offers.send');
        Route::delete('commercial-offers/{offer}', [CommercialOfferController::class, 'destroy'])->name('offers.destroy');

        Route::get('service-orders', [ServiceOrderController::class, 'index'])->name('orders.index');
        Route::post('service-orders', [ServiceOrderController::class, 'store'])->name('orders.store');
        Route::get('service-orders/{order}', [ServiceOrderController::class, 'show'])->name('orders.show');
        Route::patch('service-orders/{order}', [ServiceOrderController::class, 'update'])->name('orders.update');
        Route::post('service-orders/{order}/send', [ServiceOrderController::class, 'send'])->name('orders.send');
        Route::post('service-orders/{order}/status', [ServiceOrderController::class, 'status'])->name('orders.status');
        Route::delete('service-orders/{order}', [ServiceOrderController::class, 'destroy'])->name('orders.destroy');

        Route::get('settings', [SettingsController::class, 'edit'])->name('settings');
        Route::post('settings', [SettingsController::class, 'update'])->name('settings.update');
    });
