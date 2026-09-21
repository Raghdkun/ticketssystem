<?php

/*
 * One-time codes for the agreement flow.
 *
 * There is no SMS gateway yet. The driver is null until Syriatel or MTN
 * credentials arrive, and while it is null the acceptance flow skips the
 * code step and says so on the record. Set it to "log" to exercise the flow
 * without sending anything: codes go to the application log.
 */
return [
    'driver' => env('OTP_DRIVER'),

    // How long a code stays valid, in seconds.
    'ttl' => (int) env('OTP_TTL', 600),

    // Wrong guesses before the code is thrown away.
    'max_attempts' => 5,
];
