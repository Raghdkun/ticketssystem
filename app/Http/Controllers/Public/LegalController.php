<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class LegalController extends Controller
{
    /**
     * Privacy and terms.
     *
     * The copy is deliberately specific to what this app actually does — it
     * stores a name and mobile number against a booking, and no payment data
     * ever touches it because payment happens in person.
     */
    public function privacy(): Response
    {
        return Inertia::render('public/legal', ['document' => 'privacy']);
    }

    public function terms(): Response
    {
        return Inertia::render('public/legal', ['document' => 'terms']);
    }
}
