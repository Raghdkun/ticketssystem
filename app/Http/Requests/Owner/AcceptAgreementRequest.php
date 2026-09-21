<?php

namespace App\Http\Requests\Owner;

use App\Models\AgreementAcceptance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Propaganistas\LaravelPhone\PhoneNumber;
use Propaganistas\LaravelPhone\Rules\Phone;

class AcceptAgreementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // The form carries the id of the version it displayed. If a
            // newer one was published in the meantime, the submission is
            // for text the person did not see, and is refused.
            'agreement_version_id' => ['required', 'integer'],

            'legal_name' => ['required', 'string', 'max:160'],
            'registration_number' => ['nullable', 'string', 'max:80'],
            'representative_name' => ['required', 'string', 'max:120'],
            'representative_role' => ['required', 'string', Rule::in(AgreementAcceptance::ROLES)],
            'representative_phone' => ['required', 'string', (new Phone)->country(['SY'])->mobile()],

            // Not pre-ticked, and "accepted" rather than "boolean": the
            // rule fails on absent, on 0 and on "false" alike.
            'accept' => ['accepted'],

            'otp_code' => ['nullable', 'string', 'max:10'],
        ];
    }

    /**
     * @return array{legal_name: string, registration_number: ?string, representative_name: string, representative_role: string, representative_phone: string}
     */
    public function identity(): array
    {
        return [
            'legal_name' => $this->string('legal_name')->trim()->value(),
            'registration_number' => $this->filled('registration_number') ? $this->string('registration_number')->trim()->value() : null,
            'representative_name' => $this->string('representative_name')->trim()->value(),
            'representative_role' => $this->string('representative_role')->value(),
            'representative_phone' => $this->normalisedPhone(),
        ];
    }

    public function normalisedPhone(): string
    {
        return (new PhoneNumber($this->string('representative_phone')->value(), 'SY'))->formatE164();
    }
}
