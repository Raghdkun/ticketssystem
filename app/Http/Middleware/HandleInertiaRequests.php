<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Admin\ImpersonationController;
use App\Services\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => app(Settings::class)->appName(),
            'platform' => fn () => $this->platform(),
            'auth' => [
                'user' => $request->user(),
                // Present only while a super admin is acting as someone else.
                'impersonating' => $request->session()->has(ImpersonationController::SESSION_KEY)
                    ? ['name' => $request->user()?->name]
                    : null,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'locale' => $locale = app()->getLocale(),
            'direction' => $locale === 'ar' ? 'rtl' : 'ltr',
            /*
             * Shaped as the client's toast payload rather than a bare string:
             * the flash listener expects { type, message }, so controllers
             * using with('success') produced no visible confirmation at all.
             */
            'flash' => fn () => $this->flash($request),
            'translations' => fn () => $this->translations(),
            'legal' => fn () => trans('legal'),
        ];
    }

    /**
     * The UI string catalogue, flattened to dot notation for the client.
     *
     * Only the `ui` group is shipped; validation and auth messages are
     * rendered server-side and never needed in the bundle.
     *
     * @return array<string, string>
     */
    private function translations(): array
    {
        $strings = trans('ui');

        return is_array($strings) ? Arr::dot($strings) : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function flash(Request $request): array
    {
        foreach (['success', 'error', 'warning', 'info'] as $type) {
            $message = $request->session()->get($type);

            if (is_string($message) && $message !== '') {
                return ['toast' => ['type' => $type, 'message' => $message]];
            }
        }

        return ['toast' => null];
    }

    /**
     * Brand values a super admin can change without a deploy.
     *
     * @return array<string, string|null>
     */
    private function platform(): array
    {
        $settings = app(Settings::class);
        $locale = app()->getLocale();

        return [
            'name' => $settings->appName($locale),
            'tagline' => $settings->get($locale === 'ar' ? 'tagline_ar' : 'tagline_en'),
            'logo' => $settings->get('logo_path'),
            'support_whatsapp' => $settings->get('support_whatsapp'),
        ];
    }
}
