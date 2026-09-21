<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\CommercialOffer;
use App\Services\Commercial;
use App\Support\CommercialPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A commercial offer, as the venue reads and answers it.
 *
 * An offer belongs to exactly one venue; another venue's URL is a 404, not
 * a 403, because whether it exists is none of their business.
 */
class CommercialOfferController extends Controller
{
    public function __construct(private readonly Commercial $commercial) {}

    public function show(Request $request, CommercialOffer $offer): Response
    {
        $this->assertOwn($request, $offer);

        return Inertia::render('owner/commercial-offer', [
            'offer' => CommercialPresenter::offer($offer->load('acceptor:id,name')),
        ]);
    }

    public function accept(Request $request, CommercialOffer $offer): RedirectResponse
    {
        $this->assertOwn($request, $offer);

        // "I accept the commercial offer above as an annex to the Terms."
        // Absent, 0 and "false" all fail; only an explicit tick passes.
        $request->validate(['accept' => ['accepted']]);

        if (! $offer->isSent()) {
            throw ValidationException::withMessages(['accept' => __('ui.commercial.not_open')]);
        }

        if ($offer->isExpired()) {
            throw ValidationException::withMessages(['accept' => __('ui.commercial.expired')]);
        }

        $this->commercial->acceptOffer($offer, $request->user(), $request);

        return to_route('owner.documents')->with('success', __('ui.commercial.accepted_toast'));
    }

    public function reject(Request $request, CommercialOffer $offer): RedirectResponse
    {
        $this->assertOwn($request, $offer);
        abort_unless($offer->isSent(), 409, 'Only an open offer can be declined.');

        $this->commercial->rejectOffer($offer, $request->user());

        return to_route('owner.documents')->with('info', __('ui.commercial.rejected_toast'));
    }

    private function assertOwn(Request $request, CommercialOffer $offer): void
    {
        $place = $request->user()->places()->first();

        abort_if($place === null || $offer->place_id !== $place->id, 404);
    }
}
