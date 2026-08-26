<?php

namespace App\Http\Requests\Owner;

use Illuminate\Foundation\Http\FormRequest;

class PlaceRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name_ar' => ['required', 'string', 'max:120'],
            'name_en' => ['required', 'string', 'max:120'],
            'whatsapp_number' => ['nullable', 'string', 'max:32'],

            // A pin is all-or-nothing. Half a coordinate points at the Gulf of
            // Guinea, so each half requires the other.
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],

            'address_ar' => ['nullable', 'string', 'max:255'],
            'address_en' => ['nullable', 'string', 'max:255'],
            'landmark_ar' => ['nullable', 'string', 'max:255'],
            'landmark_en' => ['nullable', 'string', 'max:255'],
        ];
    }
}
