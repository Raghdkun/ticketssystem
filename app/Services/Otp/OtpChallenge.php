<?php

namespace App\Services\Otp;

use Illuminate\Support\Facades\Cache;

/**
 * A one-time code, issued for one purpose to one phone.
 *
 * The code itself is never stored: only its hash, in the cache, for as long
 * as it is valid. A restart forgets every outstanding code, which is the
 * right failure -- the person asks for another.
 *
 * Disabled when no sender is configured. Callers ask `isEnabled()` and skip
 * the step; the record they write says the phone was not verified rather
 * than pretending it was.
 */
final class OtpChallenge
{
    public function __construct(private readonly ?OtpSender $sender) {}

    public function isEnabled(): bool
    {
        return $this->sender !== null;
    }

    /**
     * The channel a successful verification is recorded against.
     */
    public function channel(): string
    {
        return $this->sender?->channel() ?? 'none';
    }

    /**
     * Issue a fresh code, replacing any outstanding one for this purpose.
     */
    public function start(string $purpose, string $phone): void
    {
        if ($this->sender === null) {
            return;
        }

        $code = (string) random_int(100000, 999999);

        Cache::put($this->key($purpose, $phone), [
            'hash' => hash('sha256', $code),
            'attempts' => 0,
        ], (int) config('otp.ttl', 600));

        $this->sender->send($phone, $code);
    }

    /**
     * Check a code. A correct code is consumed; a wrong one counts against
     * the attempt limit, and the last wrong one throws the code away.
     */
    public function verify(string $purpose, string $phone, string $code): bool
    {
        $key = $this->key($purpose, $phone);
        $stored = Cache::get($key);

        if (! is_array($stored)) {
            return false;
        }

        if (hash_equals($stored['hash'], hash('sha256', trim($code)))) {
            Cache::forget($key);

            return true;
        }

        $stored['attempts']++;

        if ($stored['attempts'] >= (int) config('otp.max_attempts', 5)) {
            Cache::forget($key);
        } else {
            Cache::put($key, $stored, (int) config('otp.ttl', 600));
        }

        return false;
    }

    private function key(string $purpose, string $phone): string
    {
        return 'otp:'.$purpose.':'.hash('sha256', $phone);
    }
}
