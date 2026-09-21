<?php

namespace App\Services;

use App\Enums\AgreementStatus;
use App\Models\AgreementAcceptance;
use App\Models\AgreementVersion;
use App\Models\AuditLog;
use App\Models\Place;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The Partner Terms: what is in force, who has agreed, and publishing.
 */
final class Agreements
{
    public function current(): ?AgreementVersion
    {
        return AgreementVersion::current();
    }

    /**
     * Whether this account has to accept the terms before doing anything
     * else. True only for somebody who runs a venue that has not accepted
     * the version in force; there is nothing to accept when nothing is
     * published, and door staff and administrators are not party to it.
     */
    public function needsAcceptance(User $user): bool
    {
        if (! $user->managesVenue()) {
            return false;
        }

        $place = $user->places()->first();

        if ($place === null) {
            return false;
        }

        $current = $this->current();

        return $current !== null && ! $place->hasAccepted($current);
    }

    /**
     * Record one venue's acceptance of the current version.
     *
     * The legal identity is copied onto the venue as well, so the next
     * acceptance starts from what was signed last time. Idempotent per
     * version and venue: a double submission returns the first record.
     *
     * @param  array{legal_name: string, registration_number: ?string, representative_name: string, representative_title: ?string, representative_phone: string}  $identity
     */
    public function accept(
        AgreementVersion $version,
        Place $place,
        User $user,
        array $identity,
        string $locale,
        ?string $ip,
        ?string $userAgent,
        string $otpChannel = 'none',
        bool $otpVerified = false,
    ): AgreementAcceptance {
        return DB::transaction(function () use ($version, $place, $user, $identity, $locale, $ip, $userAgent, $otpChannel, $otpVerified) {
            $existing = $place->acceptances()
                ->where('agreement_version_id', $version->id)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $place->fill($identity)->save();

            $acceptance = AgreementAcceptance::create([
                'agreement_version_id' => $version->id,
                'place_id' => $place->id,
                'user_id' => $user->id,
                ...$identity,
                'content_hash' => $version->content_hash ?? $version->hashContent(),
                'locale' => $locale,
                'ip' => $ip,
                'user_agent' => $userAgent === null ? null : mb_substr($userAgent, 0, 255),
                'otp_channel' => $otpVerified ? $otpChannel : 'none',
                'otp_verified_at' => $otpVerified ? now() : null,
                'accepted_at' => now(),
            ]);

            AuditLog::record('agreement_accepted', $user, [
                'version' => $version->version,
                'place' => $place->id,
                'otp' => $acceptance->otp_channel,
            ]);

            return $acceptance;
        });
    }

    /**
     * Put a draft in force, retiring whatever was.
     *
     * The hash is taken here, from the text as it is at this moment, and
     * the model refuses every edit from now on.
     */
    public function publish(AgreementVersion $draft, User $actor): AgreementVersion
    {
        return DB::transaction(function () use ($draft, $actor) {
            AgreementVersion::query()
                ->ofKind($draft->kind)
                ->where('status', AgreementStatus::Published)
                ->get()
                ->each(fn (AgreementVersion $previous) => $previous->forceFill([
                    'status' => AgreementStatus::Retired,
                    'retired_at' => now(),
                ])->save());

            $draft->forceFill([
                'status' => AgreementStatus::Published,
                'content_hash' => $draft->hashContent(),
                'published_at' => now(),
                'published_by' => $actor->id,
            ])->save();

            AuditLog::record('agreement_published', null, [
                'version' => $draft->version,
                'kind' => $draft->kind,
                'hash' => $draft->content_hash,
            ]);

            return $draft;
        });
    }
}
