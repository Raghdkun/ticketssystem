<?php

namespace App\Http\Middleware;

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
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
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
}
