<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function __construct(private readonly Settings $settings) {}

    public function edit(): Response
    {
        return Inertia::render('admin/settings', [
            'settings' => $this->settings->all(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'app_name_en' => ['required', 'string', 'max:60'],
            'app_name_ar' => ['required', 'string', 'max:60'],
            'tagline_en' => ['nullable', 'string', 'max:160'],
            'tagline_ar' => ['nullable', 'string', 'max:160'],
            'support_whatsapp' => ['nullable', 'string', 'max:20'],
            // SVG is deliberately not accepted: it is script-carrying markup
            // served from our own origin, which would be a stored-XSS vector.
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ]);

        /** @var array<string, string|null> $values */
        $values = Arr::except($validated, ['logo']);

        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $path = 'platform/'.Str::uuid()->toString().'.'.$file->extension();
            Storage::disk('public')->putFileAs('platform', $file, basename($path));

            // Remove the previous file rather than accumulating orphans.
            $previous = $this->settings->get('logo_path');
            if ($previous !== null) {
                Storage::disk('public')->delete($previous);
            }

            $values['logo_path'] = $path;
        }

        $this->settings->put($values, $request->user()->id);

        return back()->with('success', __('admin.settings_saved'));
    }
}
