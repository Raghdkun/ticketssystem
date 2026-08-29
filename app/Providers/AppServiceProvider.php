<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        /*
         * Listeners are not registered here. Both live in app/Listeners with
         * a handle() method type-hinting the event, so Laravel discovers them
         * on its own -- and registering them by hand as well made every
         * ticket status push go out twice, to every device.
         */
        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // Composition rules (mixed case, symbols, a 12 character floor) push
        // people towards one memorised pattern with a digit on the end, and
        // they are miserable to type on a phone keyboard -- which is where a
        // venue owner will be. NIST 800-63B advises dropping them in favour
        // of screening against known breaches, which is what is kept here:
        // eight characters, and not a password that has already leaked.
        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(8)->uncompromised()
            : null,
        );
    }
}
