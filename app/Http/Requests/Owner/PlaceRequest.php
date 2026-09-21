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

            // The legal identity that goes on the agreement. Optional here,
            // required at the moment of acceptance.
            'legal_name' => ['nullable', 'string', 'max:160'],
            'registration_number' => ['nullable', 'string', 'max:80'],
            'representative_name' => ['nullable', 'string', 'max:120'],
            'representative_title' => ['nullable', 'string', 'max:80'],
            'representative_phone' => ['nullable', 'string', 'max:32'],

        ];
    }
}
