<?php

namespace App\Services\Otp;

/**
 * Delivers a one-time code to a phone.
 *
 * One method, on purpose: a Syriatel or MTN driver is a class that sends
 * an SMS and nothing else. Generating, storing and checking the code is
 * the challenge's job and does not change with the carrier.
 */
interface OtpSender
{
    /**
     * The name recorded on the acceptance: which channel verified the phone.
     */
    public function channel(): string;

    public function send(string $phone, string $code): void;
}
