<?php

namespace App\Http\Requests\Owner;

use App\Enums\EventStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class EventRequest extends FormRequest
{
    /**
     * Authorisation is handled by the controller's policy checks.
     */
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
            'title_ar' => ['required', 'string', 'max:255'],
            'title_en' => ['required', 'string', 'max:255'],
            'description_ar' => ['nullable', 'string', 'max:5000'],
            'description_en' => ['nullable', 'string', 'max:5000'],

            'price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'currency' => ['required', 'string', 'size:3'],

            'total_quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'max_per_appointment' => ['required', 'integer', 'min:1', 'max:50'],
            'hold_hours' => ['required', 'integer', 'min:1', 'max:720'],

            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'appointments_close_at' => ['required', 'date', 'before_or_equal:starts_at'],

            'status' => ['required', new Enum(EventStatus::class)],

            // Optional on purpose: an owner should be able to get an event
            // drafted and dated before they have artwork for it. The events
            // list and the public page both render a coverless event.
            'cover' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],

            'rules' => ['array', 'max:20'],
            'rules.*.body_ar' => ['required', 'string', 'max:255'],
            'rules.*.body_en' => ['required', 'string', 'max:255'],

            'perks' => ['array', 'max:20'],
            'perks.*.body_ar' => ['required', 'string', 'max:255'],
            'perks.*.body_en' => ['required', 'string', 'max:255'],
        ];
    }
}
