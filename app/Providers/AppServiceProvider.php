<?php

namespace App\Providers;

use App\Services\Otp\LogOtpSender;
use App\Services\Otp\OtpChallenge;
use App\Services\Otp\OtpSender;
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
        // The OTP sender is whichever carrier is configured, or nothing.
        // Nothing is a real state: the agreement flow skips the code step
        // and records that the phone was not verified. Adding Syriatel or
        // MTN later is one class here and one line in config/otp.php.
        $this->app->singleton(OtpChallenge::class, function () {
            $sender = match (config('otp.driver')) {
                'log' => new LogOtpSender,
                default => null,
            };

            return new OtpChallenge($sender);
        });
        $this->app->bind(OtpSender::class, fn () => throw new \RuntimeException('Resolve OtpChallenge, not the sender.'));
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
