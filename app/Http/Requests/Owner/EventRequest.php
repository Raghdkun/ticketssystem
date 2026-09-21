<?php

namespace App\Http\Requests\Owner;

use App\Actions\RepeatEvent;
use App\Enums\EventStatus;
use App\Models\Place;
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
     * "Unlimited" is a checkbox beside the seat count. When it is ticked the
     * count is meaningless, so it is dropped here rather than validated: an
     * owner who typed 100, then ticked the box, should not be told 100 is
     * wrong.
     */
    protected function prepareForValidation(): void
    {
        if ($this->boolean('unlimited')) {
            $this->merge(['total_quantity' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $place = $this->user()?->places()->first();

        return [
            'title_ar' => ['required', 'string', 'max:255'],
            'title_en' => ['required', 'string', 'max:255'],
            'description_ar' => ['nullable', 'string', 'max:5000'],
            'description_en' => ['nullable', 'string', 'max:5000'],

            'price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'currency' => ['required', 'string', 'size:3'],

            'unlimited' => ['sometimes', 'boolean'],
            'total_quantity' => [
                Rule::requiredIf(! $this->boolean('unlimited')),
                'nullable', 'integer', 'min:1', 'max:1000000',
            ],
            'max_per_appointment' => ['required', 'integer', 'min:1', 'max:50'],
            'hold_hours' => ['required', 'integer', 'min:1', 'max:720'],

            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'appointments_close_at' => ['required', 'date', 'before_or_equal:starts_at'],

            // Optional: the form no longer carries a status. A new event is
            // a draft and goes live from the dialog after saving; an edit
            // keeps whatever the event already is.
            'status' => ['sometimes', new Enum(EventStatus::class)],

            // Checkboxes: absent when unticked, so the controller reads them
            // with boolean() rather than filling them from the safe set.
            'is_unlisted' => ['sometimes', 'boolean'],
            'auto_confirm' => ['sometimes', 'boolean'],
            'remove_cover' => ['sometimes', 'boolean'],

            // The owner's own locations, or one a venue has opened to other
            // accounts. Anything else is somebody's address without their
            // consent. An organiser has no room of its own and must pick one;
            // a venue that has not added its first location yet may still
            // draft, and falls back to its primary once one exists.
            'location_id' => [
                Rule::requiredIf(fn () => $place !== null && $place->isOrganiser()),
                'nullable',
                Rule::exists('locations', 'id')->where(fn ($query) => $query
                    ->where('place_id', $place?->id)
                    ->orWhereIn('place_id', Place::query()
                        ->where('shares_locations', true)
                        ->where('is_active', true)
                        ->select('id'))),
            ],

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

            // Copies forward on a cadence, in the same submission. Empty
            // means "just this one", which is what the select starts on.
            'repeat_cadence' => ['nullable', 'string', Rule::in(array_keys(RepeatEvent::CADENCES))],
            'repeat_count' => ['required_with:repeat_cadence', 'nullable', 'integer', 'min:1', 'max:'.RepeatEvent::MAX_COPIES],
            // Copies go out the moment they are made, rather than as drafts.
            'repeat_publish' => ['sometimes', 'boolean'],

            // "I agree to publish under the commercial terms shown". Required
            // by the controller, not here, because whether it is needed
            // depends on the event and the venue.
            'commercial_ack' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * The attributes that go straight onto the model.
     *
     * @return array<string, mixed>
     */
    public function eventAttributes(): array
    {
        return $this->safe()->except([
            'cover', 'rules', 'perks', 'unlimited', 'is_unlisted', 'auto_confirm',
            'remove_cover', 'repeat_cadence', 'repeat_count', 'repeat_publish', 'commercial_ack',
        ]);
    }
}
