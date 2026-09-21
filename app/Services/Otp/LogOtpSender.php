<?php

namespace App\Services\Otp;

use Illuminate\Support\Facades\Log;

/**
 * Writes the code to the log instead of sending it.
 *
 * For exercising the whole flow -- the code step, the wrong-code path, the
 * record it leaves -- before any carrier is wired up.
 */
final class LogOtpSender implements OtpSender
{
    public function channel(): string
    {
        return 'log';
    }

    public function send(string $phone, string $code): void
    {
        Log::info("OTP for {$phone}: {$code}");
    }
}
