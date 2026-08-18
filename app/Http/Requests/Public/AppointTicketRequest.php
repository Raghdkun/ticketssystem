<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;
use Propaganistas\LaravelPhone\PhoneNumber;
use Propaganistas\LaravelPhone\Rules\Phone;

class AppointTicketRequest extends FormRequest
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
            'full_name' => ['required', 'string', 'min:3', 'max:120'],
            'phone' => ['required', 'string', (new Phone)->country(['SY'])->mobile()],
            'quantity' => ['required', 'integer', 'min:1', 'max:50'],
            'accepted_rule_ids' => ['array'],
            'accepted_rule_ids.*' => ['integer'],
        ];
    }

    /**
     * Normalise to E.164 so lookups and WhatsApp links are consistent
     * regardless of how the visitor typed their number.
     */
    public function normalisedPhone(): string
    {
        return (new PhoneNumber($this->string('phone')->value(), 'SY'))->formatE164();
    }

    /**
     * @return array<int, int>
     */
    public function acceptedRuleIds(): array
    {
        return array_map('intval', $this->input('accepted_rule_ids', []));
    }
}
