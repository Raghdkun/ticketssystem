<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\AcceptAgreementRequest;
use App\Models\AgreementAcceptance;
use App\Models\Place;
use App\Services\Agreements;
use App\Services\Otp\OtpChallenge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Propaganistas\LaravelPhone\PhoneNumber;
use Propaganistas\LaravelPhone\Rules\Phone;

/**
 * The Partner Terms, as the venue sees them.
 *
 * One page for both moments: the blocking screen when a version has not
 * been accepted, and the record of what was accepted when it has.
 */
class AgreementController extends Controller
{
    private const OTP_PURPOSE = 'agreement';

    public function __construct(
        private readonly Agreements $agreements,
        private readonly OtpChallenge $otp,
    ) {}

    public function show(Request $request): Response
    {
        $place = $this->place($request);
        $current = $this->agreements->current();
        $accepted = $place !== null && $current !== null
            ? $place->acceptances()->where('agreement_version_id', $current->id)->first()
            : null;
        // The last thing this venue agreed to, for the "what changed" note.
        $previous = $place?->acceptances()->with('version')->latest('accepted_at')->first();
        // A wording-only update leaves the earlier acceptance standing.
        $standing = $current !== null && $accepted === null && $previous !== null && ! $current->requires_reacceptance;

        return Inertia::render('owner/agreement', [
            'agreement' => $current === null ? null : [
                'id' => $current->id,
                'version' => $current->version,
                'title_ar' => $current->title_ar,
                'title_en' => $current->title_en,
                'body_ar' => $current->body_ar,
                'body_en' => $current->body_en,
                'change_note_ar' => $current->change_note_ar,
                'change_note_en' => $current->change_note_en,
                'published_at' => $current->published_at?->toIso8601String(),
                'requires_reacceptance' => $current->requires_reacceptance,
            ],
            'standing' => $standing,
            'accepted' => $accepted === null ? null : $this->acceptance($accepted),
            'previous' => $previous === null || $previous->agreement_version_id === $current?->id
                ? null
                : ['version' => $previous->version->version, 'accepted_at' => $previous->accepted_at->toIso8601String()],
            'place' => $place === null ? null : [
                'name_ar' => $place->name_ar,
                'name_en' => $place->name_en,
                'legal_name' => $place->legal_name,
                'registration_number' => $place->registration_number,
                'representative_name' => $place->representative_name,
                'representative_role' => $place->representative_role,
                'representative_phone' => $place->representative_phone,
            ],
            'roles' => AgreementAcceptance::ROLES,
            'otp' => [
                'enabled' => $this->otp->isEnabled(),
                // Set by sendCode(), so the page knows to show the code field.
                'sent_to' => $request->session()->get('otp_sent_to'),
            ],
        ]);
    }

    /**
     * Send a one-time code to the representative's phone.
     *
     * Only ever reached when a sender is configured; the page does not
     * offer the button otherwise. The phone is taken from the form as
     * typed, so the code goes to the number that will be on the record.
     */
    public function sendCode(Request $request): RedirectResponse
    {
        abort_unless($this->otp->isEnabled(), 404);

        $validated = $request->validate([
            'representative_phone' => ['required', 'string', (new Phone)->country(['SY'])->mobile()],
        ]);

        $phone = (new PhoneNumber($validated['representative_phone'], 'SY'))->formatE164();

        $this->otp->start(self::OTP_PURPOSE, $phone);

        return back()
            ->with('otp_sent_to', $phone)
            ->with('info', __('ui.agreement.code_sent'));
    }

    public function accept(AcceptAgreementRequest $request): RedirectResponse
    {
        $place = $this->place($request);
        abort_if($place === null, 403, 'No venue is linked to this account.');

        $current = $this->agreements->current();

        // Nothing to accept, or a version the person did not see.
        if ($current === null || $current->id !== (int) $request->input('agreement_version_id')) {
            throw ValidationException::withMessages([
                'agreement_version_id' => __('ui.agreement.version_changed'),
            ]);
        }

        $identity = $request->identity();
        $otpVerified = false;

        if ($this->otp->isEnabled()) {
            $code = $request->string('otp_code')->value();

            if ($code === '' || ! $this->otp->verify(self::OTP_PURPOSE, $identity['representative_phone'], $code)) {
                throw ValidationException::withMessages([
                    'otp_code' => __('ui.agreement.code_invalid'),
                ]);
            }

            $otpVerified = true;
        }

        $this->agreements->accept(
            version: $current,
            place: $place,
            user: $request->user(),
            identity: $identity,
            locale: app()->getLocale(),
            ip: $request->ip(),
            userAgent: $request->userAgent(),
            otpChannel: $this->otp->channel(),
            otpVerified: $otpVerified,
        );

        $request->session()->forget('otp_sent_to');

        return redirect()
            ->intended(route('dashboard'))
            ->with('success', __('ui.agreement.accepted_toast', ['version' => $current->version]));
    }

    /**
     * @return array<string, mixed>
     */
    private function acceptance(AgreementAcceptance $acceptance): array
    {
        return [
            'accepted_at' => $acceptance->accepted_at->toIso8601String(),
            'legal_name' => $acceptance->legal_name,
            'representative_name' => $acceptance->representative_name,
            'representative_role' => $acceptance->representative_role,
            'representative_phone' => $acceptance->representative_phone,
            'acceptance_method' => $acceptance->acceptance_method,
            'otp_channel' => $acceptance->otp_channel,
            'content_hash' => $acceptance->content_hash,
        ];
    }

    private function place(Request $request): ?Place
    {
        return $request->user()?->places()->first();
    }
}
