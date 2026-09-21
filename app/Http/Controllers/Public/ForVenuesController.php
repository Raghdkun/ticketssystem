<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Settings;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The page for a venue that is not a partner yet.
 *
 * Registration is closed by design, so this offers the one thing that
 * leads anywhere: a conversation with whoever mints invitations. The pitch
 * and the number both come from platform settings, so an administrator
 * changes them from the dashboard without a deploy.
 */
class ForVenuesController extends Controller
{
    public function __invoke(Settings $settings): Response
    {
        $locale = app()->getLocale();

        return Inertia::render('public/for-venues', [
            'pitch' => $settings->get($locale === 'ar' ? 'venues_pitch_ar' : 'venues_pitch_en'),
            'whatsapp' => $settings->get('support_whatsapp'),
        ]);
    }
}
