<?php

namespace App\Http\Requests\Owner;

use App\Enums\EventStatus;
use App\Enums\ThemeMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
            'theme_mode' => ['required', new Enum(ThemeMode::class)],

            // Only meaningful in manual mode; auto mode derives them from the cover.
            'primary_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'secondary_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],

            'cover' => [
                Rule::requiredIf(fn () => $this->routeIs('owner.events.store')),
                'nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192',
            ],

            'rules' => ['array', 'max:20'],
            'rules.*.body_ar' => ['required', 'string', 'max:255'],
            'rules.*.body_en' => ['required', 'string', 'max:255'],
        ];
    }
}
